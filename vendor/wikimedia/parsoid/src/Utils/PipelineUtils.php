<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use DOMText;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Tokens\CommentTk;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\EOFTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\SelfclosingTagTk;
use Wikimedia\Parsoid\Tokens\SourceRange;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Wt2Html\Frame;

/**
 * This file contains parsing pipeline related utilities.
 */
class PipelineUtils {
	/**
	 * Creates a dom-fragment-token for processing 'content' (an array of tokens)
	 * in its own subpipeline all the way to DOM. These tokens will be processed
	 * by their own handler (DOMFragmentBuilder) in the last stage of the async
	 * pipeline.
	 *
	 * srcOffsets should always be provided to process top-level page content in a
	 * subpipeline. Without it, DSR computation and template wrapping cannot be done
	 * in the subpipeline. While unpackDOMFragment can do this on unwrapping, that can
	 * be a bit fragile and makes dom-fragments a leaky abstraction by leaking subpipeline
	 * processing into the top-level pipeline.
	 *
	 * @param Token[]|string $content The array of tokens to process.
	 * @param SourceRange $srcOffsets Wikitext source offsets (start/end) of these tokens.
	 * @param array $opts Parsing options.
	 *    - Token token The token that generated the content.
	 *    - bool  inlineContext Is this DOM fragment used in an inline context?
	 * @return SelfclosingTagTk
	 */
	public static function getDOMFragmentToken(
		$content, SourceRange $srcOffsets, array $opts = []
	): SelfclosingTagTk {
		$token = $opts['token'];
		return new SelfclosingTagTk( 'mw:dom-fragment-token', [
			new KV( 'contextTok', $token, $token->dataAttribs->tsr->expandTsrV() ),
			new KV( 'content', $content, $srcOffsets->expandTsrV() ),
			new KV( 'inlineContext', ( $opts['inlineContext'] ?? false ) ? "1" : "0" ),
			new KV( 'inPHPBLock', ( $opts['inPHPBLock'] ?? false ) ? "1" : "0" ),
		] );
	}

	/**
	 * Processes content (wikitext, array of tokens, whatever) in its own pipeline
	 * based on options.
	 *
	 * @param Env $env The environment/context for the expansion.
	 * @param Frame $frame
	 *    The parent frame within which the expansion is taking place.
	 *    Used for template expansion and source text tracking.
	 * @param string|Token|Token[] $content
	 *    This could be wikitext or single token or an array of tokens.
	 *    How this content is processed depends on what kind of pipeline
	 *    is constructed specified by opts.
	 * @param array $opts
	 *    Processing options that specify pipeline-type, opts, and callbacks.
	 *    - string pipelineType
	 *    - array  pipelineOpts
	 *    - array  tplArgs - if set, defines parameters for the child frame
	 *    - string tplArgs.name
	 *    - array  tplArgs.attribs
	 *    - string srcText - if set, defines the source text for the expansion
	 *    - SourceRange  srcOffsets - if set, defines the range within the
	 *          source text that $content corresponds to
	 *    - bool   sol
	 * @return Token[]|DOMDocument (depending on pipeline type)
	 */
	public static function processContentInPipeline( Env $env, Frame $frame, $content, array $opts ) {
		// Build a pipeline
		$pipeline = $env->getPipelineFactory()->getPipeline(
			$opts['pipelineType'],
			$opts['pipelineOpts']
		);

		// Set frame if necessary
		$srcText = $opts['srcText'] ?? $frame->getSrcText();
		if ( isset( $opts['tplArgs'] ) ) {
			$pipeline->setFrame( $frame, $opts['tplArgs']['title'], $opts['tplArgs']['attribs'], $srcText );
		} else {
			$pipeline->setFrame( $frame, null, [], $srcText );
		}

		// Set source offsets for this pipeline's content
		if ( isset( $opts['srcOffsets'] ) ) {
			$pipeline->setSourceOffsets( $opts['srcOffsets'] );
		}

		// Off the starting block ... ready, set, go!
		return $pipeline->parse( $content, [ "sol" => $opts['sol'] ] );
	}

