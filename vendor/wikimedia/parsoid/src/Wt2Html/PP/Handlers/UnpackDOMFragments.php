<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Handlers;

use DOMElement;
use DOMNode;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMTraverser;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PipelineUtils;
use Wikimedia\Parsoid\Utils\Utils;

class UnpackDOMFragments {
	/**
	 * @param DOMNode $targetNode
	 * @param DOMNode $fragment
	 * @return bool
	 */
	private static function hasBadNesting( DOMNode $targetNode, DOMNode $fragment ): bool {
		// SSS FIXME: This is not entirely correct. This is only
		// looking for nesting of identical tags. But, HTML tree building
		// has lot more restrictions on nesting. It seems the simplest way
		// to get all the rules right is to (serialize + reparse).

		// A-tags cannot ever be nested inside each other at any level.
		// This is the one scenario we definitely have to handle right now.
		// We need a generic robust solution for other nesting scenarios.
		return $targetNode->nodeName === 'a' &&
			DOMUtils::treeHasElement( $fragment, $targetNode->nodeName );
	}

	/**
	 * @param DOMNode $targetNode
	 * @param DOMNode $fragment
	 * @param Env $env
	 */
	private static function fixUpMisnestedTagDSR(
		DOMNode $targetNode, DOMNode $fragment, Env $env
	): void {
		// Currently, this only deals with A-tags
		if ( $targetNode->nodeName !== 'a' ) {
			return;
		}

		// Walk the fragment till you find an 'A' tag and
		// zero out DSR width for all tags from that point on.
		// This also requires adding span wrappers around
		// bare text from that point on.

		// QUICK FIX: Add wrappers unconditionally and strip unneeded ones
		// Since this scenario should be rare in practice, I am going to
		// go with this simple solution.
		PipelineUtils::addSpanWrappers( $fragment->childNodes );

		$resetDSR = false;
		$currOffset = 0;
		$dsrFixer = new DOMTraverser();
		$fixHandler = function ( DOMNode $node ) use ( &$resetDSR, &$currOffset ) {
			if ( $node instanceof DOMElement ) {
				$dp = DOMDataUtils::getDataParsoid( $node );
				if ( $node->nodeName === 'a' ) {
					$resetDSR = true;
				}
				if ( $resetDSR ) {
					if ( isset( $dp->dsr->start ) ) {
						$currOffset = $dp->dsr->end = $dp->dsr->start;
					} else {
						$dp->dsr = new DomSourceRange( $currOffset, $currOffset, null, null );
					}
					$dp->misnested = true;
				} elseif ( !empty( $dp->tmp->wrapper ) ) {
					// Unnecessary wrapper added above -- strip it.
					$next = $node->firstChild ?: $node->nextSibling;
					DOMUtils::migrateChildren( $node, $node->parentNode, $node );
					$node->parentNode->removeChild( $node );
					return $next;
				}
			}
			return true;
		};
		$dsrFixer->addHandler( null, $fixHandler );
		$dsrFixer->traverse( $env, $fragment->firstChild );
		$fixHandler( $fragment );
	}

	/**
	 * @param DOMNode $node
	 * @param int $delta
	 */
	public static function addDeltaToDSR( DOMNode $node, int $delta ): void {
		// Add 'delta' to dsr->start and dsr->end for nodes in the subtree
		// node's dsr has already been updated
		$child = $node->firstChild;
		while ( $child ) {
			if ( $child instanceof DOMElement ) {
				$dp = DOMDataUtils::getDataParsoid( $child );
				if ( !empty( $dp->dsr ) ) {
					// SSS FIXME: We've exploited partial DSR information
					// in propagating DSR values across the DOM.  But, worth
					// revisiting at some point to see if we want to change this
					// so that either both or no value is present to eliminate these
					// kind of checks.
					//
					// Currently, it can happen that one or the other
					// value can be null.  So, we should try to udpate
					// the dsr value in such a scenario.
					if ( is_int( $dp->dsr->start ) ) {
						$dp->dsr->start += $delta;
					}
					if ( is_int( $dp->dsr->end ) ) {
						$dp->dsr->end += $delta;
					}
				}
				self::addDeltaToDSR( $child, $delta );
			}
			$child = $child->nextSibling;
		}
	}

