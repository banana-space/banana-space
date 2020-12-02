<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use DOMNode;
use DOMText;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\TitleException;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\TT\Sanitizer;

class Headings {
	/**
	 * Generate anchor ids that the PHP parser assigns to headings.
	 * This is to ensure that links that are out there in the wild
	 * continue to be valid links into Parsoid HTML.
	 * @param DOMNode $node
	 * @param Env $env
	 * @return bool
	 */
	public static function genAnchors( DOMNode $node, Env $env ): bool {
		if ( !preg_match( '/^h[1-6]$/D', $node->nodeName ) ) {
			return true;
		}
		'@phan-var DOMElement $node';  /** @var DOMElement $node */

		// Cannot generate an anchor id if the heading already has an id!
		//
		// NOTE: Divergence from PHP parser behavior.
		//
		// The PHP parser generates a <h*><span id="anchor-id-here-">..</span><h*>
		// So, it can preserve the existing id if any. However, in Parsoid, we are
		// generating a <h* id="anchor-id-here"> ..</h*> => we either overwrite or
		// preserve the existing id and use it for TOC, etc. We choose to preserve it.
		if ( $node->hasAttribute( 'id' ) ) {
			DOMDataUtils::getDataParsoid( $node )->reusedId = true;
			return true;
		}

		$anchorText = Sanitizer::normalizeSectionIdWhiteSpace( self::textContentOf( $node ) );
		$anchorText = self::normalizeSectionName( $anchorText, $env );

		// Create an anchor with a sanitized id
		$anchorId = Sanitizer::escapeIdForAttribute( $anchorText );
		$fallbackId = Sanitizer::escapeIdForAttribute( $anchorText, Sanitizer::ID_FALLBACK );
		if ( $anchorId === $fallbackId ) {
			$fallbackId = null; /* not needed */
		}

		// The ids need to be unique, but we'll enforce this in a post-processing
		// step.

		$node->setAttribute( 'id', $anchorId );
		if ( $fallbackId ) {
			$span = $node->ownerDocument->createElement( 'span' );
			$span->setAttribute( 'id', $fallbackId );
			DOMUtils::addTypeOf( $span, 'mw:FallbackId' );
			$nodeDsr = DOMDataUtils::getDataParsoid( $node )->dsr ?? null;
			// Set a zero-width dsr range for the fallback id
			if ( Utils::isValidDSR( $nodeDsr ) ) {
				$offset = $nodeDsr->innerStart();
				DOMDataUtils::getDataParsoid( $span )->dsr = new DomSourceRange( $offset, $offset, null, null );
			}
			$node->insertBefore( $span, $node->firstChild );
		}

		return true;
	}

	/**
	 * Our own version of node.textContent which handles LanguageVariant
	 * markup the same way PHP does (ie, uses the source wikitext), and
	 * handles <style>/<script> tags the same way PHP does (ie, ignores
	 * the contents)
	 * @param DOMNode $node
	 * @return string
	 */
	private static function textContentOf( DOMNode $node ): string {
		$str = '';
		if ( $node->hasChildNodes() ) {
			foreach ( $node->childNodes as $n ) {
				if ( $n instanceof DOMText ) {
					$str .= $n->nodeValue;
				} elseif ( DOMUtils::hasTypeOf( $n, 'mw:LanguageVariant' ) ) {
					// Special case for -{...}-
					$dp = DOMDataUtils::getDataParsoid( $n );
					$str .= $dp->src ?? '';
				} elseif ( $n->nodeName === 'style' || $n->nodeName === 'script' ) {
					/* ignore children */
				} else {
					$str .= self::textContentOf( $n );
				}
			}
		}
		return $str;
	}

	/**
	 * see Parser::normalizeSectionName in Parser.php and T90902
	 * @param string $text
	 * @param Env $env
	 * @return string
	 */
	private static function normalizeSectionName( string $text, Env $env ): string {
		try {
			$title = $env->makeTitleFromURLDecodedStr( "#{$text}" );
			return $title->getFragment();
		} catch ( TitleException $e ) {
			return $text;
		}
	}

	/**
	 * @param array &$seenIds
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function dedupeHeadingIds( array &$seenIds, DOMNode $node ): bool {
		// NOTE: This is not completely compliant with how PHP parser does it.
		// If there is an id in the doc elsewhere, this will assign
		// the heading a suffixed id, whereas the PHP parser processes
		// headings in textual order and can introduce duplicate ids
		// in a document in the process.
		//
		// However, we believe this implemention behavior is more
		// consistent when handling this edge case, and in the common
		// case (where heading ids won't conflict with ids elsewhere),
		// matches PHP parser behavior.
		if ( !$node instanceof DOMElement ) {
			// Not an Element
			return true;
		}

		if ( !$node->hasAttribute( 'id' ) ) {
			return true;
		}
		// FIXME: Must be case-insensitively unique (T12721)
		// ...but note that core parser uses strtolower, which only does A-Z :(
		$key = $node->getAttribute( 'id' );
		$key = preg_replace_callback(
			'/[A-Z]+/',
			function ( $matches ) {
				return strtolower( $matches[0] );
			},
			$key
		);
		if ( empty( $seenIds[$key] ) ) {
			$seenIds[$key] = true;
			return true;
		}
		// Only update headings and legacy links (first children of heading)
		if ( preg_match( '/^h\d$/D', $node->nodeName ) ||
			WTUtils::isFallbackIdSpan( $node )
		) {
			$suffix = 2;
			while ( !empty( $seenIds[$key . '_' . $suffix] ) ) {
				$suffix++;
			}
			$node->setAttribute( 'id', $node->getAttribute( 'id' ) . '_' . $suffix );
			$seenIds[$key . '_' . $suffix] = true;
		}
		return true;
	}
}
