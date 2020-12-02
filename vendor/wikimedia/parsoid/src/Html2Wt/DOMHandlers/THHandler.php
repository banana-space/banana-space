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

class THHandler extends DOMHandler {

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
			$startTagSrc = $usableDP && ( $dp->stx ?? null ) === 'row' ? '!!' : '!';
		}

		// T149209: Special case to deal with scenarios
		// where the previous sibling put us in a SOL state
		// (or will put in a SOL state when the separator is emitted)
		if ( $state->onSOL || ( $state->sep->constraints['min'] ?? 0 ) > 0 ) {
			// You can use both "!!" and "||" for same-row headings (ugh!)
			$startTagSrc = preg_replace( '/!!/', '!', $startTagSrc, 1 );
			$startTagSrc = preg_replace( '/\|\|/', '!', $startTagSrc, 1 );
			$startTagSrc = preg_replace( '/{{!}}{{!}}/', '{{!}}', $startTagSrc, 1 );
		}

		$thTag = $this->serializeTableTag( $startTagSrc, $attrSepSrc, $state, $node, $wrapperUnmodified );
		$leadingSpace = $this->getLeadingSpace( $state, $node, '' );
		// If the HTML for the first th is not enclosed in a tr-tag,
		// we start a new line.  If not, tr will have taken care of it.
		WTSUtils::emitStartTag( $thTag . $leadingSpace,
			$node,
			$state
		);
		$thHandler = function ( $state, $text, $opts ) use ( $node ) {
			return $state->serializer->wteHandlers->thHandler( $node, $state, $text, $opts );
		};

		$nextTh = DOMUtils::nextNonSepSibling( $node );
		$nextUsesRowSyntax = DOMUtils::isElt( $nextTh )
			&& $nextTh instanceof DOMElement // for static analyzers
			&& ( DOMDataUtils::getDataParsoid( $nextTh )->stx ?? null ) === 'row';

		// For empty cells, emit a single whitespace to make wikitext
		// more readable as well as to eliminate potential misparses.
		if ( $nextUsesRowSyntax && !DOMUtils::firstNonDeletedChild( $node ) ) {
			$state->serializer->emitWikitext( ' ', $node );
			return $node->nextSibling;
		}

		$state->serializeChildren( $node, $thHandler );

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
		if ( $otherNode->nodeName === 'th'
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
		if ( $otherNode->nodeName === 'td' ) {
			// Force a newline break
			return [ 'min' => 1, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		} else {
			return [ 'min' => 0, 'max' => $this->maxNLsInTable( $node, $otherNode ) ];
		}
	}

}
