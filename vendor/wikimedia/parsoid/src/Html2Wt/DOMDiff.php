<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt;

use DOMDocument;
use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

/**
 * A DOM diff helper class.
 *
 * Compares two DOMs and annotates a copy of the passed-in DOM with change
 * information for the selective serializer.
 */
class DOMDiff {

	// These attributes are ignored for equality purposes if they are added to a node.
	private const IGNORE_ATTRIBUTES = [
		// SSS: Don't ignore data-parsoid because in VE, sometimes wrappers get
		// moved around without their content which occasionally leads to incorrect
		// DSR being used by selser.  Hard to describe a reduced test case here.
		// Discovered via: /mnt/bugs/2013-05-01T09:43:14.960Z-Reverse_innovation
		// 'data-parsoid',
		'data-parsoid-diff',
		'about',
		DOMDataUtils::DATA_OBJECT_ATTR_NAME,
	];

	/**
	 * @var Env
	 */
	public $env;

	/**
	 * @var array
	 */
	public $specializedAttribHandlers;

	/**
	 * @var DOMDocument
	 */
	private $domA;

	/**
	 * @var DOMDocument
	 */
	private $domB;

	/**
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	private function nextNonTemplateSibling( DOMNode $node ): ?DOMNode {
		if ( WTUtils::isEncapsulationWrapper( $node ) ) {
			return WTUtils::skipOverEncapsulatedContent( $node );
		}
		return $node->nextSibling;
	}

	/**
	 * @param mixed ...$args
	 */
	private function debug( ...$args ): void {
		$this->env->log( 'trace/domdiff', ...$args );
	}

	/**
	 * DOMDiff constructor.
	 * @param Env $env
	 */
	public function __construct( Env $env ) {
		$this->env = $env;
		$this->specializedAttribHandlers = [
			'data-mw' => function ( $nodeA, $dmwA, $nodeB, $dmwB ) {
				return $this->dataMWEquals( $nodeA, $dmwA, $nodeB, $dmwB );
			},
			'data-parsoid' => function ( $nodeA, $dpA, $nodeB, $dpB ) {
				return $dpA == $dpB;
			},
		];
	}

	/**
	 * Diff two HTML documents, and add / update data-parsoid-diff attributes with
	 * change information.
	 *
	 * @param DOMElement $nodeA
	 * @param DOMElement $nodeB
	 * @return array
	 */
	public function diff( DOMElement $nodeA, DOMElement $nodeB ): array {
		$this->domA = $nodeA->ownerDocument;
		$this->domB = $nodeB->ownerDocument;

		$this->debug( function () use( $nodeA, $nodeB ) {
			return "ORIG:\n" .
				DOMCompat::getOuterHTML( $nodeA ) .
				"\nNEW :\n" .
				DOMCompat::getOuterHTML( $nodeB );
		} );

		// The root nodes are equal, call recursive differ
		$foundChange = $this->doDOMDiff( $nodeA, $nodeB );
		return [ 'isEmpty' => !$foundChange ];
	}

	/**
	 * Test if two data-mw objects are identical.
	 * - independent of order of attributes in data-mw
	 * - html attributes are parsed to DOM and recursively compared
	 * - for id attributes, the DOM fragments are fetched and compared.
	 *
	 * @param DOMNode $nodeA
	 * @param stdClass $dmwA
	 * @param DOMNode $nodeB
	 * @param stdClass $dmwB
	 * @return bool
	 */
	public function dataMWEquals(
		DOMNode $nodeA, stdClass $dmwA, DOMNode $nodeB, stdClass $dmwB
	): bool {
		return $this->realDataMWEquals( $nodeA, (array)$dmwA, $nodeB, (array)$dmwB, [
				'isTopLevel' => true,
				'inDmwBody' => false
			]
		);
	}

