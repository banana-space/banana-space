<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Tokens\EndTagTk;
use Wikimedia\Parsoid\Tokens\KV;
use Wikimedia\Parsoid\Tokens\TagTk;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\WTUtils;

class WTSUtils {
	/**
	 * @param string $sep
	 * @return bool
	 */
	public static function isValidSep( string $sep ): bool {
		/* TODO (Anomie)
		You might be able to simplify the regex a bit using a no-backtracking group:
		'/^(?>\s*<!--.*?-->)*\s*$/s'
		Although I'm not sure that'll actually run faster.*/
		return (bool)preg_match( '/^(\s|<!--([^\-]|-(?!->))*-->)*$/uD', $sep );
	}

	/**
	 * @param DomSourceRange|null $dsr
	 * @return bool
	 */
	public static function hasValidTagWidths( ?DomSourceRange $dsr ): bool {
		return $dsr !== null && $dsr->hasValidTagWidths();
	}

	/**
	 * Get the attributes on a node in an array of KV objects.
	 *
	 * @param DOMElement $node
	 * @return KV[]
	 */
	public static function getAttributeKVArray( DOMElement $node ): array {
		$kvs = [];
		foreach ( DOMCompat::attributes( $node ) as $attrib ) {
			$kvs[] = new KV( $attrib->name, $attrib->value );
		}
		return $kvs;
	}

	/**
	 * Create a `TagTk` corresponding to a DOM node.
	 *
	 * @param DOMElement $node
	 * @return TagTk
	 */
	public static function mkTagTk( DOMElement $node ): TagTk {
		$attribKVs = self::getAttributeKVArray( $node );
		return new TagTk(
			$node->nodeName,
			$attribKVs,
			DOMDataUtils::getDataParsoid( $node )
		);
	}

	/**
	 * Create a `EndTagTk` corresponding to a DOM node.
	 *
	 * @param DOMElement $node
	 * @return EndTagTk
	 */
	public static function mkEndTagTk( DOMElement $node ): EndTagTk {
		$attribKVs = self::getAttributeKVArray( $node );
		return new EndTagTk(
			$node->nodeName,
			$attribKVs,
			DOMDataUtils::getDataParsoid( $node )
		);
	}

	/**
	 * For new elements, attrs are always considered modified.  However, For
	 * old elements, we only consider an attribute modified if we have shadow
	 * info for it and it doesn't match the current value.
	 * Returns array with data:
	 * [
	 * value => mixed,
	 * modified => bool (If the value of the attribute changed since we parsed the wikitext),
	 * fromsrc => bool (Whether we got the value from source-based roundtripping)
	 * ]
	 *
	 * @param DOMElement $node
	 * @param string $name
	 * @param ?string $curVal
	 * @return array
	 */
	public static function getShadowInfo( DOMElement $node, string $name, ?string $curVal ): array {
		$dp = DOMDataUtils::getDataParsoid( $node );

		// Not the case, continue regular round-trip information.
		if ( !isset( $dp->a ) || !array_key_exists( $name, $dp->a ) ) {
			return [
				'value' => $curVal,
				// Mark as modified if a new element
				'modified' => WTUtils::isNewElt( $node ),
				'fromsrc' => false
			];
		} elseif ( $dp->a[$name] !== $curVal ) {
			return [
				'value' => $curVal,
				'modified' => true,
				'fromsrc' => false
			];
		} elseif ( !isset( $dp->sa ) || !array_key_exists( $name, $dp->sa ) ) {
			return [
				'value' => $curVal,
				'modified' => false,
				'fromsrc' => false
			];
		} else {
			return [
				'value' => $dp->sa[$name],
				'modified' => false,
				'fromsrc' => true
			];
		}
	}

	/**
	 * Get shadowed information about an attribute on a node.
	 * Returns array with data:
	 * [
	 * value => mixed,
	 * modified => bool (If the value of the attribute changed since we parsed the wikitext),
	 * fromsrc => bool (Whether we got the value from source-based roundtripping)
	 * ]
	 *
	 * @param DOMElement $node
	 * @param string $name
	 * @return array
	 */
	public static function getAttributeShadowInfo( DOMElement $node, string $name ): array {
		return self::getShadowInfo(
			$node,
			$name,
			$node->hasAttribute( $name ) ? $node->getAttribute( $name ) : null
		);
	}

	/**
	 * @param string $comment
	 * @return string
	 */
	public static function commentWT( string $comment ): string {
		return '<!--' . WTUtils::decodeComment( $comment ) . '-->';
	}

