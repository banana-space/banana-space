<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use DOMDocument;
use DOMElement;
use DOMNode;
use RemexHtml\DOM\DOMBuilder;
use RemexHtml\Tokenizer\Tokenizer;
use RemexHtml\TreeBuilder\Dispatcher;
use RemexHtml\TreeBuilder\TreeBuilder;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Core\ClientError;

/**
 * DOM utilities for querying the DOM. This is largely independent of Parsoid
 * although some Parsoid details (diff markers, TokenUtils, inline content version)
 * have snuck in.
 */
class DOMUtils {
	/**
	 * Parse HTML, return the tree.
	 *
	 * @param string $html
	 * @return DOMDocument
	 */
	public static function parseHTML( string $html ): DOMDocument {
		if ( !preg_match( '/^<(?:!doctype|html|body)/i', $html ) ) {
			// Make sure that we parse fragments in the body. Otherwise comments,
			// link and meta tags end up outside the html element or in the head
			// elements.
			$html = '<body>' . $html;
		}

		$domBuilder = new DOMBuilder( [ 'suppressHtmlNamespace' => true ] );
		$treeBuilder = new TreeBuilder( $domBuilder, [ 'ignoreErrors' => true ] );
		$dispatcher = new Dispatcher( $treeBuilder );
		$tokenizer = new Tokenizer( $dispatcher, $html, [ 'ignoreErrors' => true ] );
		$tokenizer->execute( [] );
		if ( $domBuilder->isCoerced() ) {
			throw new ClientError( 'Encountered a name invalid in XML.' );
		}
		return $domBuilder->getFragment();
	}

	/**
	 * This is a simplified version of the DOMTraverser.
	 * Consider using that before making this more complex.
	 *
	 * FIXME: Move to DOMTraverser OR create a new class?
	 * @param DOMNode $node
	 * @param callable $handler
	 * @param mixed ...$args
	 */
	public static function visitDOM( DOMNode $node, callable $handler, ...$args ): void {
		$handler( $node, ...$args );
		$node = $node->firstChild;
		while ( $node ) {
			$next = $node->nextSibling;
			self::visitDOM( $node, $handler, ...$args );
			$node = $next;
		}
	}

	/**
	 * Move 'from'.childNodes to 'to' adding them before 'beforeNode'
	 * If 'beforeNode' is null, the nodes are appended at the end.
	 * @param DOMNode $from Source node. Children will be removed.
	 * @param DOMNode $to Destination node. Children of $from will be added here
	 * @param DOMNode|null $beforeNode Add the children before this node.
	 */
	public static function migrateChildren(
		DOMNode $from, DOMNode $to, DOMNode $beforeNode = null
	): void {
		while ( $from->firstChild ) {
			$to->insertBefore( $from->firstChild, $beforeNode );
		}
	}

	/**
	 * Copy 'from'.childNodes to 'to' adding them before 'beforeNode'
	 * 'from' and 'to' belong to different documents.
	 *
	 * If 'beforeNode' is null, the nodes are appended at the end.
	 * @param DOMNode $from
	 * @param DOMNode $to
	 * @param DOMNode|null $beforeNode
	 */
	public static function migrateChildrenBetweenDocs(
		DOMNode $from, DOMNode $to, DOMNode $beforeNode = null
	): void {
		$n = $from->firstChild;
		$destDoc = $to->ownerDocument;
		while ( $n ) {
			$to->insertBefore( $destDoc->importNode( $n, true ), $beforeNode );
			$n = $n->nextSibling;
		}
	}

	/**
	 * Check whether this is a DOM element node.
	 * @see http://dom.spec.whatwg.org/#dom-node-nodetype
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isElt( ?DOMNode $node ): bool {
		return $node && $node->nodeType === XML_ELEMENT_NODE;
	}

	// phpcs doesn't like @phan-assert...
	// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

	/**
	 * Assert that this is a DOM element node.
	 * This is primarily to help phan analyze variable types.
	 * @phan-assert DOMElement $node
	 * @param DOMNode|null $node
	 * @return bool Always returns true
	 */
	public static function assertElt( ?DOMNode $node ): bool {
		Assert::invariant( $node instanceof DOMElement, "Expected an element" );
		return true;
	}

