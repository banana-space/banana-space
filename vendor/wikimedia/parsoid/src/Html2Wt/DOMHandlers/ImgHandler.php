<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;

class ImgHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		if ( $node->getAttribute( 'rel' ) === 'mw:externalImage' ) {
			$state->serializer->emitWikitext( $node->getAttribute( 'src' ) ?: '', $node );
		} else {
			$state->serializer->figureHandler( $node );
		}
		return $node->nextSibling;
	}

}
