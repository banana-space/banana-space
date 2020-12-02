<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\TT;

use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Tokens\CommentTk;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\NlTk;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\PipelineUtils;
use Wikimedia\Parsoid\Utils\Title;
use Wikimedia\Parsoid\Utils\TitleException;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\Params;
use Wikimedia\Parsoid\Wt2Html\TokenTransformManager;

/**
 * Template and template argument handling.
 */
class TemplateHandler extends TokenHandler {
	/**
	 * @var bool Should we wrap template tokens with template meta tags?
	 */
	private $wrapTemplates;

	/**
	 * @var AttributeExpander
	 * Local copy of the attribute expander to deal with template targets
	 * that are templated themselves
	 */
	private $ae;

	/**
	 * @var ParserFunctions
	 */
	private $parserFunctions;

	/**
	 * @param TokenTransformManager $manager
	 * @param array $options
	 *  - ?bool inTemplate Is this being invoked while processing a template?
	 *  - ?bool expandTemplates Should we expand templates encountered here?
	 *  - ?string extTag The name of the extension tag, if any, which is being expanded.
	 */
	public function __construct( TokenTransformManager $manager, array $options ) {
		parent::__construct( $manager, $options );
		// Set this here so that it's available in the TokenStreamPatcher,
		// which continues to inherit from TemplateHandler.
		$this->parserFunctions = new ParserFunctions( $this->env );
		$this->ae = new AttributeExpander( $this->manager, [
			'standalone' => true, 'expandTemplates' => true
		] );
		$this->wrapTemplates = empty( $options['inTemplate'] );
	}

	/**
	 * Parser functions also need template wrapping.
	 *
	 * @param array $state
	 * @param array $ret
	 * @return array
	 */
	private function parserFunctionsWrapper( array $state, array $ret ): array {
		$chunkToks = [];
		if ( $ret ) {
			$tokens = $ret;

			// This is only for the Parsoid native expansion pipeline used in
			// parser tests. The "" token sometimes changes foster parenting
			// behavior and trips up some tests.
			$tokens = array_values( array_filter( $tokens, function ( $t ) {
				return $t !== '';
			} ) );

			// token chunk should be flattened
			$flat = true;
			foreach ( $tokens as $t ) {
				if ( is_array( $t ) ) {
					$flat = false;
					break;
				}
			}
			Assert::invariant( $flat, "Expected token chunk to be flattened" );

			$chunkToks = $this->processTemplateTokens( $state, $tokens );
		}

		// Now, ready to finish up
		$endToks = $this->finalizeTemplateTokens( $state );
		return array_merge( $chunkToks, $endToks );
	}

	/**
	 * @param array $state
	 * @param array $tokens
	 * @param array $extraDict
	 * @return array
	 */
	private function encapTokens(
		array $state, array $tokens, array $extraDict = []
	): array {
		$toks = $this->getEncapsulationInfo( $state, $tokens );
		$toks[] = $this->getEncapsulationInfoEndTag( $state );
		if ( $this->wrapTemplates ) {
			$argInfo = $this->getArgInfo( $state );
			$argInfo['dict'] = array_merge( $argInfo['dict'], $extraDict );
			$toks[0]->dataAttribs->tmp->tplarginfo = PHPUtils::jsonEncode( $argInfo );
		}
		return $toks;
	}

	/**
	 * Strip include tags, and the contents of includeonly tags as well.
	 * @param (Token|string)[] $tokens
	 * @return (Token|string)[]
	 */
	private function stripIncludeTokens( array $tokens ): array {
		$toks = [];
		$includeOnly = false;
		foreach ( $tokens as $tok ) {
			if ( is_string( $tok ) ) {
				if ( !$includeOnly ) {
					$toks[] = $tok;
				}
				continue;
			}

			switch ( get_class( $tok ) ) {
				case TagTk::class :
				case EndTagTk::class :
				case SelfclosingTagTk::class :
					$tokName = $tok->getName();
					if ( $tokName === 'noinclude' || $tokName === 'onlyinclude' ) {
						break;
					} elseif ( $tokName === 'includeonly' ) {
						$includeOnly = $tok instanceof TagTk;
						break;
					}
					// Fall through
				default:
					if ( !$includeOnly ) {
						$toks[] = $tok;
					}
			}
		}
		return $toks;
	}

	/**
	 * @param array $tokens
	 * @return ?string
	 */
	private function toStringOrNull( array $tokens ): ?string {
		$maybeTarget = TokenUtils::tokensToString( $tokens, true, [ 'retainNLs' => true ] );
		if ( !is_array( $maybeTarget ) ) {
			return $maybeTarget;
		}

		$buf = $maybeTarget[0]; // Will always be a string
		$tgtTokens = $maybeTarget[1];
		$preNlContent = null;
		foreach ( $tgtTokens as $ntt ) {
			if ( is_string( $ntt ) ) {
				$buf .= $ntt;
				continue;
			}

			switch ( get_class( $ntt ) ) {
				case SelfclosingTagTk::class:
					// Quotes are valid template targets
					if ( $ntt->getName() === 'mw-quote' ) {
						$buf .= $ntt->getAttribute( 'value' );
					} elseif ( !TokenUtils::isEmptyLineMetaToken( $ntt ) &&
						$ntt->getName() !== 'template' &&
						$ntt->getName() !== 'templatearg'
					) {
						// We are okay with empty (comment-only) lines,
						// {{..}} and {{{..}}} in template targets.
						return null;
					}
				break;

				case TagTk::class:
				case EndTagTk::class:
					return null;

				case CommentTk::class:
					// Ignore comments as well
					break;

				case NlTk::class:
					// Ignore only the leading or trailing newlines
					// (modulo whitespace and comments)
					//
					// If we only have whitespace in $buf thus far,
					// the newline can be ignored. But, if we have
					// non-ws content in $buf, everything that follows
					// can only be ws.
					if ( preg_match( '/^\s*$/D', $buf ) ) {
						break;
					} elseif ( !$preNlContent ) {
						// Buffer accumulated content
						$preNlContent = $buf;
						$buf = '';
						break;
					} else {
						return null;
					}

				default:
					PHPUtils::unreachable( 'Unexpected token type: ' . get_class( $ntt ) );
			}
		}

		if ( $preNlContent && !preg_match( '/^\s*$/D', $buf ) ) {
			// intervening newline makes this an invalid target
			return null;
		} else {
			// all good! only whitespace/comments post newline
			return ( $preNlContent ?? '' ) . $buf;
		}
	}