	/**
	 * According to MediaWiki_DOM_spec, `id` and `html` attributes are acceptable
	 * formats in `data-mw.body` and in those contexts, they reference DOMs and
	 * we are going to treat them as such.
	 *
	 * @param DOMNode $nodeA
	 * @param array $dmwA
	 * @param DOMNode $nodeB
	 * @param array $dmwB
	 * @param array $options
	 * @return bool
	 */
	private function realDataMWEquals(
		DOMNode $nodeA, array $dmwA, DOMNode $nodeB, array $dmwB, array $options
	): bool {
		$keysA = array_keys( $dmwA );
		$keysB = array_keys( $dmwB );

		// Some quick checks
		if ( count( $keysA ) !== count( $keysB ) ) {
			return false;
		} elseif ( count( $keysA ) === 0 ) {
			return true;
		}

		// Sort keys so we can traverse array and compare keys
		sort( $keysA );
		sort( $keysB );
		for ( $i = 0; $i < count( $keysA ); $i++ ) {
			$kA = $keysA[$i];
			$kB = $keysB[$i];

			if ( $kA !== $kB ) {
				return false;
			}

			$vA = $dmwA[$kA];
			$vB = $dmwB[$kA];

			// Deal with null, undefined (and 0, '')
			// since they cannot be inspected
			if ( !$vA || !$vB ) {
				if ( $vA !== $vB ) {
					return false;
				}
			} elseif ( gettype( $vA ) !== gettype( $vB ) ) {
				return false;
			} elseif ( $kA === 'id' && ( $options['inDmwBody'] ?? null ) ) {
				// For <refs> in <references> the element id can refer to the
				// global DOM, not the owner document DOM.
				$htmlA = DOMCompat::getElementById( $nodeA->ownerDocument, $vA ) ?:
					DOMCompat::getElementById( $this->domA, $vA );
				$htmlB = DOMCompat::getElementById( $nodeB->ownerDocument, $vB ) ?:
					DOMCompat::getElementById( $this->domB, $vB );

				if ( $htmlA && $htmlB && !$this->treeEquals( $htmlA, $htmlB, true ) ) {
					return false;
				} elseif ( !$htmlA || !$htmlB ) {
					$type = DOMUtils::matchTypeOf( $nodeA, '#^mw:Extension/#' );
					$extName = $type ? '---' : substr( $type, strlen( 'mw:Extension/' ) );
					// Log error
					if ( !$htmlA ) {
						$this->env->log(
							'error/domdiff/orig/' . $extName,
							'extension src id ' . PHPUtils::jsonEncode( $vA ) . ' points to non-existent element for:',
							DOMUtils::assertElt( $nodeA ) && DOMCompat::getOuterHTML( $nodeA )
						);
					}
					if ( !$htmlB ) {
						$this->env->log(
							'error/domdiff/edited/' . $extName,
							'extension src id ' . PHPUtils::jsonEncode( $vB ) . ' points to non-existent element for:',
							DOMUtils::assertElt( $nodeB ) && DOMCompat::getOuterHTML( $nodeB )
						);
					}

					// Fall back to default comparisons
					if ( $vA !== $vB ) {
						return false;
					}
				}
			} elseif ( $kA === 'html' && ( $options['inDmwBody'] ?? null ) ) {
				// For 'html' attributes, parse string and recursively compare DOM
				if ( !$this->treeEquals(
						ContentUtils::ppToDOM( $this->env, $vA, [ 'markNew' => true ] ),
						ContentUtils::ppToDOM( $this->env, $vB, [ 'markNew' => true ] ),
						true
					)
				) {
					return false;
				}
			} elseif ( is_object( $vA ) || is_array( $vA ) ) {
				// For 'array' and 'object' attributes, recursively apply _dataMWEquals
				$inDmwBody = ( $options['isTopLevel'] ?? null ) && $kA === 'body';
				if ( !$this->realDataMWEquals(
					$nodeA,
					(array)$vA,
					$nodeB,
					(array)$vB,
					[ 'inDmwBody' => $inDmwBody ]
				) ) {
					return false;
				}
			} elseif ( $vA !== $vB ) {
				return false;
			}

			// Phew! survived this key
		}

		// Phew! survived all checks -- identical objects
		return true;
	}