	/**
	 * Expands value all the way to DOM.
	 *
	 * @param Env $env
	 *    The environment/context for the expansion.
	 * @param Frame $frame
	 *    The parent frame within which the expansion is taking place.
	 *    Used for template expansion and source text tracking.
	 * @param array $v
	 *    The value to process.
	 *    The value is expected to be an associative array with a "html" property.
	 *    The html property is expanded to DOM only if it is an array (of tokens).
	 *    Non-arrays are passed back unexpanded.
	 * @param bool $expandTemplates
	 *    Should any templates encountered here be expanded
	 *    (usually false for nested templates since they are never directly editable).
	 * @param bool $inTemplate
	 *    Unexpanded templates can occur in the content of extension tags.
	 * @return array
	 */
	public static function expandValueToDOM(
		Env $env, Frame $frame, array $v, bool $expandTemplates, bool $inTemplate
	): array {
		if ( is_array( $v['html'] ?? null ) ) {
			// Set up pipeline options
			$opts = [
				'pipelineType' => 'tokens/x-mediawiki/expanded',
				'pipelineOpts' => [
					'attrExpansion' => true,
					'inlineContext' => true,
					'expandTemplates' => $expandTemplates,
					'inTemplate' => $inTemplate
				],
				'srcOffsets' => $v['srcOffsets'],
				'sol' => true
			];
			$content = array_merge( $v['html'], [ new EOFTk() ] );
			$dom = self::processContentInPipeline(
				$env, $frame, $content, $opts
			);
			// Since we aren't at the top level, data attrs
			// were not applied in cleanup.  However, tmp
			// was stripped.
			$v['html'] = ContentUtils::ppToXML( DOMCompat::getBody( $dom ), [ 'innerXML' => true ] );
		}
		// Remove srcOffsets after value is expanded, so they don't show
		// up in the output data-mw attribute
		unset( $v['srcOffsets'] );
		return $v;
	}

	/**
	 * @param Env $env
	 *    The environment/context for the expansion.
	 * @param Frame $frame
	 *    The parent frame within which the expansion is taking place.
	 *    Used for template expansion and source text tracking.
	 * @param array $vals
	 *    Array of values to expand.
	 *    Non-array elements of $vals are passed back unmodified.
	 *    If an array element, it is expected to be an associative array with a "html" property.
	 *    The html property is expanded to DOM only if it is an array (of tokens).
	 * @param bool $expandTemplates
	 *    Should any templates encountered here be expanded
	 *    (usually false for nested templates since they are never directly editable).
	 * @param bool $inTemplate
	 *    Unexpanded templates can occur in the content of extension tags.
	 * @return array
	 */
	public static function expandValuesToDOM(
		Env $env, $frame, array $vals, bool $expandTemplates, bool $inTemplate
	): array {
		$ret = [];
		foreach ( $vals as $v ) {
			$ret[] = self::expandValueToDOM( $env, $frame, $v, $expandTemplates, $inTemplate );
		}
		return $ret;
	}

	/**
	 * Convert a DOM node to a token. The node comes from a DOM whose data attributes
	 * are stored outside the DOM.
	 *
	 * @param DOMElement $node
	 * @param DOMAttr[] $attrs
	 * @return array
	 */
	private static function domAttrsToTagAttrs( DOMElement $node, array $attrs ): array {
		$out = [];
		foreach ( $attrs as $a ) {
			if ( $a->name !== DOMDataUtils::DATA_OBJECT_ATTR_NAME ) {
				$out[] = new KV( $a->name, $a->value );
			}
		}
		if ( DOMDataUtils::validDataMw( $node ) ) {
			$out[] = new KV( 'data-mw', PHPUtils::jsonEncode( DOMDataUtils::getDataMw( $node ) ) );
		}
		return [ 'attrs' => $out, 'dataAttrs' => DOMDataUtils::getDataParsoid( $node ) ];
	}