	/**
	 * @param array &$state
	 * @param string|Token|array $targetToks
	 * @param SourceRange $srcOffsets
	 * @return array|null
	 */
	private function resolveTemplateTarget( array &$state, $targetToks, $srcOffsets ): ?array {
		// Normalize to an array
		$targetToks = !is_array( $targetToks ) ? [ $targetToks ] : $targetToks;

		$env = $this->env;
		$siteConfig = $env->getSiteConfig();
		$isTemplate = true;
		$includesStrippedTokens = $this->stripIncludeTokens( $targetToks );
		$target = $this->toStringOrNull( $includesStrippedTokens );
		if ( $target === null ) {
			// Retry with a looser attempt to convert tokens to a string.
			// This lenience only applies to parser functions.
			$isTemplate = false;
			$target = TokenUtils::tokensToString( $includesStrippedTokens );
		}

		// safesubst found in content should be treated as if no modifier were
		// present. See https://en.wikipedia.org/wiki/Help:Substitution#The_safesubst:_modifier
		$target = preg_replace( '/^safesubst:/', '', trim( $target ), 1 );

		$pieces = explode( ':', $target );
		$prefix = trim( $pieces[0] );
		$lowerPrefix = mb_strtolower( $prefix );
		// The check for pieces.length > 1 is required to distinguish between
		// {{lc:FOO}} and {{lc|FOO}}.  The latter is a template transclusion
		// even though the target (=lc) matches a registered parser-function name.
		$isPF = count( $pieces ) > 1;

		// Check if we have a parser function
		$canonicalFunctionName = $siteConfig->getMagicWordForFunctionHook( $prefix ) ??
			$siteConfig->getMagicWordForFunctionHook( $lowerPrefix ) ??
			$siteConfig->getMagicWordForVariable( $prefix ) ??
			$siteConfig->getMagicWordForVariable( $lowerPrefix );

		if ( $canonicalFunctionName !== null ) {
			// Extract toks that make up pfArg
			$pfArgToks = null;
			// PORT-FIXME shouldn't we be preg_quote'ing this?
			$re = '/^(.*?)' . $prefix . '/i';

			// Because of the lenient stringifying above, we need to find the
			// prefix.  The strings we've seen so far are buffered in case they
			// combine to our prefix.  FIXME: We need to account for the call
			// to $this->stripIncludeTokens above and the safesubst replace.
			$buf = '';
			$index = -1;
			foreach ( $targetToks as $i => $t ) {
				if ( !is_string( $t ) ) {
					continue;
				}

				$buf .= $t;
				preg_match( $re, $buf, $match );
				if ( $match ) {
					// Check if they combined
					$offset = strlen( $buf ) - strlen( $t ) - strlen( $match[1] );
					if ( $offset > 0 ) {
						// PORT-FIXME shouldn't we be preg_quote'ing this?
						$re = '/^' . substr( $prefix, $offset ) . '/i';
					}
					$index = $i;
					break;
				}
			}

			if ( $index > -1 ) {
				// Strip parser-func / magic-word prefix
				$firstTok = preg_replace( $re, '', $targetToks[$index] );
				$targetToks = array_slice( $targetToks, $index + 1 );

				if ( $isPF ) {
					// Strip ":", again, after accounting for the lenient stringifying
					while ( count( $targetToks ) > 0 &&
						( !is_string( $firstTok ) || preg_match( '/^\s*$/D', $firstTok ) )
					) {
						$firstTok = $targetToks[0];
						$targetToks = array_slice( $targetToks, 1 );
					}
					Assert::invariant( is_string( $firstTok ) && preg_match( '/^\s*:/', $firstTok ),
						'Expecting : in parser function definiton'
					);
					$pfArgToks = array_merge( [ preg_replace( '/^\s*:/', '', $firstTok, 1 ) ], $targetToks );
				} else {
					$pfArgToks = array_merge( [ $firstTok ], $targetToks );
				}
			}

			if ( $pfArgToks === null ) {
				// FIXME: Protect from crashers by using the full token -- this is
				// still going to generate incorrect output, but it won't crash.
				$pfArgToks = $targetToks;
			}

			$state['parserFunctionName'] = $canonicalFunctionName;

			$magicWordType = null;
			if ( $canonicalFunctionName === '!' ) {
				$magicWordType = '!';
			} elseif ( isset( Utils::magicMasqs()[$canonicalFunctionName] ) ) {
				$magicWordType = 'MASQ';
			}

			$pfArg = substr( $target, strlen( $prefix ) + 1 );

			return [
				'isPF' => true,
				'prefix' => $canonicalFunctionName,
				'magicWordType' => $magicWordType,
				'target' => 'pf_' . $canonicalFunctionName,
				'title' => $env->makeTitleFromURLDecodedStr( "Special:ParserFunction/$canonicalFunctionName" ),
				'pfArg' => $pfArg === false ? '' : $pfArg,
				'pfArgToks' => $pfArgToks,
				'srcOffsets' => $srcOffsets,
			];
		}

		if ( !$isTemplate ) {
			return null;
		}

		// `resolveTitle()` adds the namespace prefix when it resolves fragments
		// and relative titles, and a leading colon should resolve to a template
		// from the main namespace, hence we omit a default when making a title
		$namespaceId = preg_match( '!^[:#/\.]!', $target ) ?
			null : $siteConfig->canonicalNamespaceId( 'template' );

		// Resolve a possibly relative link and
		// normalize the target before template processing.
		$title = null;
		try {
			$title = $env->resolveTitle( $target );
		} catch ( TitleException $e ) {
			// Invalid template target!
			return null;
		}

		// Entities in transclusions aren't decoded in the PHP parser
		// So, treat the title as a url-decoded string!
		$title = $env->makeTitleFromURLDecodedStr( $title, $namespaceId, true );
		if ( !$title ) {
			// Invalid template target!
			return null;
		}

		// data-mw.target.href should be a url
		$state['resolvedTemplateTarget'] = $env->makeLink( $title );

		return [
			'isPF' => false,
			'magicWordType' => null,
			'target' => $title->getPrefixedDBKey(),
			'title' => $title,
		];
	}

	/**
	 * Flatten
	 * @param (Token|string)[] $tokens
	 * @param string|null $prefix
	 * @param Token|string|(Token|string)[] $t
	 * @return array
	 */
	private function flattenAndAppendToks( array $tokens, ?string $prefix, $t ): array {
		if ( is_array( $t ) ) {
			$len = count( $t );
			if ( $len > 0 ) {
				if ( $prefix !== null && $prefix !== '' ) {
					$tokens[] = $prefix;
				}
				$tokens = array_merge( $tokens, $t );
			}
		} elseif ( is_string( $t ) ) {
			$len = strlen( $t );
			if ( $len > 0 ) {
				if ( $prefix !== null && $prefix !== '' ) {
					$tokens[] = $prefix;
				}
				$tokens[] = $t;
			}
		} else {
			if ( $prefix !== null && $prefix !== '' ) {
				$tokens[] = $prefix;
			}
			$tokens[] = $t;
		}

		return $tokens;
	}

