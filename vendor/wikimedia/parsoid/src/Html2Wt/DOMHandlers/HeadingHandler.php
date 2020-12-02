<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class HeadingHandler extends DOMHandler {

	/** @var string Heading open/close wikitext (e.g. '===') */
	public $headingWT;

	/**
	 * @param string $headingWT Heading open/close wikitext (e.g. '===')
	 */
	public function __construct( string $headingWT ) {
		parent::__construct( true );
		$this->headingWT = $headingWT;
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		// For new elements, for prettier wikitext serialization,
		// emit a space after the last '=' char.
		$space = $this->getLeadingSpace( $state, $node, ' ' );
		$state->emitChunk( $this->headingWT . $space, $node );
		$state->singleLineContext->enforce();

		if ( $node->hasChildNodes() ) {
			$state->serializeChildren( $node, null, DOMUtils::firstNonDeletedChild( $node ) );
		} else {
			// Deal with empty headings
			$state->emitChunk( '<nowiki/>', $node );
		}

		// For new elements, for prettier wikitext serialization,
		// emit a space before the first '=' char.
		$space = $this->getTrailingSpace( $state, $node, ' ' );
		$state->emitChunk( $space . $this->headingWT, $node ); // Why emitChunk here??
		$state->singleLineContext->pop();
		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( WTUtils::isNewElt( $node ) && DOMUtils::previousNonSepSibling( $node ) ) {
			// Default to two preceding newlines for new content
			return [ 'min' => 2, 'max' => 2 ];
		} elseif ( WTUtils::isNewElt( $otherNode )
			&& DOMUtils::previousNonSepSibling( $node ) === $otherNode
		) {
			// T72791: The previous node was newly inserted, separate
			// them for readability
			return [ 'min' => 2, 'max' => 2 ];
		} else {
			return [ 'min' => 1, 'max' => 2 ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 1, 'max' => 2 ];
	}

}
