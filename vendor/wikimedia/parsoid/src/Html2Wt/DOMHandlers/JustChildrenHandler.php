<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

// Just serialize the children, ignore the (implicit) tag
use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;

class JustChildrenHandler extends DOMHandler {

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

}