	/**
	 * @param array $state
	 * @param array $attribs
	 * @return array
	 */
	private function convertAttribsToString(
		array $state, array $attribs
	): array {
		$attribTokens = [];

		// Leading whitespace, if any
		$leadWS = $state['token']->dataAttribs->tmp->leadWS ?? '';
		if ( $leadWS !== '' ) {
			$attribTokens[] = $leadWS;
		}

		// Re-join attribute tokens with '=' and '|'
		foreach ( $attribs as $kv ) {
			if ( $kv->k ) {
				$attribTokens = $this->flattenAndAppendToks( $attribTokens, null, $kv->k );
			}
			if ( $kv->v ) {
				$attribTokens = $this->flattenAndAppendToks( $attribTokens, $kv->k ? '=' : '', $kv->v );
			}
			$attribTokens[] = '|';
		}

		// pop last pipe separator
		array_pop( $attribTokens );

		// Trailing whitespace, if any
		$trailWS = $state['token']->dataAttribs->tmp->trailWS ?? '';
		if ( $trailWS !== '' ) {
			$attribTokens[] = $trailWS;
		}

		$tokens = array_merge( array_merge( [ '{{' ], $attribTokens ), [ '}}', new EOFTk() ] );

		// Process exploded token in a new pipeline that
		// converts the tokens to DOM.
		$toks = PipelineUtils::processContentInPipeline(
			$this->env,
			$this->manager->getFrame(),
			$tokens,
			[
				'pipelineType' => 'tokens/x-mediawiki',
				'pipelineOpts' => [
					'expandTemplates' => $this->options['expandTemplates'],
					'inTemplate' => $this->options['inTemplate'],
				],
				'sol' => true,
				'srcOffsets' => $state['token']->dataAttribs->tsr,
			]
		);

		TokenUtils::stripEOFTkfromTokens( $toks );

		$hasTemplatedTarget = isset( $state['token']->dataAttribs->tmp->templatedAttribs );
		if ( $hasTemplatedTarget && $this->wrapTemplates ) {
			// Add encapsulation if we had a templated target
			// FIXME: This is a deliberate wrapping of the entire
			// "broken markup" where one or more templates are nested
			// inside invalid transclusion markup. The proper way to do
			// this would be to disentangle everything and identify
			// transclusions and wrap them individually with meta tags
			// and data-mw info. But, this is an edge case which can be
			// more readily fixed by fixing the markup. The goal here is
			// to ensure that the output renders properly and it roundtrips
			// without dirty diffs rather then faithful DOMspec representation.
			$toks = $this->encapTokens( $state, $toks );
		}

		return $toks;
	}

	/**
	 * checkRes
	 *
	 * @param mixed $target
	 * @param Title $title
	 * @param bool $ignoreLoop
	 * @return ?array
	 */
	private function checkRes(
		$target, Title $title, bool $ignoreLoop
	): ?array {
		$checkRes = $this->manager->getFrame()->loopAndDepthCheck(
			$title, $this->env->getSiteConfig()->getMaxTemplateDepth(),
			$ignoreLoop
		);
		if ( $checkRes ) {
			// Loop detected or depth limit exceeded, abort!
			$res = [
				new TagTk( 'span', [ new KV( 'class', 'error' ) ] ),
				$checkRes,
				new SelfclosingTagTk( 'wikilink', [ new KV( 'href', $target, null, '', '' ) ] ),
				new EndTagTk( 'span' ),
			];
			return $res;
		}
		return null;
	}

	/**
	 * Fetch, tokenize and token-transform a template after all arguments and
	 * the target were expanded.
	 *
	 * @param array $state
	 * @param array $attribs
	 * @return array
	 */
	private function expandTemplate( array $state, array $attribs ): array {
		$env = $this->env;
		$target = $attribs[0]->k;
		if ( !$target ) {
			$env->log( 'debug', 'No template target! ', $attribs );
		}

		// Resolve the template target again now that the template token's
		// attributes have been expanded by the AttributeTransformManager
		$resolvedTgt = $this->resolveTemplateTarget( $state, $target, $attribs[0]->srcOffsets->key );
		if ( $resolvedTgt === null ) {
			// Target contains tags, convert template braces and pipes back into text
			// Re-join attribute tokens with '=' and '|'
			return $this->convertAttribsToString( $state, $attribs );
		}

		// TODO:
		// check for 'subst:'
		// check for variable magic names
		// check for msg, msgnw, raw magics
		// check for parser functions

		// XXX: wrap attribs in object with .dict() and .named() methods,
		// and each member (key/value) into object with .tokens(), .dom() and
		// .wikitext() methods (subclass of Array)

		$res = null;
		$target = $resolvedTgt['target'];
		if ( $resolvedTgt['isPF'] ) {
			// FIXME: Parsoid may not have implemented the parser function natively
			// Emit an error message, but encapsulate it so it roundtrips back.
			if ( !is_callable( [ $this->parserFunctions, $target ] ) ) {
				$res = [ 'Parser function implementation for ' . $target . ' missing in Parsoid.' ];
				if ( $this->wrapTemplates ) {
					$res[] = $this->getEncapsulationInfoEndTag( $state );
				}
				return $res;
			}

			$pfAttribs = new Params( $attribs );
			$pfAttribs->args[0] = new KV(
				$resolvedTgt['pfArg'], [],
				$resolvedTgt['srcOffsets']->expandTsrK()
			);
			$env->log( 'debug', 'entering prefix', $target, $state['token'] );
			$res = call_user_func( [ $this->parserFunctions, $target ],
				$state['token'], $this->manager->getFrame(), $pfAttribs );
			if ( $this->wrapTemplates ) {
				$res = $this->parserFunctionsWrapper( $state, $res );
			}
			return $res;
		}

		// Loop detection needs to be enabled since we're doing our own template
		// expansion
		$checkRes = $this->checkRes( $target, $resolvedTgt['title'], false );
		if ( is_array( $checkRes ) ) {
			return $checkRes;
		}

		// XXX: notes from brion's mediawiki.parser.environment
		// resolve template name
		// load template w/ canonical name
		// load template w/ variant names (language variants)

		// strip template target
		$attribs = array_slice( $attribs, 1 );

		// Fetch template source and expand it
		$title = $resolvedTgt['title'];
		$res = $this->fetchTemplateAndTitle( $target, $state, $attribs );
		if ( isset( $res['tplSrc'] ) ) {
			return $this->processTemplateSource(
				$state,
				[ 'name' => $target, 'title' => $title, 'attribs' => $attribs ],
				$res['tplSrc']
			);
		} else {
			return $res['tokens'];
		}
	}