	// phpcs:enable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

	/**
	 * Check whether this is a DOM text node.
	 * @see http://dom.spec.whatwg.org/#dom-node-nodetype
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isText( ?DOMNode $node ): bool {
		return $node && $node->nodeType === XML_TEXT_NODE;
	}

	/**
	 * Check whether this is a DOM comment node.
	 * @see http://dom.spec.whatwg.org/#dom-node-nodetype
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isComment( ?DOMNode $node ): bool {
		return $node && $node->nodeType === XML_COMMENT_NODE;
	}

	/**
	 * Determine whether this is a block-level DOM element.
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isBlockNode( ?DOMNode $node ): bool {
		return $node && TokenUtils::isBlockTag( $node->nodeName );
	}

	/**
	 * @param ?DOMNode $node
	 * @return bool
	 */
	public static function isRemexBlockNode( ?DOMNode $node ): bool {
		return self::isElt( $node ) &&
			!isset( WikitextConstants::$HTML['OnlyInlineElements'][$node->nodeName] ) &&
			// From \\MediaWiki\Tidy\RemexCompatMunger::$metadataElements
			// This is a superset but matches `emitsSolTransparentWT` below
			!isset( WikitextConstants::$HTML['MetaTags'][$node->nodeName] );
	}

	/**
	 * Determine whether this is a formatting DOM element.
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isFormattingElt( ?DOMNode $node ): bool {
		return $node && isset( WikitextConstants::$HTML['FormattingTags'][$node->nodeName] );
	}

	/**
	 * Determine whether this is a quote DOM element.
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isQuoteElt( ?DOMNode $node ): bool {
		return $node && isset( WikitextConstants::$WTQuoteTags[$node->nodeName] );
	}

	/**
	 * Determine whether this is the <body> DOM element.
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isBody( ?DOMNode $node ): bool {
		return $node && $node->nodeName === 'body';
	}

	/**
	 * Determine whether this is a removed DOM node but DOMNode object yet
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isRemoved( ?DOMNode $node ): bool {
		return !$node || !isset( $node->nodeType );
	}

	/**
	 * PORT-FIXME: Is this necessary with PHP DOM unlike Domino in JS?
	 *
	 * Test the number of children this node has without using
	 * `Node#childNodes.length`.  This walks the sibling list and so
	 * takes O(`nchildren`) time -- so `nchildren` is expected to be small
	 * (say: 0, 1, or 2).
	 *
	 * Skips all diff markers by default.
	 * @param DOMNode $node
	 * @param int $nchildren
	 * @param bool $countDiffMarkers
	 * @return bool
	 */
	public static function hasNChildren(
		DOMNode $node, int $nchildren, bool $countDiffMarkers = false
	): bool {
		for ( $child = $node->firstChild; $child; $child = $child->nextSibling ) {
			if ( !$countDiffMarkers && self::isDiffMarker( $child ) ) {
				continue;
			}
			if ( $nchildren <= 0 ) {
				return false;
			}
			$nchildren -= 1;
		}
		return ( $nchildren === 0 );
	}

	/**
	 * Build path from a node to its passed-in ancestor.
	 * Doesn't include the ancestor in the returned path.
	 *
	 * @param DOMNode $node
	 * @param DOMNode|null $ancestor
	 *   $ancestor should be an ancestor of $node.
	 *   If null, we'll walk to the document root.
	 * @return DOMNode[]
	 */
	public static function pathToAncestor( DOMNode $node, DOMNode $ancestor = null ): array {
		$path = [];
		while ( $node && $node !== $ancestor ) {
			$path[] = $node;
			$node = $node->parentNode;
		}
		return $path;
	}

	/**
	 * Build path from a node to the root of the document.
	 *
	 * @param DOMNode $node
	 * @return DOMNode[]
	 */
	public static function pathToRoot( DOMNode $node ): array {
		return self::pathToAncestor( $node, null );
	}

	/**
	 * Build path from a node to its passed-in sibling.
	 * Return will not include the passed-in sibling.
	 *
	 * @param DOMNode $node
	 * @param DOMNode $sibling
	 * @param bool $left indicates whether to go backwards, use previousSibling instead of nextSibling.
	 * @return DOMNode[]
	 */
	public static function pathToSibling( DOMNode $node, DOMNode $sibling, bool $left ): array {
		$path = [];
		while ( $node && $node !== $sibling ) {
			$path[] = $node;
			$node = $left ? $node->previousSibling : $node->nextSibling;
		}
		return $path;
	}