	/**
	 * Test if two DOM nodes are equal.
	 *
	 * @param DOMNode $nodeA
	 * @param DOMNode $nodeB
	 * @param bool $deep
	 * @return bool
	 */
	public function treeEquals( DOMNode $nodeA, DOMNode $nodeB, bool $deep ): bool {
		if ( $nodeA->nodeType !== $nodeB->nodeType ) {
			return false;
		} elseif ( DOMUtils::isText( $nodeA ) ) {
			// In the past we've had bugs where we let non-primitive strings
			// leak into our DOM.  Safety first:
			Assert::invariant( $nodeA->nodeValue === (string)$nodeA->nodeValue, '' );
			Assert::invariant( $nodeB->nodeValue === (string)$nodeB->nodeValue, '' );
			// ok, now do the comparison.
			return $nodeA->nodeValue === $nodeB->nodeValue;
		} elseif ( DOMUtils::isComment( $nodeA ) ) {
			return WTUtils::decodeComment( $nodeA->nodeValue ) ===
				WTUtils::decodeComment( $nodeB->nodeValue );
		} elseif ( DOMUtils::isElt( $nodeA ) ) {
			// Compare node name and attribute length
			if ( $nodeA->nodeName !== $nodeB->nodeName
				|| !$nodeA instanceof DOMElement || !$nodeB instanceof DOMElement
				|| !DiffUtils::attribsEquals(
					$nodeA,
					$nodeB,
					self::IGNORE_ATTRIBUTES,
					$this->specializedAttribHandlers
				)
			) {
				return false;
			}

			// Passed all tests, element node itself is equal.
			if ( $deep ) {
				$childA = null;
				$childB = null;
				// Compare # of children, since that's fast.
				// (Avoid de-optimizing DOM by using node#childNodes)
				for ( $childA = $nodeA->firstChild, $childB = $nodeB->firstChild;
					$childA && $childB;
					$childA = $childA->nextSibling, $childB = $childB->nextSibling
				) {

					/* don't look inside children yet, just look at # of children */
				}

				if ( $childA || $childB ) {
					return false; /* nodes have different # of children */
				}
				// Now actually compare the child subtrees
				for ( $childA = $nodeA->firstChild, $childB = $nodeB->firstChild;
					$childA && $childB;
					$childA = $childA->nextSibling, $childB = $childB->nextSibling
				) {
					if ( !$this->treeEquals( $childA, $childB, $deep ) ) {
						return false;
					}
				}
			}

			// Did not find a diff yet, so the trees must be equal.
			return true;
		}
		PHPUtils::unreachable( 'we shouldn\'t get here' );
		return false;
	}

