<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;

class DedupeStyles {

	/**
	 * @param DOMElement $node
	 * @param Env $env
	 * @return bool|DOMElement
	 */
	public static function dedupe( DOMElement $node, Env $env ) {
		if ( !$node->hasAttribute( 'data-mw-deduplicate' ) ) {
			// Not a templatestyles <style> tag
			return true;
		}

		$key = $node->getAttribute( 'data-mw-deduplicate' );
		if ( !isset( $env->styleTagKeys[$key] ) ) {
			// Not a dupe
			$env->styleTagKeys[$key] = true;
			return true;
		}

		if ( !DOMUtils::isFosterablePosition( $node ) ) {
			// Dupe - replace with a placeholder <link> reference
			$link = $node->ownerDocument->createElement( 'link' );
			$link->setAttribute( 'rel', 'mw-deduplicated-inline-style' );
			$link->setAttribute( 'href', 'mw-data:' . $key );
			$link->setAttribute( 'about', $node->getAttribute( 'about' ) );
			$link->setAttribute( 'typeof', $node->getAttribute( 'typeof' ) );
			DOMDataUtils::setDataParsoid( $link, DOMDataUtils::getDataParsoid( $node ) );
			DOMDataUtils::setDataMw( $link, DOMDataUtils::getDataMw( $node ) );
			$node->parentNode->replaceChild( $link, $node );
			return $link;
		} else {
			$env->log( 'info/wt2html/templatestyle',
				'Duplicate style tag found in fosterable position. ' .
					'Not deduping it, but emptying out the style tag for performance reasons.'
			);
			DOMCompat::setInnerHTML( $node, '' );
			return true;
		}
	}
}
