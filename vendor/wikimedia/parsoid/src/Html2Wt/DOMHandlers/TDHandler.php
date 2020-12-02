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

class TDHandler extends DOMHandler {

	public function __construct() {
		parent::__construct( false );
	}

	/** @inheritDoc */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		$dp = DOMDataUtils::getDataParsoid( $node );
		$usableDP = $this->stxInfoValidForTableCell( $state, $node );
		$attrSepSrc = $usableDP ? PHPUtils::coalesce( $dp->attrSepSrc ?? null, null ) : null;
		$startTagSrc = $usableDP ? ( $dp->startTagSrc ?? null ) : '';
		if ( !$startTagSrc ) {
			$startTagSrc = ( $usableDP && ( $dp->stx ?? null ) === 'row' ) ? '||' : '|';
		}

		// T149209: Special case to deal with scenarios
		// where the previous sibling put us in a SOL state
		// (or will put in a SOL state when the separator is emitted)
		if ( $state->onSOL || ( $state->sep->constraints['min'] ?? 0 ) > 0 ) {
			$startTagSrc = preg_replace( '/\|\|/', '|', $startTagSrc, 1 );
			$startTagSrc = preg_replace( '/{{!}}{{!}}/', '{{!}}', $startTagSrc, 1 );
		}

		// If the HTML for the first td is not enclosed in a tr-tag,
		// we start a new line.  If not, tr will have taken care of it.
		$tdTag = $this->serializeTableTag(
			$startTagSrc, $attrSepSrc,
			$state, $node, $wrapperUnmodified
		);
		$inWideTD = (bool)preg_match( '/\|\||^{{!}}{{!}}/', $tdTag );
		$leadingSpace = $this->getLeadingSpace( $state, $node, '' );
		WTSUtils::emitStartTag( $tdTag . $leadingSpace, $node, $state );
		$tdHandler = function ( $state, $text, $opts ) use ( $node, $inWideTD ) {
			return $state->serializer->wteHandlers->tdHandler( $node, $inWideTD, $state, $text, $opts );
		};

		$nextTd = DOMUtils::nextNonSepSibling( $node );
		$nextUsesRowSyntax = DOMUtils::isElt( $nextTd )
			&& $nextTd instanceof DOMElement // for static analyzers
			&& ( DOMDataUtils::getDataParsoid( $nextTd )->stx ?? null ) === 'row';

		// For empty cells, emit a single whitespace to make wikitext
		// more readable as well as to eliminate potential misparses.
		if ( $nextUsesRowSyntax && !DOMUtils::firstNonDeletedChild( $node ) ) {
			$state->serializer->emitWikitext( ' ', $node );
			return $node->nextSibling;
		}

		$state->serializeChildren( $node, $tdHandler );

		// PORT-FIXME does regexp whitespace semantics change matter?
		if ( $nextUsesRowSyntax && !preg_match( '/\s$/D', $state->currLine->text ) ) {
			$trailingSpace = $this->getTrailingSpace( $state, $node, '' );
			if ( $trailingSpace ) {
				$state->appendSep( $trailingSpace );
			}
		}

		return $node->nextSibling;
	}

	/** @inheritDoc */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		if ( $otherNode->nodeName === 'td'
			&& ( DOMDataUtils::getDataParsoid( $node )->stx ?? null ) === 'row'
		) {
			// force single line
			return [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		} else {
			return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		}
	}

	/** @inheritDoc */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
	}

}
