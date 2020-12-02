<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Parsoid\Core\DataParsoid;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

class TRHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$dp = DOMDataUtils::getDataParsoid( $node );

		if ( $this->trWikitextNeeded( $node, $dp ) ) {
			WTSUtils::emitStartTag(
				$this->serializeTableTag(
					PHPUtils::coalesce( $dp->startTagSrc ?? null, '|-' ), '', $state,
					$node, $wrapperUnmodified
				),
				$node, $state
			);
		}

		$state->serializeChildren( $node );
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( $this->trWikitextNeeded( $node,  DOMDataUtils::getDataParsoid( $node ) ) ) {
			return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		} else {
			return [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

	/**
	 * @param DOMElement $node
	 * @param stdClass|DataParsoid $dp
	 * @return bool
	 */
	private function trWikitextNeeded( DOMElement $node, stdClass $dp ): bool {
		// If the token has 'startTagSrc' set, it means that the tr
		// was present in the source wikitext and we emit it -- if not,
		// we ignore it.
		// ignore comments and ws
		if ( ( $dp->startTagSrc ?? null ) || DOMUtils::previousNonSepSibling( $node ) ) {
			return true;
		} else {
			// If parent has a thead/tbody previous sibling, then
			// we need the |- separation. But, a caption preceded
			// this node's parent, all is good.
			$parentSibling = DOMUtils::previousNonSepSibling( $node->parentNode );

			// thead/tbody/tfoot is always present around tr tags in the DOM.
			return $parentSibling && $parentSibling->nodeName !== 'caption';
		}
	}

}
