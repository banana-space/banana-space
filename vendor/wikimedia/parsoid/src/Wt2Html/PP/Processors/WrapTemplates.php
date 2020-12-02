<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Error;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\Frame;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

/**
 * Template encapsulation happens in three steps.
 *
 * 1. findWrappableTemplateRanges
 *
 *    Locate start and end metas. Walk upwards towards the root from both and
 *    find a common ancestor A. The subtree rooted at A is now effectively the
 *    scope of the dom template ouput.
 *
 * 2. findTopLevelNonOverlappingRanges
 *
 *    Mark all nodes in a range and walk up to root from each range start to
 *    determine overlaps, nesting. Merge overlapping and nested ranges to find
 *    the subset of top-level non-overlapping ranges which will be wrapped as
 *    individual units.
 *
 *    range.startElem, range.endElem are the start/end meta tags for a transclusion
 *    range.start, range.end are the start/end DOM nodes after the range is
 *    expanded, merged with other ranges, etc. In the simple cases, they will
 *    be identical to startElem, endElem.
 *
 * 3. encapsulateTemplates
 *
 *    For each non-overlapping range,
 *    - compute a data-mw according to the DOM spec
 *    - replace the start / end meta markers with transclusion type and data-mw
 *      on the first DOM node
 *    - add about ids on all top-level nodes of the range
 *
 * This is a simple high-level overview of the 3 steps to help understand this
 * code.
 *
 * FIXME: At some point, more of the details should be extracted and documented
 * in pseudo-code as an algorithm.
 * @module
 */
class WrapTemplates implements Wt2HtmlDOMProcessor {

	private const MAP_TBODY_TR = [
		'tbody' => true,
		'tr' => true
	];

	/**
	 * @param stdClass $range
	 * @param bool|null $startsWithText
	 * @return bool
	 */
	private static function expandRangeToAvoidSpanWrapping(
		stdClass $range, ?bool $startsWithText = null
	): bool {
		// SSS FIXME: Later on, if safe, we could consider expanding the
		// range unconditionally rather than only if a span is required.

		$mightAddSpan = $startsWithText;
		if ( $startsWithText === null ) {
			$n = $range->start;
			if ( WTUtils::isTplMarkerMeta( $n ) ) {
				$n = $n->nextSibling;
			}
			$mightAddSpan = DOMUtils::isText( $n );
		}

		$expandable = false;
		if ( $mightAddSpan ) {
			// See if we can expand the range to the parent node.
			// Eliminates useless spanning of wikitext of the form: {{1x|foo}}
			// where the the entire template content is contained in a paragraph.
			$contentParent = $range->start->parentNode;
			$expandable = $contentParent->nodeName === 'p' &&
				!WTUtils::isLiteralHTMLNode( $contentParent ) &&
				$contentParent->firstChild === $range->startElem &&
				$contentParent->lastChild === $range->endElem &&
				$contentParent === $range->end->parentNode;

			if ( $expandable ) {
				$range->start = $contentParent;
				$range->end = $contentParent;
			}
		}

		return $expandable;
	}

	/**
	 * @param DOMElement $target
	 * @param DOMElement $source
	 */
	private static function updateDSRForFirstTplNode( DOMElement $target, DOMElement $source ): void {
		$srcDP = DOMDataUtils::getDataParsoid( $source );
		$tgtDP = DOMDataUtils::getDataParsoid( $target );

		// Since TSRs on template content tokens are cleared by the
		// template handler, all computed dsr values for template content
		// is always inferred from top-level content values and is safe.
		// So, do not overwrite a bigger end-dsr value.
		if ( isset( $srcDP->dsr->end ) && isset( $tgtDP->dsr->end ) &&
			$tgtDP->dsr->end > $srcDP->dsr->end
		) {
			$tgtDP->dsr->start = $srcDP->dsr->start ?? null;
		} else {
			$tgtDP->dsr = clone $srcDP->dsr;
			$tgtDP->src = $srcDP->src ?? null;
		}
	}

	/**
	 * @param stdClass $range
	 * @return DomSourceRange|null
	 */
	private static function getRangeEndDSR( stdClass $range ): ?DomSourceRange {
		$endNode = $range->end;
		if ( $endNode instanceof DOMElement ) {
			return DOMDataUtils::getDataParsoid( $endNode )->dsr ?? null;
		} else {
			// In the rare scenario where the last element of a range is not an ELEMENT,
			// extrapolate based on DSR of first leftmost sibling that is an ELEMENT.
			// We don't try any harder than this for now.
			$offset = 0;
			$n = $endNode->previousSibling;
			while ( $n && !( $n instanceof DOMElement ) ) {
				if ( $n instanceof DOMText ) {
					$offset += strlen( $n->nodeValue );
				} else {
					// A comment
					$offset += WTUtils::decodedCommentLength( $n );
				}
				$n = $n->previousSibling;
			}

			$dsr = null;
			if ( $n ) {
				/**
				 * The point of the above loop is to ensure we're working
				 * with a DOMElement if there is an $n.
				 *
				 * @var DOMElement $n
				 */
				'@phan-var DOMElement $n';
				$dsr = DOMDataUtils::getDataParsoid( $n )->dsr ?? null;
			}

			if ( $dsr && is_int( $dsr->end ?? null ) ) {
				$len = $endNode instanceof DOMText
					? strlen( $endNode->nodeValue )
					: WTUtils::decodedCommentLength( $endNode );
				$dsr = new DomSourceRange( $dsr->end + $offset, $dsr->end + $offset + $len, null, null );
			}

			return $dsr;
		}
	}

