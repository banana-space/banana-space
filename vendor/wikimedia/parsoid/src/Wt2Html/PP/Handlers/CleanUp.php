<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use DOMNode;
use DOMText;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

class CleanUp {
	/**
	 * @param DOMElement $node
	 * @param Env $env
	 * @return bool|DOMElement
	 */
	public static function stripMarkerMetas( DOMElement $node, Env $env ) {
		$rtTestMode = $env->getSiteConfig()->rtTestMode();

		// Sometimes a non-tpl meta node might get the mw:Transclusion typeof
		// element attached to it. So, check if the node has data-mw,
		// in which case we also have to keep it.
		if (
			(
				!$rtTestMode &&
				DOMUtils::hasTypeOf( $node, 'mw:Placeholder/StrippedTag' )
			) || (
				DOMUtils::matchTypeOf( $node, '#^mw:(StartTag|EndTag|TSRMarker|Transclusion)(/|$)#' ) &&
				!DOMDataUtils::validDataMw( $node )
			)
		) {
			$nextNode = $node->nextSibling;
			$node->parentNode->removeChild( $node );
			// stop the traversal, since this node is no longer in the DOM.
			return $nextNode;
		} else {
			return true;
		}
	}

	/**
	 * @param DOMNode $node
	 * @param Env $env
	 * @param array $options
	 * @param bool $atTopLevel
	 * @param stdClass|null $tplInfo
	 * @return bool|DOMNode
	 */
	public static function handleEmptyElements(
		DOMNode $node, Env $env, array $options, bool $atTopLevel = false, ?stdClass $tplInfo = null
	) {
		if ( !( $node instanceof DOMElement ) ||
			!isset( WikitextConstants::$Output['FlaggedEmptyElts'][$node->nodeName] ) ||
			!DOMUtils::nodeEssentiallyEmpty( $node )
		) {
			return true;
		}
		if ( DOMCompat::hasAttributes( $node ) ) {
			foreach ( DOMCompat::attributes( $node ) as $a ) {
				if ( ( $a->name !== DOMDataUtils::DATA_OBJECT_ATTR_NAME ) &&
					( !$tplInfo || $a->name !== 'about' || !Utils::isParsoidObjectId( $a->value ) )

				) {
					return true;
				}
			}
		}

		/**
		 * The node is known to be empty and a deletion candidate
		 * - If node is part of template content, it can be deleted
		 *   (since we know it has no attributes, it won't be the
		 *   first node that has about, typeof, and other attrs)
		 * - If not, we add the mw-empty-elt class so that wikis
		 *   can decide what to do with them.
		 */
		if ( $tplInfo ) {
			$nextNode = $node->nextSibling;
			$node->parentNode->removeChild( $node );
			return $nextNode;
		} else {
			DOMCompat::getClassList( $node )->add( 'mw-empty-elt' );
			return true;
		}
	}

	/**
	 * FIXME: Worry about "about" siblings
	 *
	 * @param Env $env
	 * @param DOMElement $node
	 * @return bool
	 */
	private static function inNativeContent( Env $env, DOMElement $node ): bool {
		while ( !DOMUtils::atTheTop( $node ) ) {
			if ( WTUtils::getNativeExt( $env, $node ) !== null ) {
				return true;
			}
			$node = $node->parentNode;
		}
		return false;
	}

	/**
	 * Whitespace in this function refers to [ \t] only
	 * @param DOMNode $node
	 */
	private static function trimWhiteSpace( DOMNode $node ): void {
		// Trim leading ws (on the first line)
		for ( $c = $node->firstChild; $c; $c = $next ) {
			$next = $c->nextSibling;
			if ( DOMUtils::isText( $c ) && preg_match( '/^[ \t]*$/D', $c->nodeValue ) ) {
				$node->removeChild( $c );
			} elseif ( !WTUtils::isRenderingTransparentNode( $c ) ) {
				break;
			}
		}

		if ( DOMUtils::isText( $c ) ) {
			$c->nodeValue = preg_replace( '/^[ \t]+/', '', $c->nodeValue, 1 );
		}

		// Trim trailing ws (on the last line)
		for ( $c = $node->lastChild; $c; $c = $prev ) {
			$prev = $c->previousSibling;
			if ( DOMUtils::isText( $c ) && preg_match( '/^[ \t]*$/D', $c->nodeValue ) ) {
				$node->removeChild( $c );
			} elseif ( !WTUtils::isRenderingTransparentNode( $c ) ) {
				break;
			}
		}

		if ( DOMUtils::isText( $c ) ) {
			$c->nodeValue = preg_replace( '/[ \t]+$/D', '', $c->nodeValue, 1 );
		}
	}

