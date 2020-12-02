<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;

class BodyHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$state->serializeChildren( $node );
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function firstChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 0, 'max' => 1 ];
	}

	/** @inheritDoc */
	public function lastChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 0, 'max' => 1 ];
	}

}