	/**
	 * Find the common DOM ancestor of two DOM nodes.
	 * @param Frame $frame
	 * @param DOMDocument $doc
	 * @param DOMElement $startElem
	 * @param DOMElement $endMeta
	 * @param DOMElement $endElem
	 * @return object
	 */
	private static function getDOMRange(
		Frame $frame, DOMDocument $doc, DOMElement $startElem, DOMElement $endMeta, DOMElement $endElem
	) {
		$env = $frame->getEnv();
		$range = (object)[
			'startElem' => $startElem,
			'endElem' => $endMeta,
			'id' => Utils::stripParsoidIdPrefix( $startElem->getAttribute( 'about' ) ),
			'startOffset' => DOMDataUtils::getDataParsoid( $startElem )->tsr->start,
			'flipped' => false
		];

		// Find common ancestor of startElem and endElem
		$startAncestors = DOMUtils::pathToRoot( $startElem );
		$elem = $endElem;
		$parentNode = $endElem->parentNode;
		while ( $parentNode && $parentNode->nodeType !== XML_DOCUMENT_NODE ) {
			$i = array_search( $parentNode, $startAncestors, true );
			if ( $i === 0 ) {
				// widen the scope to include the full subtree
				$range->root = $startElem;
				$range->start = $startElem->firstChild;
				$range->end = $startElem->lastChild;
				break;
			} elseif ( $i > 0 ) {
				$range->root = $parentNode;
				$range->start = $startAncestors[$i - 1];
				$range->end = $elem;
				break;
			}
			$elem = $parentNode;
			$parentNode = $elem->parentNode;
		}

		// Detect empty content in unfosterable positions and
		// wrap them in spans.
		if ( $startElem->nodeName === 'meta' &&
			$startElem->nextSibling === $endElem &&
			!DOMUtils::isFosterablePosition( $startElem )
		) {
			$emptySpan = $doc->createElement( 'span' );
			$startElem->parentNode->insertBefore( $emptySpan, $endElem );
		}

		// Handle unwrappable content in fosterable positions
		// and expand template range, if required.
		if ( DOMUtils::isFosterablePosition( $range->start ) &&
			( !DOMUtils::isElt( $range->start ) ||
				// NOTE: These template marker meta tags are translated from comments
				// *after* the DOM has been built which is why they can show up in
				// fosterable positions in the DOM.
				( WTUtils::isTplMarkerMeta( $range->start ) &&
					WTUtils::isTplMarkerMeta( $range->start->nextSibling ) ) ||
				( WTUtils::isTplMarkerMeta( $range->start ) &&
					!DOMUtils::isElt( $range->start->nextSibling ) )
			)
		) {
			$rangeStartParent = $range->start->parentNode;

			// 1. If we are in a table in a foster-element position, then all non-element
			// nodes will be white-space and comments. Skip over all of them and find
			// the first table content node
			$newStart = $range->start;
			while ( $newStart && !$newStart instanceof DOMElement ) {
				$newStart = $newStart->nextSibling;
			}

			// 2. Push leading comments and whitespace into the element node
			// as long as it is a tr/tbody -- pushing whitespace into the
			// other (th/td/caption) can change display semantics.
			if ( $newStart && isset( self::MAP_TBODY_TR[$newStart->nodeName] ) ) {
				/**
				 * The point of the above loop is to ensure we're working
				 * with a DOMElement if there is an $newStart.
				 *
				 * @var DOMElement $newStart
				 */
				'@phan-var DOMElement $newStart';
				$insertPosition = $newStart->firstChild;
				$n = $range->start;
				while ( $n !== $newStart ) {
					$next = $n->nextSibling;
					$newStart->insertBefore( $n, $insertPosition );
					$n = $next;
				}
				$range->start = $newStart;
				// Update dsr to point to original start
				self::updateDSRForFirstTplNode( $range->start, $startElem );
			} else {
				$range->start = $rangeStartParent;
				$range->end = $rangeStartParent;
			}
		}

		// Ensure range.start is an element node since we want to
		// add/update the data-parsoid attribute to it.
		if ( !DOMUtils::isElt( $range->start ) &&
			!self::expandRangeToAvoidSpanWrapping( $range, true )
		) {
			$span = $doc->createElement( 'span' );
			$range->start->parentNode->insertBefore( $span, $range->start );
			$span->appendChild( $range->start );
			self::updateDSRForFirstTplNode( $span, $startElem );
			$range->start = $span;
		}

		if ( $range->start->nodeName === 'table' ) {
			// If we have any fostered content, include it as well.
			for (
				$rangeStartPreviousSibling = $range->start->previousSibling;
				$rangeStartPreviousSibling instanceof DOMElement &&
					!empty( DOMDataUtils::getDataParsoid( $rangeStartPreviousSibling )->fostered );
				$rangeStartPreviousSibling = $range->start->previousSibling
			) {
				$range->start = $rangeStartPreviousSibling;
			}
		}

		$rangeStartNextSibling = $range->start->nextSibling;
		if ( $range->start === $startElem && $rangeStartNextSibling instanceof DOMElement ) {
			// HACK!
			// The strip-double-tds pass has a HACK that requires DSR and src
			// information being set on this element node. So, this HACK here
			// is supporting that HACK there.
			//
			// (The parser test for T52603 will fail without this fix)
			self::updateDSRForFirstTplNode( $rangeStartNextSibling, $startElem );
		}

		// Use the negative test since it doesn't mark the range as flipped
		// if range.start === range.end
		if ( !DOMUtils::inSiblingOrder( $range->start, $range->end ) ) {
			// In foster-parenting situations, the end-meta tag (and hence range.end)
			// can show up before the range.start which would be the table itself.
			// So, we record this info for later analysis.
			$range->flipped = true;
		}

		$env->log(
			'trace/tplwrap/findranges',
			function () use ( &$range ) {
				$msg = '';
				$dp1 = DOMDataUtils::getDataParsoid( $range->start );
				$dp2 = DOMDataUtils::getDataParsoid( $range->end );
				$tmp1 = $dp1->tmp;
				$tmp2 = $dp2->tmp;
				$dp1->tmp = null;
				$dp2->tmp = null;
				$msg .= "\n----------------------------------------------";
				$msg .= "\nFound range : " . $range->id . '; flipped? ' . $range->flipped .
					'; offset: ' . $range->startOffset;
				$msg .= "\nstart-elem : " . DOMCompat::getOuterHTML( $range->startElem ) . '; DP: ' .
					PHPUtils::jsonEncode( DOMDataUtils::getDataParsoid( $range->startElem ) );
				$msg .= "\nend-elem : " . DOMCompat::getOuterHTML( $range->endElem ) . '; DP: ' .
					PHPUtils::jsonEncode( DOMDataUtils::getDataParsoid( $range->endElem ) );
				$msg .= "\nstart : [TAG_ID " . $tmp1->tagId . ']: ' . DOMCompat::getOuterHTML( $range->start ) .
					'; DP: ' . PHPUtils::jsonEncode( $dp1 );
				$msg .= "\nend : [TAG_ID " . $tmp2->tagId . ']: ' . DOMCompat::getOuterHTML( $range->end ) .
					'; DP: ' . PHPUtils::jsonEncode( $dp2 );
				$msg .= "\n----------------------------------------------";
				$dp1->tmp = $tmp1;
				$dp2->tmp = $tmp2;
				return $msg;
			}
		);

		return $range;
	}