	/**
	 * Emit the start tag source when not round-trip testing, or when the node is
	 * not marked with autoInsertedStart.
	 *
	 * @param string $src
	 * @param DOMElement $node
	 * @param SerializerState $state
	 * @param bool $dontEmit
	 * @return bool
	 */
	public static function emitStartTag(
		string $src, DOMElement $node, SerializerState $state, bool $dontEmit = false
	): bool {
		if ( empty( $state->rtTestMode ) ||
			empty( DOMDataUtils::getDataParsoid( $node )->autoInsertedStart )
		) {
			if ( !$dontEmit ) {
				$state->emitChunk( $src, $node );
			}
			return true;
		} else {
			// drop content
			return false;
		}
	}

	/**
	 * Emit the start tag source when not round-trip testing, or when the node is
	 * not marked with autoInsertedStart.
	 *
	 * @param string $src
	 * @param DOMElement $node
	 * @param SerializerState $state
	 * @param bool $dontEmit
	 * @return bool
	 */
	public static function emitEndTag(
		string $src, DOMElement $node, SerializerState $state, bool $dontEmit = false
	): bool {
		if ( empty( $state->rtTestMode ) ||
			empty( DOMDataUtils::getDataParsoid( $node )->autoInsertedEnd )
		) {
			if ( !$dontEmit ) {
				$state->emitChunk( $src, $node );
			}
			return true;
		} else {
			// drop content
			return false;
		}
	}

	/**
	 * In wikitext, did origNode occur next to a block node which has been
	 * deleted? While looking for next, we look past DOM nodes that are
	 * transparent in rendering. (See emitsSolTransparentSingleLineWT for
	 * which nodes.)
	 *
	 * @param DOMNode|null $origNode
	 * @param bool $before
	 * @return bool
	 */
	public static function nextToDeletedBlockNodeInWT( ?DOMNode $origNode, bool $before ): bool {
		if ( !$origNode || DOMUtils::isBody( $origNode ) ) {
			return false;
		}

		while ( true ) {
			// Find the nearest node that shows up in HTML (ignore nodes that show up
			// in wikitext but don't affect sol-state or HTML rendering -- note that
			// whitespace is being ignored, but that whitespace occurs between block nodes).
			$node = $origNode;
			do {
				$node = $before ? $node->previousSibling : $node->nextSibling;
				if ( DiffUtils::maybeDeletedNode( $node ) ) {
					return DiffUtils::isDeletedBlockNode( $node );
				}
			} while ( $node && WTUtils::emitsSolTransparentSingleLineWT( $node ) );

			if ( $node ) {
				return false;
			} else {
				// Walk up past zero-width wikitext parents
				$node = $origNode->parentNode;
				if ( !WTUtils::isZeroWidthWikitextElt( $node ) ) {
					// If the parent occupies space in wikitext,
					// clearly, we are not next to a deleted block node!
					// We'll eventually hit BODY here and return.
					return false;
				}
				$origNode = $node;
			}
		}
	}