	/**
	 * Check whether a node `n1` comes before another node `n2` in
	 * their parent's children list.
	 *
	 * @param DOMNode $n1 The node you expect to come first.
	 * @param DOMNode $n2 Expected later sibling.
	 * @return bool
	 */
	public static function inSiblingOrder( DOMNode $n1, DOMNode $n2 ): bool {
		while ( $n1 && $n1 !== $n2 ) {
			$n1 = $n1->nextSibling;
		}
		return $n1 !== null;
	}

	/**
	 * Check that a node 'n1' is an ancestor of another node 'n2' in
	 * the DOM. Returns true if n1 === n2.
	 * $n1 is the suspected ancestor.
	 * $n2 The suspected descendant.
	 *
	 * @param DOMNode $n1
	 * @param DOMNode $n2
	 * @return bool
	 */
	public static function isAncestorOf( DOMNode $n1, DOMNode $n2 ): bool {
		while ( $n2 && $n2 !== $n1 ) {
			$n2 = $n2->parentNode;
		}
		return $n2 !== null;
	}

	/**
	 * Check whether `node` has an ancestor named `name`.
	 *
	 * @param DOMNode $node
	 * @param string $name
	 * @return bool
	 */
	public static function hasAncestorOfName( DOMNode $node, string $name ): bool {
		while ( $node && $node->nodeName !== $name ) {
			$node = $node->parentNode;
		}
		return $node !== null;
	}

	/**
	 * Determine whether the node matches the given nodeName and attribute value.
	 * Returns true if node name matches and the attribute equals "typeof"
	 *
	 * @param DOMNode $n The node to test
	 * @param string $name The expected nodeName of $n
	 * @param string $typeRe Regular expression matching the expected value of
	 *   `typeof` attribute.
	 * @return ?string The matching `typeof` value, or `null` if there is
	 *   no match.
	 */
	public static function matchNameAndTypeOf( DOMNode $n, string $name, string $typeRe ): ?string {
		return $n->nodeName === $name ? self::matchTypeOf( $n, $typeRe ) : null;
	}

	/**
	 * Determine whether the node matches the given nodeName and typeof
	 * attribute value; the typeof is given as string.
	 *
	 * @param DOMNode $n
	 * @param string $name node name to test for
	 * @param string $type Expected value of "typeof" attribute (literal string)
	 * @return bool True if the node matches.
	 */
	public static function hasNameAndTypeOf( DOMNode $n, string $name, string $type ) {
		return self::matchNameAndTypeOf(
			$n, $name, '/^' . preg_quote( $type, '/' ) . '$/'
		) !== null;
	}

	/**
	 * Determine whether the node matches the given `typeof` attribute value.
	 *
	 * @param DOMNode $n The node to test
	 * @param string $typeRe Regular expression matching the expected value of
	 *   the `typeof` attribute.
	 * @return ?string The matching `typeof` value, or `null` if there is
	 *   no match.
	 */
	public static function matchTypeOf( DOMNode $n, string $typeRe ): ?string {
		if ( !( $n instanceof DOMElement && $n->hasAttribute( 'typeof' ) ) ) {
			return null;
		}
		foreach ( preg_split( '/\s+/', $n->getAttribute( 'typeof' ), -1, PREG_SPLIT_NO_EMPTY ) as $ty ) {
			$count = preg_match( $typeRe, $ty );
			Assert::invariant( $count !== false, "Bad regexp" );
			if ( $count ) {
				return $ty;
			}
		}
		return null;
	}

	/**
	 * Determine whether the node matches the given typeof attribute value.
	 *
	 * @param DOMNode $n
	 * @param string $type Expected value of "typeof" attribute, as a literal
	 *   string.
	 * @return bool True if the node matches.
	 */
	public static function hasTypeOf( DOMNode $n, string $type ) {
		// fast path
		if ( !( $n instanceof DOMElement && $n->hasAttribute( 'typeof' ) ) ) {
			return false;
		}
		if ( $n->getAttribute( 'typeof' ) === $type ) {
			return true;
		}
		// fallback
		return self::matchTypeOf(
			$n, '/^' . preg_quote( $type, '/' ) . '$/D'
		) !== null;
	}

