<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class ListHandler extends DOMHandler {

	/** @var string[] List of tag names which van be first children of the list */
	public $firstChildNames;

	/**
	 * @param string[] $firstChildNames List of tag names which van be first children of the list
	 */
	public function __construct( array $firstChildNames ) {
		parent::__construct( true );
		$this->firstChildNames = $firstChildNames;
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		// Disable single-line context here so that separators aren't
		// suppressed between nested list elements.
		$state->singleLineContext->disable();

		$firstChildElt = DOMUtils::firstNonSepChild( $node );

		// Skip builder-inserted wrappers
		// Ex: <ul><s auto-inserted-start-and-end-><li>..</li><li>..</li></s>...</ul>
		// output from: <s>\n*a\n*b\n*c</s>
		while ( $firstChildElt && $this->isBuilderInsertedElt( $firstChildElt ) ) {
			$firstChildElt = DOMUtils::firstNonSepChild( $firstChildElt );
		}

		if ( !$firstChildElt || !in_array( $firstChildElt->nodeName, $this->firstChildNames, true )
			|| WTUtils::isLiteralHTMLNode( $firstChildElt )
		) {
			$state->emitChunk( $this->getListBullets( $state, $node ), $node );
		}

		$liHandler = function ( $state, $text, $opts ) use ( $node ) {
			return $state->serializer->wteHandlers->liHandler( $node, $state, $text, $opts );
		};
		$state->serializeChildren( $node, $liHandler );
		$state->singleLineContext->pop();
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( DOMUtils::isBody( $otherNode ) ) {
			return [ 'min' => 0, 'max' => 0 ];
		}

		// node is in a list & otherNode has the same list parent
		// => exactly 1 newline
		if ( DOMUtils::isListItem( $node->parentNode ) && $otherNode->parentNode === $node->parentNode ) {
			return [ 'min' => 1, 'max' => 1 ];
		}

		// A list in a block node (<div>, <td>, etc) doesn't need a leading empty line
		// if it is the first non-separator child (ex: <div><ul>...</div>)
		if ( DOMUtils::isBlockNode( $node->parentNode )
			&& DOMUtils::firstNonSepChild( $node->parentNode ) === $node
		) {
			return [ 'min' => 1, 'max' => 2 ];
		} elseif ( DOMUtils::isFormattingElt( $otherNode ) ) {
			return [ 'min' => 1, 'max' => 1 ];
		} else {
			return [ 'min' => WTUtils::isNewElt( $node ) ? 2 : 1, 'max' => 2 ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return $this->wtListEOL( $node, $otherNode );
	}

}