	/**
	 * Convert a DOM to tokens. Data attributes for nodes are stored outside the DOM.
	 *
	 * @param DOMNode $node The root of the DOM tree to convert to tokens
	 * @param Token[] $tokBuf This is where the tokens get stored
	 * @return array
	 */
	private static function convertDOMtoTokens( DOMNode $node, array $tokBuf ): array {
		if ( $node instanceof DOMElement ) {
			$nodeName = strtolower( $node->nodeName );
			$attrInfo = self::domAttrsToTagAttrs( $node, DOMCompat::attributes( $node ) );

			if ( Utils::isVoidElement( $nodeName ) ) {
				$tokBuf[] = new SelfclosingTagTk( $nodeName, $attrInfo['attrs'], $attrInfo['dataAttrs'] );
			} else {
				$tokBuf[] = new TagTk( $nodeName, $attrInfo['attrs'], $attrInfo['dataAttrs'] );
				for ( $child = $node->firstChild;  $child;  $child = $child->nextSibling ) {
					$tokBuf = self::convertDOMtoTokens( $child, $tokBuf );
				}
				$endTag = new EndTagTk( $nodeName );
				// Keep stx parity
				if ( WTUtils::isLiteralHTMLNode( $node ) ) {
					$endTag->dataAttribs = PHPUtils::arrayToObject( [ 'stx' => 'html' ] );
				}
				$tokBuf[] = $endTag;
			}
		} elseif ( $node instanceof DOMText ) {
			$tokBuf = array_merge( $tokBuf, TokenUtils::newlinesToNlTks( $node->nodeValue ) );
		} elseif ( $node instanceof DOMComment ) {
			$tokBuf[] = new CommentTk( $node->nodeValue );
		} else {
			// getWrapperTokens calls convertDOMToTokens with a DOMElement
			// and children of dom elements are always text/comment/elements
			// which are all covered above.
			PHPUtils::unreachable( "Should never get here!" );
		}

		return $tokBuf;
	}

