<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\TokenUtils;

/**
 * Used as a fallback in other tag handles.
 */
class FallbackHTMLHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$serializer = $state->serializer;

		// Wikitext supports the following list syntax:
		//
		// * <li class="a"> hello world
		//
		// The "LI Hack" gives support for this syntax, and we need to
		// specially reconstruct the above from a single <li> tag.
		$serializer->handleLIHackIfApplicable( $node );

		$tag = $serializer->serializeHTMLTag( $node, $wrapperUnmodified );
		WTSUtils::emitStartTag( $tag, $node, $state );

		if ( $node->hasChildNodes() ) {
			$inPHPBlock = $state->inPHPBlock;
			if ( TokenUtils::tagOpensBlockScope( $node->nodeName ) ) {
				$state->inPHPBlock = true;
			}

			// TODO(arlolra): As of 1.3.0, html pre is considered an extension
			// and wrapped in encapsulation.  When that version is no longer
			// accepted for serialization, we can remove this backwards
			// compatibility code.
			if ( $node->nodeName === 'pre' ) {
				// Handle html-pres specially
				// 1. If the node has a leading newline, add one like it (logic copied from VE)
				// 2. If not, and it has a data-parsoid strippedNL flag, add it back.
				// This patched DOM will serialize html-pres correctly.

				$lostLine = '';
				$fc = $node->firstChild;
				if ( $fc && DOMUtils::isText( $fc ) ) {
					 preg_match( '/^\n/', $fc->nodeValue, $m );
					$lostLine = $m[0] ?? '';
				}

				if ( !$lostLine && ( DOMDataUtils::getDataParsoid( $node )->strippedNL ?? false ) ) {
					$lostLine = "\n";
				}

				$state->emitChunk( $lostLine, $node );
			}

			$state->serializeChildren( $node );
			$state->inPHPBlock = $inPHPBlock;
		}

		$endTag = $serializer->serializeHTMLEndTag( $node, $wrapperUnmodified );
		WTSUtils::emitEndTag( $endTag, $node, $state );
		return $node->nextSibling;
	}
}
