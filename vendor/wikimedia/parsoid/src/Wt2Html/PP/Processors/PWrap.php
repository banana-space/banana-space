<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class PWrap implements Wt2HtmlDOMProcessor {
	/**
	 * Flattens an array with other arrays for elements into
	 * an array without nested arrays.
	 *
	 * @param array[] $a
	 * @return array
	 */
	private function flatten( array $a ): array {
		return $a === [] ? [] : array_merge( ...$a );
	}

	/**
	 * This is equivalent to DOMUtils.emitsSolTransparentSingleLineWT except
	 * for the single line constraint.
	 *
	 * @param DOMNode $n
	 * @return bool
	 */
	private function emitsSolTransparentWT( DOMNode $n ): bool {
		return DOMUtils::isText( $n ) && preg_match( '/^\s*$/D', $n->nodeValue ) ||
			DOMUtils::isComment( $n ) ||
			isset( WikitextConstants::$HTML['MetaTags'][$n->nodeName] );
	}

	/**
	 * Can we split the subtree rooted at $n into multiple adjacent
	 * subtrees rooted in a clone of $n where each of those subtrees
	 * get a contiguous subset of $n's children?
	 *
	 * This is probably equivalent to asking if this node supports the
	 * adoption agency algorithm in the HTML5 spec.
	 *
	 * @param DOMNode $n
	 * @return bool
	 */
	private function isSplittableTag( DOMNode $n ): bool {
		// Seems safe to split span, sub, sup, cite tags
		//
		// However, if we want to mimic Parsoid and HTML5 spec
		// precisely, we should only use isFormattingElt(n)
		return DOMUtils::isFormattingElt( $n );
	}

	/**
	 * Is 'n' a block tag, or does the subtree rooted at 'n' have a block tag
	 * in it?
	 *
	 * @param DOMNode $n
	 * @return bool
	 */
	private function hasBlockTag( DOMNode $n ): bool {
		if ( DOMUtils::isRemexBlockNode( $n ) ) {
			return true;
		}
		$c = $n->firstChild;
		while ( $c ) {
			if ( $this->hasBlockTag( $c ) ) {
				return true;
			}
			$c = $c->nextSibling;
		}
		return false;
	}

	/**
	 * Merge a contiguous run of split subtrees that have identical pwrap properties
	 *
	 * @param DOMElement $n
	 * @param array $a
	 * @return array
	 */
	private function mergeRuns( DOMElement $n, array $a ): array {
		$ret = [];
		// This flag should be transferred to the rightmost
		// clone of this node in the loop below.
		$origAIEnd = DOMDataUtils::getDataParsoid( $n )->autoInsertedEnd ?? null;
		$i = -1;
		foreach ( $a as $v ) {
			if ( $i < 0 ) {
				$ret[] = [ 'pwrap' => $v['pwrap'], 'node' => $n ];
				$i++;
			} elseif ( $ret[$i]['pwrap'] === null ) {
				// @phan-suppress-previous-line PhanTypeInvalidDimOffset
				$ret[$i]['pwrap'] = $v['pwrap'];
			} elseif ( $ret[$i]['pwrap'] !== $v['pwrap'] && $v['pwrap'] !== null ) {
				// @phan-suppress-previous-line PhanTypeInvalidDimOffset
				// @phan-suppress-next-line PhanTypeInvalidDimOffset
				DOMDataUtils::getDataParsoid( $ret[$i]['node'] )->autoInsertedEnd = true;
				$cnode = $n->cloneNode();
				$cnode->removeAttribute( DOMDataUtils::DATA_OBJECT_ATTR_NAME );
				$ret[] = [ 'pwrap' => $v['pwrap'], 'node' => $cnode ];
				$i++;
				DOMDataUtils::getDataParsoid( $ret[$i]['node'] )->autoInsertedStart = true;
			}
			$ret[$i]['node']->appendChild( $v['node'] );
		}

		if ( $i >= 0 && $origAIEnd !== null ) {
			DOMDataUtils::getDataParsoid( $ret[$i]['node'] )->autoInsertedEnd = $origAIEnd;
		}
		return $ret;
	}

	/**
	 * Implements the split operation described in the algorithm below.
	 *
	 * @param DOMNode $n
	 * @return array
	 */
	private function split( DOMNode $n ): array {
		if ( $this->emitsSolTransparentWT( $n ) ) {
			// The null stuff here is mainly to support mw:EndTag metas getting in
			// the way of runs and causing unnecessary wrapping.
			return [ [ 'pwrap' => null, 'node' => $n ] ];
		} elseif ( DOMUtils::isText( $n ) ) {
			return [ [ 'pwrap' => true, 'node' => $n ] ];
		} elseif ( !$this->isSplittableTag( $n ) || count( $n->childNodes ) === 0 ) {
			// block tag OR non-splittable inline tag
			return [
				[ 'pwrap' => !$this->hasBlockTag( $n ), 'node' => $n ]
			];
		} else {
			DOMUtils::assertElt( $n );
			// splittable inline tag
			// split for each child and merge runs
			$children = $n->childNodes;
			$splits = [];
			foreach ( $children as $child ) {
				$splits[] = $this->split( $child );
			}
			return $this->mergeRuns( $n, $this->flatten( $splits ) );
		}
	}

	/**
	 * Wrap children of '$root' with paragraph tags while
	 * so that the final output has the following properties:
	 *
	 * 1. A paragraph will have at least one non-whitespace text
	 *    node or an non-block element node in its subtree.
	 *
	 * 2. Two paragraph nodes aren't siblings of each other.
	 *
	 * 3. If a child of $root is not a paragraph node, it is one of:
	 * - a white-space only text node
	 * - a comment node
	 * - a block element
	 * - a splittable inline element which has some block node
	 *   on *all* paths from it to all leaves in its subtree.
	 * - a non-splittable inline element which has some block node
	 *   on *some* path from it to a leaf in its subtree.
	 *
	 * This output is generated with the following algorithm
	 *
	 * 1. Block nodes are skipped over
	 * 2. Non-splittable inline nodes that have a block tag
	 *    in its subtree are skipped over.
	 * 3. A splittable inline node, I, that has at least one block tag
	 *    in its subtree is split into multiple tree such that
	 *    - each new tree is $rooted in I
	 *    - the trees alternate between two kinds
	 *    (a) it has no block node inside
	 *        => pwrap is true
	 *    (b) all paths from I to its leaves have some block node inside
	 *        => pwrap is false
	 * 4. A paragraph tag is wrapped around adjacent runs of comment nodes,
	 *    text nodes, and an inline node that has no block node embedded inside.
	 *    This paragraph tag does not start with a white-space-only text node
	 *    or a comment node. The current algorithm does not ensure that it doesn't
	 *    end with one of those either, but that is a potential future enhancement.
	 *
	 * @param DOMNode $root
	 */
	private function pWrapDOM( DOMNode $root ) {
		$p = null;
		$c = $root->firstChild;
		while ( $c ) {
			$next = $c->nextSibling;
			if ( DOMUtils::isRemexBlockNode( $c ) ) {
				$p = null;
			} else {
				$vs = $this->split( $c );
				foreach ( $vs as $v ) {
					$n = $v['node'];
					if ( $v['pwrap'] === false ) {
						$p = null;
						$root->insertBefore( $n, $next );
					} elseif ( $this->emitsSolTransparentWT( $n ) ) {
						if ( $p ) {
							$p->appendChild( $n );
						} else {
							$root->insertBefore( $n, $next );
						}
					} else {
						if ( !$p ) {
							$p = $root->ownerDocument->createElement( 'p' );
							$root->insertBefore( $p, $next );
						}
						$p->appendChild( $n );
					}
				}
			}
			$c = $next;
		}
	}

	/**
	 * This function walks the DOM tree $rooted at '$root'
	 * and uses pWrapDOM to add appropriate paragraph wrapper
	 * tags around children of nodes with tag name '$tagName'.
	 *
	 * @param DOMElement $root
	 * @param string $tagName
	 */
	private function pWrapInsideTag( DOMElement $root, string $tagName ) {
		$c = $root->firstChild;
		while ( $c ) {
			$next = $c->nextSibling;
			if ( $c->nodeName === $tagName ) {
				$this->pWrapDOM( $c );
			} elseif ( $c instanceof DOMElement ) {
				$this->pWrapInsideTag( $c, $tagName );
			}
			$c = $next;
		}
	}

	/**
	 * Wrap children of <body> as well as children of
	 * <blockquote> found anywhere in the DOM tree.
	 *
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		$this->pWrapDOM( $root );
		$this->pWrapInsideTag( $root, 'blockquote' );
	}
}