	/**
	 * @param DOMElement $meta
	 */
	private static function stripStartMeta( DOMElement $meta ): void {
		if ( $meta->nodeName === 'meta' ) {
			$meta->parentNode->removeChild( $meta );
		} else {
			// Remove mw:* from the typeof.
			$type = $meta->getAttribute( 'typeof' );
			$type = preg_replace( '/(?:^|\s)mw:[^\/]*(\/[^\s]+|(?=$|\s))/D', '', $type );
			$meta->setAttribute( 'typeof', $type );
		}
	}

	/**
	 * @param array $nestingInfo
	 * @param string|null $startId
	 * @return string|null
	 */
	private static function findToplevelEnclosingRange(
		array $nestingInfo, ?string $startId
	): ?string {
		// Walk up the implicit nesting tree to find the
		// top-level range within which rId is nested.
		// No cycles can exist since they have been suppressed.
		$visited = [];
		$rId = $startId;
		while ( isset( $nestingInfo[$rId] ) ) {
			if ( isset( $visited[$rId] ) ) {
				throw new Error( "Found a cycle in tpl-range nesting where there shouldn't have been one." );
			}
			$visited[$rId] = true;
			$rId = $nestingInfo[$rId];
		}
		return $rId;
	}

	/**
	 * @param Frame $frame
	 * @param array &$compoundTpls
	 * @param string $compoundTplId
	 * @param stdClass $tpl
	 * @param stdClass $argInfo
	 */
	private static function recordTemplateInfo(
		Frame $frame, array &$compoundTpls, string $compoundTplId, stdClass $tpl, stdClass $argInfo
	): void {
		if ( !isset( $compoundTpls[$compoundTplId] ) ) {
			$compoundTpls[$compoundTplId] = [];
		}

		// Record template args info along with any intervening wikitext
		// between templates that are part of the same compound structure.
		/** @var array $tplArray */
		$tplArray = &$compoundTpls[$compoundTplId];
		$dp = DOMDataUtils::getDataParsoid( $tpl->startElem );
		$dsr = $dp->dsr;

		if ( count( $tplArray ) > 0 ) {
			$prevTplInfo = PHPUtils::lastItem( $tplArray );
			if ( $prevTplInfo->dsr->end < $dsr->start ) {
				$width = $dsr->start - $prevTplInfo->dsr->end;
				$tplArray[] = (object)[
					'wt' => PHPUtils::safeSubstr( $frame->getSrcText(), $prevTplInfo->dsr->end, $width ),
				];
			}
		}

		if ( !empty( $dp->unwrappedWT ) ) {
			$tplArray[] = (object)[ 'wt' => $dp->unwrappedWT ];
		}

		// Get rid of src-offsets since they aren't needed anymore.
		foreach ( $argInfo->paramInfos as &$pi ) {
			unset( $pi->srcOffsets );
		}
		$tplArray[] = (object)[
			'dsr' => $dsr,
			'args' => $argInfo->dict,
			'paramInfos' => $argInfo->paramInfos
		];
	}

	/**
	 * Nesting cycles with multiple ranges can show up because of foster
	 * parenting scenarios if they are not detected and suppressed.
	 * @param string $start
	 * @param string $end
	 * @param array $nestingInfo
	 * @return bool
	 */
	private static function introducesCycle( string $start, string $end, array $nestingInfo ): bool {
		$visited = [ $start => true ];
		$elt = $nestingInfo[$end] ?? null;
		while ( $elt ) {
			if ( !empty( $visited[$elt] ) ) {
				return true;
			}
			$elt = $nestingInfo[$elt] ?? null;
		}
		return false;
	}

	/**
	 * The `inSiblingOrder` check here is sufficient to determine overlaps
	 * because the algorithm in `findWrappableTemplateRanges` will put the
	 * start/end elements for intersecting ranges on the same plane and prev/
	 * curr are in textual order (which hopefully translates to dom order).
	 *
	 * @param stdClass $prev
	 * @param stdClass $curr
	 * @return bool
	 */
	private static function rangesOverlap( stdClass $prev, stdClass $curr ): bool {
		$prevEnd = ( !$prev->flipped ) ? $prev->end : $prev->start;
		$currStart = ( !$curr->flipped ) ? $curr->start : $curr->end;
		return DOMUtils::inSiblingOrder( $currStart, $prevEnd );
	}