	/**
	 * Diff two DOM trees by comparing them node-by-node.
	 *
	 * TODO: Implement something more intelligent like
	 * http://gregory.cobena.free.fr/www/Publications/%5BICDE2002%5D%20XyDiff%20-%20published%20version.pdf,
	 * which uses hash signatures of subtrees to efficiently detect moves /
	 * wrapping.
	 *
	 * Adds / updates a data-parsoid-diff structure with change information.
	 *
	 * Returns true if subtree is changed, false otherwise.
	 *
	 * TODO:
	 * Assume typical CSS white-space, so ignore ws diffs in non-pre content.
	 *
	 * @param DOMNode $baseParentNode
	 * @param DOMNode $newParentNode
	 * @return bool
	 */
	public function doDOMDiff( DOMNode $baseParentNode, DOMNode $newParentNode ): bool {
		// Perform a relaxed version of the recursive treeEquals algorithm that
		// allows for some minor differences and tries to produce a sensible diff
		// marking using heuristics like look-ahead on siblings.
		$baseNode = $baseParentNode->firstChild;
		$newNode = $newParentNode->firstChild;
		$lookaheadNode = null;
		$subtreeDiffers = null;
		$foundDiffOverall = false;

		while ( $baseNode && $newNode ) {
			$dontAdvanceNewNode = false;
			$this->debugOut( $baseNode, $newNode );
			// shallow check first
			if ( !$this->treeEquals( $baseNode, $newNode, false ) ) {
				$this->debug( '-- not equal --' );
				$savedNewNode = $newNode;
				$foundDiff = false;

				// Some simplistic look-ahead, currently limited to a single level
				// in the DOM.

				// look-ahead in *new* DOM to detect insertions
				if ( DOMUtils::isContentNode( $baseNode ) ) {
					$this->debug( '--lookahead in new dom--' );
					$lookaheadNode = $newNode->nextSibling;
					while ( $lookaheadNode ) {
						$this->debugOut( $baseNode, $lookaheadNode, 'new' );
						if ( DOMUtils::isContentNode( $lookaheadNode ) &&
							$this->treeEquals( $baseNode, $lookaheadNode, true )
						) {
							// mark skipped-over nodes as inserted
							$markNode = $newNode;
							while ( $markNode !== $lookaheadNode ) {
								$this->debug( '--found diff: inserted--' );
								$this->markNode( $markNode, 'inserted' );
								$markNode = $markNode->nextSibling;
							}
							$foundDiff = true;
							$newNode = $lookaheadNode;
							break;
						}
						$lookaheadNode = self::nextNonTemplateSibling( $lookaheadNode );
					}
				}

				// look-ahead in *base* DOM to detect deletions
				if ( !$foundDiff && DOMUtils::isContentNode( $newNode ) ) {
					$isBlockNode = WTUtils::isBlockNodeWithVisibleWT( $baseNode );
					$this->debug( '--lookahead in old dom--' );
					$lookaheadNode = $baseNode->nextSibling;
					while ( $lookaheadNode ) {
						$this->debugOut( $lookaheadNode, $newNode, 'old' );
						if ( DOMUtils::isContentNode( $lookaheadNode ) &&
							$this->treeEquals( $lookaheadNode, $newNode, true )
						) {
							$this->debug( '--found diff: deleted--' );
							// mark skipped-over nodes as deleted
							$this->markNode( $newNode, 'deleted', $isBlockNode );
							$baseNode = $lookaheadNode;
							$foundDiff = true;
							break;
						} elseif ( !WTUtils::emitsSolTransparentSingleLineWT( $lookaheadNode ) ) {
							// We only care about the deleted node prior to the one that
							// gets a tree match (but, ignore nodes that show up in wikitext
							// but don't affect sol-state or HTML rendering -- note that
							// whitespace is being ignored, but that whitespace occurs
							// between block nodes).
							$isBlockNode = WTUtils::isBlockNodeWithVisibleWT( $lookaheadNode );
						}
						$lookaheadNode = self::nextNonTemplateSibling( $lookaheadNode );
					}
				}

				if ( !$foundDiff ) {
					if ( !( $savedNewNode instanceof DOMElement ) ) {
						$this->debug( '--found diff: modified text/comment--' );
						$this->markNode( $savedNewNode, 'deleted', WTUtils::isBlockNodeWithVisibleWT( $baseNode ) );
					} elseif ( $savedNewNode->nodeName === $baseNode->nodeName &&
						DOMUtils::assertElt( $baseNode ) &&
						( DOMDataUtils::getDataParsoid( $savedNewNode )->stx ?? null ) ===
						( DOMDataUtils::getDataParsoid( $baseNode )->stx ?? null )
					) {
						// Identical wrapper-type, but modified.
						// Mark modified-wrapper, and recurse.
						$this->debug( '--found diff: modified-wrapper--' );
						$this->markNode( $savedNewNode, 'modified-wrapper' );
						if ( !WTUtils::isEncapsulationWrapper( $baseNode ) &&
							!WTUtils::isEncapsulationWrapper( $savedNewNode )
						) {
							// Dont recurse into template-like-content
							$subtreeDiffers = $this->doDOMDiff( $baseNode, $savedNewNode );
							if ( $subtreeDiffers ) {
								$this->debug( '--found diff: subtree-changed--' );
								$this->markNode( $newNode, 'subtree-changed' );
							}
						}
					} else {
						// We now want to compare current newNode with the next baseNode.
						$dontAdvanceNewNode = true;

						// Since we are advancing in an old DOM without advancing
						// in the new DOM, there were deletions. Add a deletion marker
						// since this is important for accurate separator handling in selser.
						$this->markNode( $savedNewNode, 'deleted', WTUtils::isBlockNodeWithVisibleWT( $baseNode ) );
					}
				}

				// Record the fact that direct children changed in the parent node
				$this->debug( '--found diff: children-changed--' );
				$this->markNode( $newParentNode, 'children-changed' );

				$foundDiffOverall = true;
			} elseif ( !WTUtils::isEncapsulationWrapper( $baseNode ) &&
				!WTUtils::isEncapsulationWrapper( $newNode )
			) {
				$this->debug( '--shallow equal: recursing--' );
				// Recursively diff subtrees if not template-like content
				$subtreeDiffers = $this->doDOMDiff( $baseNode, $newNode );
				if ( $subtreeDiffers ) {
					$this->debug( '--found diff: subtree-changed--' );
					$this->markNode( $newNode, 'subtree-changed' );
				}
				$foundDiffOverall = $subtreeDiffers || $foundDiffOverall;
			}

			// And move on to the next pair (skipping over template HTML)
			if ( $baseNode && $newNode ) {
				$baseNode = self::nextNonTemplateSibling( $baseNode );
				if ( !$dontAdvanceNewNode ) {
					$newNode = self::nextNonTemplateSibling( $newNode );
				}
			}
		}

		// mark extra new nodes as inserted
		while ( $newNode ) {
			$this->debug( '--found trailing new node: inserted--' );
			$this->markNode( $newNode, 'inserted' );
			$foundDiffOverall = true;
			$newNode = self::nextNonTemplateSibling( $newNode );
		}

		// If there are extra base nodes, something was deleted. Mark the parent as
		// having lost children for now.
		if ( $baseNode ) {
			$this->debug( '--found trailing base nodes: deleted--' );
			$this->markNode( $newParentNode, 'children-changed' );
			// SSS FIXME: WTS checks for zero children in a few places
			// That code would have to be upgraded if we emit mw:DiffMarker
			// in this scenario. So, bailing out in this one case for now.
			if ( $newParentNode->hasChildNodes() ) {
				$meta = $newParentNode->ownerDocument->createElement( 'meta' );
				DOMUtils::addTypeOf( $meta, 'mw:DiffMarker/deleted' );
				if ( WTUtils::isBlockNodeWithVisibleWT( $baseNode ) ) {
					$meta->setAttribute( 'data-is-block', 'true' );
				}
				$newParentNode->appendChild( $meta );
			}
			$foundDiffOverall = true;
		}

		return $foundDiffOverall;
	}

