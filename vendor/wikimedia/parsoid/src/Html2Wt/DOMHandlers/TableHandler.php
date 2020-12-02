<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class TableHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$dp = DOMDataUtils::getDataParsoid( $node );
		$wt = PHPUtils::coalesce( $dp->startTagSrc ?? null, '{|' );
		$indentTable = $node->parentNode->nodeName === 'dd'
			&& DOMUtils::previousNonSepSibling( $node ) === null;
		if ( $indentTable ) {
			$state->singleLineContext->disable();
		}
		$state->emitChunk(
			$this->serializeTableTag( $wt, '', $state, $node, $wrapperUnmodified ),
			$node
		);
		if ( !WTUtils::isLiteralHTMLNode( $node ) ) {
			$state->wikiTableNesting++;
		}
		$state->serializeChildren( $node );
		if ( !WTUtils::isLiteralHTMLNode( $node ) ) {
			$state->wikiTableNesting--;
		}
		if ( $state->sep->constraints === null ) {
			// Special case hack for "{|\n|}" since state.sep is
			// cleared in SSP.emitSep after a separator is emitted.
			// However, for {|\n|}, the <table> tag has no element
			// children which means lastchild -> parent constraint
			// is never computed and set here.
			$state->sep->constraints = [ 'min' => 1, 'max' => 2, 'constraintInfo' => [] ];
		}
		WTSUtils::emitEndTag( PHPUtils::coalesce( $dp->endTagSrc ?? null, '|}' ), $node, $state );
		if ( $indentTable ) {
			$state->singleLineContext->pop();
		}
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		// Handle special table indentation case!
		if ( $node->parentNode === $otherNode && $otherNode->nodeName === 'dd' ) {
			return [ 'min' => 0, 'max' => 2 ];
		} else {
			return [ 'min' => 1, 'max' => 2 ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( ( WTUtils::isNewElt( $node ) || WTUtils::isNewElt( $otherNode ) )
			&& !DOMUtils::isBody( $otherNode )
		) {
			return [ 'min' => 1, 'max' => 2 ];
		} else {
			return [ 'min' => 0, 'max' => 2 ];
		}
	}

	/** @inheritDoc */
	public function firstChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

	/** @inheritDoc */
	public function lastChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

}
