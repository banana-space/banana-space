<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt\DOMHandlers;

use DOMElement;
use DOMNode;
use LogicException;
use Wikimedia\Parsoid\Html2Wt\SerializerState;
use Wikimedia\Parsoid\Html2Wt\WTSUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

/**
 * HTML -> Wikitext serialization relies on walking the DOM and delegating
 * the serialization requests to different DOM nodes.
 *
 * This class represents the interface that various DOM handlers are expected
 * to implement.
 *
 * There is the core 'handle' method that deals with converting the content
 * of the node into wikitext markup.
 *
 * Then there are 4 newline-constraint methods that specify the constraints
 * that need to be satisfied for the markup to be valid. For example, list items
 * should always start on a newline, but can only have a single newline separator.
 * Paragraphs always start on a newline and need at least 2 newlines in wikitext
 * for them to be recognized as paragraphs.
 *
 * Each of the 4 newline-constraint methods (before, after, firstChild, lastChild)
 * return an array with a 'min' and 'max' property. If a property is missing, it
 * means that the dom node doesn't have any newline constraints. Some DOM handlers
 * might therefore choose to implement none, some, or all of these methods.
 *
 * The return values of each of these methods are treated as consraints and the
 * caller will have to resolve potentially conflicting constraints between a
 * pair of nodes (siblings, parent-child). For example, if an after handler of
 * a node wants 1 newline, but the before handler of its sibling wants none.
 *
 * Ideally, there should not be any incompatible constraints, but we haven't
 * actually verified that this is the case. All consraint-hanlding code is in
 * the separators-handling methods.
 */
class DOMHandler {

	/** @var bool */
	private $forceSOL;

	/**
	 * @param bool $forceSOL
	 */
	public function __construct( bool $forceSOL = false ) {
		$this->forceSOL = $forceSOL;
	}

	/**
	 * Serialize a DOM node to wikitext.
	 * Serialized wikitext should be returned via $state::emitChunk().
	 * @param DOMElement $node
	 * @param SerializerState $state
	 * @param bool $wrapperUnmodified
	 * @return DOMNode|null The node to continue with (need not be an element always)
	 */
	public function handle(
		DOMElement $node, SerializerState $state, bool $wrapperUnmodified = false
	): ?DOMNode {
		throw new LogicException( 'Not implemented.' );
	}