	/**
	 * @param DOMDocument $document
	 * @param Frame $frame
	 * @param DOMNode $docRoot
	 * @param array $tplRanges
	 * @return stdClass [ 'ranges' => $newRanges, 'tplArrays' => $compoundTpls ]
	 */
	public static function findTopLevelNonOverlappingRanges(
		DOMDocument $document, Frame $frame, DOMNode $docRoot, array $tplRanges
	): stdClass {
		$env = $frame->getEnv();
		$numRanges = count( $tplRanges );

		// For each node, assign an attribute that is a record of all
		// tpl ranges it belongs to at the top-level.
		//
		// FIXME: Ideally we would have used a hash-table external to the
		// DOM, but we have no way of computing a hash-code on a dom-node
		// right now.  So, this is the next best solution (=hack) to use
		// node.data as hash-table storage.
		for ( $i = 0;  $i < $numRanges;  $i++ ) {
			$r = $tplRanges[$i];
			$n = !$r->flipped ? $r->start : $r->end;
			$e = !$r->flipped ? $r->end : $r->start;

			while ( $n ) {
				if ( $n instanceof DOMElement ) {
					// Initialize tplRanges, if necessary.
					$dp = DOMDataUtils::getDataParsoid( $n );
					if ( !isset( $dp->tmp->tplRanges ) ) {
						$dp->tmp->tplRanges = [];
					}

					// Record 'r'
					$dp->tmp->tplRanges[$r->id] = $r;

					// Done
					if ( $n === $e ) {
						break;
					}
				}

				$n = $n->nextSibling;
			}
		}

		// In the first pass over `numRanges` below, `subsumedRanges` is used to
		// record purely the nested ranges.  However, in the second pass, we also
		// add the relationships between overlapping ranges so that
		// `findToplevelEnclosingRange` can use that information to add `argInfo`
		// to the right `compoundTpls`.  This scenario can come up when you have
		// three ranges, 1 intersecting with 2 but not 3, and 3 nested in 2.
		$subsumedRanges = [];

		// For each range r:(s, e), walk up from s --> docRoot and if any of
		// these nodes have tpl-ranges (besides r itself) assigned to them,
		// then r is nested in those other templates and can be ignored.
		for ( $k = 0;  $k < $numRanges;  $k++ ) {
			$r = $tplRanges[$k];
			$n = $r->start;

			while ( $n !== $docRoot ) {
				$ranges = DOMDataUtils::getDataParsoid( $n )->tmp->tplRanges ?? null;
				if ( $ranges ) {
					if ( $n !== $r->start ) {
						// console.warn(" -> nested; n_tpls: " + Object.keys(ranges));

						// 'r' is nested for sure
						// Record the outermost range in which 'r' is nested.
						$rangeIds = array_keys( $ranges );
						$findOutermostRange = function ( $previous, $next ) use ( &$ranges ) {
							return ( $ranges[$next]->startOffset < $ranges[$previous]->startOffset )
								? $next
								: $previous;
						};
						$subsumedRanges[$r->id] =
							(string)array_reduce( $rangeIds, $findOutermostRange, $rangeIds[0] );
						break;
					} else {
						// n === r.start
						//
						// We have to make sure this is not an overlap scenario.
						// Find the ranges that r.start and r.end belong to and
						// compute their intersection. If this intersection has
						// another tpl range besides r itself, we have a winner!
						//
						// The code below does the above check efficiently.
						$sTpls = $ranges;
						$eTpls = DOMDataUtils::getDataParsoid( $r->end )->tmp->tplRanges ?? null;
						$sKeys = array_keys( $sTpls );
						$foundNesting = false;

						$sKeysCount = count( $sKeys );
						for ( $j = 0;  $j < $sKeysCount;  $j++ ) {
							// - Don't record nesting cycles.
							// - Record the outermost range in which 'r' is nested in.
							$otherId = (string)$sKeys[$j];
							$other = $sTpls[$otherId];
							if ( $otherId !== $r->id &&
								!empty( $eTpls[$otherId] ) &&
								// When we have identical ranges, pick the range with
								// the larger offset to be subsumed.
								( $r->start !== $other->start ||
									$r->end !== $other->end ||
									$other->startOffset < $r->startOffset
								) &&
								!self::introducesCycle( $r->id, $otherId, $subsumedRanges )
							) {
								$foundNesting = true;
								if ( !isset( $subsumedRanges[$r->id] ) ||
									$other->startOffset < $sTpls[$subsumedRanges[$r->id]]->startOffset
								) {
									$subsumedRanges[$r->id] = $otherId;
								}
							}
						}

						if ( $foundNesting ) {
							// 'r' is nested
							// console.warn(" -> nested: sTpls: " + Object.keys(sTpls) +
							// "; eTpls: " + Object.keys(eTpls) +
							// "; set to: " + subsumedRanges.get(r.id));
							break;
						}
					}
				}

				// Move up
				$n = $n->parentNode;
			}
		}

		// Sort by start offset in source wikitext
		usort( $tplRanges, function ( $r1, $r2 ) {
			return $r1->startOffset - $r2->startOffset;
		} );

		// Since the tpl ranges are sorted in textual order (by start offset),
		// it is sufficient to only look at the most recent template to see
		// if the current one overlaps with the previous one.
		//
		// This works because we've already identify nested ranges and can ignore them.

		$newRanges = [];
		$prev = null;
		$compoundTpls = [];

		for ( $l = 0;  $l < $numRanges;  $l++ ) {
			$endTagToRemove = null;
			$startTagToStrip = null;

			$r = $tplRanges[$l];

			// Extract argInfo
			$tmp = DOMDataUtils::getDataParsoid( $r->startElem )->tmp;
			$argInfo = $tmp->tplarginfo ?? null;
			if ( $argInfo ) {
				/** @var stdClass $argInfo */
				$argInfo = json_decode( $argInfo );
			} else {
				// An assertion here is probably an indication that we're
				// mistakenly doing template wrapping in a nested context.
				Assert::invariant( isset( $tmp->fromFoster ), 'Template range without arginfo.' );
			}

			$env->log( 'trace/tplwrap/merge', function () use ( &$DOMDataUtils, &$r ) {
				$msg = '';
				$dp1 = DOMDataUtils::getDataParsoid( $r->start );
				$dp2 = DOMDataUtils::getDataParsoid( $r->end );
				$tmp1 = $dp1->tmp;
				$tmp2 = $dp2->tmp;
				$dp1->tmp = null;
				$dp2->tmp = null;
				$msg .= "\n##############################################";
				$msg .= "\nrange " . $r->id . '; r-start-elem: ' . DOMCompat::getOuterHTML( $r->startElem ) .
					'; DP: ' . PHPUtils::jsonEncode( DOMDataUtils::getDataParsoid( $r->startElem ) );
				$msg .= "\nrange " . $r->id . '; r-end-elem: ' . DOMCompat::getOuterHTML( $r->endElem ) .
					'; DP: ' . PHPUtils::jsonEncode( DOMDataUtils::getDataParsoid( $r->endElem ) );
				$msg .= "\nrange " . $r->id . '; r-start: [TAG_ID ' . $tmp1->tagId . ']: ' .
					DOMCompat::getOuterHTML( $r->start ) . '; DP: ' . PHPUtils::jsonEncode( $dp1 );
				$msg .= "\nrange " . $r->id . '; r-end: [TAG_ID ' . $tmp2->tagId . ']: ' .
					DOMCompat::getOuterHTML( $r->end ) . '; DP: ' . PHPUtils::jsonEncode( $dp2 );
				$msg .= "\n----------------------------------------------";
				$dp1->tmp = $tmp1;
				$dp2->tmp = $tmp2;
				return $msg;
			} );

			$enclosingRangeId = self::findToplevelEnclosingRange(
				$subsumedRanges,
				$subsumedRanges[$r->id] ?? null
			);
			if ( $enclosingRangeId ) {
				$env->log( 'trace/tplwrap/merge', '--nested in ', $enclosingRangeId, '--' );

				// Nested -- ignore r
				$startTagToStrip = $r->startElem;
				$endTagToRemove = $r->endElem;
				if ( $argInfo ) {
					// 'r' is nested in 'enclosingRange' at the top-level
					// So, enclosingRange gets r's argInfo
					self::recordTemplateInfo( $frame, $compoundTpls, $enclosingRangeId, $r, $argInfo );
				}
			} elseif ( $prev && self::rangesOverlap( $prev, $r ) ) {
				// In the common case, in overlapping scenarios, r.start is
				// identical to prev.end. However, in fostered content scenarios,
				// there can true overlap of the ranges.
				$env->log( 'trace/tplwrap/merge', '--overlapped--' );

				// See comment above, where `subsumedRanges` is defined.
				$subsumedRanges[$r->id] = $prev->id;

				// Overlapping ranges.
				// r is the regular kind
				// Merge r with prev

				// Note that if a table comes from a template, a foster box isn't
				// emitted so the enclosure isn't guaranteed.  In pathological
				// cases, like where the table end tag isn't emitted, we can still
				// end up with flipped ranges if the template end marker gets into
				// a fosterable position (which can still happen despite being
				// emitted as a comment).
				Assert::invariant( !$r->flipped,
					'Flipped range should have been enclosed.'
				);

				$startTagToStrip = $r->startElem;
				$endTagToRemove = $prev->endElem;

				$prev->end = $r->end;
				$prev->endElem = $r->endElem;

				// Update compoundTplInfo
				if ( $argInfo ) {
					self::recordTemplateInfo( $frame, $compoundTpls, $prev->id, $r, $argInfo );
				}
			} else {
				$env->log( 'trace/tplwrap/merge', '--normal--' );

				// Default -- no overlap
				// Emit the merged range
				$newRanges[] = $r;
				$prev = $r;

				// Update compoundTpls
				if ( $argInfo ) {
					self::recordTemplateInfo( $frame, $compoundTpls, $r->id, $r, $argInfo );
				}
			}

			if ( $endTagToRemove ) {
				// Remove start and end meta-tags
				// Not necessary to remove the start tag, but good to cleanup
				$endTagToRemove->parentNode->removeChild( $endTagToRemove );
				self::stripStartMeta( $startTagToStrip );
			}
		}

		return (object)[ 'ranges' => $newRanges, 'tplArrays' => $compoundTpls ];
	}