	/**
	 * @param Env $env
	 * @param DOMNode $node
	 * @param array &$aboutIdMap
	 */
	private static function fixAbouts( Env $env, DOMNode $node, array &$aboutIdMap = [] ): void {
		$c = $node->firstChild;
		while ( $c ) {
			if ( $c instanceof DOMElement ) {
				if ( $c->hasAttribute( 'about' ) ) {
					$cAbout = $c->getAttribute( 'about' );
					// Update about
					$newAbout = $aboutIdMap[$cAbout] ?? null;
					if ( !$newAbout ) {
						$newAbout = $env->newAboutId();
						$aboutIdMap[$cAbout] = $newAbout;
					}
					$c->setAttribute( 'about', $newAbout );
				}
				self::fixAbouts( $env, $c, $aboutIdMap );
			}
			$c = $c->nextSibling;
		}
	}

	/**
	 * @param DOMNode $node
	 * @param string $about
	 */
	private static function makeChildrenEncapWrappers(
		DOMNode $node, string $about
	): void {
		PipelineUtils::addSpanWrappers( $node->childNodes );

		$c = $node->firstChild;
		while ( $c ) {
			/**
			 * We just span wrapped the child nodes, so it's safe to assume
			 * they're all DOMElements.
			 *
			 * @var DOMElement $c
			 */
			'@phan-var DOMElement $c';
			// FIXME: This unconditionally sets about on children
			// This is currently safe since all of them are nested
			// inside a transclusion, but do we need future-proofing?
			$c->setAttribute( 'about', $about );
			$c = $c->nextSibling;
		}
	}

