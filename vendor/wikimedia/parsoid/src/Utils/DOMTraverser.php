<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

/**
 * Class for helping us traverse the DOM.
 *
 * This class currently does a pre-order depth-first traversal.
 * See {@link DOMPostOrder} for post-order traversal.
 */
class DOMTraverser implements Wt2HtmlDOMProcessor {
	/**
	 * List of handlers to call on each node. Each handler is an array with the following fields:
	 * - action: a callable to call
	 * - nodeName: if set, only call it on nodes with this name
	 * @var array<array{action:callable,nodeName:string}>
	 * @see addHandler()
	 */
	private $handlers;

	/**
	 */
	public function __construct() {
		$this->handlers = [];
	}

	/**
	 * Add a handler to the DOM traverser.
	 *
	 * @param string|null $nodeName An optional node name filter
	 * @param callable $action A callback, called on each node we traverse that matches nodeName.
	 *   Will be called with the following parameters:
	 *   - DOMNode $node: the node being processed
	 *   - Env $env: the parser environment
	 *   - array $options: (only passed if optional $passOptions is true)
	 *        a closure of extra information passed to DOMTraverser::traverse
	 *   - bool $atTopLevel: passed through from DOMTraverser::traverse
	 *   - stdClass $tplInfo: Template information. See traverse().
	 *   Return value: DOMNode|null|true.
	 *   - true: proceed normally
	 *   - DOMNode: traversal will continue on the new node (further handlers will not be called
	 *     on the current node); after processing it and its siblings, it will continue with the
	 *     next sibling of the closest ancestor which has one.
	 *   - null: like the DOMNode case, except there is no new node to process before continuing.
	 */
	public function addHandler(
		?string $nodeName, callable $action
	): void {
		$this->handlers[] = [
			'action' => $action,
			'nodeName' => $nodeName,
		];
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @param array $options
	 * @param bool $atTopLevel
	 * @param stdClass|null $tplInfo
	 * @return bool|mixed
	 */
	private function callHandlers(
		DOMNode $node, Env $env, array $options, bool $atTopLevel, ?stdClass $tplInfo
	) {
		$name = $node->nodeName ?: '';

		foreach ( $this->handlers as $handler ) {
			if ( $handler['nodeName'] === null || $handler['nodeName'] === $name ) {
				$result = call_user_func(
					$handler['action'], $node, $env, $options, $atTopLevel, $tplInfo
				);
				if ( $result !== true ) {
					// abort processing for this node
					return $result;
				}
			}
		}

		return true;
	}

	/**
	 * Traverse the DOM and fire the handlers that are registered.
	 *
	 * Handlers can return
	 * - the next node to process: aborts processing for current node (ie. no further handlers are
	 *   called) and continues processing on returned node. Essentially, that node and its siblings
	 *   replace the current node and its siblings for the purposes of the traversal; after they
	 *   are fully processed, the algorithm moves back to the parent of $workNode to look for
	 *   the next sibling.
	 * - `null`: same as above, except it continues from the next sibling of the parent (or if
	 *   that does not exist, the next sibling of the grandparent etc). This is so that returning
	 *   `$workNode->nextSibling` works even when workNode is a last child of its parent.
	 * - `true`: continues regular processing on current node.
	 *
	 * @param Env $env
	 * @param DOMNode $workNode The root node for the traversal.
	 * @param array $options
	 * @param bool $atTopLevel
	 * @param stdClass|null $tplInfo Template information. When set, it must have all of these fields:
	 *   - first: (DOMNode) first sibling
	 *   - last: (DOMNode) last sibling
	 *   - dsr: field from Pasoid ino
	 *   - clear: when set, the template will not be passed along for further processing
	 */
	public function traverse(
		Env $env, DOMNode $workNode,
		array $options = [], bool $atTopLevel = false, ?stdClass $tplInfo = null
	) {
		while ( $workNode !== null ) {
			if ( $workNode instanceof DOMElement ) {
				// Identify the first template/extension node.
				// You'd think the !tplInfo check isn't necessary since
				// we don't have nested transclusions, however, you can
				// get extensions in transclusions.
				if ( !$tplInfo && WTUtils::isFirstEncapsulationWrapperNode( $workNode )
					// Ensure this isn't just a meta marker, since we might
					// not be traversing after encapsulation.  Note that the
					// valid data-mw assertion is the same test as used in
					// cleanup.
					&& ( !WTUtils::isTplMarkerMeta( $workNode ) || DOMDataUtils::validDataMw( $workNode ) )
					// Encapsulation info on sections should not be used to
					// traverse with since it's designed to be dropped and
					// may have expanded ranges.
					&& !WTUtils::isParsoidSectionTag( $workNode )
				) {
					$about = $workNode->getAttribute( 'about' );
					$aboutSiblings = WTUtils::getAboutSiblings( $workNode, $about );
					$tplInfo = (object)[
						'first' => $workNode,
						'last' => end( $aboutSiblings ),
						'clear' => false,
					];
				}
			}

			// Call the handlers on this workNode
			$possibleNext = $this->callHandlers(
				$workNode, $env, $options, $atTopLevel, $tplInfo
			);

			// We may have walked passed the last about sibling or want to
			// ignore the template info in future processing.
			if ( $tplInfo && $tplInfo->clear ) {
				$tplInfo = null;
			}

			if ( $possibleNext === true ) {
				// the 'continue processing' case
				if ( DOMUtils::isElt( $workNode ) && $workNode->hasChildNodes() ) {
					$this->traverse( $env, $workNode->firstChild, $options, $atTopLevel, $tplInfo );
				}
				$possibleNext = $workNode->nextSibling;
			}

			// Clear the template info after reaching the last about sibling.
			if ( $tplInfo && $tplInfo->last === $workNode ) {
				$tplInfo = null;
			}

			$workNode = $possibleNext;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $workNode, array $options = [], bool $atTopLevel = false
	): void {
		$this->traverse( $env, $workNode, $options, $atTopLevel );
	}
}