	/**
	 * @param stdClass $range
	 * @return string|null
	 */
	private static function findFirstTemplatedNode( stdClass $range ): ?string {
		$firstNode = $range->start;

		// Skip tpl marker meta
		if ( WTUtils::isTplMarkerMeta( $firstNode ) ) {
			$firstNode = $firstNode->nextSibling;
		}

		// Walk past fostered nodes since they came from within a table
		// Note that this is not foolproof because in some scenarios,
		// fostered content is not marked up. Ex: when a table is templated,
		// and content from the table is fostered.
		$dp = DOMDataUtils::getDataParsoid( $firstNode );
		while ( !empty( $dp->fostered ) ) {
			$firstNode = $firstNode->nextSibling;
			/** @var DOMElement $firstNode */
			DOMUtils::assertElt( $firstNode );
			$dp = DOMDataUtils::getDataParsoid( $firstNode );
		}

		// FIXME: It is harder to use META as a node name since this is a generic
		// placeholder for a whole bunch of things each of which has its own
		// newline constraint requirements. So, for now, I am skipping that
		// can of worms to prevent confusing the serializer with an overloaded
		// tag name.
		if ( $firstNode->nodeName === 'meta' ) {
			return null;
		}

		// FIXME spec-compliant values would be upper-case, this is just a workaround
		// for current PHP DOM implementation and could be removed in the future
		$nodeName = mb_strtoupper( $firstNode->nodeName );

		return !empty( $dp->stx ) ? $nodeName . '_' . $dp->stx : $nodeName;
	}

