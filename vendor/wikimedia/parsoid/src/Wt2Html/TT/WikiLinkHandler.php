<?php
declare( strict_types = 1 );

/**
 * Simple link handler.
 *
 * TODO: keep round-trip information in meta tag or the like
 */

namespace Wikimedia\Parsoid\Wt2Html\TT;

use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Core\InternalException;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\PipelineUtils;
use Wikimedia\Parsoid\Utils\TitleException;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\PegTokenizer;
use Wikimedia\Parsoid\Wt2Html\PP\Processors\AddMediaInfo;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

class WikiLinkHandler extends TokenHandler {
	/**
	 * @var PegTokenizer
	 */
	private $urlParser;

	/** @inheritDoc */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );

		// Create a new peg parser for image options.
		if ( !$this->urlParser ) {
			// Actually the regular tokenizer, but we'll call it with the
			// url rule only.
			$this->urlParser = new PegTokenizer( $this->env );
		}
	}

	/**
	 * @param string $str
	 * @return array|null
	 */
	private static function hrefParts( string $str ): ?array {
		if ( preg_match( '/^([^:]+):(.*)$/D', $str, $matches ) ) {
			return [ 'prefix' => $matches[1], 'title' => $matches[2] ];
		} else {
			return null;
		}
	}

	/**
	 * Normalize and analyze a wikilink target.
	 *
	 * Returns an object containing
	 * - href: The expanded target string
	 * - hrefSrc: The original target wikitext
	 * - title: A title object *or*
	 * - language: An interwikiInfo object *or*
	 * - interwiki: An interwikiInfo object.
	 * - localprefix: Set if the link had a localinterwiki prefix (or prefixes)
	 * - fromColonEscapedText: Target was colon-escaped ([[:en:foo]])
	 * - prefix: The original namespace or language/interwiki prefix without a
	 *   colon escape.
	 *
	 * @param Token $token
	 * @param string $href
	 * @param string $hrefSrc
	 * @return stdClass The target info.
	 * @throws InternalException
	 */
	private function getWikiLinkTargetInfo( Token $token, string $href, string $hrefSrc ): stdClass {
		$env = $this->manager->env;
		$siteConfig = $env->getSiteConfig();
		$info = (object)[
			'href' => $href,
			'hrefSrc' => $hrefSrc,
			// Initialize these properties to avoid isset checks
			'interwiki' => null,
			'language' => null,
			'localprefix' => null,
			'fromColonEscapedText' => null
		];

		if ( preg_match( '/^:/', $info->href ) ) {
			$info->fromColonEscapedText = true;
			// remove the colon escape
			$info->href = substr( $info->href, 1 );
		}
		if ( preg_match( '/^:/', $info->href ) ) {
			if ( $siteConfig->linting() ) {
				$lint = [
					'dsr' => DomSourceRange::fromTsr( $token->dataAttribs->tsr ),
					'params' => [ 'href' => ':' . $info->href ],
					'templateInfo' => null
				];
				if ( $this->options['inTemplate'] ) {
					// Match Linter.findEnclosingTemplateName(), by first
					// converting the title to an href using env.makeLink
					$name = preg_replace(
						'#^\./#', '',
						$env->makeLink( $this->manager->getFrame()->getTitle() ),
						1
					);
					$lint['templateInfo'] = [ 'name' => $name ];
					// TODO(arlolra): Pass tsr info to the frame
					$lint['dsr'] = new DomSourceRange( 0, 0, null, null );
				}
				$env->recordLint( 'lint/multi-colon-escape', $lint );
			}
			// This will get caught by the caller, and mark the target as invalid
			throw new InternalException( 'Multiple colons prefixing href.' );
		}

		$title = $env->resolveTitle( Utils::decodeURIComponent( $info->href ) );
		$hrefBits = self::hrefParts( $info->href );
		if ( $hrefBits ) {
			$nsPrefix = $hrefBits['prefix'];
			$info->prefix = $nsPrefix;
			$nnn = Utils::normalizeNamespaceName( trim( $nsPrefix ) );
			$interwikiInfo = $siteConfig->interwikiMap()[$nnn] ?? null;
			// check for interwiki / language links
			$ns = $siteConfig->namespaceId( $nnn );
			// also check for url to protect against [[constructor:foo]]
			if ( $ns !== null ) {
				$info->title = $env->makeTitleFromURLDecodedStr( $title );
			} elseif ( isset( $interwikiInfo['localinterwiki'] ) ) {
				if ( $hrefBits['title'] === '' ) {
					// Empty title => main page (T66167)
					$info->title = $env->makeTitleFromURLDecodedStr( $siteConfig->mainpage() );
				} else {
					$info->href = ( preg_match( '/:/', $hrefBits['title'] ) ? ':' : '' ) . $hrefBits['title'];
					// Recurse!
					$info = $this->getWikiLinkTargetInfo( $token, $info->href, $info->hrefSrc );
					$info->localprefix = $nsPrefix .
						( $info->localprefix ? ( ':' . $info->localprefix ) : '' );
				}
			} elseif ( !empty( $interwikiInfo['url'] ) ) {
				$info->href = $hrefBits['title'];
				// Ensure a valid title, even though we're discarding the result
				$env->makeTitleFromURLDecodedStr( $title );
				// Interwiki or language link? If no language info, or if it starts
				// with an explicit ':' (like [[:en:Foo]]), it's not a language link.
				if ( $info->fromColonEscapedText ||
					( !isset( $interwikiInfo['language'] ) && !isset( $interwikiInfo['extralanglink'] ) )
				) {
					// An interwiki link.
					$info->interwiki = $interwikiInfo;
				} else {
					// A language link.
					$info->language = $interwikiInfo;
				}
			} else {
				$info->title = $env->makeTitleFromURLDecodedStr( $title );
			}
		} else {
			$info->title = $env->makeTitleFromURLDecodedStr( $title );
		}

		return $info;
	}

	/**
	 * Handle mw:redirect tokens
	 *
	 * @param Token $token
	 * @return array
	 * @throws InternalException
	 */
	private function onRedirect( Token $token ): array {
		// Avoid duplicating the link-processing code by invoking the
		// standard onWikiLink handler on the embedded link, intercepting
		// the generated tokens using the callback mechanism, reading
		// the href from the result, and then creating a
		// <link rel="mw:PageProp/redirect"> token from it.

		$rlink = new SelfclosingTagTk( 'link', Utils::clone( $token->attribs ),
			Utils::clone( $token->dataAttribs ) );
		$wikiLinkTk = $rlink->dataAttribs->linkTk;
		$rlink->setAttribute( 'rel', 'mw:PageProp/redirect' );

		// Remove the nested wikiLinkTk token and the cloned href attribute
		unset( $rlink->dataAttribs->linkTk );
		$rlink->removeAttribute( 'href' );

		// Transfer href attribute back to wikiLinkTk, since it may have been
		// template-expanded in the pipeline prior to this point.
		$wikiLinkTk->attribs = Utils::clone( $token->attribs );

		// Set "redirect" attribute on the wikilink token to indicate that
		// image and category links should be handled as plain links.
		$wikiLinkTk->setAttribute( 'redirect', 'true' );

		// Render the wikilink (including interwiki links, etc) then collect
		// the resulting href and transfer it to rlink.
		$r = $this->onWikiLink( $wikiLinkTk );
		$firstToken = ( $r['tokens'][0] ?? null );
		$isValid = $firstToken instanceof Token &&
			preg_match( '/^(a|link)$/D', $firstToken->getName() );
		if ( $isValid ) {
			$da = $r['tokens'][0]->dataAttribs;
			$rlink->addNormalizedAttribute( 'href', $da->a['href'], $da->sa['href'] );
			return [ 'tokens' => [ $rlink ] ];
		} else {
			// Bail!  Emit tokens as if they were parsed as a list item:
			// #REDIRECT....
			$src = $rlink->dataAttribs->src;
			$tsr = $rlink->dataAttribs->tsr;
			preg_match( '/^([^#]*)(#)/', $src, $srcMatch );
			$ntokens = strlen( $srcMatch[1] ) ? [ $srcMatch[1] ] : [];
			$hashPos = $tsr->start + strlen( $srcMatch[1] );
			$tsr0 = new SourceRange( $hashPos, $hashPos + 1 );
			$li = new TagTk( 'listItem', [
				new KV( 'bullets', [ '#' ], $tsr0->expandTsrV() )
			], (object)[ 'tsr' => $tsr0 ] );
			$ntokens[] = $li;
			$ntokens[] = substr( $src, strlen( $srcMatch[0] ) );
			return [ 'tokens' => array_merge( $ntokens, $r['tokens'] ) ];
		}
	}

	/**
	 * @param Env $env
	 * @param Token $token
	 * @param bool $isExtLink
	 * @return array
	 */
	public static function bailTokens( Env $env, Token $token, bool $isExtLink ): array {
		$count = $isExtLink ? 1 : 2;
		$tokens = [ str_repeat( '[', $count ) ];
		$content = [];

		if ( $isExtLink ) {
			// FIXME: Use this attribute in regular extline
			// cases to rt spaces correctly maybe?  Unsure
			// it is worth it.
			$spaces = $token->getAttribute( 'spaces' ) ?? '';
			if ( strlen( $spaces ) ) {
				$content[] = $spaces;
			}

			$mwc = $token->getAttribute( 'mw:content' );
			if ( is_string( $mwc ) ) {
				$content[] = $mwc;
			} elseif ( count( $mwc ) ) {
				$content = array_merge( $content, $mwc );
			}
		} else {
			foreach ( $token->attribs as $kv ) {
				if ( $kv->k === 'mw:maybeContent' ) {
					$content[] = '|';
					$content = array_merge( $content, $kv->v );
				}
			}
		}

		$dft = null;
		if ( TokenUtils::hasTypeOf( $token, 'mw:ExpandedAttrs' ) ) {
			$attribs = PHPUtils::jsonDecode( $token->getAttribute( 'data-mw' ), false )->attribs;
			$html = null;
			foreach ( $attribs as $a ) {
				if ( $a[0]->txt === 'href' ) {
					$html = $a[1]->html;
					break;
				}
			}

			// Since we are splicing off '['s and ']'s from the incoming token,
			// adjust TSR of the DOM-fragment by `count` each on both end.
			$tsr = $token->dataAttribs->tsr ?? null;
			if ( $tsr && $tsr->start !== null && $tsr->end !== null ) {
				// If content is present, the fragment we're building doesn't
				// extend all the way to the end of the token, so the end tsr
				// is invalid.
				$end = count( $content ) > 0 ? null : $tsr->end - $count;
				// XXX it would be better to compute an actual value for
				// $end here if possible
				$tsr = new SourceRange( $tsr->start + $count, $end );
			} else {
				$tsr = null;
			}

			$body = ContentUtils::ppToDOM( $env, $html );
			$dft = PipelineUtils::tunnelDOMThroughTokens( $env, $token, $body, [
					'tsr' => $tsr,
					'pipelineOpts' => [ 'inlineContext' => true ]
				]
			);
		} else {
			$dft = $token->getAttribute( 'href' );
			if ( !is_array( $dft ) ) {
				$dft = [ $dft ];
			}
		}

		return array_merge( $tokens, $dft,
			is_array( $content ) ? $content : [ $content ], [ str_repeat( ']', $count ) ] );
	}

	/**
	 * Handle a mw:WikiLink token.
	 *
	 * @param Token $token
	 * @return array|bool|string
	 * @throws InternalException
	 */
	private function onWikiLink( Token $token ) {
		$env = $this->manager->env;
		$hrefKV = $token->getAttributeKV( 'href' );
		$hrefTokenStr = TokenUtils::tokensToString( $hrefKV->v );

		// Don't allow internal links to pages containing PROTO:
		// See Parser::replaceInternalLinks2()
		if ( $env->getSiteConfig()->hasValidProtocol( $hrefTokenStr ) ) {
			$src = substr( $token->dataAttribs->tsr->substr(
				$this->manager->getFrame()->getSrcText()
			), 1, -1 );
			$extToks = $this->urlParser->tokenizeExtlink( $src, /* sol */true );
			if ( $extToks !== false ) {
				TokenUtils::shiftTokenTSR( $extToks, $token->dataAttribs->tsr->start + 1 );
			} else {
				$extToks = [ $src ];
			}

			$tokens = array_merge( [ '[' ], $extToks, [ ']' ] );
			return [ 'tokens' => $tokens ];
		}

		// Xmlish tags in title position are invalid.  Not according to the
		// preprocessor ABNF but at later stages in the legacy parser,
		// namely replaceInternalLinks.
		if (
			is_array( $hrefKV->v ) &&
			TokenUtils::hasTypeOf( $token, 'mw:ExpandedAttrs' )
		) {
			$attribs = PHPUtils::jsonDecode( $token->getAttribute( 'data-mw' ), false )->attribs;
			foreach ( $attribs as $a ) {
				if ( $a[0]->txt === 'href' ) {
					if ( preg_match( '/mw:(Nowiki|Extension)/', $a[1]->html ) ) {
						return [ 'tokens' => self::bailTokens( $env, $token, false ) ];
					}
					break;
				}
			}
		}

		// First check if the expanded href contains a pipe.
		if ( preg_match( '/[|]/', $hrefTokenStr ) ) {
			// It does. This 'href' was templated and also returned other
			// parameters separated by a pipe. We don't have any sane way to
			// handle such a construct currently, so prevent people from editing
			// it.  See T226523
			// TODO: add useful debugging info for editors ('if you would like to
			// make this content editable, then fix template X..')
			// TODO: also check other parameters for pipes!
			return [ 'tokens' => self::bailTokens( $env, $token, false ) ];
		}

		$target = null;
		try {
			$target = $this->getWikiLinkTargetInfo( $token, $hrefTokenStr, $hrefKV->vsrc );
		} catch ( TitleException | InternalException $e ) {
			// Invalid title
			return [ 'tokens' => self::bailTokens( $env, $token, false ) ];
		}

		// Ok, it looks like we have a sane href. Figure out which handler to use.
		$isRedirect = (bool)$token->getAttribute( 'redirect' );
		return $this->wikiLinkHandler( $token, $target, $isRedirect );
	}

	/**
	 * Figure out which handler to use to render a given WikiLink token. Override
	 * this method to add new handlers or swap out existing handlers based on the
	 * target structure.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @param bool $isRedirect
	 * @return array
	 * @throws InternalException
	 */
	private function wikiLinkHandler( Token $token, stdClass $target, bool $isRedirect ) {
		$title = $target->title ?? null;
		if ( $title ) {
			if ( $isRedirect ) {
				return $this->renderWikiLink( $token, $target );
			}
			$ns = $title->getNamespace();
			if ( $ns->isMedia() ) {
				// Render as a media link.
				return $this->renderMedia( $token, $target );
			}
			if ( !$target->fromColonEscapedText ) {
				if ( $ns->isFile() ) {
					// Render as a file.
					return $this->renderFile( $token, $target );
				}
				if ( $ns->isCategory() ) {
					// Render as a category membership.
					return $this->renderCategory( $token, $target );
				}
			}

			// Render as plain wiki links.
			return $this->renderWikiLink( $token, $target );
		}

		// language and interwiki links
		if ( $target->interwiki ) {
			return $this->renderInterwikiLink( $token, $target );
		}
		if ( $target->language ) {
			$ns = $this->env->getPageConfig()->getNs();
			$noLanguageLinks = $this->env->getSiteConfig()->namespaceIsTalk( $ns ) ||
				!$this->env->getSiteConfig()->interwikimagic();
			if ( $noLanguageLinks ) {
				$target->interwiki = $target->language;
				return $this->renderInterwikiLink( $token, $target );
			}

			return $this->renderLanguageLink( $token, $target );
		}

		// Neither a title, nor a language or interwiki. Should not happen.
		throw new InternalException( 'Unknown link type' );
	}

	/** ------------------------------------------------------------
	 * This (overloaded) function does three different things:
	 * - Extracts link text from attrs (when k === "mw:maybeContent").
	 *   As a performance micro-opt, only does if asked to (getLinkText)
	 * - Updates existing rdfa type with an additional rdf-type,
	 *   if one is provided (rdfaType)
	 * - Collates about, typeof, and linkAttrs into a new attr. array
	 *
	 * @param array $attrs
	 * @param bool $getLinkText
	 * @param string|null $rdfaType
	 * @param array|null $linkAttrs
	 * @return array
	 */
	public static function buildLinkAttrs( array $attrs, bool $getLinkText,
			?string $rdfaType, ?array $linkAttrs ): array {
		$newAttrs = [];
		$linkTextKVs = [];
		$about = null;

		// In one pass through the attribute array, fetch about, typeof, and linkText
		//
		// about && typeof are usually at the end of the array if at all present
		foreach ( $attrs as $kv ) {
			$k = $kv->k;
			$v = $kv->v;

			// link-text attrs have the key "maybeContent"
			if ( $getLinkText && $k === 'mw:maybeContent' ) {
				$linkTextKVs[] = $kv;
			} elseif ( is_string( $k ) && $k ) {
				if ( trim( $k ) === 'typeof' ) {
					$rdfaType = $rdfaType ? $rdfaType . ' ' . $v : $v;
				} elseif ( trim( $k ) === 'about' ) {
					$about = $v;
				} elseif ( trim( $k ) === 'data-mw' ) {
					$newAttrs[] = $kv;
				}
			}
		}

		if ( $rdfaType ) {
			$newAttrs[] = new KV( 'typeof', $rdfaType );
		}

		if ( $about ) {
			$newAttrs[] = new KV( 'about', $about );
		}

		if ( $linkAttrs ) {
			$newAttrs = array_merge( $newAttrs, $linkAttrs );
		}

		return [
			'attribs' => $newAttrs,
			'contentKVs' => $linkTextKVs,
			'hasRdfaType' => $rdfaType !== null
		];
	}

	/**
	 * Generic wiki link attribute setup on a passed-in new token based on the
	 * wikilink token and target. As a side effect, this method also extracts the
	 * link content tokens and returns them.
	 *
	 * @param Token $newTk
	 * @param Token $token
	 * @param stdClass $target
	 * @param bool $buildDOMFragment
	 * @return array
	 */
	private function addLinkAttributesAndGetContent(
		Token $newTk, Token $token, stdClass $target, bool $buildDOMFragment = false
	): array {
		$attribs = $token->attribs;
		$dataAttribs = $token->dataAttribs;
		$newAttrData = self::buildLinkAttrs( $attribs, true, null, [ new KV( 'rel', 'mw:WikiLink' ) ] );
		$content = $newAttrData['contentKVs'];
		$env = $this->manager->env;

		// Set attribs and dataAttribs
		$newTk->attribs = $newAttrData['attribs'];
		$newTk->dataAttribs = Utils::clone( $dataAttribs );
		unset( $newTk->dataAttribs->src ); // clear src string since we can serialize this

		// Note: Link tails are handled on the DOM in handleLinkNeighbours, so no
		// need to handle them here.
		$l = count( $content );
		if ( $l > 0 ) {
			$newTk->dataAttribs->stx = 'piped';
			$out = [];
			// re-join content bits
			foreach ( $content as $i => $kv ) {
				$toks = $kv->v;
				// since this is already a link, strip autolinks from content
				if ( !is_array( $toks ) ) {
					$toks = [ $toks ];
				}

				$toks = array_values( array_filter( $toks, function ( $t ) {
					return $t !== '';
				} ) );
				$n = count( $toks );
				$newToks = [];
				foreach ( $toks as $j => $t ) {
					if ( $t instanceof TagTk && $t->getName() === 'a' ) {
						if ( $j + 1 < $n && $toks[$j + 1] instanceof EndTagTk &&
							$toks[$j + 1]->getName() === 'a'
						) {
							// autonumbered links in the stream get rendered
							// as an <a> tag with no content -- but these ought
							// to be treated as plaintext since we don't allow
							// nested links.
							$newToks[] = '[' . $t->getAttribute( 'href' ) . ']';
						}
						// suppress <a>
						continue;
					}

					if ( $t instanceof EndTagTk && $t->getName() === 'a' ) {
						continue; // suppress </a>
					}

					$newToks[] = $t;
				}
				$out = array_merge( $out, $newToks );
				if ( $i < $l - 1 ) {
					$out[] = '|';
				}
			}

			if ( $buildDOMFragment ) {
				// content = [part 0, .. part l-1]
				// offsets = [start(part-0), end(part l-1)]
				$offsets = isset( $dataAttribs->tsr ) ?
					new SourceRange( $content[0]->srcOffsets->key->start,
						$content[$l - 1]->srcOffsets->key->end ) : null;
				$content = [ PipelineUtils::getDOMFragmentToken( $out, $offsets,
					[ 'inlineContext' => true, 'token' => $token ] ) ];
			} else {
				$content = $out;
			}
		} else {
			$newTk->dataAttribs->stx = 'simple';
			$morecontent = Utils::decodeURIComponent( $target->href );

			// Strip leading colon
			$morecontent = preg_replace( '/^:/', '', $morecontent, 1 );

			// Try to match labeling in core
			if ( $env->getSiteConfig()->namespaceHasSubpages( $env->getPageConfig()->getNs() ) ) {
				// subpage links with a trailing slash get the trailing slashes stripped.
				// See https://gerrit.wikimedia.org/r/173431
				if ( preg_match( '#^((\.\./)+|/)(?!\.\./)(.*?[^/])/+$#D', $morecontent, $match ) ) {
					$morecontent = $match[3];
				} elseif ( preg_match( '#^\.\./#', $morecontent ) ) {
					$morecontent = $env->resolveTitle( $morecontent );
				}
			}

			// for interwiki links, include the interwiki prefix in the link text
			if ( $target->interwiki ) {
				$morecontent = $target->prefix . ':' . $morecontent;
			}

			// for local links, include the local prefix in the link text
			if ( $target->localprefix ) {
				$morecontent = $target->localprefix . ':' . $morecontent;
			}

			$content = [ $morecontent ];
		}
		return $content;
	}

	/**
	 * Render a plain wiki link.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderWikiLink( Token $token, stdClass $target ): array {
		$newTk = new TagTk( 'a' );
		$content = $this->addLinkAttributesAndGetContent( $newTk, $token, $target, true );

		$newTk->addNormalizedAttribute( 'href', $this->env->makeLink( $target->title ),
			$target->hrefSrc );

		// Add title unless it's just a fragment
		if ( $target->href[0] !== '#' ) {
			$newTk->setAttribute( 'title', $target->title->getPrefixedText() );
		}

		return [ 'tokens' => array_merge( [ $newTk ], $content, [ new EndTagTk( 'a' ) ] ) ];
	}

	/**
	 * Render a category 'link'. Categories are really page properties, and are
	 * normally rendered in a box at the bottom of an article.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderCategory( Token $token, stdClass $target ): array {
		$newTk = new SelfclosingTagTk( 'link' );
		$content = $this->addLinkAttributesAndGetContent( $newTk, $token, $target );
		$env = $this->manager->env;

		// Change the rel to be mw:PageProp/Category
		$newTk->getAttributeKV( 'rel' )->v = 'mw:PageProp/Category';

		$strContent = TokenUtils::tokensToString( $content );
		$saniContent = preg_replace( '/#/', '%23', Sanitizer::sanitizeTitleURI( $strContent, false ) );
		$newTk->addNormalizedAttribute( 'href', $env->makeLink( $target->title ), $target->hrefSrc );
		// Change the href to include the sort key, if any (but don't update the rt info)
		if ( isset( $strContent ) && $strContent !== '' && $strContent !== $target->href ) {
			$hrefkv = $newTk->getAttributeKV( 'href' );
			$hrefkv->v .= '#';
			$hrefkv->v .= $saniContent;
		}

		if ( count( $content ) !== 1 ) {
			// Deal with sort keys that come from generated content (transclusions, etc.)
			$key = [ 'txt' => 'mw:sortKey' ];
			$contentKV = $token->getAttributeKV( 'mw:maybeContent' );
			$so = $contentKV->valueOffset();
			$val = PipelineUtils::expandValueToDOM(
				$this->manager->env,
				$this->manager->getFrame(),
				[ 'html' => $content, 'srcOffsets' => $so ],
				$this->options['expandTemplates'],
				$this->options['inTemplate']
			);
			$attr = [ $key, $val ];
			$dataMW = $newTk->getAttribute( 'data-mw' );
			if ( $dataMW ) {
				$dataMW = PHPUtils::jsonDecode( $dataMW, false );
				$dataMW->attribs[] = $attr;
			} else {
				$dataMW = (object)[ 'attribs' => [ $attr ] ];
			}

			// Mark token as having expanded attrs
			$newTk->addAttribute( 'about', $env->newAboutId() );
			$newTk->addSpaceSeparatedAttribute( 'typeof', 'mw:ExpandedAttrs' );
			$newTk->addAttribute( 'data-mw', PHPUtils::jsonEncode( $dataMW ) );
		}
		return [ 'tokens' => [ $newTk ] ];
	}

	/**
	 * Render a language link. Those normally appear in the list of alternate
	 * languages for an article in the sidebar, so are really a page property.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderLanguageLink( Token $token, stdClass $target ): array {
		// The prefix is listed in the interwiki map

		$newTk = new SelfclosingTagTk( 'link', [], $token->dataAttribs );
		$this->addLinkAttributesAndGetContent( $newTk, $token, $target );

		// add title attribute giving the presentation name of the
		// "extra language link"
		if ( isset( $target->language['extralanglink'] ) &&
			!empty( $target->language['linktext'] )
		) {
			$newTk->addNormalizedAttribute( 'title', $target->language['linktext'], null );
		}

		// We set an absolute link to the article in the other wiki/language
		$title = Sanitizer::sanitizeTitleURI( Utils::decodeURIComponent( $target->href ), false );
		$absHref = str_replace( '$1', $title, $target->language['url'] );
		if ( isset( $target->language['protorel'] ) ) {
			$absHref = preg_replace( '/^https?:/', '', $absHref, 1 );
		}
		$newTk->addNormalizedAttribute( 'href', $absHref, $target->hrefSrc );

		// Change the rel to be mw:PageProp/Language
		$newTk->getAttributeKV( 'rel' )->v = 'mw:PageProp/Language';

		return [ 'tokens' => [ $newTk ] ];
	}

	/**
	 * Render an interwiki link.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderInterwikiLink( Token $token, stdClass $target ): array {
		// The prefix is listed in the interwiki map

		$tokens = [];
		$newTk = new TagTk( 'a', [], $token->dataAttribs );
		$content = $this->addLinkAttributesAndGetContent( $newTk, $token, $target, true );

		// We set an absolute link to the article in the other wiki/language
		$isLocal = !empty( $target->interwiki['local'] );
		$title = Sanitizer::sanitizeTitleURI( Utils::decodeURIComponent( $target->href ), !$isLocal );
		$absHref = str_replace( '$1', $title, $target->interwiki['url'] );
		if ( isset( $target->interwiki['protorel'] ) ) {
			$absHref = preg_replace( '/^https?:/', '', $absHref, 1 );
		}
		$newTk->addNormalizedAttribute( 'href', $absHref, $target->hrefSrc );

		// Change the rel to be mw:ExtLink
		$newTk->getAttributeKV( 'rel' )->v = 'mw:WikiLink/Interwiki';
		// Remember that this was using wikitext syntax though
		$newTk->dataAttribs->isIW = true;
		// Add title unless it's just a fragment (and trim off fragment)
		// (The normalization here is similar to what Title#getPrefixedDBKey() does.)
		if ( $target->href === '' || $target->href[0] !== '#' ) {
			$titleAttr = $target->interwiki['prefix'] . ':' .
				Utils::decodeURIComponent( preg_replace( '/_/', ' ',
					preg_replace( '/#[\s\S]*/', '', $target->href, 1 ) ) );
			$newTk->setAttribute( 'title', $titleAttr );
		}
		$tokens[] = $newTk;

		$tokens = array_merge( $tokens, $content, [ new EndTagTk( 'a' ) ] );
		return [ 'tokens' => $tokens ];
	}

	/**
	 * Get the style and class lists for an image's wrapper element.
	 *
	 * @param array $opts The option hash from renderFile.
	 * @return array with boolean isInline Whether the image is inline after handling options.
	 *               or classes The list of classes for the wrapper.
	 */
	private static function getWrapperInfo( array $opts ) {
		$format = self::getFormat( $opts );
		$isInline = !( $format === 'thumbnail' || $format === 'framed' );
		$classes = [];
		$halign = ( $opts['format']['v'] ?? '' ) === 'framed' ? 'right' : null;

		if ( !isset( $opts['size']['src'] ) ) {
			$classes[] = 'mw-default-size';
		}

		if ( isset( $opts['border'] ) ) {
			$classes[] = 'mw-image-border';
		}

		if ( isset( $opts['halign'] ) ) {
			$halign = $opts['halign']['v'];
		}

		$halignOpt = $opts['halign']['v'] ?? null;
		switch ( $halign ) {
			case 'none':
				// PHP parser wraps in <div class="floatnone">
				$isInline = false;
				if ( $halignOpt === 'none' ) {
					$classes[] = 'mw-halign-none';
				}
				break;

			case 'center':
				// PHP parser wraps in <div class="center"><div class="floatnone">
				$isInline = false;
				if ( $halignOpt === 'center' ) {
					$classes[] = 'mw-halign-center';
				}
				break;

			case 'left':
				// PHP parser wraps in <div class="floatleft">
				$isInline = false;
				if ( $halignOpt === 'left' ) {
					$classes[] = 'mw-halign-left';
				}
				break;

			case 'right':
				// PHP parser wraps in <div class="floatright">
				$isInline = false;
				if ( $halignOpt === 'right' ) {
					$classes[] = 'mw-halign-right';
				}
				break;
		}

		if ( $isInline ) {
			$valignOpt = $opts['valign']['v'] ?? null;
			switch ( $valignOpt ) {
				case 'middle':
					$classes[] = 'mw-valign-middle';
					break;

				case 'baseline':
					$classes[] = 'mw-valign-baseline';
					break;

				case 'sub':
					$classes[] = 'mw-valign-sub';
					break;

				case 'super':
					$classes[] = 'mw-valign-super';
					break;

				case 'top':
					$classes[] = 'mw-valign-top';
					break;

				case 'text_top':
					$classes[] = 'mw-valign-text-top';
					break;

				case 'bottom':
					$classes[] = 'mw-valign-bottom';
					break;

				case 'text_bottom':
					$classes[] = 'mw-valign-text-bottom';
					break;

				default:
					break;
			}
		}

		return [ 'classes' => $classes, 'isInline' => $isInline ];
	}

	/**
	 * Determine the name of an option.
	 *
	 * @param string $optStr
	 * @param Env $env
	 * @return array|null
	 * 	 ck Canonical key for the image option.
	 *   v Value of the option.
	 *   ak Aliased key for the image option - includes `"$1"` for placeholder.
	 *   s Whether it's a simple option or one with a value.
	 */
	private static function getOptionInfo( string $optStr, Env $env ): ?array {
		$oText = trim( $optStr );
		$siteConfig = $env->getSiteConfig();
		$getOption = $siteConfig->getMediaPrefixParameterizedAliasMatcher();
		// oText contains the localized name of this option.  the
		// canonical option names (from mediawiki upstream) are in
		// English and contain an '(img|timedmedia)_' prefix.  We drop the
		// prefix before stuffing them in data-parsoid in order to
		// save space (that's shortCanonicalOption)
		$canonicalOption = $siteConfig->magicWordCanonicalName( $oText ) ?? '';
		$shortCanonicalOption = preg_replace( '/^(img|timedmedia)_/', '', $canonicalOption, 1 );
		// 'imgOption' is the key we'd put in opts; it names the 'group'
		// for the option, and doesn't have an img_ prefix.
		$imgOption = WikitextConstants::$Media['SimpleOptions'][$canonicalOption] ?? null;
		$bits = $getOption( $oText );
		$normalizedBit0 = $bits ? mb_strtolower( trim( $bits['k'] ) ) : null;
		$key = $bits ? ( WikitextConstants::$Media['PrefixOptions'][$normalizedBit0] ?? null ) : null;

		if ( !empty( $imgOption ) && $key === null ) {
			return [
				'ck' => $imgOption,
				'v' => $shortCanonicalOption,
				'ak' => $optStr,
				's' => true
			];
		}

		// bits.a has the localized name for the prefix option
		// (with $1 as a placeholder for the value, which is in bits.v)
		// 'normalizedBit0' is the canonical English option name
		// (from mediawiki upstream) with a prefix.
		// 'key' is the parsoid 'group' for the option; it doesn't
		// have a prefix (it's the key we'd put in opts)
		if ( $bits && $key ) {
			$shortCanonicalOption = preg_replace( '/^(img|timedmedia)_/', '', $normalizedBit0, 1 );
			// map short canonical name to the localized version used

			// Note that we deliberately do entity decoding
			// *after* splitting so that HTML-encoded pipes don't
			// separate options.  This matches PHP, whether or
			// not it's a good idea.
			return [
				'ck' => $shortCanonicalOption,
				'v' => Utils::decodeWtEntities( $bits['v'] ),
				'ak' => $optStr,
				's' => false
			];
		}

		return null;
	}

	/**
	 * @param Env $env
	 * @param ?array &$optInfo
	 * @param string $prefix
	 * @param string $resultStr
	 * @return bool
	 */
	private static function isWikitextOpt( Env $env, ?array &$optInfo, string $prefix,
											  string $resultStr ): bool {
		// link and alt options are allowed to contain arbitrary
		// wikitext (even though only strings are supported in reality)
		// SSS FIXME: Is this actually true of all options rather than
		// just link and alt?
		if ( $optInfo === null ) {
			$optInfo = self::getOptionInfo( $prefix . $resultStr, $env );
		}
		return $optInfo !== null && preg_match( '/^(link|alt)$/D', $optInfo['ck'] );
	}

	/**
	 * Make option token streams into a stringy thing that we can recognize.
	 *
	 * @param array $tstream
	 * @param string $prefix Anything that came before this part of the recursive call stack.
	 * @param Env $env
	 * @return string|string[]|null
	 */
	private static function stringifyOptionTokens( array $tstream, string $prefix, Env $env ) {
		// Seems like this should be a more general "stripTags"-like function?
		$tokenType = null;
		$tkHref = null;
		$nextResult = null;
		$skipToEndOf = null;
		$optInfo = null;
		$resultStr = '';

		for ( $i = 0;  $i < count( $tstream );  $i++ ) {
			$currentToken = $tstream[$i];

			if ( $skipToEndOf ) {
				if ( $currentToken instanceof EndTagTk && $currentToken->getName() === $skipToEndOf ) {
					$skipToEndOf = null;
				}
				continue;
			}

			if ( is_string( $currentToken ) ) {
				$resultStr .= $currentToken;
			} elseif ( is_array( $currentToken ) ) {
				$nextResult = self::stringifyOptionTokens( $currentToken, $prefix . $resultStr, $env );

				if ( $nextResult === null ) {
					return null;
				}

				$resultStr .= $nextResult;
			} elseif ( !( $currentToken instanceof EndTagTk ) ) {
				// This is actually a token
				if ( TokenUtils::hasDOMFragmentType( $currentToken ) ) {
					if ( self::isWikitextOpt( $env, $optInfo, $prefix, $resultStr ) ) {
						$str = TokenUtils::tokensToString( [ $currentToken ], false, [
								'unpackDOMFragments' => true,
								'env' => $env
							]
						);
						// Entity encode pipes since we wouldn't have split on
						// them from fragments and we're about to attempt to
						// when this function returns.
						// This is similar to getting the shadow "href" below.
						// FIXME: Sneaking in `env` to avoid changing the signature

						$resultStr .= preg_replace( '/\|/', '&vert;', $str, 1 );
						$optInfo = null; // might change the nature of opt
						continue;
					} else {
						// if this is a nowiki, we must be in a caption
						return null;
					}
				}
				if ( $currentToken->getName() === 'mw-quote' ) {
					if ( self::isWikitextOpt( $env, $optInfo, $prefix, $resultStr ) ) {
						// just recurse inside
						$optInfo = null; // might change the nature of opt
						continue;
					}
				}
				// Similar to TokenUtils.tokensToString()'s includeEntities
				if ( TokenUtils::isEntitySpanToken( $currentToken ) ) {
					$resultStr .= $currentToken->dataAttribs->src;
					$skipToEndOf = 'span';
					continue;
				}
				if ( $currentToken->getName() === 'a' ) {
					if ( $optInfo === null ) {
						$optInfo = self::getOptionInfo( $prefix . $resultStr, $env );
						if ( $optInfo === null ) {
							// An <a> tag before a valid option?
							// This is most likely a caption.
							$optInfo = null;
							return null;
						}
					}

					if ( self::isWikitextOpt( $env, $optInfo, $prefix, $resultStr ) ) {
						$tokenType = $currentToken->getAttribute( 'rel' );
						// Using the shadow since entities (think pipes) would
						// have already been decoded.
						$tkHref = $currentToken->getAttributeShadowInfo( 'href' )['value'];
						$isLink = $optInfo && $optInfo['ck'] === 'link';
						// Reset the optInfo since we're changing the nature of it
						$optInfo = null;
						// Figure out the proper string to put here and break.
						if (
							$tokenType === 'mw:ExtLink' &&
							( $currentToken->dataAttribs->stx ?? '' ) === 'url'
						) {
							// Add the URL
							$resultStr .= $tkHref;
							// Tell our loop to skip to the end of this tag
							$skipToEndOf = 'a';
						} elseif ( $tokenType === 'mw:WikiLink/Interwiki' ) {
							if ( $isLink ) {
								$resultStr .= $currentToken->getAttribute( 'href' );
								$i += 2;
								continue;
							}
							// Nothing to do -- the link content will be
							// captured by walking the rest of the tokens.
						} elseif ( $tokenType === 'mw:WikiLink' || $tokenType === 'mw:MediaLink' ) {

							// Nothing to do -- the link content will be
							// captured by walking the rest of the tokens.
						} else {
							// There shouldn't be any other kind of link...
							// This is likely a caption.
							return null;
						}
					} else {
						// Why would there be an a tag without a link?
						return null;
					}
				}
			}
		}

		return $resultStr;
	}

	/**
	 * Get the format for media.
	 *
	 * @param array $opts
	 * @return string|null
	 */
	private static function getFormat( array $opts ) {
		if ( $opts['manualthumb'] ) {
			return 'thumbnail';
		}

		return $opts['format']['v'] ?? null;
	}

	private $used;

	/**
	 * This is the set of file options that apply to the container, rather
	 * than the media element itself (or, apply generically to a span).
	 * Other options depend on the fetched media type and won't necessary be
	 * applied.
	 *
	 * @return array
	 */
	private function getUsed(): array {
		if ( $this->used ) {
			return $this->used;
		}
		$this->used = PHPUtils::makeSet( [
				'lang', 'width', 'class', 'upright',
				'border', 'frameless', 'framed', 'thumbnail',
				'left', 'right', 'center', 'none',
				'baseline', 'sub', 'super', 'top', 'text_top', 'middle', 'bottom', 'text_bottom'
			]
		);
		return $this->used;
	}

	/**
	 * @param array $toks
	 * @return bool
	 */
	private function hasTransclusion( array $toks ): bool {
		foreach ( $toks as $t ) {
			if (
				$t instanceof SelfclosingTagTk &&
				TokenUtils::hasTypeOf( $t, 'mw:Transclusion' )
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Render a file. This can be an image, a sound, a PDF etc.
	 *
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderFile( Token $token, stdClass $target ) {
		$manager = $this->manager;
		$env = $manager->env;

		// FIXME: Re-enable use of media cache and figure out how that fits
		// into this new processing model. See T98995
		// const cachedMedia = env.mediaCache[token.dataAttribs.src];

		$dataAttribs = Utils::clone( $token->dataAttribs );
		$dataAttribs->optList = [];

		// Account for the possibility of an expanded target
		$dataMwAttr = $token->getAttribute( 'data-mw' );
		$dataMw = $dataMwAttr ? PHPUtils::jsonDecode( $dataMwAttr, false ) : new stdClass;

		$opts = [
			'title' => [
				'v' => $env->makeLink( $target->title ),
				'src' => $token->getAttributeKV( 'href' )->vsrc
			],
			'size' => [
				'v' => [
					'height' => null,
					'width' => null
				]
			],
			// Initialize these properties to avoid isset checks
			'caption' => null,
			'format' => null,
			'manualthumb' => null,
			'class' => null
		];

		$hasExpandableOpt = false;

		$optKVs = self::buildLinkAttrs( $token->attribs, true, null, null )['contentKVs'];
		while ( count( $optKVs ) > 0 ) {
			$oContent = array_shift( $optKVs );
			Assert::invariant( $oContent instanceof KV, 'bad type' );

			$origOptSrc = $oContent->v;
			if ( is_array( $origOptSrc ) && count( $origOptSrc ) === 1 ) {
				$origOptSrc = $origOptSrc[0];
			}

			$oText = TokenUtils::tokensToString( $origOptSrc, true, [ 'includeEntities' => true ] );

			if ( !is_string( $oText ) ) {
				// Might be that this is a valid option whose value is just
				// complicated. Try to figure it out, step through all tokens.
				$maybeOText = self::stringifyOptionTokens( $oText, '', $env );
				if ( $maybeOText !== null ) {
					$oText = $maybeOText;
				}
			}

			$optInfo = null;
			if ( is_string( $oText ) ) {
				if ( preg_match( '/\|/', $oText ) ) {
					// Split the pipe-separated string into pieces
					// and convert each one into a KV obj and add them
					// to the beginning of the array. Note that this is
					// a hack to support templates that provide multiple
					// image options as a pipe-separated string. We aren't
					// really providing editing support for this yet, or
					// ever, maybe.
					//
					// TODO(arlolra): Tables in captions suppress breaking on
					// "linkdesc" pipes so `stringifyOptionTokens` should account
					// for pipes in table cell content.  For the moment, breaking
					// here is acceptable since it matches the php implementation
					// bug for bug.
					$pieces = array_map( function ( $s ) {
						return new KV( 'mw:maybeContent', $s );
					}, explode( '|', $oText ) );
					$optKVs = array_merge( $pieces, $optKVs );

					// Record the fact that we won't provide editing support for this.
					$dataAttribs->uneditable = true;
					continue;
				} else {
					// We're being overly accepting of media options at this point,
					// since we don't know the type yet.  After the info request,
					// we'll filter out those that aren't appropriate.
					$optInfo = self::getOptionInfo( $oText, $env );
				}
			}

			// For the values of the caption and options, see
			// getOptionInfo's documentation above.
			//
			// If there are multiple captions, this code always
			// picks the last entry. This is the spec; see
			// "Image with multiple captions" parserTest.
			if ( !is_string( $oText ) || $optInfo === null ||
				// Deprecated options
				in_array( $optInfo['ck'], [ 'noicon', 'noplayer', 'disablecontrols' ], true )
			) {
				// No valid option found!?
				// Record for RT-ing
				$optsCaption = [
					'v' => $oContent->v,
					'src' => $oContent->vsrc ?? $oText,
					'srcOffsets' => $oContent->valueOffset(),
					// remember the position
					'pos' => count( $dataAttribs->optList )
				];
				// if there was a 'caption' previously, round-trip it as a
				// "bogus option".
				if ( !empty( $opts['caption'] ) ) {
					// Wrap the caption opt in an array since the option itself is an array!
					// Without the wrapping, the splicing will flatten the value.
					array_splice( $dataAttribs->optList, $opts['caption']['pos'], 0, [ [
							'ck' => 'bogus',
							'ak' => $opts['caption']['src']
						] ]
					);
					$optsCaption['pos']++;
				}
				$opts['caption'] = $optsCaption;
				continue;
			}

			if ( isset( $opts[$optInfo['ck']] ) ) {
				// first option wins, the rest are 'bogus'
				$dataAttribs->optList[] = [
					'ck' => 'bogus',
					'ak' => $optInfo['ak']
				];
				continue;
			}

			$opt = [
				'ck' => $optInfo['v'],
				'ak' => $oContent->vsrc ?? $optInfo['ak']
			];

			if ( $optInfo['s'] === true ) {
				// Default: Simple image option
				$opts[$optInfo['ck']] = [ 'v' => $optInfo['v'] ];
			} else {
				// Map short canonical name to the localized version used.
				$opt['ck'] = $optInfo['ck'];

				// The MediaWiki magic word for image dimensions is called 'width'
				// for historical reasons
				// Unlike other options, use last-specified width.
				if ( $optInfo['ck'] === 'width' ) {
					// We support a trailing 'px' here for historical reasons
					// (T15500, T53628)
					$maybeDim = Utils::parseMediaDimensions( $optInfo['v'] );
					if ( $maybeDim !== null ) {
						$opts['size']['v'] = [
							'width' => Utils::validateMediaParam( $maybeDim['x'] ) ? $maybeDim['x'] : null,
							'height' => array_key_exists( 'y', $maybeDim ) &&
								Utils::validateMediaParam( $maybeDim['y'] ) ? $maybeDim['y'] : null
						];
						// Only round-trip a valid size
						$opts['size']['src'] = $oContent->vsrc ?? $optInfo['ak'];
					}
				} else {
					$opts[$optInfo['ck']] = [
						'v' => $optInfo['v'],
						'src' => $oContent->vsrc ?? $optInfo['ak'],
						'srcOffsets' => $oContent->valueOffset(),
					];
				}
			}

			// Collect option in dataAttribs (becomes data-parsoid later on)
			// for faithful serialization.
			$dataAttribs->optList[] = $opt;

			// Collect source wikitext for image options for possible template expansion.
			$maybeOpt = !isset( self::getUsed()[$opt['ck']] );
			$expOpt = null;
			// Links more often than not show up as arrays here because they're
			// tokenized as `autourl`.  To avoid unnecessarily considering them
			// expanded, we'll use a more restrictive test, at the cost of
			// perhaps missing some edgy behaviour.
			if ( $opt['ck'] === 'link' ) {
				$expOpt = is_array( $origOptSrc ) &&
					$this->hasTransclusion( $origOptSrc );
			} else {
				$expOpt = is_array( $origOptSrc );
			}
			if ( $maybeOpt || $expOpt ) {
				$val = [];
				if ( $expOpt ) {
					$hasExpandableOpt = true;
					$val['html'] = $origOptSrc;
					$val['srcOffsets'] = $oContent->valueOffset();
					$val = PipelineUtils::expandValueToDOM(
						$env, $manager->getFrame(), $val,
						$this->options['expandTemplates'],
						$this->options['inTemplate']
					);
				}

				// This is a bit of an abuse of the "txt" property since
				// `optInfo.v` isn't unnecessarily wikitext from source.
				// It's a result of the specialized stringifying above, which
				// if interpreted as wikitext upon serialization will result
				// in some (acceptable) normalization.
				//
				// We're storing these options in data-mw because they aren't
				// guaranteed to apply to all media types and we'd like to
				// avoid the need to back them out later.
				//
				// Note that the caption in the legacy parser depends on the
				// exact set of options parsed, which we aren't attempting to
				// try and replicate after fetching the media info, since we
				// consider that more of bug than a feature.  It prevent anyone
				// from ever safely adding media options in the future.
				//
				// See T163582
				if ( $maybeOpt ) {
					$val['txt'] = $optInfo['v'];
				}
				if ( !isset( $dataMw->attribs ) ) {
					$dataMw->attribs = [];
				}
				$dataMw->attribs[] = [ $opt['ck'], $val ];
			}
		}

		// Add the last caption in the right position if there is one
		if ( $opts['caption'] ) {
			// Wrap the caption opt in an array since the option itself is an array!
			// Without the wrapping, the splicing will flatten the value.
			array_splice( $dataAttribs->optList, $opts['caption']['pos'], 0, [ [
					'ck' => 'caption',
					'ak' => $opts['caption']['src']
				] ]
			);
		}

		// Handle image default sizes and upright option after extracting all
		// options
		// @phan-suppress-next-line PhanRedundantCondition
		if ( !empty( $opts['format'] ) && $opts['format']['v'] === 'framed' ) {
			// width and height is ignored for framed images
			// https://phabricator.wikimedia.org/T64258
			$opts['size']['v'] = [ 'width' => null, 'height' => null ];
		} elseif ( $opts['format'] ) {
			if ( !$opts['size']['v']['height'] && !$opts['size']['v']['width'] ) {
				$defaultWidth = $env->getSiteConfig()->widthOption();
				if ( isset( $opts['upright'] ) ) {
					// FIXME: If non-numeric, should this option be treated as a caption?
					if ( is_numeric( $opts['upright']['v'] ) && $opts['upright']['v'] > 0 ) {
						$defaultWidth *= $opts['upright']['v'];
					} else {
						$defaultWidth *= 0.75;
					}
					// round to nearest 10 pixels
					$defaultWidth = 10 * round( $defaultWidth / 10 );
				}
				$opts['size']['v']['width'] = $defaultWidth;
			}
		}

		// FIXME: Default type, since we don't have the info.  That right?
		$rdfaType = 'mw:Image';

		// If the format is something we *recognize*, add the subtype
		$format = self::getFormat( $opts );
		switch ( $format ) {
			case 'thumbnail':
				$rdfaType .= '/Thumb';
				break;
			case 'framed':
				$rdfaType .= '/Frame';
				break;
			case 'frameless':
				$rdfaType .= '/Frameless';
				break;
		}

		// Tell VE that it shouldn't try to edit this
		if ( !empty( $dataAttribs->uneditable ) ) {
			$rdfaType .= ' mw:Placeholder';
		} else {
			unset( $dataAttribs->src );
		}

		$wrapperInfo = self::getWrapperInfo( $opts );

		$isInline = $wrapperInfo['isInline'];
		$containerName = $isInline ? 'figure-inline' : 'figure';

		$classes = $wrapperInfo['classes'];
		if ( !empty( $opts['class'] ) ) {
			$classes = array_merge( $classes, explode( ' ', $opts['class']['v'] ) );
		}

		$attribs = [ new KV( 'typeof', $rdfaType ) ];
		if ( count( $classes ) > 0 ) {
			array_unshift( $attribs, new KV( 'class', implode( ' ', $classes ) ) );
		}

		$container = new TagTk( $containerName, $attribs, $dataAttribs );
		$containerClose = new EndTagTk( $containerName );

		if ( $hasExpandableOpt ) {
			$container->addAttribute( 'about', $env->newAboutId() );
			$container->addSpaceSeparatedAttribute( 'typeof', 'mw:ExpandedAttrs' );
		} elseif ( preg_match( '/\bmw:ExpandedAttrs\b/', $token->getAttribute( 'typeof' ) ?? '' ) ) {
			$container->addSpaceSeparatedAttribute( 'typeof', 'mw:ExpandedAttrs' );
		}

		$span = new TagTk( 'span', [] );

		// "resource" and "lang" are allowed attributes on spans
		$span->addNormalizedAttribute( 'resource', $opts['title']['v'], $opts['title']['src'] );
		if ( isset( $opts['lang'] ) ) {
			$span->addNormalizedAttribute( 'lang', $opts['lang']['v'], $opts['lang']['src'] );
		}

		// Token's KV attributes only accept strings, Tokens or arrays of those.
		$size = $opts['size']['v'];
		if ( !empty( $size['width'] ) ) {
			$span->addAttribute( 'data-width', (string)$size['width'] );
		}
		if ( !empty( $size['height'] ) ) {
			$span->addAttribute( 'data-height', (string)$size['height'] );
		}

		$anchor = new TagTk( 'a' );
		$filePath = Sanitizer::sanitizeTitleURI( $target->title->getKey(), false );
		$anchor->setAttribute( 'href', "./Special:FilePath/{$filePath}" );

		$tokens = [
			$container,
			$anchor,
			$span,
			// FIXME: The php parser seems to put the link text here instead.
			// The title can go on the `anchor` as the "title" attribute.
			$target->title->getPrefixedText(),
			new EndTagTk( 'span' ),
			new EndTagTk( 'a' )
		];

		$optsCaption = $opts['caption'] ?? null;
		if ( $isInline ) {
			if ( $optsCaption ) {
				if ( !is_array( $optsCaption['v'] ) ) {
					$opts['caption']['v'] = $optsCaption['v'] = [ $optsCaption['v'] ];
				}
				// Parse the caption
				$captionDOM = PipelineUtils::processContentInPipeline(
					$this->manager->env,
					$this->manager->getFrame(),
					array_merge( $optsCaption['v'], [ new EOFTk() ] ),
					[
						'pipelineType' => 'tokens/x-mediawiki/expanded',
						'pipelineOpts' => [
							'inlineContext' => true,
							'expandTemplates' => $this->options['expandTemplates'],
							'inTemplate' => $this->options['inTemplate']
						],
						'srcOffsets' => $optsCaption['srcOffsets'] ?? null,
						'sol' => true
					]
				);
				// Use parsed DOM given in `captionDOM`
				// FIXME: Does this belong in `dataMw.attribs`?
				$dataMw->caption = ContentUtils::ppToXML(
					DOMCompat::getBody( $captionDOM ), [ 'innerXML' => true ] );
			}
		} else {
			// We always add a figcaption for blocks
			$tsr = $optsCaption['srcOffsets'] ?? null;
			$tokens[] = new TagTk( 'figcaption', [], (object)[ 'tsr' => $tsr ] );
			if ( $optsCaption ) {
				if ( is_string( $optsCaption['v'] ) ) {
					$tokens[] = $optsCaption['v'];
				} else {
					$tokens[] = PipelineUtils::getDOMFragmentToken(
						$optsCaption['v'],
						$tsr,
						[ 'inlineContext' => true, 'token' => $token ]
					);
				}
			}
			$tokens[] = new EndTagTk( 'figcaption' );
		}

		if ( count( array_keys( get_object_vars( $dataMw ) ) ) ) {
			$container->addAttribute( 'data-mw', PHPUtils::jsonEncode( $dataMw ) );
		}

		return [ 'tokens' => array_merge( $tokens, [ $containerClose ] ) ];
	}

	/**
	 * @param Token $token
	 * @param stdClass $target
	 * @param array $errs
	 * @param array $info
	 * @return array
	 */
	private function linkToMedia( Token $token, stdClass $target, array $errs, array $info ): array {
		// Only pass in the url, since media links should not link to the thumburl
		$imgHref = preg_replace( '#^https?://#', '//', $info['url'], 1 ); // Copied from getPath
		$imgHrefFileName = preg_replace( '#.*/#', '', $imgHref, 1 );

		$link = new TagTk( 'a', [], Utils::clone( $token->dataAttribs ) );
		$link->addAttribute( 'rel', 'mw:MediaLink' );
		$link->addAttribute( 'href', $imgHref );
		// html2wt will use the resource rather than try to parse the href.
		$link->addNormalizedAttribute(
			'resource',
			$this->env->makeLink( $target->title ),
			$target->hrefSrc
		);
		// Normalize title according to how PHP parser does it currently
		$link->setAttribute( 'title', preg_replace( '/_/', ' ', $imgHrefFileName ) );
		unset( $link->dataAttribs->src ); // clear src string since we can serialize this

		$type = $token->getAttribute( 'typeof' );
		if ( $type ) {
			$link->addSpaceSeparatedAttribute( 'typeof', $type );
		}

		if ( count( $errs ) > 0 ) {
			// Set RDFa type to mw:Error so VE and other clients
			// can use this to do client-specific action on these.
			$link->addAttribute( 'typeof', 'mw:Error' );

			// Update data-mw
			$dataMwAttr = $token->getAttribute( 'data-mw' );
			$dataMw = $dataMwAttr ? PHPUtils::jsonDecode( $dataMwAttr, false ) : new stdClass;
			if ( is_array( $dataMw->errors ?? null ) ) {
				$errs = array_merge( $dataMw->errors, $errs );
			}
			$dataMw->errors = $errs;
			$link->addAttribute( 'data-mw', PHPUtils::jsonEncode( $dataMw ) );
		}

		$content = preg_replace( '/^:/', '',
			TokenUtils::tokensToString( $token->getAttribute( 'href' ) ), 1 );
		$content = $token->getAttribute( 'mw:maybeContent' ) ?? [ $content ];
		$tokens = array_merge( [ $link ], $content, [ new EndTagTk( 'a' ) ] );
		return [ 'tokens' => $tokens ];
	}

	// FIXME: The media request here is only used to determine if this is a
	// redlink and deserves to be handling in the redlink post-processing pass.

	/**
	 * @param Token $token
	 * @param stdClass $target
	 * @return array
	 */
	private function renderMedia( Token $token, stdClass $target ) {
		$env = $this->manager->env;
		$title = $target->title;
		$errs = [];
		$temp2 = AddMediaInfo::requestInfo( $env, $title->getKey(), [
			'height' => null, 'width' => null
		] );

		$err = $temp2['err'];
		if ( $err ) {
			$errs[] = $err;
		}

		$info = $temp2['info'];
		return $this->linkToMedia( $token, $target, $errs, $info );
	}

	/** @inheritDoc */
	public function onTag( Token $token ) {
		switch ( $token->getName() ) {
			case 'wikilink':
				return $this->onWikiLink( $token );
			case 'mw:redirect':
				return $this->onRedirect( $token );
			default:
				return $token;
		}
	}
}