	/**
	 * Get tokens representing a DOM forest (from transclusions, extensions,
	 * whatever that were generated as part of a separate processing pipeline)
	 * in the token stream. These tokens will tunnel the subtree through the
	 * token processing while preserving token stream semantics as if
	 * the DOM had been converted to tokens.
	 *
	 * @param DOMNode[] $nodes List of DOM nodes that need to be tunneled through.
	 * @param array $opts
	 * @see encapsulateExpansionHTML's doc. for more info about these options.
	 * @return Token[] List of token representatives.
	 */
	public static function getWrapperTokens( array $nodes, array $opts ): array {
		if ( !$nodes ) {
			return [ new TagTk( 'span' ), new EndTagTk( 'span' ) ];
		}

		$node = $nodes[0];

		// Do we represent this with inline or block elements?
		// This is to ensure that we get p-wrapping correct.
		//
		// * If all content is inline, we use inline-elements to represent this
		// so that this content gets swallowed into the P tag that wraps
		// adjacent inline content.
		//
		// * If any part of this is a block content, we treat extension content
		// independent of surrounding content and don't want inline content
		// here to be swallowed into a P tag that wraps adjacent inline content.
		//
		// This behavior ensures that we and clients can "drop-in" extension content
		// into the DOM without messing with fixing up paragraph tags of surrounding
		// content. It could potentially introduce minor rendering differences when
		// compared to PHP parser output, but we'll swallow it for now.
		$wrapperType = 'INLINE';
		if ( !empty( $opts['pipelineOpts']['inlineContext'] ) ) {
			// If the DOM fragment is being processed in the context where P wrapping
			// has been suppressed, we represent the DOM fragment with inline-tokens.
			//
			// FIXME(SSS): Looks like we have some "impedance mismatch" here. But, this
			// is correct in scenarios where link-content or image-captions are being
			// processed in a sub-pipeline and we don't want a <div> in the link-caption
			// to cause the <a>..</a> to get split apart.
			//
			// Filed as T49963
		} elseif ( !empty( $opts['sealFragment'] ) ) {
			// Sealed fragments aren't amenable to inspection, since the
			// ultimate content is unknown.  For example, refs shuttle content
			// through treebuilding that ends up in the references list.
			//
			// FIXME(arlolra): Do we need a mechanism to specify content
			// categories?
		} else {
			for ( $i = 0;  $i < count( $nodes );  $i++ ) {
				if ( DOMUtils::isBlockNode( $nodes[$i] ) ||
					DOMUtils::hasBlockElementDescendant( $nodes[$i] )
				) {
					$wrapperType = 'BLOCK';
					break;
				}
			}
		}

		$wrapperName = null;
		if ( $wrapperType === 'BLOCK' && !DOMUtils::isBlockNode( $node ) ) {
			$wrapperName = 'div';
		} elseif ( $node->nodeName === 'a' ) {
			// Do not use 'A' as a wrapper node because it could
			// end up getting nested inside another 'A' and the DOM
			// structure can change where the wrapper tokens are no
			// longer siblings.
			// Ex: "[http://foo.com Bad nesting [[Here]]].
			$wrapperName = 'span';
		} elseif ( in_array( $node->nodeName, [ 'style', 'script' ], true ) && count( $nodes ) > 1 ) {
			// <style>/<script> tags are not fostered, so if we're wrapping
			// more than a single node, they aren't a good representation for
			// the content.  It can lead to fosterable content being inserted
			// in a fosterable position after treebuilding is done, which isn't
			// roundtrippable.
			$wrapperName = 'span';
		} elseif ( !DOMUtils::isElt( $node ) ) {
			$wrapperName = 'span';
		} else {
			$wrapperName = $node->nodeName;
		}

		if ( $node instanceof DOMElement ) {
			Assert::invariant(
				// No need to look for data-mw as well.
				// Nodes that have data-mw also have data-parsoid.
				!$node->hasAttribute( 'data-parsoid' ),
				"Expected node to have its data attributes loaded" );

			$nodeData = Utils::clone( DOMDataUtils::getNodeData( $node ) );

			if ( $wrapperName !== $node->nodeName ) {
				// Create a copy of the node without children
				$workNode = $node->ownerDocument->createElement( $wrapperName );

				// Copy over attributes
				foreach ( DOMCompat::attributes( $node ) as $attribute ) {
					'@phan-var \DOMAttr $attribute'; // @var \DOMAttr $attribute
					// "typeof" is ignored since it'll be removed below.
					if ( $attribute->name !== 'typeof' ) {
						$workNode->setAttribute( $attribute->name, $attribute->value );
					}
				}

				// We are applying a different wrapper.
				// So, node's data-parsoid isn't applicable.
				$nodeData->parsoid = new stdClass;
			} else {
				// Shallow clone since we don't want to convert the whole tree to tokens.
				$workNode = $node->cloneNode( false );

				// Reset 'tsr' since it isn't applicable.
				// FIXME: The above comment is only true if we are reusing
				// DOM fragments from cache from previous revisions in
				// incremental parsing scenarios.  See T98992
				if ( isset( $nodeData->parsoid->tsr ) ) {
					$nodeData->parsoid->tsr = null;
				}
			}

			DOMDataUtils::setNodeData( $workNode, $nodeData );
		} else {
			$workNode = $node->ownerDocument->createElement( $wrapperName );
		}

		$tokens = self::convertDOMtoTokens( $workNode, [] );

		// Remove the typeof attribute from the first token.
		// It will be replaced with mw:DOMFragment.
		$tokens[0]->removeAttribute( 'typeof' );

		// Remove the about attribute from the first token.
		// We want to be able to distinguish when this wrapper was template
		// annotated.
		$tokens[0]->removeAttribute( 'about' );

		return $tokens;
	}

