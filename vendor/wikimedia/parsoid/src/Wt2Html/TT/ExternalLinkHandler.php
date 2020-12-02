<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\PipelineUtils;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\PegTokenizer;

class ExternalLinkHandler extends TokenHandler {
	/** @var PegTokenizer */
	private $urlParser;

	/** @var int */
	private $linkCount;

	/** @inheritDoc */
	public function __construct( object $manager, array $options ) {
		parent::__construct( $manager, $options );

		// Create a new peg parser for image options.
		if ( !$this->urlParser ) {
			// Actually the regular tokenizer, but we'll call it with the
			// url rule only.
			$this->urlParser = new PegTokenizer( $this->env );
		}

		$this->reset();
	}

	private function reset(): void {
		$this->linkCount = 1;
	}

	/**
	 * @param string $str
	 * @return bool
	 */
	private static function imageExtensions( string $str ): bool {
		switch ( $str ) {
			case 'jpg': // fall through
			case 'png': // fall through
			case 'gif': // fall through
			case 'svg':
				return true;
			default:
				return false;
		}
	}

	/**
	 * @param array $array
	 * @param callable $fn
	 * @return bool
	 */
	private function arraySome( array $array, callable $fn ): bool {
		foreach ( $array as $value ) {
			if ( $fn( $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $href
	 * @return bool
	 */
	private function hasImageLink( string $href ): bool {
		$allowedPrefixes = $this->manager->env->getSiteConfig()->allowedExternalImagePrefixes();
		$bits = explode( '.', $href );
		$hasImageExtension = count( $bits ) > 1 &&
			self::imageExtensions( end( $bits ) ) &&
			preg_match( '#^https?://#i', $href );
		// Typical settings for mediawiki configuration variables
		// $wgAllowExternalImages and $wgAllowExternalImagesFrom will
		// result in values like these:
		//  allowedPrefixes = undefined; // no external images
		//  allowedPrefixes = [''];      // allow all external images
		//  allowedPrefixes = ['http://127.0.0.1/', 'http://example.com'];
		// Note that the values include the http:// or https:// protocol.
		// See https://phabricator.wikimedia.org/T53092
		return $hasImageExtension &&
			// true if some prefix in the list matches href
			self::arraySome( $allowedPrefixes, function ( string $prefix ) use ( &$href ) {
				return $prefix === "" || strpos( $href, $prefix ) === 0;
			} );
	}

	/**
	 * @param Token $token
	 * @return Token|array
	 */
	private function onUrlLink( Token $token ) {
		$tagAttrs = null;
		$builtTag = null;
		$env = $this->manager->env;
		$origHref = $token->getAttribute( 'href' );
		$href = TokenUtils::tokensToString( $origHref );
		$dataAttribs = Utils::clone( $token->dataAttribs );

		if ( $this->hasImageLink( $href ) ) {
			$checkAlt = explode( '/', $href );
			$tagAttrs = [
				new KV( 'src', $href ),
				new KV( 'alt', end( $checkAlt ) ),
				new KV( 'rel', 'mw:externalImage' )
			];

			// combine with existing rdfa attrs
			$tagAttrs = WikiLinkHandler::buildLinkAttrs(
				$token->attribs, false, null, $tagAttrs )['attribs'];
			return [ 'tokens' => [ new SelfclosingTagTk( 'img', $tagAttrs, $dataAttribs ) ] ];
		} else {
			$tagAttrs = [
				new KV( 'rel', 'mw:ExtLink' )
			];

			// combine with existing rdfa attrs
			// href is set explicitly below
			$tagAttrs = WikiLinkHandler::buildLinkAttrs(
				$token->attribs, false, null, $tagAttrs )['attribs'];
			$builtTag = new TagTk( 'a', $tagAttrs, $dataAttribs );
			$dataAttribs->stx = 'url';

			if ( !$this->options['inTemplate'] ) {
				// Since we messed with the text of the link, we need
				// to preserve the original in the RT data. Or else.
				$builtTag->addNormalizedAttribute(
					'href', $href, $token->getWTSource( $this->manager->getFrame() )
				);
			} else {
				$builtTag->addAttribute( 'href', $href );
			}

			return [ 'tokens' => [
					$builtTag,
					// Make sure there are no IDN-ignored characters in the text so
					// the user doesn't accidentally copy any.
					Sanitizer::cleanUrl( $env, $href, '' ),   // mode could be 'wikilink'
					new EndTagTk(
						'a',
						[],
						(object)[ 'tsr' => $dataAttribs->tsr->expandTsrK()->value ]
					)
				]
			];
		}
	}

	/**
	 * Bracketed external link
	 * @param Token $token
	 * @return Token|array
	 */
	private function onExtLink( Token $token ) {
		$newAttrs = null;
		$aStart = null;
		$env = $this->manager->env;
		$origHref = $token->getAttribute( 'href' );
		$hasExpandedAttrs = TokenUtils::hasTypeOf( $token, 'mw:ExpandedAttrs' );
		$href = TokenUtils::tokensToString( $origHref );
		$hrefWithEntities = TokenUtils::tokensToString( $origHref, false, [
				'includeEntities' => true
			]
		);
		$content = $token->getAttribute( 'mw:content' );
		$dataAttribs = Utils::clone( $token->dataAttribs );
		$magLinkType = TokenUtils::matchTypeOf(
			$token, '#^mw:(Ext|Wiki)Link/(ISBN|RFC|PMID)$#'
		);
		$tokens = null;

		if ( $magLinkType ) {
			$newHref = $href;
			$newRel = 'mw:ExtLink';
			if ( preg_match( '#/ISBN$#', $magLinkType ) ) {
				$newHref = $env->getSiteConfig()->relativeLinkPrefix() . $href;
				// ISBNs use mw:WikiLink instead of mw:ExtLink
				$newRel = 'mw:WikiLink';
			}
			$newAttrs = [
				new KV( 'href', $newHref ),
				new KV( 'rel', $newRel )
			];
			$token->removeAttribute( 'typeof' );

			// SSS FIXME: Right now, Parsoid does not support templating
			// of ISBN attributes.  So, "ISBN {{1x|1234567890}}" will not
			// parse as you might expect it to.  As a result, this code below
			// that attempts to combine rdf attrs from earlier is unnecessary
			// right now.  But, it will become necessary if Parsoid starts
			// supporting templating of ISBN attributes.
			//
			// combine with existing rdfa attrs
			$newAttrs = WikiLinkHandler::buildLinkAttrs(
				$token->attribs, false, null, $newAttrs )['attribs'];
			$aStart = new TagTk( 'a', $newAttrs, $dataAttribs );
			$tokens = array_merge( [ $aStart ],
				is_array( $content ) ? $content : [ $content ], [ new EndTagTk( 'a' ) ] );
			return [ 'tokens' => $tokens ];
		} elseif ( ( !$hasExpandedAttrs && is_string( $origHref ) ) ||
					$this->urlParser->tokenizeURL( $hrefWithEntities ) !== false
		) {
			$rdfaType = 'mw:ExtLink';
			if ( is_array( $content ) && count( $content ) === 1 && is_string( $content[0] ) ) {
				$src = $content[0];
				if ( $env->getSiteConfig()->hasValidProtocol( $src ) &&
					$this->urlParser->tokenizeURL( $src ) !== false &&
					$this->hasImageLink( $src )
				) {
					$checkAlt = explode( '/', $src );
					$content = [ new SelfclosingTagTk( 'img', [
						new KV( 'src', $src ),
						new KV( 'alt', end( $checkAlt ) )
						], PHPUtils::arrayToObject( [ 'type' => 'extlink' ] )
					) ];
				}
			}

			$newAttrs = [ new KV( 'rel', $rdfaType ) ];
			// combine with existing rdfa attrs
			// href is set explicitly below
			$newAttrs = WikiLinkHandler::buildLinkAttrs(
				$token->attribs, false, null, $newAttrs )['attribs'];
			$aStart = new TagTk( 'a', $newAttrs, $dataAttribs );

			if ( empty( $this->options['inTemplate'] ) ) {
				// If we are from a top-level page, add normalized attr info for
				// accurate roundtripping of original content.
				//
				// extLinkContentOffsets->start covers all spaces before content
				// and we need src without those spaces.
				$tsr0a = $dataAttribs->tsr->start + 1;
				$tsr1a = $dataAttribs->extLinkContentOffsets->start -
					strlen( $token->getAttribute( 'spaces' ) ?? '' );
				$length = $tsr1a - $tsr0a;
				$aStart->addNormalizedAttribute( 'href', $href,
					substr( $this->manager->getFrame()->getSrcText(), $tsr0a, $length ) );
			} else {
				$aStart->addAttribute( 'href', $href );
			}

			$content = PipelineUtils::getDOMFragmentToken(
				$content,
				$dataAttribs->tsr ? $dataAttribs->extLinkContentOffsets : null,
				[ 'inlineContext' => true, 'token' => $token ]
			);

			$tokens = array_merge( [ $aStart ], [ $content ], [ new EndTagTk( 'a' ) ] );
			return [ 'tokens' => $tokens ];
		} else {
			// Not a link, convert href to plain text.
			return [ 'tokens' => WikiLinkHandler::bailTokens( $env, $token, true ) ];
		}
	}

	/** @inheritDoc */
	public function onTag( Token $token ) {
		switch ( $token->getName() ) {
			case 'urllink':
				return $this->onUrlLink( $token );
			case 'extlink':
				return $this->onExtLink( $token );
			default:
				return $token;
		}
	}

	/** @inheritDoc */
	public function onEnd( EOFTk $token ) {
		$this->reset();
		return $token;
	}
}