	/**
	 * Add a type to the typeof attribute.  This method should almost always
	 * be used instead of `setAttribute`, to ensure we don't overwrite existing
	 * typeof information.
	 *
	 * @param DOMElement $node node
	 * @param string $type type
	 */
	public static function addTypeOf( DOMElement $node, string $type ): void {
		$typeOf = $node->getAttribute( 'typeof' ) ?? '';
		if ( $typeOf !== '' ) {
			$types = preg_split( '/\s+/', $typeOf );
			if ( !in_array( $type, $types, true ) ) {
				// not in type set yet, so add it.
				$types[] = $type;
			}
			$node->setAttribute( 'typeof', implode( ' ', $types ) );
		} else {
			$node->setAttribute( 'typeof', $type );
		}
	}

	/**
	 * Remove a type from the typeof attribute.
	 *
	 * @param DOMElement $node node
	 * @param string $type type
	 */
	public static function removeTypeOf( DOMElement $node, string $type ): void {
		$typeOf = $node->getAttribute( 'typeof' ) ?? '';
		if ( $typeOf !== '' ) {
			$types = array_filter( preg_split( '/\s+/', $typeOf ), function ( $t ) use ( $type ) {
				return $t !== $type;
			} );
			if ( count( $types ) > 0 ) {
				$node->setAttribute( 'typeof', implode( ' ', $types ) );
			} else {
				$node->removeAttribute( 'typeof' );
			}
		}
	}

	/**
	 * Check whether `node` is in a fosterable position.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isFosterablePosition( ?DOMNode $n ): bool {
		return $n && isset( WikitextConstants::$HTML['FosterablePosition'][$n->parentNode->nodeName] );
	}

	/**
	 * Check whether `node` is a list.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isList( ?DOMNode $n ): bool {
		return $n && isset( WikitextConstants::$HTML['ListTags'][$n->nodeName] );
	}

	/**
	 * Check whether `node` is a list item.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isListItem( ?DOMNode $n ): bool {
		return $n && isset( WikitextConstants::$HTML['ListItemTags'][$n->nodeName] );
	}

	/**
	 * Check whether `node` is a list or list item.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isListOrListItem( ?DOMNode $n ): bool {
		return self::isList( $n ) || self::isListItem( $n );
	}

	/**
	 * Check whether `node` is nestee in a list item.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isNestedInListItem( ?DOMNode $n ): bool {
		$parentNode = $n->parentNode;
		while ( $parentNode ) {
			if ( self::isListItem( $parentNode ) ) {
				return true;
			}
			$parentNode = $parentNode->parentNode;
		}
		return false;
	}

	/**
	 * Check whether `node` is a nested list or a list item.
	 *
	 * @param DOMNode|null $n
	 * @return bool
	 */
	public static function isNestedListOrListItem( ?DOMNode $n ): bool {
		return self::isListOrListItem( $n ) && self::isNestedInListItem( $n );
	}

	/**
	 * Check a node to see whether it's a meta with some typeof.
	 *
	 * @param DOMNode $n
	 * @param string $type
	 * @return bool
	 */
	public static function isMarkerMeta( DOMNode $n, string $type ): bool {
		return self::hasNameAndTypeOf( $n, 'meta', $type );
	}

	// FIXME: This would ideally belong in DiffUtils.js
	// but that would introduce circular dependencies.

	/**
	 * Check a node to see whether it's a diff marker.
	 *
	 * @param ?DOMNode $node
	 * @param string|null $mark
	 * @return bool
	 */
	public static function isDiffMarker( ?DOMNode $node, string $mark = null ): bool {
		if ( !$node ) {
			return false;
		}

		if ( $mark ) {
			return self::isMarkerMeta( $node, 'mw:DiffMarker/' . $mark );
		} else {
			return $node->nodeName === 'meta' &&
				self::matchTypeOf( $node, '#^mw:DiffMarker/#' );
		}
	}