	/**
	 * Generates wrapper tokens for a HTML expansion -- the wrapper
	 * tokens are placeholders that adequately represent semantics
	 * of the HTML DOM for the purposes of additional token transformations
	 * that will be applied to them.
	 *
	 * @param Env $env
	 *    The active environment/context.
	 * @param Token $token
	 *    The token that generated the DOM.
	 * @param array $expansion
	 *    - string html HTML of the expansion.
	 *    - DOMNode[] nodes Outermost nodes of the HTML.
	 * @param array $opts
	 *    - SourceRange tsr
	 *            The TSR to set on the generated tokens. This TSR is
	 *            used to compute DSR on the placeholder tokens.
	 *            The computed DSR is transferred over to the unpacked DOM
	 *            if setDSR is true (see below).
	 *    - bool  setDSR
	 *            When the DOM fragment is unpacked, this option governs
	 *            whether the DSR from the placeholder node is transferred
	 *            over to the unpacked DOM or not.
	 *            For example: Cite, reused transclusions.
	 *    - bool  fromCache
	 *    - array pipelineOpts
	 *    - bool  sealFragment
	 *    - string wrapperName
	 * @return Token[]
	 */
	public static function encapsulateExpansionHTML(
		Env $env, Token $token, array $expansion, array $opts = []
	): array {
		// Get placeholder tokens to get our subdom through the token processing
		// stages. These will be finally unwrapped on the DOM.
		$toks = self::getWrapperTokens( $expansion['nodes'], $opts );
		$firstWrapperToken = $toks[0];

		// Add the DOMFragment type so that we get unwrapped later.
		$sealFragment = !empty( $opts['sealFragment'] );
		$fragmentType = 'mw:DOMFragment' . ( $sealFragment ? '/sealed/' . $opts['wrapperName'] : '' );
		$firstWrapperToken->setAttribute( 'typeof', $fragmentType );

		// Assign the HTML fragment to the data-parsoid.html on the first wrapper token.
		$firstWrapperToken->dataAttribs->html = $expansion['html'];

		// Pass through setDSR flag
		if ( !empty( $opts['setDSR'] ) ) {
			if ( !$firstWrapperToken->dataAttribs->tmp ) {
				$firstWrapperToken->dataAttribs->tmp = new stdClass;
			}
			$firstWrapperToken->dataAttribs->tmp->setDSR = $opts['setDSR'];
		}

		// Pass through fromCache flag
		if ( !empty( $opts['fromCache'] ) ) {
			if ( !$firstWrapperToken->dataAttribs->tmp ) {
				$firstWrapperToken->dataAttribs->tmp = new stdClass;
			}
			$firstWrapperToken->dataAttribs->tmp->fromCache = $opts['fromCache'];
		}

		// Transfer the tsr.
		// The first token gets the full width, the following tokens zero width.
		$tokenTsr = $opts['tsr'] ?? $token->dataAttribs->tsr ?? null;
		if ( $tokenTsr ) {
			$firstWrapperToken->dataAttribs->tsr = $tokenTsr;
			$firstWrapperToken->dataAttribs->extTagOffsets = $token->dataAttribs->extTagOffsets ?? null;
			// XXX to investigate: if $tokenTsr->end is null, then we're losing
			// the 'hint' we'd like to provide here that this is a zero-width
			// source range.
			// ->end can be set to null by WikiLinkHandler::bailTokens()
			$endTsr = new SourceRange( $tokenTsr->end, $tokenTsr->end );
			for ( $i = 1;  $i < count( $toks );  $i++ ) {
				$toks[$i]->dataAttribs->tsr = clone $endTsr;
			}
		}

		return $toks;
	}

	/**
	 * @param DOMDocument $doc
	 * @param array &$textCommentAccum
	 */
	private static function wrapAccum(
		DOMDocument $doc, array &$textCommentAccum
	): void {
		// Wrap accumulated nodes in a span
		$span = $doc->createElement( 'span' );
		$parentNode = $textCommentAccum[0]->parentNode;
		$parentNode->insertBefore( $span, $textCommentAccum[0] );
		foreach ( $textCommentAccum as $n ) {
			$span->appendChild( $n );
		}
		DOMDataUtils::setDataParsoid( $span,
			(object)[ 'tmp' => PHPUtils::arrayToObject( [ 'wrapper' => true ] ) ] );
		$textCommentAccum = [];
	}

	/**
	 * Wrap text and comment nodes in a node list into spans, so that all
	 * top-level nodes are elements.
	 *
	 * @param DOMNodeList $nodes List of DOM nodes to wrap, mix of node types.
	 */
	public static function addSpanWrappers( DOMNodeList $nodes ): void {
		$textCommentAccum = [];
		$doc = $nodes->item( 0 )->ownerDocument;

		// Build a real array out of nodes.
		//
		// Operating directly on DOM child-nodes array
		// and manipulating them by adding span wrappers
		// changes the traversal itself
		$nodeBuf = [];
		foreach ( $nodes as $node ) {
			$nodeBuf[] = $node;
		}

		foreach ( $nodeBuf as $node ) {
			if ( DOMUtils::isText( $node ) || DOMUtils::isComment( $node ) ) {
				$textCommentAccum[] = $node;
			} elseif ( count( $textCommentAccum ) ) {
				self::wrapAccum( $doc, $textCommentAccum );
			}
		}

		if ( count( $textCommentAccum ) ) {
			self::wrapAccum( $doc, $textCommentAccum );
		}
	}