	/**
	 * How many newlines should be emitted *before* this node?
	 *
	 * @param DOMElement $node
	 * @param DOMNode $otherNode
	 * @param SerializerState $state
	 * @return array
	 */
	public function before( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

	/**
	 * How many newlines should be emitted *after* this node?
	 *
	 * @param DOMElement $node
	 * @param DOMNode $otherNode
	 * @param SerializerState $state
	 * @return array
	 */
	public function after( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

	/**
	 * How many newlines should be emitted before the first child?
	 *
	 * @param DOMElement $node
	 * @param DOMNode $otherNode
	 * @param SerializerState $state
	 * @return array
	 */
	public function firstChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

	/**
	 * How many newlines should be emitted after the last child?
	 *
	 * @param DOMElement $node
	 * @param DOMNode $otherNode
	 * @param SerializerState $state
	 * @return array
	 */
	public function lastChild( DOMElement $node, DOMNode $otherNode, SerializerState $state ): array {
		return [];
	}

	/**
	 * Put the serializer in start-of-line mode before it is handled.
	 * All non-newline whitespace found between HTML nodes is stripped
	 * to ensure SOL state is guaranteed.
	 *
	 * @return bool
	 */
	public function isForceSOL(): bool {
		return $this->forceSOL;
	}

	/**
	 * List helper: This is a shared *after* newline handler for list items.
	 *
	 * @param DOMElement $node
	 * @param DOMNode $otherNode
	 * @return array An array in the form [ 'min' => <int>, 'max' => <int> ] or an empty array.
	 */
	protected function wtListEOL( DOMElement $node, DOMNode $otherNode ): array {
		if ( !DOMUtils::isElt( $otherNode ) || DOMUtils::isBody( $otherNode ) ) {
			return [ 'min' => 0, 'max' => 2 ];
		}
		'@phan-var DOMElement $otherNode';/** @var DOMElement $otherNode */

		if ( WTUtils::isFirstEncapsulationWrapperNode( $otherNode ) ) {
			return [ 'min' => DOMUtils::isList( $node ) ? 1 : 0, 'max' => 2 ];
		}

		$nextSibling = DOMUtils::nextNonSepSibling( $node );
		$dp = DOMDataUtils::getDataParsoid( $otherNode );
		if ( $nextSibling === $otherNode && ( $dp->stx ?? null ) === 'html' || isset( $dp->src ) ) {
			return [ 'min' => 0, 'max' => 2 ];
		} elseif ( $nextSibling === $otherNode && DOMUtils::isListOrListItem( $otherNode ) ) {
			if ( DOMUtils::isList( $node ) && $otherNode->nodeName === $node->nodeName ) {
				// Adjacent lists of same type need extra newline
				return [ 'min' => 2, 'max' => 2 ];
			} elseif ( DOMUtils::isListItem( $node )
				|| in_array( $node->parentNode->nodeName, [ 'li', 'dd' ], true )
			) {
				// Top-level list
				return [ 'min' => 1, 'max' => 1 ];
			} else {
				return [ 'min' => 1, 'max' => 2 ];
			}
		} elseif ( DOMUtils::isList( $otherNode )
			|| ( DOMUtils::isElt( $otherNode ) && ( $dp->stx ?? null ) === 'html' )
		) {
			// last child in ul/ol (the list element is our parent), defer
			// separator constraints to the list.
			return [];
		} elseif ( DOMUtils::isBlockNode( $node->parentNode )
			&& DOMUtils::lastNonSepChild( $node->parentNode ) === $node
		) {
			// A list in a block node (<div>, <td>, etc) doesn't need a trailing empty line
			// if it is the last non-separator child (ex: <div>..</ul></div>)
			return [ 'min' => 1, 'max' => 2 ];
		} elseif ( DOMUtils::isFormattingElt( $otherNode ) ) {
			return [ 'min' => 1, 'max' => 1 ];
		} else {
			return [ 'min' => WTUtils::isNewElt( $node ) ? 2 : 1, 'max' => 2 ];
		}
	}

	/**
	 * List helper: DOM-based list bullet construction.
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @return string
	 */
	protected function getListBullets( SerializerState $state, DOMElement $node ): string {
		$parentTypes = [
			'ul' => '*',
			'ol' => '#'
		];
		$listTypes = [
			'ul' => '',
			'ol' => '',
			'dl' => '',
			'li' => '',
			'dt' => ';',
			'dd' => ':'
		];

		// For new elements, for prettier wikitext serialization,
		// emit a space after the last bullet (if required)
		$space = $this->getLeadingSpace( $state, $node, ' ' );

		$res = '';
		while ( $node ) {
			$dp = DOMDataUtils::getDataParsoid( $node );
			$stx = $dp->stx ?? null;
			if ( $stx !== 'html' && isset( $listTypes[$node->nodeName] ) ) {
				if ( $node->nodeName === 'li' ) {
					$parentNode = $node->parentNode;
					while ( $parentNode && !( isset( $parentTypes[$parentNode->nodeName] ) ) ) {
						$parentNode = $parentNode->parentNode;
					}

					if ( $parentNode ) {
						$res = $parentTypes[$parentNode->nodeName] . $res;
					} else {
						$state->getEnv()->log( 'error/html2wt', 'Input DOM is not well-formed.',
							"Top-level <li> found that is not nested in <ol>/<ul>\n LI-node:",
							DOMCompat::getOuterHTML( $node )
						);
					}
				} else {
					$res = $listTypes[$node->nodeName] . $res;
				}
			} elseif ( $stx !== 'html' ||
				empty( $dp->autoInsertedStart ) || empty( $dp->autoInsertedEnd )
			) {
				break;
			}

			$node = $node->parentNode;
		}

		// Don't emit a space if we aren't returning any bullets.
		return strlen( $res ) ? $res . $space : '';
	}

	/**
	 * Helper: Newline constraint helper for table nodes
	 * @param DOMElement $node
	 * @param DOMNode $origNode
	 * @return int
	 */
	protected function maxNLsInTable( DOMElement $node, DOMNode $origNode ): int {
		return ( WTUtils::isNewElt( $node ) || WTUtils::isNewElt( $origNode ) ) ? 1 : 2;
	}

	/**
	 * Private helper for serializing table nodes
	 * @param string $symbol
	 * @param string|null $endSymbol
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @return string
	 */
	private function serializeTableElement(
		string $symbol, ?string $endSymbol, SerializerState $state, DOMElement $node
	): string {
		$token = WTSUtils::mkTagTk( $node );
		$sAttribs = $state->serializer->serializeAttributes( $node, $token );
		if ( $sAttribs !== '' ) {
			// IMPORTANT: use ?? not ?: in the first check because we want to preserve an
			// empty string. Use != '' in the second to avoid treating '0' as empty.
			return $symbol . ' ' . $sAttribs . ( $endSymbol ?? ' |' );
		} else {
			return $symbol . ( $endSymbol != '' ? $endSymbol : '' );
		}
	}

	/**
	 * Helper: Handles content serialization for table nodes
	 * @param string $symbol
	 * @param string|null $endSymbol
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @param bool $wrapperUnmodified
	 * @return string
	 */
	protected function serializeTableTag(
		string $symbol,
		?string $endSymbol,
		SerializerState $state,
		DOMElement $node,
		bool $wrapperUnmodified
	): string {
		if ( $wrapperUnmodified ) {
			$dsr = DOMDataUtils::getDataParsoid( $node )->dsr;
			return $state->getOrigSrc( $dsr->start, $dsr->innerStart() ) ?? '';
		} else {
			return $this->serializeTableElement( $symbol, $endSymbol, $state, $node );
		}
	}

	/**
	 * Helper: Checks whether syntax information in data-parsoid is valid
	 * in the presence of table edits. For example "|" is no longer valid
	 * table-cell markup if a table cell is added before this cell.
	 *
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @return bool
	 */
	protected function stxInfoValidForTableCell( SerializerState $state, DOMElement $node ): bool {
		// If row syntax is not set, nothing to worry about
		if ( ( DOMDataUtils::getDataParsoid( $node )->stx ?? null ) !== 'row' ) {
			return true;
		}

		// If we have an identical previous sibling, nothing to worry about
		$prev = DOMUtils::previousNonDeletedSibling( $node );
		return $prev !== null && $prev->nodeName === $node->nodeName;
	}

	/**
	 * Helper for several DOM handlers: Returns whitespace that needs to be emitted
	 * between the markup for the node and its content (ex: table cells, list items)
	 * based on node state (whether the node is original or new content) and other
	 * state (HTML version, whether selective serialization is enabled or not).
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @param string $newEltDefault
	 * @return string
	 */
	protected function getLeadingSpace(
		SerializerState $state, DOMElement $node, string $newEltDefault
	): string {
		$space = '';
		$fc = DOMUtils::firstNonDeletedChild( $node );
		if ( WTUtils::isNewElt( $node ) ) {
			// PORT-FIXME are different \s semantics going to be a problem?
			if ( $fc && ( !DOMUtils::isText( $fc ) || !preg_match( '/^\s/', $fc->nodeValue ) ) ) {
				$space = $newEltDefault;
			}
		} elseif ( $state->useWhitespaceHeuristics && $state->selserMode
			&& ( !$fc || !DOMUtils::isElt( $fc ) || WTUtils::isNewElt( $fc ) )
		) {
			$dsr = DOMDataUtils::getDataParsoid( $node )->dsr ?? null;
			if ( Utils::isValidDSR( $dsr, true ) ) {
				$offset = $dsr->innerStart();
				$space = $offset < $dsr->innerEnd() ?
					( $state->getOrigSrc( $offset, $offset + 1 ) ?? '' ) : '';
				if ( !preg_match( '/[ \t]/', $space ) ) {
					$space = '';
				}
			}
		}
		return $space;
	}

	/**
	 * Helper for several DOM handlers: Returns whitespace that needs to be emitted
	 * between the markup for the node and its next sibling based on node state
	 * (whether the node is original or new content) and other state (HTML version,
	 * whether selective serialization is enabled or not).
	 * @param SerializerState $state
	 * @param DOMElement $node
	 * @param string $newEltDefault
	 * @return string
	 */
	protected function getTrailingSpace(
		SerializerState $state, DOMElement $node, string $newEltDefault
	): string {
		$space = '';
		$lc = DOMUtils::lastNonDeletedChild( $node );
		if ( WTUtils::isNewElt( $node ) ) {
			// PORT-FIXME are different \s semantics going to be a problem?
			if ( $lc && ( !DOMUtils::isText( $lc ) || !preg_match( '/\s$/D', $lc->nodeValue ) ) ) {
				$space = $newEltDefault;
			}
		} elseif ( $state->useWhitespaceHeuristics && $state->selserMode
			&& ( !$lc || !DOMUtils::isElt( $lc ) || WTUtils::isNewElt( $lc ) )
		) {
			$dsr = DOMDataUtils::getDataParsoid( $node )->dsr ?? null;
			if ( Utils::isValidDSR( $dsr, true ) ) {
				$offset = $dsr->innerEnd() - 1;
				// The > instead of >= is to deal with an edge case
				// = = where that single space is captured by the
				// getLeadingSpace case above
				$space = $offset > $dsr->innerStart() ?
					( $state->getOrigSrc( $offset, $offset + 1 ) ?? '' ) : '';
				if ( !preg_match( '/[ \t]/', $space ) ) {
					$space = '';
				}
			}
		}
		return $space;
	}

	/**
	 * Helper: Is this node auto-inserted by the HTML5 tree-builder
	 * during wt->html?
	 * @param DOMNode $node
	 * @return bool
	 */
	protected function isBuilderInsertedElt( DOMNode $node ): bool {
		if ( !DOMUtils::isElt( $node ) ) {
			return false;
		}
		'@phan-var DOMElement $node';/** @var DOMElement $node */
		$dp = DOMDataUtils::getDataParsoid( $node );
		return !empty( $dp->autoInsertedStart ) && !empty( $dp->autoInsertedEnd );
	}

	/**
	 * Uneditable forms wrapped with mw:Placeholder tags OR unedited nowikis
	 * N.B. We no longer emit self-closed nowikis as placeholders, so remove this
	 * once all our stored content is updated.
	 * @param DOMElement $node
	 * @param SerializerState $state
	 */
	protected function emitPlaceholderSrc( DOMElement $node, SerializerState $state ) {
		$dp = DOMDataUtils::getDataParsoid( $node );
		if ( preg_match( '!<nowiki\s*/>!', $dp->src ?? '' ) ) {
			$state->hasSelfClosingNowikis = true;
		}
		// FIXME: Should this also check for tabs and plain space
		// chars interspersed with newlines?
		if ( preg_match( '/^\n+$/D', $dp->src ?? '' ) ) {
			$state->appendSep( $dp->src );
		} else {
			$state->serializer->emitWikitext( $dp->src, $node );
		}
	}

}