	/**
	 * Perform some final cleanup and save data-parsoid attributes on each node.
	 *
	 * @param array $usedIdIndex
	 * @param DOMNode $node
	 * @param Env $env
	 * @param bool $atTopLevel
	 * @param stdClass|null $tplInfo
	 * @return bool|DOMText
	 */
	public static function cleanupAndSaveDataParsoid(
		array $usedIdIndex, DOMNode $node, Env $env,
		bool $atTopLevel = false, ?stdClass $tplInfo = null
	) {
		if ( !( $node instanceof DOMElement ) ) {
			return true;
		}

		$dp = DOMDataUtils::getDataParsoid( $node );
		// $dp will be a DataParsoid object once but currently it is an stdClass
		// with a fake type hint. Unfake it to prevent phan complaining about unset().
		'@phan-var stdClass $dp';

		// Delete from data parsoid, wikitext originating autoInsertedEnd info
		if ( !empty( $dp->autoInsertedEnd ) && !WTUtils::hasLiteralHTMLMarker( $dp ) &&
			isset( WikitextConstants::$WTTagsWithNoClosingTags[$node->nodeName] )
		) {
			unset( $dp->autoInsertedEnd );
		}

		$isFirstEncapsulationWrapperNode = ( $tplInfo->first ?? null ) === $node ||
			// Traversal isn't done with tplInfo for section tags, but we should
			// still clean them up as if they are the head of encapsulation.
			WTUtils::isParsoidSectionTag( $node );

		// Remove dp.src from elements that have valid data-mw and dsr.
		// This should reduce data-parsoid bloat.
		//
		// Presence of data-mw is a proxy for us knowing how to serialize
		// this content from HTML. Token handlers should strip src for
		// content where data-mw isn't necessary and html2wt knows how to
		// handle the HTML markup.
		$validDSR = DOMDataUtils::validDataMw( $node ) && Utils::isValidDSR( $dp->dsr ?? null );
		$isPageProp = $node->nodeName === 'meta' &&
			preg_match( '#^mw:PageProp/(.*)$#D', $node->getAttribute( 'property' ) );
		if ( $validDSR && !$isPageProp ) {
			unset( $dp->src );
		} elseif ( $isFirstEncapsulationWrapperNode && ( !$atTopLevel || empty( $dp->tsr ) ) ) {
			// Transcluded nodes will not have dp.tsr set
			// and don't need dp.src either.
			unset( $dp->src );
		}

		// Remove tsr
		if ( property_exists( $dp, 'tsr' ) ) {
			unset( $dp->tsr );
		}

		// Remove temporary information
		unset( $dp->tmp );
		unset( $dp->extLinkContentOffsets ); // not stored in tmp currently

		// Various places, like ContentUtils::shiftDSR, can set this to `null`
		if ( property_exists( $dp, 'dsr' ) && $dp->dsr === null ) {
			unset( $dp->dsr );
		}

		// Make dsr zero-range for fostered content
		// to prevent selser from duplicating this content
		// outside the table from where this came.
		//
		// But, do not zero it out if the node has template encapsulation
		// information.  That will be disastrous (see T54638, T54488).
		if ( !empty( $dp->fostered ) && !empty( $dp->dsr ) && !$isFirstEncapsulationWrapperNode ) {
			$dp->dsr->start = $dp->dsr->end;
		}

		if ( $atTopLevel ) {
			// Strip nowiki spans from encapsulated content but leave behind
			// wrappers on root nodes since they have valid about ids and we
			// don't want to break the about-chain by stripping the wrapper
			// and associated ids (we cannot add an about id on the nowiki-ed
			// content since that would be a text node).
			if ( $tplInfo && !WTUtils::hasParsoidAboutId( $node ) &&
				 DOMUtils::hasTypeOf( $node, 'mw:Nowiki' )
			) {
				DOMUtils::migrateChildren( $node, $node->parentNode, $node->nextSibling );
				// Replace the span with an empty text node.
				// (better for perf instead of deleting the node)
				$next = $node->ownerDocument->createTextNode( '' );
				$node->parentNode->replaceChild( $next, $node );
				return $next;
			}

			// Trim whitespace from some wikitext markup
			// not involving explicit HTML tags (T157481)
			if ( !WTUtils::hasLiteralHTMLMarker( $dp ) &&
				isset( WikitextConstants::$WikitextTagsWithTrimmableWS[$node->nodeName] )
			) {
				self::trimWhiteSpace( $node );
			}

			$discardDataParsoid = $env->discardDataParsoid;

			// Strip data-parsoid from templated content, where unnecessary.
			if ( $tplInfo &&
				// Always keep info for the first node
				!$isFirstEncapsulationWrapperNode &&
				// We can't remove data-parsoid from inside <references> text,
				// as that's the only HTML representation we have left for it.
				!self::inNativeContent( $env, $node ) &&
				// FIXME: We can't remove dp from nodes with stx information
				// because the serializer uses stx information in some cases to
				// emit the right newline separators.
				//
				// For example, "a\n\nb" and "<p>a</p><p>b/p>" both generate
				// identical html but serialize to different wikitext.
				//
				// This is only needed for the last top-level node .
				( empty( $dp->stx ) || ( $tplInfo->last ?? null ) !== $node )
			) {
				$discardDataParsoid = true;
			}

			DOMDataUtils::storeDataAttribs( $node, [
					'discardDataParsoid' => $discardDataParsoid,
					// Even though we're passing in the `env`, this is the only place
					// we want the storage to happen, so don't refactor this in there.
					'storeInPageBundle' => $env->pageBundle,
					'idIndex' => $usedIdIndex,
					'env' => $env
				]
			);
		} // We only need the env in this case.
		return true;
	}
}