	/**
	 * @param DOMDocument $doc
	 * @param Frame $frame
	 * @param array $tplRanges
	 * @param array $tplArrays
	 */
	private static function encapsulateTemplates(
		DOMDocument $doc, Frame $frame, array $tplRanges, array $tplArrays
	): void {
		$env = $frame->getEnv();
		$numRanges = count( $tplRanges );
		for ( $i = 0;  $i < $numRanges;  $i++ ) {
			$range = $tplRanges[$i];

			// We should never have flipped overlapping ranges, and indeed that's
			// asserted in `findTopLevelNonOverlappingRanges`.  Flipping results
			// in either completely nested ranges, or non-intersecting ranges.
			//
			// If the table causing the fostering is not transcluded, we emit a
			// foster box and wrap the whole table+fb in metas, producing nested
			// ranges.  For ex,
			//
			// <table>
			// {{1x|<div>}}
			//
			// The tricky part is when the table *is* transcluded, and we omit the
			// foster box.  The common case (for some definition of common) might
			// be like,
			//
			// {{1x|<table>}}
			// {{1x|<div>}}
			//
			// Here, #mwt1 leaves a table open and the end meta from #mwt2 is
			// fostered, since it gets closed into the div.  The range for #mwt1
			// is the entire table, which thankfully contains #mwt2, so we still
			// have the expected entire neseting.  Any tricks to extend the range
			// of #mwt2 beyond the table so that we have an overlapping range will
			// ineviatbly result in the end meta not being fostered, and we avoid
			// this situation altogether.
			//
			// The very edgy case is as follows,
			//
			// {{1x|<table><div>}}</div>
			// {{1x|<div>}}
			//
			// where both end metas are fostered.  Ignoring that we don't even
			// roundtrip the first transclusion properly on its own, here we have
			// a flipped range where, since the end meta for the first range was
			// also fostered, the ranges still don't overlap.

			if ( $range->flipped ) {
				// FIXME: The code below needs to be aware of flipped ranges.
				$env->log( 'warn/template',
					'Encapsulating a flipped range: ' . $range->id
				);
			}

			self::expandRangeToAvoidSpanWrapping( $range );

			$n = $range->start;
			$e = $range->end;
			$startElem = $range->startElem;
			$about = $startElem->getAttribute( 'about' );

			while ( $n ) {
				$next = $n->nextSibling;
				if ( !DOMUtils::isElt( $n ) ) {
					// Don't add span-wrappers in fosterable positions
					//
					// NOTE: there cannot be any non-IEW text in fosterable position
					// since the HTML tree builder would already have fostered it out.
					if ( !DOMUtils::isFosterablePosition( $n ) ) {
						$span = $doc->createElement( 'span' );
						$span->setAttribute( 'about', $about );
						$n->parentNode->replaceChild( $span, $n );
						$span->appendChild( $n );
						$n = $span;
					}
				} else {
					$n->setAttribute( 'about', $about );
				}

				if ( $n === $e ) {
					break;
				}

				$n = $next;
			}

			// Encap. info for the range
			$encapInfo = (object)[
				'valid' => false,
				'target' => $range->start,
				'tplArray' => $tplArrays[$range->id],
				'datamw' => null,
				'dp' => null
			];

			$encapTgt = $encapInfo->target;
			'@phan-var \DOMNode $encapTgt';

			// Skip template-marker meta-tags.
			// Also, skip past comments/text nodes found in fosterable positions
			// which wouldn't have been span-wrapped in the while-loop above.
			while ( WTUtils::isTplMarkerMeta( $encapTgt ) ||
				!( $encapTgt instanceof DOMElement )
			) {
				// Detect unwrappable template and bail out early.
				if ( $encapTgt === $range->end ||
					( !( $encapTgt instanceof DOMElement ) &&
						!DOMUtils::isFosterablePosition( $encapTgt )
					)
				) {
					throw new Error( 'Cannot encapsulate transclusion. Start=' .
						DOMCompat::getOuterHTML( $startElem ) );
				}
				$encapTgt = $encapTgt->nextSibling;
			}

			'@phan-var \DOMElement $encapTgt';
			$encapInfo->target = $encapTgt;
			$encapInfo->dp = DOMDataUtils::getDataParsoid( $encapTgt );

			// Update type-of (always even if tpl-encap below will fail).
			// This ensures that VE will still "edit-protect" this template
			// and not allow its content to be edited directly.
			if ( $startElem !== $encapTgt ) {
				$t1 = $startElem->getAttribute( 'typeof' );
				$t2 = $encapTgt->getAttribute( 'typeof' );
				$encapTgt->setAttribute( 'typeof', $t2 ? $t1 . ' ' . $t2 : $t1 );
			}

			/* ----------------------------------------------------------------
			 * We'll attempt to update dp1.dsr to reflect the entire range of
			 * the template.  This relies on a couple observations:
			 *
			 * 1. In the common case, dp2.dsr->end will be > dp1.dsr->end
			 *    If so, new range = dp1.dsr->start, dp2.dsr->end
			 *
			 * 2. But, foster parenting can complicate this when range.end is a table
			 *    and range.start has been fostered out of the table (range.end).
			 *    But, we need to verify this assumption.
			 *
			 *    2a. If dp2.dsr->start is smaller than dp1.dsr->start, this is a
			 *        confirmed case of range.start being fostered out of range.end.
			 *
			 *    2b. If dp2.dsr->start is unknown, we rely on fostered flag on
			 *        range.start, if any.
			 * ---------------------------------------------------------------- */
			$dp1 = Utils::clone( DOMDataUtils::getDataParsoid( $range->start ) );
			$dp2DSR = self::getRangeEndDSR( $range );

			if ( isset( $dp1->dsr ) ) {
				if ( $dp2DSR ) {
					// Case 1. above
					if ( $dp2DSR->end > $dp1->dsr->end ) {
						$dp1->dsr->end = $dp2DSR->end;
					}

					// Case 2. above
					$endDsr = $dp2DSR->start;
					if ( $range->end->nodeName === 'table' &&
						$endDsr !== null &&
						( $endDsr < $dp1->dsr->start || !empty( $dp1->fostered ) )
					) {
						$dp1->dsr->start = $endDsr;
					}
				}

				// encapsulation possible only if dp1.dsr is valid
				$encapInfo->valid = Utils::isValidDSR( $dp1->dsr ?? null ) &&
					$dp1->dsr->end >= $dp1->dsr->start;
			}

			$tplArray = $encapInfo->tplArray;
			Assert::invariant( (bool)$tplArray, 'No parts for template range!' );
			if ( $encapInfo->valid ) {
				// Find transclusion info from the array (skip past a wikitext element)
				$firstTplInfo = !empty( $tplArray[0]->wt ) ? $tplArray[1] : $tplArray[0];

				// Add any leading wikitext
				if ( $firstTplInfo->dsr->start > $dp1->dsr->start ) {
					// This gap in dsr (between the final encapsulated content, and the
					// content that actually came from a template) is indicative of this
					// being a mixed-template-content-block and/or multi-template-content-block
					// scenario.
					//
					// In this case, record the name of the first node in the encapsulated
					// content. During html -> wt serialization, newline constraints for
					// this entire block has to be determined relative to this node.
					$ftn = self::findFirstTemplatedNode( $range );
					if ( $ftn !== null ) {
						$encapInfo->dp->firstWikitextNode = $ftn;
					}
					$width = $firstTplInfo->dsr->start - $dp1->dsr->start;
					array_unshift(
						$tplArray,
						(object)[ 'wt' => PHPUtils::safeSubstr( $frame->getSrcText(), $dp1->dsr->start, $width ) ]
					);
				}

				// Add any trailing wikitext
				$lastTplInfo = PHPUtils::lastItem( $tplArray );
				if ( $lastTplInfo->dsr->end < $dp1->dsr->end ) {
					$width = $dp1->dsr->end - $lastTplInfo->dsr->end;
					$tplArray[] = (object)[
						'wt' => PHPUtils::safeSubstr( $frame->getSrcText(), $lastTplInfo->dsr->end, $width ),
					];
				}

				// Extract the key orders for the templates
				$paramInfoArrays = [];
				foreach ( $tplArray as $a ) {
					// Empty arrays should be added there, skip undefined and null values
					if ( ( $a->paramInfos ?? null ) !== null ) {
						$paramInfoArrays[] = $a->paramInfos;
					}
				}

				// Map the array of { dsr: .. , args: .. } objects to just the args property
				$infoIndex = 0;
				$tplArray = array_map(
					function ( $a ) use ( &$infoIndex, $startElem ) {
						if ( !empty( $a->wt ) ) {
							return $a->wt;
						} else {
							// Remember the position of the transclusion relative
							// to other transclusions. Should match the index of
							// the corresponding private metadata in paramInfoArrays
							// above.
							if ( !empty( $a->args ) ) {
								$a->args->i = $infoIndex;
							}
							$infoIndex++;
							return DOMUtils::hasTypeOf( $startElem, 'mw:Param' )
								? (object)[ 'templatearg' => $a->args ?? null ]
								: (object)[ 'template' => $a->args ?? null ];
						}
					},
					$tplArray
				);

				// Set up dsr->start, dsr->end, and data-mw on the target node
				$encapInfo->datamw = (object)[ 'parts' => $tplArray ];
				if ( WTUtils::isGeneratedFigure( $encapTgt ) ) {
					// Preserve attributes for media since those will be used
					// when adding info, which only happens after this pass.
					// FIXME: There's a question here about whether we should
					// be doing this unconditionally, which is T214241
					$oldMw = DOMDataUtils::getDataMw( $encapTgt );
					if ( isset( $oldMw->attribs ) ) {
						$encapInfo->datamw->attribs = $oldMw->attribs;
					}
				}
				DOMDataUtils::setDataMw( $encapTgt, $encapInfo->datamw );
				$encapInfo->dp->pi = $paramInfoArrays;

				// Special case when mixed-attribute-and-content templates are
				// involved. This information is reliable and comes from the
				// AttributeExpander and gets around the problem of unmarked
				// fostered content that findFirstTemplatedNode runs into.
				$firstWikitextNode = DOMDataUtils::getDataParsoid(
					$range->startElem
				)->firstWikitextNode ?? null;
				if ( empty( $encapInfo->dp->firstWikitextNode ) && $firstWikitextNode ) {
					$encapInfo->dp->firstWikitextNode = $firstWikitextNode;
				}
			} else {
				$errors = [ 'Do not have necessary info. to encapsulate Tpl: ' . $i ];
				$errors[] = 'Start Elt : ' . DOMCompat::getOuterHTML( $startElem );
				$errors[] = 'End Elt   : ' . DOMCompat::getOuterHTML( $range->endElem );
				$errors[] = 'Start DSR : ' . PHPUtils::jsonEncode( $dp1->dsr ?? 'no-start-dsr' );
				$errors[] = 'End   DSR : ' . PHPUtils::jsonEncode( $dp2DSR ?? [] );
				$env->log( 'error', implode( "\n", $errors ) );
			}

			// Make DSR range zero-width for fostered templates after
			// setting up data-mw. However, since template encapsulation
			// sometimes captures both fostered content as well as the table
			// from which it was fostered from, in those scenarios, we should
			// leave DSR info untouched.
			//
			// SSS FIXME:
			// 1. Should we remove the fostered flag from the entire
			// encapsulated block if we dont set dsr width range to zero
			// since only part of the block is fostered, not the entire
			// encapsulated block?
			//
			// 2. In both cases, should we mark these uneditable by adding
			// mw:Placeholder to the typeof?
			if ( !empty( $dp1->fostered ) ) {
				$encapInfo->datamw = DOMDataUtils::getDataMw( $encapTgt );
				if ( !$encapInfo->datamw ||
					!$encapInfo->datamw->parts ||
					count( $encapInfo->datamw->parts ) === 1
				) {
					$dp1->dsr->end = $dp1->dsr->start;
				}
			}

			// Update DSR after fostering-related fixes are done.
			if ( $encapInfo->valid ) {
				if ( !$encapInfo->dp ) {
					// This wouldn't have been initialized if tplArray was null
					$encapInfo->dp = DOMDataUtils::getDataParsoid( $encapTgt );
				}
				// encapInfo.dp points to DOMDataUtils.getDataParsoid(encapInfo.target)
				// and all updates below update properties in that object tree.
				if ( empty( $encapInfo->dp->dsr ) ) {
					$encapInfo->dp->dsr = $dp1->dsr;
				} else {
					$encapInfo->dp->dsr->start = $dp1->dsr->start;
					$encapInfo->dp->dsr->end = $dp1->dsr->end;
				}
				$encapInfo->dp->src = $encapInfo->dp->dsr->substr(
					$frame->getSrcText()
				);
			}

			// Remove startElem (=range.startElem) if a meta.  If a meta,
			// it is guaranteed to be a marker meta added to mark the start
			// of the template.
			if ( WTUtils::isTplMarkerMeta( $startElem ) ) {
				$startElem->parentNode->removeChild( $startElem );
			}

			$range->endElem->parentNode->removeChild( $range->endElem );
		}
	}

