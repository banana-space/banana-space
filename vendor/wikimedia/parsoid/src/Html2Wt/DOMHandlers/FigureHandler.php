<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class FigureHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$state->serializer->figureHandler( $node );
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( WTUtils::isNewElt( $node )
			&& $node->parentNode
			&& DOMUtils::isBody( $node->parentNode )
		) {
			return [ 'min' => 1 ];
		}
		return [];
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( WTUtils::isNewElt( $node )
			&& $node->parentNode
			&& DOMUtils::isBody( $node->parentNode )
		) {
			return [ 'min' => 1 ];
		}
		return [];
	}

}