	/**
	 * Convert a HTML5 DOM into a mw:DOMFragment and generate appropriate
	 * tokens to insert into the token stream for further processing.
	 *
	 * The DOMPostProcessor will unpack the fragment and insert the HTML
	 * back into the DOM.
	 *
	 * @param Env $env
	 *    The active environment/context.
	 * @param Token $token
	 *    The token that generated the DOM.
	 * @param DOMElement $body
	 *    The DOM that the token expanded to.
	 * @param array $opts
	 *    Options to be passed onto the encapsulation code
	 *    See encapsulateExpansionHTML's doc. for more info about these options.
	 * @return Token[]
	 */
	public static function tunnelDOMThroughTokens(
		Env $env, Token $token, DOMElement $body, array $opts
	): array {
		Assert::invariant( DOMUtils::isBody( $body ), 'DOMFragment expected body node.' );
		// Get placeholder tokens to get our subdom through the token processing
		// stages. These will be finally unwrapped on the DOM.
		$expansion = self::makeExpansion( $env, iterator_to_array( $body->childNodes ) );
		return self::encapsulateExpansionHTML( $env, $token, $expansion, $opts );
	}

	/**
	 * @param Env $env
	 * @param DOMNode[] $nodes
	 * @return array
	 */
	public static function makeExpansion( Env $env, array $nodes ): array {
		$fragmentId = $env->newFragmentId();
		$env->setDOMFragment( $fragmentId, $nodes );
		return [ 'nodes' => $nodes, 'html' => $fragmentId ];
	}

	/**
	 * @param Env $env
	 * @param array &$expansions
	 * @param DOMNode $node
	 */
	private static function doExtractExpansions( Env $env, array &$expansions, DOMNode $node ): void {
		$nodes = null;
		$expAccum = null;
		while ( $node ) {
			if ( $node instanceof DOMElement ) {
				if ( DOMUtils::matchTypeOf( $node, '#^mw:(Transclusion$|Extension/)#' ) &&
						$node->hasAttribute( 'about' )
					) {
					$dp = DOMDataUtils::getDataParsoid( $node );
					$about = $node->hasAttribute( 'about' ) ? $node->getAttribute( 'about' ) : null;
					$nodes = WTUtils::getAboutSiblings( $node, $about );
					$key = null;
					if ( DOMUtils::hasTypeOf( $node, 'mw:Transclusion' ) ) {
						$expAccum = $expansions['transclusions'];
						$key = $dp->src;
					} elseif ( DOMUtils::matchTypeOf( $node, '#^mw:Extension/#' ) ) {
						$expAccum = $expansions['extensions'];
						$key = $dp->src;
					} else {
						$expAccum = $expansions['media'];
						// XXX gwicke: use proper key that is not
						// source-based? This also needs to work for
						// transclusion output.
						$key = null;
					}

					if ( $key ) {
						$expAccum[$key] = self::makeExpansion( $env, $nodes );
					}

					$node = end( $nodes );
				} else {
					self::doExtractExpansions( $env, $expansions, $node->firstChild );
				}
			}
			$node = $node->nextSibling;
		}
	}

	/**
	 * Extract transclusion and extension expansions from a DOM, and return
	 * them in a structure like this:
	 *     {
	 *         transclusions: {
	 *             'key1': {
	 *                  html: 'html1',
	 *                  nodes: [<node1>, <node2>]
	 *             }
	 *         },
	 *         extensions: {
	 *             'key2': {
	 *                  html: 'html2',
	 *                  nodes: [<node1>, <node2>]
	 *             }
	 *         },
	 *         files: {
	 *             'key3': {
	 *                  html: 'html3',
	 *                  nodes: [<node1>, <node2>]
	 *             }
	 *         }
	 *     }
	 *
	 * @param Env $env
	 * @param DOMElement $body
	 * @return array
	 */
	public static function extractExpansions( Env $env, DOMElement $body ): array {
		$expansions = [
			'transclusions' => [],
			'extensions' => [],
			'media' => []
		];
		// Kick off the extraction
		self::doExtractExpansions( $env, $expansions, $body->firstChild );
		return $expansions;
	}
}
