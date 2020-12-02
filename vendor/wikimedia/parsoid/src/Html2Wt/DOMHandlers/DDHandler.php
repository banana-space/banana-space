<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class DDHandler extends DOMHandler {

	/** @var string|null Syntax */
	private $stx;

	/**
	 * @param string|null $stx
	 */
	public function __construct( string $stx = null ) {
		parent::__construct( $stx !== 'row' );
		$this->stx = $stx;
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$firstChildElement = DOMUtils::firstNonSepChild( $node );
		$chunk = ( $this->stx === 'row' ) ? ':' : $this->getListBullets( $state, $node );
		if ( !DOMUtils::isList( $firstChildElement )
			 || WTUtils::isLiteralHTMLNode( $firstChildElement )
		) {
			$state->emitChunk( $chunk, $node );
		}
		$liHandler = function ( $state, $text, $opts ) use ( $node ) {
			return $state->serializer->wteHandlers->liHandler( $node, $state, $text, $opts );
		};
		$state->singleLineContext->enforce();
		$state->serializeChildren( $node, $liHandler );
		$state->singleLineContext->pop();
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( $this->stx === 'row' ) {
			return [ 'min' => 0, 'max' => 0 ];
		} else {
			return [ 'min' => 1, 'max' => 2 ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return $this->wtListEOL( $node, $otherNode );
	}

	/** @inheritDoc */
	public function firstChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( !DOMUtils::isList( $otherNode ) ) {
			return [ 'min' => 0, 'max' => 0 ];
		} else {
			return [];
		}
	}

}