	/* ***************************************************
	 * Helpers
	 * ***************************************************/

	/**
	 * @param DOMNode $node
	 * @param string $mark
	 * @param bool $blockNodeDeleted
	 */
	private function markNode( DOMNode $node, string $mark, bool $blockNodeDeleted = false ): void {
		$meta = null;
		if ( $mark === 'deleted' ) {
			// insert a meta tag marking the place where content used to be
			$meta = DiffUtils::prependTypedMeta( $node, 'mw:DiffMarker/' . $mark );
		} else {
			if ( $node instanceof DOMElement ) {
				DiffUtils::setDiffMark( $node, $this->env, $mark );
			} elseif ( DOMUtils::isText( $node ) || DOMUtils::isComment( $node ) ) {
				if ( $mark !== 'inserted' ) {
					$this->env->log( 'error/domdiff',
						'BUG! CHANGE-marker for ' . $node->nodeType . ' node is: ' . $mark
					);
				}
				$meta = DiffUtils::prependTypedMeta( $node, 'mw:DiffMarker/' . $mark );
			} elseif ( $node->nodeType !== XML_DOCUMENT_NODE &&
				$node->nodeType !== XML_DOCUMENT_TYPE_NODE
			) {
				$this->env->log( 'error/domdiff', 'Unhandled node type', $node->nodeType, 'in markNode!' );
			}
		}

		if ( $meta && $blockNodeDeleted ) {
			$meta->setAttribute( 'data-is-block', 'true' );
		}

		if ( $mark === 'deleted' || $mark === 'inserted' ) {
			$this->markNode( $node->parentNode, 'children-changed' );
		}
	}

	/**
	 * @param DOMNode $nodeA
	 * @param DOMNode $nodeB
	 * @param string $laPrefix
	 */
	private function debugOut( DOMNode $nodeA, DOMNode $nodeB, string $laPrefix = '' ): void {
		$this->env->log(
			'trace/domdiff',
			'--> A' . $laPrefix . ':' .
				( $nodeA instanceof DOMElement
					? DOMCompat::getOuterHTML( $nodeA )
					: PHPUtils::jsonEncode( $nodeA->nodeValue ) )
		);

		$this->env->log(
			'trace/domdiff',
			'--> B' . $laPrefix . ':' .
				( $nodeB instanceof DOMElement
					? DOMCompat::getOuterHTML( $nodeB )
					: PHPUtils::jsonEncode( $nodeB->nodeValue ) )
		);
	}
}