	/**
	 * Check whether a node has any children that are elements.
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function hasElementChild( DOMNode $node ): bool {
		for ( $child = $node->firstChild; $child; $child = $child->nextSibling ) {
			if ( self::isElt( $child ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if a node has a block-level element descendant.
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function hasBlockElementDescendant( DOMNode $node ): bool {
		for ( $child = $node->firstChild; $child; $child = $child->nextSibling ) {
			if ( self::isElt( $child ) &&
				( self::isBlockNode( $child ) || // Is a block-level node
				self::hasBlockElementDescendant( $child ) ) // or has a block-level child or grandchild or..
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is a node representing inter-element whitespace?
	 *
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isIEW( ?DOMNode $node ): bool {
		// ws-only
		return self::isText( $node ) && preg_match( '/^\s*$/D', $node->nodeValue );
	}

	/**
	 * Is a node a document fragment?
	 *
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isDocumentFragment( ?DOMNode $node ): bool {
		return $node && $node->nodeType === XML_DOCUMENT_FRAG_NODE;
	}

	/**
	 * Is a node at the top?
	 *
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function atTheTop( ?DOMNode $node ): bool {
		return self::isDocumentFragment( $node ) || self::isBody( $node );
	}

	/**
	 * Is a node a content node?
	 *
	 * @param DOMNode|null $node
	 * @return bool
	 */
	public static function isContentNode( ?DOMNode $node ): bool {
		return !self::isComment( $node ) &&
			!self::isIEW( $node ) &&
			!self::isDiffMarker( $node );
	}

	/**
	 * Get the first child element or non-IEW text node, ignoring
	 * whitespace-only text nodes, comments, and deleted nodes.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function firstNonSepChild( DOMNode $node ): ?DOMNode {
		$child = $node->firstChild;
		while ( $child && !self::isContentNode( $child ) ) {
			$child = $child->nextSibling;
		}
		return $child;
	}

	/**
	 * Get the last child element or non-IEW text node, ignoring
	 * whitespace-only text nodes, comments, and deleted nodes.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function lastNonSepChild( DOMNode $node ): ?DOMNode {
		$child = $node->lastChild;
		while ( $child && !self::isContentNode( $child ) ) {
			$child = $child->previousSibling;
		}
		return $child;
	}

	/**
	 * Get the previous non seperator sibling node.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function previousNonSepSibling( DOMNode $node ): ?DOMNode {
		$prev = $node->previousSibling;
		while ( $prev && !self::isContentNode( $prev ) ) {
			$prev = $prev->previousSibling;
		}
		return $prev;
	}

	/**
	 * Get the next non seperator sibling node.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function nextNonSepSibling( DOMNode $node ): ?DOMNode {
		$next = $node->nextSibling;
		while ( $next && !self::isContentNode( $next ) ) {
			$next = $next->nextSibling;
		}
		return $next;
	}

	/**
	 * Return the numbler of non deleted child nodes.
	 *
	 * @param DOMNode $node
	 * @return int
	 */
	public static function numNonDeletedChildNodes( DOMNode $node ): int {
		$n = 0;
		$child = $node->firstChild;
		while ( $child ) {
			if ( !self::isDiffMarker( $child ) ) { // FIXME: This is ignoring both inserted/deleted
				$n++;
			}
			$child = $child->nextSibling;
		}
		return $n;
	}

	/**
	 * Get the first non-deleted child of node.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function firstNonDeletedChild( DOMNode $node ): ?DOMNode {
		$child = $node->firstChild;
		// FIXME: This is ignoring both inserted/deleted
		while ( $child && self::isDiffMarker( $child ) ) {
			$child = $child->nextSibling;
		}
		return $child;
	}

	/**
	 * Get the last non-deleted child of node.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function lastNonDeletedChild( DOMNode $node ): ?DOMNode {
		$child = $node->lastChild;
		// FIXME: This is ignoring both inserted/deleted
		while ( $child && self::isDiffMarker( $child ) ) {
			$child = $child->previousSibling;
		}
		return $child;
	}

	/**
	 * Get the next non deleted sibling.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function nextNonDeletedSibling( DOMNode $node ): ?DOMNode {
		$node = $node->nextSibling;
		while ( $node && self::isDiffMarker( $node ) ) { // FIXME: This is ignoring both inserted/deleted
			$node = $node->nextSibling;
		}
		return $node;
	}

	/**
	 * Get the previous non deleted sibling.
	 *
	 * @param DOMNode $node
	 * @return DOMNode|null
	 */
	public static function previousNonDeletedSibling( DOMNode $node ): ?DOMNode {
		$node = $node->previousSibling;
		while ( $node && self::isDiffMarker( $node ) ) { // FIXME: This is ignoring both inserted/deleted
			$node = $node->previousSibling;
		}
		return $node;
	}

