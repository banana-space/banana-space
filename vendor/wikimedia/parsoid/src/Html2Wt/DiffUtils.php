<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;

class DiffUtils {
	/**
	 * Get a node's diff marker.
	 *
	 * @param DOMNode $node
	 * @param Env $env
	 * @return stdClass|null
	 */
	public static function getDiffMark( DOMNode $node, Env $env ): ?stdClass {
		if ( !( $node instanceof DOMElement ) ) {
			return null;
		}

		$data = DOMDataUtils::getNodeData( $node );
		$dpd = $data->parsoid_diff ?? null;
		return ( $dpd && $dpd->id === $env->getPageConfig()->getPageId() ) ? $dpd : null;
	}

	/**
	 * Check that the diff markers on the node exist and are recent.
	 *
	 * @param DOMNode $node
	 * @param Env $env
	 * @return bool
	 */
	public static function hasDiffMarkers( DOMNode $node, Env $env ): bool {
		return self::getDiffMark( $node, $env ) !== null || DOMUtils::isDiffMarker( $node );
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @param string $mark
	 * @return bool
	 */
	public static function hasDiffMark( DOMNode $node, Env $env, string $mark ): bool {
		// For 'deletion' and 'insertion' markers on non-element nodes,
		// a mw:DiffMarker meta is added
		if ( $mark === 'deleted' || ( $mark === 'inserted' && !DOMUtils::isElt( $node ) ) ) {
			return DOMUtils::isDiffMarker( $node->previousSibling, $mark );
		} else {
			$diffMark = self::getDiffMark( $node, $env );
			return $diffMark && array_search( $mark, $diffMark->diff, true ) !== false;
		}
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @return bool
	 */
	public static function hasInsertedDiffMark( DOMNode $node, Env $env ): bool {
		return self::hasDiffMark( $node, $env, 'inserted' );
	}

	/**
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function maybeDeletedNode( ?DOMNode $node ): bool {
		return $node && DOMUtils::isElt( $node ) && DOMUtils::isDiffMarker( $node, 'deleted' );
	}

	/**
	 * Is node a mw:DiffMarker node that represents a deleted block node?
	 * This annotation is added by the DOMDiff pass.
	 *
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isDeletedBlockNode( ?DOMNode $node ): bool {
		return self::maybeDeletedNode( $node ) && DOMUtils::assertElt( $node ) &&
			$node->hasAttribute( 'data-is-block' );
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @return bool
	 */
	public static function directChildrenChanged( DOMNode $node, Env $env ): bool {
		return self::hasDiffMark( $node, $env, 'children-changed' );
	}

	/**
	 * @param DOMElement $node
	 * @param Env $env
	 * @return bool
	 */
	public static function onlySubtreeChanged( DOMElement $node, Env $env ): bool {
		$dmark = self::getDiffMark( $node, $env ) ?? null;
		if ( !$dmark ) {
			return false;
		}

		foreach ( $dmark->diff as $mark ) {
			if ( $mark !== 'subtree-changed' && $mark !== 'children-changed' ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @param string $mark
	 */
	public static function addDiffMark( DOMNode $node, Env $env, string $mark ): void {
		if ( $mark === 'deleted' || $mark === 'moved' ) {
			self::prependTypedMeta( $node, 'mw:DiffMarker/' . $mark );
		} elseif ( DOMUtils::isText( $node ) || DOMUtils::isComment( $node ) ) {
			if ( $mark !== 'inserted' ) {
				$env->log( 'error', 'BUG! CHANGE-marker for ', $node->nodeType, ' node is: ', $mark );
			}
			self::prependTypedMeta( $node, 'mw:DiffMarker/' . $mark );
		} else {
			self::setDiffMark( $node, $env, $mark );
		}
	}

	/**
	 * Set a diff marker on a node.
	 *
	 * @param DOMNode $node
	 * @param Env $env
	 * @param string $change
	 */
	public static function setDiffMark( DOMNode $node, Env $env, string $change ): void {
		if ( !( $node instanceof DOMElement ) ) {
			return;
		}

		$dpd = self::getDiffMark( $node, $env );
		if ( $dpd ) {
			// Diff is up to date, append this change if it doesn't already exist
			if ( array_search( $change, $dpd->diff, true ) === false ) {
				$dpd->diff[] = $change;
			}
		} else {
			// Was an old diff entry or no diff at all, reset
			$dpd = (object)[ // FIXME object or array?
				// The base page revision this change happened on
				'id' => $env->getPageConfig()->getPageId(),
				'diff' => [ $change ]
			];
		}
		DOMDataUtils::getNodeData( $node )->parsoid_diff = $dpd;
	}

	/**
	 * Insert a meta element with the passed-in typeof attribute before a node.
	 *
	 * @param DOMNode $node
	 * @param string $type
	 * @return DOMElement
	 */
	public static function prependTypedMeta( DOMNode $node, string $type ): DOMElement {
		$meta = $node->ownerDocument->createElement( 'meta' );
		DOMUtils::addTypeOf( $meta, $type );
		$node->parentNode->insertBefore( $meta, $node );
		return $meta;
	}

	/**
	 * @param DOMElement $node
	 * @param array $ignoreableAttribs
	 * @return stdClass
	 */
	private static function arrayToHash( DOMElement $node, array $ignoreableAttribs ): stdClass {
		$h = [];
		$count = 0;
		foreach ( DOMCompat::attributes( $node ) as $a ) {
			if ( !in_array( $a->name, $ignoreableAttribs, true ) ) {
				$count++;
				$h[$a->name] = $a->value;
			}
		}
		// If there's no special attribute handler, we want a straight
		// comparison of these.
		if ( !in_array( 'data-parsoid', $ignoreableAttribs, true ) ) {
			$h['data-parsoid'] = DOMDataUtils::getDataParsoid( $node );
			$count++;
		}
		if ( !in_array( 'data-mw', $ignoreableAttribs, true ) && DOMDataUtils::validDataMw( $node ) ) {
			$h['data-mw'] = DOMDataUtils::getDataMw( $node );
			$count++;
		}
		return (object)[ 'h' => $h, 'count' => $count ];
	}

	/**
	 * Attribute equality test.
	 *
	 * @param DOMElement $nodeA
	 * @param DOMElement $nodeB
	 * @param array $ignoreableAttribs
	 * @param array $specializedAttribHandlers
	 * @return bool
	 */
	public static function attribsEquals(
		DOMElement $nodeA, DOMElement $nodeB, array $ignoreableAttribs, array $specializedAttribHandlers
	): bool {
		if ( !$ignoreableAttribs ) {
			$ignoreableAttribs = [];
		}
		if ( !$specializedAttribHandlers ) {
			$specializedAttribHandlers = [];
		}

		$xA = self::arrayToHash( $nodeA, $ignoreableAttribs );
		$xB = self::arrayToHash( $nodeB, $ignoreableAttribs );

		if ( $xA->count !== $xB->count ) {
			return false;
		}

		$hA = $xA->h;
		$keysA = array_keys( $hA );
		sort( $keysA );
		$hB = $xB->h;
		$keysB = array_keys( $hB );
		sort( $keysB );

		for ( $i = 0; $i < $xA->count; $i++ ) {
			$k = $keysA[$i];
			if ( $k !== $keysB[$i] ) {
				return false;
			}

			$attribEquals = $specializedAttribHandlers[$k] ?? null;
			if ( $attribEquals ) {
				// Use a specialized compare function, if provided
				if ( !$hA[$k] || !$hB[$k] || !$attribEquals( $nodeA, $hA[$k], $nodeB, $hB[$k] ) ) {
					return false;
				}
			} elseif ( $hA[$k] !== $hB[$k] ) {
				return false;
			}
		}

		return true;
	}
}