	/**
	 * Process a fetched template source to a token stream.
	 *
	 * @param array $state
	 * @param array $tplArgs
	 * @param ?string $src
	 * @return array
	 */
	private function processTemplateSource(
		array $state, array $tplArgs, ?string $src
	): array {
		if ( !$src ) {
			// We have a choice between aborting or keeping going and reporting the
			// error inline.
			// TODO: report as special error token and format / remove that just
			// before the serializer. (something like <mw:error ../> as source)
			$src = '';
		}
		$env = $this->env;
		if ( $env->hasDumpFlag( 'tplsrc' ) ) {
			$env->log( 'dump/tplsrc', str_repeat( '=', 80 ) );
			$env->log( 'dump/tplsrc', 'TEMPLATE:', $tplArgs['name'], '; TRANSCLUSION:',
				PHPUtils::jsonEncode( $state['token']->dataAttribs->src ) );
			$env->log( 'dump/tplsrc', str_repeat( '-', 80 ) );
			$env->log( 'dump/tplsrc', $src );
			$env->log( 'dump/tplsrc', str_repeat( '-', 80 ) );
		}

		$this->env->log( 'debug', 'TemplateHandler.processTemplateSource',
			$tplArgs['name'], $tplArgs['attribs'] );

		// Get a nested transformation pipeline for the input type. The input
		// pipeline includes the tokenizer, synchronous stage-1 transforms for
		// 'text/wiki' input and asynchronous stage-2 transforms).
		$toks = PipelineUtils::processContentInPipeline(
			$this->env,
			$this->manager->getFrame(),
			$src,
			[
				'pipelineType' => 'text/x-mediawiki',
				'pipelineOpts' => [
					'inTemplate' => true,
					'isInclude' => true,
					// FIXME: In reality, this is broken for parser tests where
					// we expand templates natively. We do want all nested templates
					// to be expanded. But, setting this to !usePHPPreProcessor seems
					// to break a number of tests. Not pursuing this line of enquiry
					// for now since this parserTests vs production distinction will
					// disappear with parser integration. We'll just bear the stench
					// till that time.
					//
					// NOTE: No expansion required for nested templates.
					'expandTemplates' => false,
					'extTag' => $this->options['extTag'] ?? null
				],
				'srcText' => $src,
				'srcOffsets' => new SourceRange( 0, strlen( $src ) ),
				'tplArgs' => $tplArgs,
				'sol' => true
			]
		);

		$toks = $this->processTemplateTokens( $state, $toks );
		return array_merge( $toks, $this->finalizeTemplateTokens( $state ) );
	}

	/**
	 * @param array $state
	 * @param ?array $chunk
	 * @return array
	 */
	private function getEncapsulationInfo(
		array $state, ?array $chunk = null
	): array {
		// TODO
		// * only add this information for top-level includes, but track parameter
		// expansion in lower-level templates
		// * ref all tables to this (just add about)
		// * ref end token to this, add property="mw:Transclusion/End"

		$attrs = [
			new KV( 'typeof', $state['wrapperType'] ),
			new KV( 'about', '#' . $state['wrappedObjectId'] )
		];
		$dataParsoid = (object)[
			'tsr' => clone $state['token']->dataAttribs->tsr,
			'src' => $state['token']->dataAttribs->src,
			'tmp' => new stdClass
		];

		$meta = [ new SelfclosingTagTk( 'meta', $attrs, $dataParsoid ) ];
		$chunk = $chunk ? array_merge( $meta, $chunk ) : $meta;
		return $chunk;
	}

	/**
	 * @param array $state
	 * @return Token
	 */
	private function getEncapsulationInfoEndTag( array $state ): Token {
		$tsr = $state['token']->dataAttribs->tsr ?? null;
		return new SelfclosingTagTk( 'meta',
			[
				new KV( 'typeof', $state['wrapperType'] . '/End' ),
				new KV( 'about', '#' . $state['wrappedObjectId'] )
			],
			PHPUtils::arrayToObject( [ 'tsr' => new SourceRange( null, $tsr ? $tsr->end : null ) ] )
		);
	}

