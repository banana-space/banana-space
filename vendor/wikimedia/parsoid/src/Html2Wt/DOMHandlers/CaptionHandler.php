<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;

class CaptionHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$dp = DOMDataUtils::getDataParsoid( $node );
		// Serialize the tag itself
		$tableTag = $this->serializeTableTag(
			PHPUtils::coalesce( $dp->startTagSrc ?? null, '|+' ), null, $state, $node,
			$wrapperUnmodified
		);
		WTSUtils::emitStartTag( $tableTag, $node, $state );
		$state->serializeChildren( $node );
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return ( $otherNode->nodeName !== 'table' )
			? [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ]
			: [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

}