	/**
	 * Recursive worker.
	 * @private
	 * @param DOMDocument $doc
	 * @param Frame $frame
	 * @param DOMNode $rootNode
	 * @param array &$tpls
	 * @return array
	 */
	private static function findWrappableTemplateRanges(
		DOMDocument $doc, Frame $frame, DOMNode $rootNode, array &$tpls = []
	): array {
		$env = $frame->getEnv();
		$tplRanges = [];
		$elem = $rootNode->firstChild;
		$about = null;
		$aboutRef = null;

		while ( $elem ) {
			// get the next sibling before doing anything since
			// we may delete elem as part of encapsulation
			$nextSibling = $elem->nextSibling;

			if ( $elem instanceof DOMElement ) {
				$metaType = WTUtils::matchTplType( $elem );

				// Ignore templates without tsr.
				//
				// These are definitely nested in other templates / extensions
				// and need not be wrapped themselves since they
				// can never be edited directly.
				//
				// NOTE: We are only testing for tsr presence on the start-elem
				// because wikitext errors can lead to parse failures and no tsr
				// on end-meta-tags.
				//
				// Ex: "<ref>{{1x|bar}}<!--bad-></ref>"
				if ( $metaType !== null &&
					( !empty( DOMDataUtils::getDataParsoid( $elem )->tsr ) ||
						preg_match( '#/End$#D', $metaType )
					)
				) {
					$about = $elem->getAttribute( 'about' );
					$aboutRef = $tpls[$about] ?? null;
					// Is this a start marker?
					if ( !preg_match( '#/End$#D', $metaType ) ) {
						if ( $aboutRef ) {
							$aboutRef->start = $elem;
							// content or end marker existed already
							if ( !empty( $aboutRef->end ) ) {
								// End marker was foster-parented.
								// Found actual start tag.
								$env->log( 'warn/template', 'end marker was foster-parented for', $about );
								$tplRanges[] = self::getDOMRange( $frame, $doc, $elem, $aboutRef->end, $aboutRef->end );
							} else {
								// should not happen!
								PHPUtils::unreachable( "start found after content for $about." );
							}
						} else {
							$tpls[$about] = (object)[ 'start' => $elem ];
						}
					} else {
						// elem is the end-meta tag
						if ( $aboutRef ) {
							/* ------------------------------------------------------------
							 * Special case: In some cases, the entire template content can
							 * get fostered out of a table, not just the start/end marker.
							 *
							 * Simplest example:
							 *
							 *   {|
							 *   {{1x|foo}}
							 *   |}
							 *
							 * More complex example:
							 *
							 *   {|
							 *   {{1x|
							 *   a
							 *    b
							 *
							 *     c
							 *   }}
							 *   |}
							 *
							 * Since meta-tags dont normally get fostered out, this scenario
							 * only arises when the entire content including meta-tags was
							 * wrapped in p-tags.  So, we look to see if:
							 * 1. the end-meta-tag's parent has a table sibling,
							 * 2. the DSR of the start-meta-tag's parent is nested inside
							 *    that table's DSR
							 * If so, we recognize this as a adoption scenario and fix up
							 * DSR of start-meta-tag's parent to include the table's DSR.
							 * ------------------------------------------------------------*/
							$sm = $aboutRef->start;
							$em = $elem;
							$ee = $em;
							$tbl = $em->parentNode->nextSibling;

							// Dont get distracted by a newline node -- skip over it
							// Unsure why it shows up occasionally
							if ( $tbl && $tbl instanceof DOMText && preg_match( '/^\n$/D', $tbl->data ) ) {
								$tbl = $tbl->nextSibling;
							}

							$dp = DOMDataUtils::getDataParsoid( $sm->parentNode );
							if ( $tbl && $tbl->nodeName === 'table' && !empty( $dp->fostered ) ) {
								'@phan-var DOMElement $tbl';  /** @var DOMElement $tbl */
								$tblDP = DOMDataUtils::getDataParsoid( $tbl );
								if ( isset( $dp->tsr->start ) && $dp->tsr->start !== null &&
									isset( $tblDP->dsr->start ) && $tblDP->dsr->start === null
								) {
									$tblDP->dsr->start = $dp->tsr->start;
								}
								$tbl->setAttribute( 'about', $about ); // set about on elem
								$ee = $tbl;
							}
							$tplRanges[] = self::getDOMRange( $frame, $doc, $sm, $em, $ee );
						} else {
							$tpls[$about] = (object)[ 'end' => $elem ];
						}
					}
				} else {
					$tplRanges = array_merge(
						$tplRanges,
						self::findWrappableTemplateRanges( $doc, $frame, $elem, $tpls )
					);
				}
			}

			$elem = $nextSibling;
		}

		return $tplRanges;
	}

	/**
	 * @param DOMDocument $document
	 * @param Frame $frame
	 * @param DOMNode $node
	 */
	private static function wrapTemplatesInTree(
		DOMDocument $document, Frame $frame, DOMNode $node
	): void {
		$tplRanges = self::findWrappableTemplateRanges( $document, $frame, $node );
		if ( count( $tplRanges ) > 0 ) {
			$tplRanges = self::findTopLevelNonOverlappingRanges( $document, $frame, $node, $tplRanges );
			self::encapsulateTemplates( $document, $frame, $tplRanges->ranges, $tplRanges->tplArrays );
		}
	}

	/**
	 * Encapsulate template-affected DOM structures by wrapping text nodes into
	 * spans and adding RDFa attributes to all subtree roots according to
	 * http://www.mediawiki.org/wiki/Parsoid/RDFa_vocabulary#Template_content
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		self::wrapTemplatesInTree( $root->ownerDocument, $options['frame'], $root );
	}
}
