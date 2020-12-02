<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * A Parsoid extension module may register handlers for one or more
 * extension tags. The only method which is generally
 * required by all extension tags is `sourceToDom` (but Translate doesn't
 * even implement that).  All other methods have default do-nothing
 * implementations; override them iff you wish to implement those
 * features.  Default implementations consistently return `false`
 * to indicate not-implemented (in some cases `null` would be a
 * valid return value, and in other cases `null` would be a likely
 * "accidental" return value which we'd like to catch and flag).
 */
abstract class ExtensionTagHandler {

	/**
	 * Convert an extension tag's content to DOM
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $src Extension tag content
	 * @param array $extArgs Extension tag arguments
	 *   The extension tag arguments should be treated as opaque objects
	 *   and any necessary inspection should be handled through the API.
	 * @return DOMDocument|false
	 */
	public function sourceToDom( ParsoidExtensionAPI $extApi, string $src, array $extArgs ) {
		return false; /* Use default wrapper */
	}

	/**
	 * Lint handler for this extension.
	 *
	 * If the extension has lints it wants to expose, it should use $extApi
	 * to register those lints. Alternatively, the extension might simply
	 * inspect its DOM and invoke the default lint handler on a DOM tree
	 * that it wants inspected. For example, <ref> nodes often only have
	 * a pointer (the id attribute) to its content, and is lint handler would
	 * look up the DOM tree and invoke the default lint handler on that tree.
	 *
	 * FIXME: There is probably no reason for the lint handler to return anything.
	 * The caller should simply proceed with the next sibling of $rootNode
	 * after the lint handler returns.
	 *
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $rootNode Extension content's root node
	 * @param callable $defaultHandler Default lint handler
	 *    - Default lint handler has signature $defaultHandler( DOMElement $elt ): void
	 * @return DOMNode|null|false Return `false` to indicate that this
	 *   extension has no special lint handler (the default lint handler will
	 *   be used.  Return `null` to indicate linting should proceed with the
	 *   next sibling.  (Deprecated) A `DOMNode` can be returned to indicate
	 *   the point in the tree where linting should resume.
	 */
	public function lintHandler(
		ParsoidExtensionAPI $extApi, DOMElement $rootNode, callable $defaultHandler
	) {
		/* Use default linter */
		return false;
	}

	/**
	 * Serialize a DOM node created by this extension to wikitext.
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $node
	 * @param bool $wrapperUnmodified
	 * @return string|false Return false to use the default serialization.
	 */
	public function domToWikitext(
		ParsoidExtensionAPI $extApi, DOMElement $node, bool $wrapperUnmodified
	) {
		/* Use default serialization */
		return false;
	}

	/**
	 * Some extensions require the ability to modify the argument
	 * dictionary.
	 * @param ParsoidExtensionApi $extApi
	 * @param object $argDict
	 */
	public function modifyArgDict( ParsoidExtensionAPI $extApi, object $argDict ): void {
		/* do not modify the argument dictionary by default */
	}
}