	/**
	 * Check if whitespace preceding this node would NOT trigger an indent-pre.
	 *
	 * @param DOMNode $node
	 * @param DOMNode $sepNode
	 * @return bool
	 */
	public static function precedingSpaceSuppressesIndentPre( DOMNode $node, DOMNode $sepNode ): bool {
		if ( $node !== $sepNode && DOMUtils::isText( $node ) ) {
			// if node is the same as sepNode, then the separator text
			// at the beginning of it has been stripped out already, and
			// we cannot use it to test it for indent-pre safety
			return (bool)preg_match( '/^[ \t]*\n/', $node->nodeValue );
		} elseif ( $node->nodeName === 'br' ) {
			return true;
		} elseif ( WTUtils::isFirstEncapsulationWrapperNode( $node ) ) {
			DOMUtils::assertElt( $node );
			// Dont try any harder than this
			return !$node->hasChildNodes() || DOMCompat::getInnerHTML( $node )[0] === "\n";
		} else {
			return WTUtils::isBlockNodeWithVisibleWT( $node );
		}
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public static function traceNodeName( DOMNode $node ): string {
		switch ( $node->nodeType ) {
			case XML_ELEMENT_NODE:
				return ( DOMUtils::isDiffMarker( $node ) ) ? 'DIFF_MARK' : 'NODE: ' . $node->nodeName;
			case XML_TEXT_NODE:
				return 'TEXT: ' . PHPUtils::jsonEncode( $node->nodeValue );
			case XML_COMMENT_NODE:
				return 'CMT : ' . PHPUtils::jsonEncode( self::commentWT( $node->nodeValue ) );
			default:
				return $node->nodeName;
		}
	}

	/**
	 * In selser mode, check if an unedited node's wikitext from source wikitext
	 * is reusable as is.
	 *
	 * @param Env $env
	 * @param DOMNode $node
	 * @return bool
	 */
	public static function origSrcValidInEditedContext( Env $env, DOMNode $node ): bool {
		$prev = null;

		if ( WTUtils::isRedirectLink( $node ) ) {
			return DOMUtils::isBody( $node->parentNode ) && !$node->previousSibling;
		} elseif ( $node->nodeName === 'th' || $node->nodeName === 'td' ) {
			DOMUtils::assertElt( $node );
			// The wikitext representation for them is dependent
			// on cell position (first cell is always single char).

			// If there is no previous sibling, nothing to worry about.
			$prev = $node->previousSibling;
			if ( !$prev ) {
				return true;
			}

			// If previous sibling is unmodified, nothing to worry about.
			if ( !DOMUtils::isDiffMarker( $prev ) &&
				!DiffUtils::hasInsertedDiffMark( $prev, $env ) &&
				!DiffUtils::directChildrenChanged( $prev, $env )
			) {
				return true;
			}

			// If it didn't have a stx marker that indicated that the cell
			// showed up on the same line via the "||" or "!!" syntax, nothing
			// to worry about.
			return ( DOMDataUtils::getDataParsoid( $node )->stx ?? '' ) !== 'row';
		} elseif ( $node->nodeName === 'tr' && DOMUtils::assertElt( $node ) &&
			empty( DOMDataUtils::getDataParsoid( $node )->startTagSrc )
		) {
			// If this <tr> didn't have a startTagSrc, it would have been
			// the first row of a table in original wikitext. So, it is safe
			// to reuse the original source for the row (without a "|-") as long as
			// it continues to be the first row of the table.  If not, since we need to
			// insert a "|-" to separate it from the newly added row (in an edit),
			// we cannot simply reuse orig. wikitext for this <tr>.
			return !DOMUtils::previousNonSepSibling( $node );
		} elseif ( DOMUtils::isNestedListOrListItem( $node ) ) {
			// If there are no previous siblings, bullets were assigned to
			// containing elements in the ext.core.ListHandler. For example,
			//
			// *** a
			//
			// Will assign bullets as,
			//
			// <ul><li-*>
			// <ul><li-*>
			// <ul><li-*> a</li></ul>
			// </li></ul>
			// </li></ul>
			//
			// If we reuse the src for the inner li with the a, we'd be missing
			// two bullets because the tag handler for lists in the serializer only
			// emits start tag src when it hits a first child that isn't a list
			// element. We need to walk up and get them.
			$prev = $node->previousSibling;
			if ( !$prev ) {
				return false;
			}

			// If a previous sibling was modified, we can't reuse the start dsr.
			while ( $prev ) {
				if ( DOMUtils::isDiffMarker( $prev ) || DiffUtils::hasInsertedDiffMark( $prev, $env ) ) {
					return false;
				}
				$prev = $prev->previousSibling;
			}

			return true;
		} else {
			return true;
		}
	}

	/**
	 * Extracts the media type from attribute string
	 *
	 * @param DOMElement $node
	 * @return array
	 */
	public static function getMediaType( DOMElement $node ): array {
		$mediaType = DOMUtils::matchTypeOf( $node, '#^mw:(Image|Video|Audio)(/|$)#' );
		$parts = explode( '/', $mediaType ?? '' );
		return [
			'rdfaType' => $parts[0] ?? '',
			'format' => $parts[1] ?? '',
		];
	}

	/**
	 * FIXME: This method should probably be moved to DOMDataUtils class since
	 * it is used by both html2wt and wt2html code
	 *
	 * @param stdClass $dataMw
	 * @param string $key
	 * @param bool $keep
	 * @return array|null
	 */
	public static function getAttrFromDataMw(
		stdClass $dataMw, string $key, bool $keep
	): ?array {
		$arr = $dataMw->attribs ?? [];
		$i = false;
		foreach ( $arr as $k => $a ) {
			if ( is_string( $a[0] ) ) {
				$txt = $a[0];
			} elseif ( is_object( $a[0] ) ) {
				$txt = $a[0]->txt ?? null;
			} else {
				PHPUtils::unreachable( 'Control should never get here!' );
				break;
			}
			if ( $txt === $key ) {
				$i = $k;
				break;
			}
		}
		if ( $i === false ) {
			return null;
		}

		$ret = $arr[$i];
		if ( !$keep && !isset( $ret[1]->html ) ) {
			array_splice( $arr, $i, 1 );
			$dataMw->attribs = $arr;
		}
		return $ret;
	}
}