	/**
	 * Parameter processing helpers.
	 *
	 * @param mixed $tokens
	 * @return bool
	 */
	private static function isSimpleParam( $tokens ): bool {
		if ( !is_array( $tokens ) ) {
			$tokens = [ $tokens ];
		}
		foreach ( $tokens as $t ) {
			if ( !is_string( $t ) && !( $t instanceof CommentTk ) && !( $t instanceof NlTk ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Add its HTML conversion to a parameter
	 *
	 * @param array $paramData
	 */
	private function getParamHTML( array $paramData ): void {
		$param = $paramData['param'];
		$srcStart = $paramData['info']['srcOffsets']->value->start;
		$srcEnd = $paramData['info']['srcOffsets']->value->end;
		if ( !empty( $paramData['info']['spc'] ) ) {
			$srcStart += count( $paramData['info']['spc'][2] );
			$srcEnd -= count( $paramData['info']['spc'][3] );
		}

		$dom = PipelineUtils::processContentInPipeline(
			$this->env, $this->manager->getFrame(),
			$param->wt,
			[
				'pipelineType' => 'text/x-mediawiki/full',
				'pipelineOpts' => [
					'isInclude' => false,
					'expandTemplates' => true,
					// No need to do paragraph-wrapping here
					'inlineContext' => true
				],
				'srcOffsets' => new SourceRange( $srcStart, $srcEnd ),
				'sol' => true
			]
		);
		$body = DOMCompat::getBody( $dom );
		// FIXME: We're better off setting a pipeline option above
		// to skip dsr computation to begin with.  Worth revisitting
		// if / when `addHTMLTemplateParameters` is enabled.
		// Remove DSR from children
		DOMUtils::visitDOM( $body, function ( $node ) {
			if ( !DOMUtils::isElt( $node ) ) {
				return;
			}
			$dp = DOMDataUtils::getDataParsoid( $node );
			$dp->dsr = null;
		} );
		$param->html = ContentUtils::ppToXML( $body, [ 'innerXML' => true ] );
	}

	/**
	 * Process the main template element, including the arguments.
	 *
	 * @param array $state
	 * @return array
	 */
	private function encapsulateTemplate( array $state ): array {
		$i = null;
		$n = null;
		$env = $this->env;
		$chunk = $this->getEncapsulationInfo( $state );

		// Template encapsulation normally wouldn't happen in nested context,
		// since they should have already been expanded, and indeed we set
		// expandTemplates === false in processTemplateSource.  However,
		// extension tags from templates can have content that requires wikitext
		// parsing and, due to precedence, contain unexpanded templates.
		//
		// For example, {{1x|hi<ref>{{1x|ho}}</ref>}}
		//
		// Since extensions can require template expansion unconditionally, we can
		// end up here inTemplate, in which case the substrings of env.page.src
		// used in getArgInfo are no longer accurate, and so tplarginfo should be
		// omitted.  Presumably, template wrapping in the dom post processor won't
		// be happening anyways, so this is unnecessary work as it is.
		if ( $this->wrapTemplates ) {
			// Get the arg dict
			$argInfo = $this->getArgInfo( $state );
			$argDict = $argInfo['dict'];

			if ( $env->getSiteConfig()->addHTMLTemplateParameters() ) {
				// Collect the parameters that need parsing into HTML, that is,
				// those that are not simple strings.
				// This optimizes for the common case where all are simple strings,
				// in which we don't need to go async.
				$params = [];
				foreach ( $argInfo['paramInfos'] as $paramInfo ) {
					$param = $argDict['params']->{$paramInfo['k']};
					$paramTokens = null;
					if ( !empty( $paramInfo['named'] ) ) {
						$paramTokens = $state['token']->getAttribute( $paramInfo['k'] );
					} else {
						$paramTokens = $state['token']->attribs[$paramInfo['k']]->v;
					}

					// No need to pass through a whole sub-pipeline to get the
					// html if the param is either a single string, or if it's
					// just text, comments or newlines.
					if ( $paramTokens &&
						( is_string( $paramTokens ) || self::isSimpleParam( $paramTokens ) )
					) {
						$param->html = $param->wt;
					} elseif ( preg_match( '#^https?://[^[\]{}\s]*$#D', $param->wt ) ) {
						// If the param is just a simple URL, we can process it to
						// HTML directly without going through a sub-pipeline.
						$param->html = "<a rel='mw:ExtLink' href='" .
							preg_replace( "/'/", '&#39;', $param->wt ) . "'>" . $param->wt . '</a>';
					} else {
						// Prepare the data needed to parse to HTML
						$params[] = [
							'param' => $param,
							'info' => $paramInfo,
							'tokens' => $paramTokens
						];
					}
				}

				if ( count( $params ) ) {
					foreach ( $params as $paramData ) {
						$this->getParamHTML( $paramData );
					}
				}
			} else {
				// Don't add the HTML template parameters, just use their wikitext
			}
			// Use a data-attribute to prevent the sanitizer from stripping this
			// attribute before it reaches the DOM pass where it is needed
			$chunk[0]->dataAttribs->tmp->tplarginfo = PHPUtils::jsonEncode( $argInfo );
		}

		$env->log( 'debug', 'TemplateHandler.encapsulateTemplate', $chunk );
		return $chunk;
	}

	/**
	 * Handle chunk emitted from the input pipeline after feeding it a template.
	 *
	 * @param array $state
	 * @param array $chunk
	 * @return array
	 */
	private function processTemplateTokens( array $state, array $chunk ): array {
		TokenUtils::stripEOFTkfromTokens( $chunk );

		foreach ( $chunk as $i => $t ) {
			if ( $t && isset( $t->dataAttribs->tsr ) ) {
				unset( $t->dataAttribs->tsr );
			}
			if ( $t instanceof SelfclosingTagTk &&
				strtolower( $t->getName() ) === 'meta' &&
				TokenUtils::hasTypeOf( $t, 'mw:Placeholder' )
			) {
				// replace with empty string to avoid metas being foster-parented out
				$chunk[$i] = '';
			}
		}

		// FIXME: What is this stuff here? Why do we care about stripping out comments
		// so much that we create a new token array for every expanded template?
		// Unlikely to help perf very much.
		if ( !$this->options['expandTemplates'] ) {
			// Ignore comments in template transclusion mode
			$newChunk = [];
			for ( $i = 0, $n = count( $chunk ); $i < $n;  $i++ ) {
				if ( !( $chunk[$i] instanceof CommentTk ) ) {
					$newChunk[] = $chunk[$i];
				}
			}
			$chunk = $newChunk;
		}

		$this->env->log( 'debug', 'TemplateHandler.processTemplateTokens', $chunk );
		return $chunk;
	}

	/**
	 * @param array $state
	 * @return array
	 */
	private function finalizeTemplateTokens( array $state ): array {
		$this->env->log( 'debug', 'TemplateHandler.finalizeTemplateTokens' );
		$toks = [];
		if ( $this->wrapTemplates ) {
			$toks[] = $this->getEncapsulationInfoEndTag( $state );
		}
		return $toks;
	}

	/**
	 * Get the public data-mw structure that exposes the template name and
	 * parameters.
	 *
	 * @param array $state
	 * @return array
	 */
	private function getArgInfo( array $state ): array {
		$src = $this->manager->getFrame()->getSrcText();
		$params = $state['token']->attribs;
		// TODO: `dict` might be a good candidate for a T65370 style cleanup as a
		// Map, but since it's intended to be stringified almost immediately, we'll
		// just have to be cautious with it by checking for own properties.
		$dict = new stdClass;
		$paramInfos = [];
		$argIndex = 1;

		// Use source offsets to extract arg-name and arg-value wikitext
		// since the 'k' and 'v' values in params will be expanded tokens
		//
		// Ignore params[0] -- that is the template name
		for ( $i = 1,  $n = count( $params );  $i < $n;  $i++ ) {
			$srcOffsets = $params[$i]->srcOffsets;
			$kSrc = null;
			$vSrc = null;
			if ( $srcOffsets !== null ) {
				$kSrc = $srcOffsets->key->substr( $src );
				$vSrc = $srcOffsets->value->substr( $src );
			} else {
				$kSrc = $params[$i]->k;
				$vSrc = $params[$i]->v;
			}

			$kWt = trim( $kSrc );
			$k = TokenUtils::tokensToString( $params[$i]->k, true, [ 'stripEmptyLineMeta' => true ] );
			if ( is_array( $k ) ) {
				// The PHP parser only removes comments and whitespace to construct
				// the real parameter name, so if there were other tokens, use the
				// original text
				$k = $kWt;
			} else {
				$k = trim( $k );
			}
			$v = $vSrc;

			// Number positional parameters
			$isPositional = null;

			// Even if k is empty, we need to check v immediately follows. If not,
			// it's a blank parameter name (which is valid) and we shouldn't make it
			// positional.
			if ( $k === '' &&
				 $srcOffsets &&
				 $srcOffsets->key->end === $srcOffsets->value->start
			) {
				$isPositional = true;
				$k = (string)$argIndex;
				$argIndex++;
			} else {
				$isPositional = false;
				// strip ws from named parameter values
				$v = trim( $v );
			}

			if ( !isset( $dict->$k ) ) {
				$paramInfo = [
					'k' => $k,
					'srcOffsets' => $srcOffsets
				];

				Assert::invariant(
					preg_match( '/^(\s*)(?:.*\S)?(\s*)$/sD', $kSrc, $keySpaceMatch ),
					'Template argument whitespace match failed.'
				);
				$valueSpaceMatch = null;

				if ( $isPositional ) {
					// PHP parser does not strip whitespace around
					// positional params and neither will we.
					$valueSpaceMatch = [ null, '', '' ];
				} else {
					$paramInfo['named'] = true;
					if ( $v !== '' ) {
						Assert::invariant(
							preg_match( '/^(\s*)(?:.*\S)?(\s*)$/sD', $vSrc, $valueSpaceMatch ),
							'Template argument whitespace match failed.'
						);
					} else {
						$valueSpaceMatch = [ null, '', $vSrc ];
					}
				}

				// Preserve key and value space prefix / postfix, if any.
				// "=" is the default spacing used by the serializer,
				if ( $keySpaceMatch[1] || $keySpaceMatch[2] || $valueSpaceMatch[1] || $valueSpaceMatch[2] ) {
					// Remember non-standard spacing
					$paramInfo['spc'] = [
						$keySpaceMatch[1], $keySpaceMatch[2],
						$valueSpaceMatch[1], $valueSpaceMatch[2]
					];
				}

				$paramInfos[] = $paramInfo;
			}

			$dict->$k = (object)[ 'wt' => $v ];
			// Only add the original parameter wikitext if named and different from
			// the actual parameter.
			if ( !$isPositional && $kWt !== $k ) {
				$dict->$k->key = (object)[ 'wt' => $kWt ];
			}
		}

		$ret = [
			'dict' => [
				'target' => [],
				'params' => $dict
			],
			'paramInfos' => $paramInfos
		];

		$tgtSrcOffsets = $params[0]->srcOffsets;
		if ( $tgtSrcOffsets ) {
			$tplTgtWT = $tgtSrcOffsets->key->substr( $src );
			$ret['dict']['target']['wt'] = $tplTgtWT;
		}

		// Add in tpl-target/pf-name info
		// Only one of these will be set.
		if ( isset( $state['parserFunctionName'] ) ) {
			$ret['dict']['target']['function'] = $state['parserFunctionName'];
		} elseif ( isset( $state['resolvedTemplateTarget'] ) ) {
			$ret['dict']['target']['href'] = $state['resolvedTemplateTarget'];
		}

		return $ret;
	}

	/**
	 * Fetch a template.
	 *
	 * @param string $templateName
	 * @param array $state
	 * @param array $attribs
	 * @return array
	 */
	private function fetchTemplateAndTitle(
		string $templateName, array $state, array $attribs
	): array {
		$env = $this->env;
		if ( isset( $env->pageCache[$templateName] ) ) {
			$tplSrc = $env->pageCache[$templateName];
		} elseif ( $env->noDataAccess() ) {
			// This is only useful for offline development mode
			$tokens = [ $state['token']->dataAttribs->src ];
			if ( $this->wrapTemplates ) {
				// FIXME: We've already emitted a start meta to the accumulator in
				// `encapsulateTemplate`.  We could reach for that and modify it,
				// or refactor to emit it later for all paths, but the pragmatic
				// thing to do is just ignore it and wrap this anew.
				$state['wrappedObjectId'] = $env->newObjectId();
				$tokens = $this->encapTokens( $state, $tokens, [
					'errors' => [
						[
							'key' => 'mw-api-tplfetch-error',
							'message' => 'Page / template fetching disabled, and no cache for ' . $templateName
						]
					]
				] );
				$typeOf = $tokens[0]->getAttribute( 'typeof' );
				$tokens[0]->setAttribute( 'typeof', 'mw:Error' . ( $typeOf ? " $typeOf" : '' ) );
			}
			return [ 'tokens' => $tokens ];
		} else {
			$pageContent = $env->getDataAccess()->fetchPageContent( $env->getPageConfig(), $templateName );
			if ( !$pageContent ) {
				// Missing page!
				// FIXME: This should be a redlink here!
				$tplSrc = '';
			} else {
				// PORT-FIXME: Hard-coded 'main' role
				$tplSrc = $pageContent->getContent( 'main' );
			}
		}

		return [ 'tplSrc' => $tplSrc ];
	}

	/**
	 * Fetch the preprocessed wikitext for a template-like construct.
	 *
	 * @param string $transclusion
	 * @return array
	 */
	private function fetchExpandedTpl( string $transclusion ): array {
		$env = $this->env;
		if ( $env->noDataAccess() ) {
			$err = 'Warning: Page/template fetching disabled cannot expand ' . $transclusion;
			return [
				'error' => true,
				'tokens' => [ $err ]
			];
		} else {
			$pageConfig = $env->getPageConfig();
			$ret = $env->getDataAccess()->preprocessWikitext( $pageConfig, $transclusion );
			$wikitext = $this->manglePreprocessorResponse( $ret );
			return [
				'error' => false,
				'src' => $wikitext
			];
		}
	}

	/**
	 * This takes properties value of 'expandtemplates' output and computes
	 * magicword wikitext for those properties.
	 *
	 * This is needed for Parsoid/JS compatibility, but may go away in the future.
	 *
	 * @param array $ret
	 * @return string
	 */
	public function manglePreprocessorResponse( array $ret ): string {
		$env = $this->env;
		$wikitext = $ret['wikitext'];

		foreach ( [ 'modules', 'modulescripts', 'modulestyles' ] as $prop ) {
			$env->addOutputProperty( $prop, $ret[$prop] );
		}

		// Add the categories which were added by parser functions directly
		// into the page and not as in-text links.
		foreach ( ( $ret['categories'] ?? [] ) as $category => $sortkey ) {
			$wikitext .= "\n[[Category:" . $category;
			if ( $sortkey ) {
				$wikitext .= "|" . $sortkey;
			}
			$wikitext .= ']]';
		}

		// FIXME: This seems weirdly special-cased for displaytitle & displaysort
		// For now, just mimic what Parsoid/JS does, but need to revisit this
		foreach ( ( $ret['properties'] ?? [] ) as $name => $value ) {
			if ( $name === 'displaytitle' || $name === 'defaultsort' ) {
				$wikitext .= "\n{{" . mb_strtoupper( $name ) . ':' . $value . '}}';
			}
		}

		return $wikitext;
	}

	/**
	 * @param mixed $arg
	 * @param SourceRange $srcOffsets
	 * @return array
	 */
	private function fetchArg( $arg, SourceRange $srcOffsets ): array {
		if ( is_string( $arg ) ) {
			return [ 'tokens' => [ $arg ] ];
		} else {
			$toks = $this->manager->getFrame()->expand( $arg, [
				'expandTemplates' => false,
				'type' => 'tokens/x-mediawiki/expanded',
				'srcOffsets' => $srcOffsets,
			] );
			TokenUtils::stripEOFTkfromTokens( $toks );
			return [ 'tokens' => $toks ];
		}
	}

	/**
	 * @param array $args
	 * @param KV[] $attribs
	 * @param array $ret
	 * @return array
	 */
	private function lookupArg( array $args, $attribs, array $ret ): array {
		$toks = $ret['tokens'];
		// FIXME: Why is there a trim in one, but not the other??
		// Feels like a bug
		$argName = is_string( $toks ) ? $toks : trim( TokenUtils::tokensToString( $toks ) );
		$res = $args['dict'][$argName] ?? null;

		if ( $res !== null ) {
			if ( is_string( $res ) ) {
				$res = [ $res ];
			}
			return [ 'tokens' =>
				isset( $args['namedArgs'][$argName] ) ? TokenUtils::tokenTrim( $res ) : $res ];
		} elseif ( count( $attribs ) > 1 ) {
			return $this->fetchArg( $attribs[1]->v, $attribs[1]->srcOffsets->value );
		} else {
			return [ 'tokens' => [ '{{{' . $argName . '}}}' ] ];
		}
	}

	/**
	 * @param mixed $tokens
	 * @return bool
	 */
	private static function hasTemplateToken( $tokens ): bool {
		if ( is_array( $tokens ) ) {
			foreach ( $tokens as $t ) {
				if ( TokenUtils::isTemplateToken( $t ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Process the special magic word as specified by `resolvedTgt.magicWordType`.
	 * ```
	 * magicWordType === '!'    => {{!}} is the magic word
	 * magicWordtype === 'MASQ' => DEFAULTSORT, DISPLAYTITLE are the magic words
	 *                             (Util.magicMasqs.has(..))
	 * ```
	 * @param bool $atTopLevel
	 * @param Token $tplToken
	 * @param array|null $resolvedTgt
	 * @return Token[]|null
	 */
	public function processSpecialMagicWord(
		bool $atTopLevel, Token $tplToken, array $resolvedTgt = null
	): ?array {
		$env = $this->env;

		// Special case for {{!}} magic word.  Note that this is only necessary
		// because of the call from the TokenStreamPatcher.  Otherwise, ! is a
		// variable like any other and can be dropped from this function.
		// However, we keep both cases flowing through here for consistency.
		if ( ( $resolvedTgt && $resolvedTgt['magicWordType'] === '!' ) ||
			$tplToken->attribs[0]->k === '!'
		) {
			// If we're not at the top level, return a table cell. This will always
			// be the case. Either {{!}} was tokenized as a td, or it was tokenized
			// as template but the recursive call to fetch its content returns a
			// single | in an ambiguous context which will again be tokenized as td.
			if ( empty( $atTopLevel ) ) {
				return [ new TagTk( 'td' ) ];
			}
			$state = [
				'token' => $tplToken,
				'wrapperType' => 'mw:Transclusion',
				'wrappedObjectId' => $env->newObjectId()
			];
			$this->resolveTemplateTarget( $state, '!', $tplToken->attribs[0]->srcOffsets->key );
			return $this->encapTokens( $state, [ '|' ] );
		}

		if ( !$resolvedTgt || $resolvedTgt['magicWordType'] !== 'MASQ' ) {
			// Nothing to do
			return null;
		}

		$magicWord = mb_strtolower( $resolvedTgt['prefix'] );
		$pageProp = 'mw:PageProp/';
		if ( $magicWord === 'defaultsort' ) {
			$pageProp .= 'category';
		}
		$pageProp .= $magicWord;

		$metaToken = new SelfclosingTagTk( 'meta',
			[ new KV( 'property', $pageProp ) ],
			Utils::clone( $tplToken->dataAttribs )
		);

		if ( isset( $tplToken->dataAttribs->tmp->templatedAttribs ) ) {
			// No shadowing if templated
			//
			// SSS FIXME: post-tpl-expansion, WS won't be trimmed. How do we handle this?
			$metaToken->addAttribute(
				'content', $resolvedTgt['pfArgToks'],
				$resolvedTgt['srcOffsets']->expandTsrV()
			);
			$metaToken->addAttribute( 'about', $env->newAboutId() );
			$metaToken->addSpaceSeparatedAttribute( 'typeof', 'mw:ExpandedAttrs' );

			// See [[mw:Specs/HTML/1.4.0#Transclusion-affected_attributes]]
			//
			// For every attribute that has a templated name and/or value,
			// AttributeExpander creates a 2-item array for that attribute.
			// [ {txt: '..', html: '..'}, { html: '..'} ]
			// 'txt' is the plain-text name/value
			// 'html' is the HTML-version of the name/value
			//
			// Massage the templated magic-word info into a similar format.
			// In this case, the attribute name is 'content' (implicit) and
			// since it is implicit, the name itself cannot be attribute.
			// Hence 'html' property is empty.
			//
			// The attribute value has been templated and is encoded there.
			//
			// NOTE: If any part of the 'MAGIC_WORD:value' string is templated,
			// we consider the magic word as having expanded attributes, rather
			// than only when the 'value' part of it. This is because of the
			// limitation of our token representation for templates. This is
			// an edge case that it is not worth a refactoring right now to
			// handle this properly and choose mw:Transclusion or mw:ExpandedAttrs
			// depending on which part is templated.
			//
			// FIXME: Is there a simpler / better repn. for templated attrs?
			// FIXME: the content still contains the parser function prefix
			//  (eg, the html is 'DISPLAYTITLE:Foo' even though the stripped
			//   content attribute is 'Foo')
			$ta = $tplToken->dataAttribs->tmp->templatedAttribs;
			$ta[0] = [
				[ 'txt' => 'content' ],         // Magic-word attribute name
				[ 'html' => $ta[0][0]['html'] ] // HTML repn. of the attribute value
			];
			$metaToken->addAttribute( 'data-mw', PHPUtils::jsonEncode( [ 'attribs' => $ta ] ) );
		} else {
			// Leading/trailing WS should be stripped
			$key = trim( $resolvedTgt['pfArg'] );

			$src = $tplToken->dataAttribs->src ?? '';
			if ( $src ) {
				// If the token has original wikitext, shadow the sort-key
				$origKey = preg_replace( '/}}$/D', '', preg_replace( '/[^:]+:?/', '', $src, 1 ), 1 );
				$metaToken->addNormalizedAttribute( 'content', $key, $origKey );
			} else {
				// If not, this token came from an extension/template
				// in which case, dont bother with shadowing since the token
				// will never be edited directly.
				$metaToken->addAttribute( 'content', $key );
			}
		}

		return [ $metaToken ];
	}

	/**
	 * Main template token handler.
	 *
	 * Expands target and arguments (both keys and values) and either directly
	 * calls or sets up the callback to expandTemplate, which then fetches and
	 * processes the template.
	 *
	 * @param Token $token
	 * @return array
	 */
	private function onTemplate( Token $token ): array {
		// If the template name is templated, use the attribute transform manager
		// to process all attributes to tokens, and force reprocessing of the token.
		if ( self::hasTemplateToken( $token->attribs[0]->k ) ) {
			$ret = $this->ae->processComplexAttributes( $token );

			// Note that there's some hacky code in the attribute expander
			// to try and prevent it from returning templates in the
			// expanded attribs.  Otherwise, we can find outselves in a loop
			// here, where `hasTemplateToken` continuously returns true.
			//
			// That was happening when a template name depending on a top
			// level templatearg failed to expand.
			//
			// FIXME: ae->processComplexAttributes isn't passing us the right
			// retry signal. So, we are forced to rely on the hack above as
			// well as the unconditional retry below. We should fix that code
			// in AttributeExpander to pass back the retry signal that is
			// correct when we call it from the TemplateHandler so we can
			// get rid of that hack and the unconditional retry signal below.
			$ret['retry'] = true;

			return $ret;
		}

		$toks = null;
		$env = $this->env;
		$text = $token->dataAttribs->src ?? '';
		$state = [
			'token' => $token,
			'wrapperType' => 'mw:Transclusion',
			'wrappedObjectId' => $env->newObjectId(),
		];

		// This template target resolution may be incomplete for
		// cases where the template's target itself was templated.
		$tgt = $this->resolveTemplateTarget(
			$state, $token->attribs[0]->k, $token->attribs[0]->srcOffsets->key
		);
		if ( $tgt && $tgt['magicWordType'] ) {
			$toks = $this->processSpecialMagicWord( $this->atTopLevel, $token, $tgt );
			Assert::invariant( $toks !== null, "Expected non-null tokens array." );
			return [ 'tokens' => $toks ];
		}

		$expandTemplates = $this->options['expandTemplates'];

		if ( $expandTemplates && $tgt === null ) {
			// Target contains tags, convert template braces and pipes back into text
			// Re-join attribute tokens with '=' and '|'
			return [ 'tokens' => $this->convertAttribsToString( $state, $token->attribs ) ];
		}

		if ( $env->nativeTemplateExpansionEnabled() ) {
			// Expand argument keys
			$atm = new AttributeTransformManager( $this->manager->getFrame(),
				[ 'expandTemplates' => false, 'inTemplate' => true ]
			);
			$newAttribs = $atm->process( $token->attribs );
			$tplToks = $this->expandTemplate( $state, $newAttribs );
			if ( $expandTemplates ) {
				$encapToks = $this->encapsulateTemplate( $state );
				return [ 'tokens' => array_merge( $encapToks, $tplToks ) ];
			} else {
				return [ 'tokens' => $tplToks ];
			}
		} else {
			if ( $expandTemplates ) {
				// Use MediaWiki's preprocessor
				//
				// The tokenizer needs to use `text` as the cache key for caching
				// expanded tokens from the expanded transclusion text that we get
				// from the preprocessor, since parameter substitution will already
				// have taken place.
				//
				// It's sufficient to pass `[]` in place of attribs since they
				// won't be used.  In `usePHPPreProcessor`, there is no parameter
				// substitution coming from the frame.

				/* If $tgt is not null, target will be present. */
				$templateName = $tgt['target'];
				$templateTitle = $tgt['title'];
				$attribs = [];

				// We still need to check for limit violations because of the
				// higher precedence of extension tags, which can result in nested
				// templates even while using the php preprocessor for expansion.
				$checkRes = $this->checkRes( $templateName, $templateTitle, true );
				if ( is_array( $checkRes ) ) {
					return [ 'tokens' => $checkRes ];
				}

				// Check if we have an expansion for this template in the cache already
				$cachedTransclusion = $env->transclusionCache[$text] ?? null;
				if ( $cachedTransclusion ) {
					// cache hit: reuse the expansion DOM
					// FIXME(SSS): How does this work again for
					// templates like {{start table}} and {[end table}}??
					return PipelineUtils::encapsulateExpansionHTML( $env, $token, $cachedTransclusion, [
						'fromCache' => true
					] );
				} else {
					// Fetch and process the template expansion
					$expansion = $this->fetchExpandedTpl( $text );
					if ( $expansion['error'] ) {
						$tplToks = $expansion['tokens'];
					} else {
						$tplToks = $this->processTemplateSource(
							$state,
							[
								'name' => $templateName,
								'title' => $templateTitle,
								'attribs' => $attribs
							],
							$expansion['src']
						);
					}

					// Encapsulate
					$encapToks = $this->encapsulateTemplate( $state );

					return [ 'tokens' => array_merge( $encapToks, $tplToks ) ];
				}
			} else {
				// We don't perform recursive template expansion- something
				// template-like that the PHP parser did not expand. This is
				// encapsulated already, so just return the plain text.
				Assert::invariant( TokenUtils::isTemplateToken( $token ), "Expected template token." );
				return [ 'tokens' => $this->convertAttribsToString( $state, $token->attribs ) ];
			}
		}
	}

	/**
	 * Expand template arguments with tokens from the containing frame.
	 * @param Token $token
	 * @return array
	 */
	private function onTemplateArg( Token $token ): array {
		$args = $this->manager->getFrame()->getArgs()->named();
		$attribs = $token->attribs;
		$res = $this->fetchArg( $attribs[0]->k, $attribs[0]->srcOffsets->key );
		$res = $this->lookupArg( $args, $attribs, $res );

		if ( $this->options['expandTemplates'] ) {
			// This is a bare use of template arg syntax at the top level
			// outside any template use context.  Wrap this use with RDF attrs.
			// so that this chunk can be RT-ed en-masse.
			$state = [
				'token' => $token,
				'wrapperType' => 'mw:Param',
				'wrappedObjectId' => $this->env->newObjectId()
			];
			$res = [ 'tokens' => $this->encapTokens( $state, $res['tokens'] ) ];
		}

		return $res;
	}

	/**
	 * @param Token $token
	 * @return string|Token|array
	 */
	public function onTag( Token $token ) {
		switch ( $token->getName() ) {
			case "template":
				return $this->onTemplate( $token );
			case "templatearg":
				return $this->onTemplateArg( $token );
			default:
				return $token;
		}
	}
}