	/**
	 * DOMTraverser handler that unpacks DOM fragments which were injected in the
	 * token pipeline.
	 * @param DOMNode $node
	 * @param Env $env
	 * @return bool|DOMNode
	 */
	public static function handler( DOMNode $node, Env $env ) {
		if ( !$node instanceof DOMElement ) {
			return true;
		}

		// sealed fragments shouldn't make it past this point
		if ( !DOMUtils::hasTypeOf( $node, 'mw:DOMFragment' ) ) {
			return true;
		}

		$dp = DOMDataUtils::getDataParsoid( $node );

		// Replace this node and possibly a sibling with node.dp.html
		$fragmentParent = $node->parentNode;
		$dummyNode = $node->ownerDocument->createElement( $fragmentParent->nodeName );

		Assert::invariant( preg_match( '/^mwf/', $dp->html ), '' );
		$nodes = $env->getDOMFragment( $dp->html );

		if ( !empty( $dp->tmp->isHtmlExt ) ) {
			// FIXME: This is a silly workaround for foundationwiki which has the
			// "html" extension tag which lets through arbitrary content and
			// often does so in a way that doesn't consider that we'd like to
			// encapsulate it.  For example, it closes the tag in the middle
			// of style tag content to insert a template and then closes the style
			// tag in another "html" extension tag.  The balance proposal isn't
			// its friend.
			//
			// This works because importNode does attribute error checking, whereas
			// parsing does not.  A better fix would be to use one ownerDocument
			// for the entire parse, so no adoption is needed.  See T179082
			$nodeStrings = array_map( function ( $n ) {
				$str = ContentUtils::ppToXML( $n );
				// Put $n back in canonical form.
				// Since $nodes isn't used beyond this point, we can also
				// set $nodes to null but this is an uncommon use case so
				// simpler to do this here.
				DOMDataUtils::visitAndLoadDataAttribs( $n );
				return $str;
			}, $nodes );
			$html = implode( '', $nodeStrings );
			ContentUtils::ppToDOM( $env, $html, [ 'node' => $dummyNode ] );
		} else {
			array_walk( $nodes, function ( $n ) use ( &$dummyNode ) {
				// Dump $n's node data from the data-bag onto the node attribute
				DOMDataUtils::visitAndStoreDataAttribs( $n );
				$imp = $dummyNode->ownerDocument->importNode( $n, true );
				$dummyNode->appendChild( $imp );
			} );
			DOMDataUtils::visitAndLoadDataAttribs( $dummyNode );
		}

		$contentNode = $dummyNode->firstChild;

		if ( DOMUtils::hasTypeOf( $node, 'mw:Transclusion' ) ) {
			// Ensure our `firstChild` is an element to add annotation.  At present,
			// we're unlikely to end up with translusion annotations on fragments
			// where span wrapping hasn't occurred (ie. link contents, since that's
			// placed on the anchor itself) but in the future, nowiki spans may be
			// omitted or new uses for dom fragments found.  For now, the test case
			// necessitating this is an edgy link-in-link scenario:
			//   [[Test|{{1x|[[Hmm|Something <sup>strange</sup>]]}}]]
			PipelineUtils::addSpanWrappers( $dummyNode->childNodes );
			// Reset `contentNode`, since the `firstChild` may have changed in
			// span wrapping.
			$contentNode = $dummyNode->firstChild;
			DOMUtils::assertElt( $contentNode );
			// Transfer typeof, data-mw, and param info
			// about attributes are transferred below.
			DOMDataUtils::setDataMw( $contentNode, Utils::clone( DOMDataUtils::getDataMw( $node ) ) );
			DOMUtils::addTypeOf( $contentNode, 'mw:Transclusion' );
			DOMDataUtils::getDataParsoid( $contentNode )->pi = $dp->pi ?? null;
		}

		// Update DSR:
		//
		// - Only update DSR for content that came from cache.
		// - For new DOM fragments from this pipeline,
		//   previously-computed DSR is valid.
		// - EXCEPTION: fostered content from tables get their DSR reset
		//   to zero-width.
		// - FIXME: We seem to also be doing this for new extension content,
		//   which is the only place still using `setDSR`.
		//
		// There is currently no DSR for DOMFragments nested inside
		// transclusion / extension content (extension inside template
		// content etc).
		// TODO: Make sure that is the only reason for not having a DSR here.
		$dsr = $dp->dsr ?? null;
		if ( $dsr &&
			!( empty( $dp->tmp->setDSR ) && empty( $dp->tmp->fromCache ) && empty( $dp->fostered ) )
		) {
			DOMUtils::assertElt( $contentNode );
			$cnDP = DOMDataUtils::getDataParsoid( $contentNode );
			if ( DOMUtils::hasTypeOf( $contentNode, 'mw:Transclusion' ) ) {
				// FIXME: An old comment from c28f137 said we just use dsr->start and
				// dsr->end since tag-widths will be incorrect for reuse of template
				// expansions.  The comment was removed in ca9e760.
				$cnDP->dsr = new DomSourceRange( $dsr->start, $dsr->end, null, null );
			} elseif (
				DOMUtils::matchTypeOf( $contentNode, '/^mw:(Nowiki|Extension(\/[^\s]+))$/' ) !== null
			) {
				$cnDP->dsr = $dsr;
			} else { // non-transcluded images
				$cnDP->dsr = new DomSourceRange( $dsr->start, $dsr->end, 2, 2 );
				// Reused image -- update dsr by tsrDelta on all
				// descendents of 'firstChild' which is the <figure> tag
				$tsrDelta = $dp->tmp->tsrDelta ?? 0;
				if ( $tsrDelta ) {
					self::addDeltaToDSR( $contentNode, $tsrDelta );
				}
			}
		}

		if ( !empty( $dp->tmp->fromCache ) ) {
			// Replace old about-id with new about-id that is
			// unique to the global page environment object.
			//
			// <figure>s are reused from cache. Note that figure captions
			// can contain multiple independent transclusions. Each one
			// of those individual transclusions should get a new unique
			// about id. Hence a need for an aboutIdMap and the need to
			// walk the entire tree.
			self::fixAbouts( $env, $dummyNode );
		}

		// If the fragment wrapper has an about id, it came from template
		// annotating (the wrapper was an about sibling) and should be transferred
		// to top-level nodes after span wrapping.  This should happen regardless
		// of whether we're coming `fromCache` or not.
		// FIXME: Presumably we have a nesting issue here if this is a cached
		// transclusion.
		$about = $node->getAttribute( 'about' );
		if ( $about !== '' ) {
			// Span wrapping may not have happened for the transclusion above if
			// the fragment is not the first encapsulation wrapper node.
			PipelineUtils::addSpanWrappers( $dummyNode->childNodes );
			$n = $dummyNode->firstChild;
			while ( $n ) {
				DOMUtils::assertElt( $n );
				$n->setAttribute( 'about', $about );
				$n = $n->nextSibling;
			}
		}

		$nextNode = $node->nextSibling;

		if ( self::hasBadNesting( $fragmentParent, $dummyNode ) ) {
			DOMUtils::assertElt( $fragmentParent );
			/* -----------------------------------------------------------------------
			 * If fragmentParent is an A element and the fragment contains another
			 * A element, we have an invalid nesting of A elements and needs fixing up
			 *
			 * doc1: ... fragmentParent -> [... dummyNode=mw:DOMFragment, ...] ...
			 *
			 * 1. Change doc1:fragmentParent -> [... "#unique-hash-code", ...] by replacing
			 *    node with the "#unique-hash-code" text string
			 *
			 * 2. str = parentHTML.replace(#unique-hash-code, dummyHTML)
			 *    We now have a HTML string with the bad nesting. We will now use the HTML5
			 *    parser to parse this HTML string and give us the fixed up DOM
			 *
			 * 3. ParseHTML(str) to get
			 *    doc2: [BODY -> [[fragmentParent -> [...], nested-A-tag-from-dummyNode, ...]]]
			 *
			 * 4. Replace doc1:fragmentParent with doc2:body.childNodes
			 * ----------------------------------------------------------------------- */
			$timestamp = (string)time();
			$fragmentParent->replaceChild( $node->ownerDocument->createTextNode( $timestamp ), $node );

			// If fragmentParent has an about, it presumably is nested inside a template
			// Post fixup, its children will surface to the encapsulation wrapper level.
			// So, we have to fix them up so they dont break the encapsulation.
			//
			// Ex: {{1x|[http://foo.com This is [[bad]], very bad]}}
			//
			// In this example, the <a> corresponding to Foo is fragmentParent and has an about.
			// dummyNode is the DOM corresponding to "This is [[bad]], very bad". Post-fixup
			// "[[bad]], very bad" are at encapsulation level and need about ids.
			$about = $fragmentParent->getAttribute( 'about' );
			if ( $about !== '' ) {
				self::makeChildrenEncapWrappers( $dummyNode, $about );
			}

			// Set zero-dsr width on all elements that will get split
			// in dummyNode's tree to prevent selser-based corruption
			// on edits to a page that contains badly nested tags.
			self::fixUpMisnestedTagDSR( $fragmentParent, $dummyNode, $env );

			$dummyHTML = ContentUtils::ppToXML( $dummyNode, [
					'innerXML' => true,
					// We just added some span wrappers and we need to keep
					// that tmp info so the unnecessary ones get stripped.
					// Should be fine since tmp was stripped before packing.
					'keepTmp' => true
				]
			);
			$parentHTML = ContentUtils::ppToXML( $fragmentParent );

			$p = $fragmentParent->previousSibling;

			// We rely on HTML5 parser to fixup the bad nesting (see big comment above)
			$newDoc = DOMUtils::parseHTML( str_replace( $timestamp, $dummyHTML, $parentHTML ) );
			$body = DOMCompat::getBody( $newDoc );
			DOMUtils::migrateChildrenBetweenDocs( $body, $fragmentParent->parentNode, $fragmentParent );

			if ( !$p ) {
				$p = $fragmentParent->parentNode->firstChild;
			} else {
				$p = $p->nextSibling;
			}

			while ( $p !== $fragmentParent ) {
				DOMDataUtils::visitAndLoadDataAttribs( $p );
				$p = $p->nextSibling;
			}

			// Set nextNode to the previous-sibling of former fragmentParent (which will get deleted)
			// This will ensure that all nodes will get handled
			$nextNode = $fragmentParent->previousSibling;

			// fragmentParent itself is useless now
			$fragmentParent->parentNode->removeChild( $fragmentParent );
		} else {
			// Move the content nodes over and delete the placeholder node
			DOMUtils::migrateChildren( $dummyNode, $fragmentParent, $node );
			$node->parentNode->removeChild( $node );
		}

		return $nextNode;
	}
}