	/**
	 * Are all children of this node text or comment nodes?
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function allChildrenAreTextOrComments( DOMNode $node ): bool {
		$child = $node->firstChild;
		while ( $child ) {
			if ( !self::isDiffMarker( $child )
				&& !self::isText( $child )
				&& !self::isComment( $child )
			) {
				return false;
			}
			$child = $child->nextSibling;
		}
		return true;
	}

	/**
	 * Does `node` contain nothing or just non-newline whitespace?
	 * `strict` adds the condition that all whitespace is forbidden.
	 *
	 * @param DOMNode $node
	 * @param bool $strict
	 * @return bool
	 */
	public static function nodeEssentiallyEmpty( DOMNode $node, bool $strict = false ): bool {
		$n = $node->firstChild;
		while ( $n ) {
			if ( self::isElt( $n ) && !self::isDiffMarker( $n ) ) {
				return false;
			} elseif ( self::isText( $n ) &&
				( $strict || !preg_match( '/^[ \t]*$/D',  $n->nodeValue ) )
			) {
				return false;
			} elseif ( self::isComment( $n ) ) {
				return false;
			}
			$n = $n->nextSibling;
		}
		return true;
	}

	/**
	 * Check if the dom-subtree rooted at node has an element with tag name 'tagName'
	 * The root node is not checked.
	 *
	 * @param DOMNode $node
	 * @param string $tagName
	 * @return bool
	 */
	public static function treeHasElement( DOMNode $node, string $tagName ): bool {
		$node = $node->firstChild;
		while ( $node ) {
			if ( self::isElt( $node ) ) {
				if ( $node->nodeName === $tagName || self::treeHasElement( $node, $tagName ) ) {
					return true;
				}
			}
			$node = $node->nextSibling;
		}
		return false;
	}

	/**
	 * Is node a table tag (table, tbody, td, tr, etc.)?
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function isTableTag( DOMNode $node ): bool {
		return isset( WikitextConstants::$HTML['TableTags'][$node->nodeName] );
	}

	/**
	 * Returns a media element nested in `node`
	 *
	 * @param DOMElement $node
	 * @return DOMElement|null
	 */
	public static function selectMediaElt( DOMElement $node ): ?DOMElement {
		return DOMCompat::querySelector( $node, 'img, video, audio' );
	}

	/**
	 * Extract http-equiv headers from the HTML, including content-language and
	 * vary headers, if present
	 *
	 * @param DOMDocument $doc
	 * @return array<string,string>
	 */
	public static function findHttpEquivHeaders( DOMDocument $doc ): array {
		$elts = DOMCompat::querySelectorAll( $doc, 'meta[http-equiv][content]' );
		$r = [];
		foreach ( $elts as $el ) {
			$r[strtolower( $el->getAttribute( 'http-equiv' ) )] = $el->getAttribute( 'content' );
		}
		return $r;
	}

	/**
	 * @param DOMDocument $doc
	 * @return string|null
	 */
	public static function extractInlinedContentVersion( DOMDocument $doc ): ?string {
		$el = DOMCompat::querySelector( $doc, 'meta[property="mw:html:version"]' );
		return $el ? $el->getAttribute( 'content' ) : null;
	}

	/**
	 * Add attributes to a node element.
	 *
	 * @param DOMElement $elt element
	 * @param array $attrs attributes
	 */
	public static function addAttributes( DOMElement $elt, array $attrs ): void {
		foreach ( $attrs as $key => $value ) {
			if ( $value !== null ) {
				if ( $key === 'id' ) {
					DOMCompat::setIdAttribute( $elt, $value );
				} else {
					$elt->setAttribute( $key, $value );
				}
			}
		}
	}

}
