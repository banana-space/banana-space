<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext;

use DOMNode;
use Wikimedia\Parsoid\Utils\WTUtils as WTU;

/**
 * This class provides helpers needed by extensions.
 * These helpers help with extracting wikitext information from the DOM.
 */
class WTUtils {
	/**
	 * Is the $node from extension content?
	 * @param DOMNode $node
	 * @param string $extType
	 * @return bool
	 */
	public static function fromExtensionContent( DOMNode $node, string $extType ): bool {
		return WTU::fromExtensionContent( $node, $extType );
	}

	/**
	 * Is $node a sealed DOMFragment of a specific extension?
	 * @param DOMNode $node
	 * @param string $name
	 * @return bool
	 */
	public static function isSealedFragmentOfType( DOMNode $node, string $name ): bool {
		return WTU::isSealedFragmentOfType( $node, $name );
	}

}
