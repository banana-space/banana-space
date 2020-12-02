<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Utils;

class SpanHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$env = $state->getEnv();
		$dp = DOMDataUtils::getDataParsoid( $node );
		$contentSrc = ( $node->textContent != '' ) ? $node->textContent
			: DOMCompat::getInnerHTML( $node );
		if ( self::isRecognizedSpanWrapper( $node ) ) {
			if ( DOMUtils::hasTypeOf( $node, 'mw:Nowiki' ) ) {
				$ext = $env->getSiteConfig()->getExtTagImpl( 'nowiki' );
				$src = $ext->domToWikitext( $state->extApi, $node, $wrapperUnmodified );
				$state->serializer->emitWikitext( $src, $node );
			} elseif ( DOMUtils::matchTypeOf(
				$node, '#^mw:(Image|Video|Audio)(/(Frame|Frameless|Thumb))?$#'
			) ) {
				// TODO: Remove when 1.5.0 content is deprecated,
				// since we no longer emit media in spans.  See the test,
				// "Serialize simple image with span wrapper"
				$state->serializer->figureHandler( $node );
			} elseif (
				DOMUtils::hasTypeOf( $node, 'mw:Entity' ) &&
				DOMUtils::hasNChildren( $node, 1 )
			) {
				// handle a new mw:Entity (not handled by selser) by
				// serializing its children
				if ( isset( $dp->src ) && $contentSrc === ( $dp->srcContent ?? null ) ) {
					$state->serializer->emitWikitext( $dp->src, $node );
				} elseif ( DOMUtils::isText( $node->firstChild ) ) {
					$state->emitChunk(
						Utils::entityEncodeAll( $node->firstChild->nodeValue ),
						$node->firstChild );
				} else {
					$state->serializeChildren( $node );
				}
			} elseif ( DOMUtils::hasTypeOf( $node, 'mw:DisplaySpace' ) ) {
				// FIXME(T254501): Turn this into an `PHPUtils::unreachable()`
				$state->emitChunk( ' ', $node );
			} elseif ( DOMUtils::matchTypeOf( $node, '#^mw:Placeholder(/|$)#' ) ) {
				if ( isset( $dp->src ) ) {
					$this->emitPlaceholderSrc( $node, $state );
					return $node->nextSibling;
				} else {
					( new FallbackHTMLHandler )->handle( $node, $state );
				}
			}
		} else {
			$kvs = array_filter( WTSUtils::getAttributeKVArray( $node ), function ( KV $kv ) {
				return !preg_match( '/^data-parsoid/', $kv->k )
					&& ( $kv->k !== DOMDataUtils::DATA_OBJECT_ATTR_NAME )
					&& !( $kv->k === 'id' && preg_match( '/^mw[\w-]{2,}$/D', $kv->v ) );
			} );
			if ( !$state->rtTestMode && !empty( $dp->misnested ) && ( $dp->stx ?? null ) !== 'html'
				&& !count( $kvs )
			) {
				// Discard span wrappers added to flag misnested content.
				// Warn since selser should have reused source.
				$env->log( 'warn', 'Serializing misnested content: ' . DOMCompat::getOuterHTML( $node ) );
				$state->serializeChildren( $node );
			} else {
				// Fall back to plain HTML serialization for spans created
				// by the editor.
				( new FallbackHTMLHandler )->handle( $node, $state );
			}
		}
		return $node->nextSibling;
	}

	/**
	 * @param DOMElement $node
	 * @return string|null
	 */
	private static function isRecognizedSpanWrapper( DOMElement $node ): ?string {
		return DOMUtils::matchTypeOf(
			$node,
			// FIXME(T254501): Remove mw:DisplaySpace
			'#^mw:('
				. 'Nowiki|Entity|DisplaySpace|Placeholder(/\w+)?'
				. '|(Image|Video|Audio)(/(Frameless|Frame|Thumb))?'
				. ')$#'
		);
	}

}
