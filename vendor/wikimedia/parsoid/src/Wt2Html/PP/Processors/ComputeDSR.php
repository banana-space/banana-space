<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants as Consts;
use Wikimedia\Parsoid\Core\DataParsoid;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\Frame;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class ComputeDSR implements Wt2HtmlDOMProcessor {
	/**
	 * For an explanation of what TSR is, see ComputeDSR::computeNodeDSR()
	 *
	 * TSR info on all these tags are only valid for the opening tag.
	 * (closing tags dont have attrs since tree-builder strips them
	 * and adds meta-tags tracking the corresponding TSR)
	 *
	 * On other tags, a, hr, br, meta-marker tags, the tsr spans
	 * the entire DOM, not just the tag.
	 *
	 * This code is not in WikitextConstants.php because this
	 * information is Parsoid-implementation-specific.
	 */
	private static $WtTagsWithLimitedTSR = [
		"b"  => true,
		"i"  => true,
		"h1" => true,
		"h2" => true,
		"h3" => true,
		"h4" => true,
		"h5" => true,
		"h6" => true,
		"ul" => true,
		"ol" => true,
		"dl" => true,
		"li" => true,
		"dt" => true,
		"dd" => true,
		"table" => true,
		"caption" => true,
		"tr" => true,
		"td" => true,
		"th" => true,
		"hr" => true, // void element
		"br" => true, // void element
		"pre" => true,
	];

	/**
	 * Do $parsoidData->tsr values span the entire DOM subtree rooted at $n?
	 *
	 * @param DOMElement $n
	 * @param stdClass $parsoidData
	 * @return bool
	 */
	private function tsrSpansTagDOM( DOMElement $n, stdClass $parsoidData ): bool {
		// - tags known to have tag-specific tsr
		// - html tags with 'stx' set
		// - tags with certain typeof properties (Parsoid-generated
		//   constructs: placeholders, lang variants)
		$name = $n->nodeName;
		return !(
			isset( self::$WtTagsWithLimitedTSR[$name] ) ||
			DOMUtils::matchTypeOf(
				$n,
				'/^mw:(Placeholder|LanguageVariant)$/D'
			) ||
			WTUtils::hasLiteralHTMLMarker( $parsoidData )
		);
	}

	/**
	 * Is the inconsistency between two different ways of computing
	 * start offset ($cs, $s) explainable and acceptable?
	 * If so, we can suppress warnings.
	 *
	 * @param array $opts
	 * @param DOMNode $node
	 * @param int $cs
	 * @param int $s
	 * @return bool
	 */
	private function acceptableInconsistency( array $opts, DOMNode $node, int $cs, int $s ): bool {
		/**
		 * 1. For wikitext URL links, suppress cs-s diff warnings because
		 *    the diffs can come about because of various reasons since the
		 *    canonicalized/decoded href will become the a-link text whose width
		 *    will not match the tsr width of source wikitext
		 *
		 *    (a) urls with encoded chars (ex: 'http://example.com/?foo&#61;bar')
		 *    (b) non-canonical spaces (ex: 'RFC  123' instead of 'RFC 123')
		 *
		 * 2. We currently dont have source offsets for attributes.
		 *    So, we get a lot of spurious complaints about cs/s mismatch
		 *    when DSR computation hit the <body> tag on this attribute.
		 *    $opts['attrExpansion'] tell us when we are processing an attribute
		 *    and let us suppress the mismatch warning on the <body> tag.
		 *
		 * 3. Other scenarios .. to be added
		 */
		if ( $node->nodeName === 'a' &&
			 DOMUtils::assertElt( $node ) && (
				WTUtils::usesURLLinkSyntax( $node, null ) ||
				WTUtils::usesMagicLinkSyntax( $node, null )
			)
		) {
			return true;
		} elseif ( isset( $opts['attrExpansion'] ) && DOMUtils::isBody( $node ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Compute wikitext string length that contributes to this
	 * list item's open tag. Closing tag width is always 0 for lists.
	 *
	 * @param DOMNode $li
	 * @return int
	 */
	private function computeListEltWidth( DOMNode $li ): int {
		if ( !$li->previousSibling && $li->firstChild ) {
			if ( DOMUtils::isList( $li->firstChild ) ) {
				// Special case!!
				// First child of a list that is on a chain
				// of nested lists doesn't get a width.
				return 0;
			}
		}

		// count nest listing depth and assign
		// that to the opening tag width.
		$depth = 0;
		while ( $li->nodeName === 'li' || $li->nodeName === 'dd' ) {
			$depth++;
			$li = $li->parentNode->parentNode;
		}

		return $depth;
	}

	/**
	 * Compute wikitext string lengths that contribute to this
	 * anchor's opening (<a>) and closing (</a>) tags.
	 *
	 * @param DOMElement $node
	 * @param stdClass|null $dp
	 * @return int[]|null
	 */
	private function computeATagWidth( DOMElement $node, ?stdClass $dp ): ?array {
		/* -------------------------------------------------------------
		 * Tag widths are computed as per this logic here:
		 *
		 * 1. [[Foo|bar]] <-- piped mw:WikiLink
		 *     -> start-tag: "[[Foo|"
		 *     -> content  : "bar"
		 *     -> end-tag  : "]]"
		 *
		 * 2. [[Foo]] <-- non-piped mw:WikiLink
		 *     -> start-tag: "[["
		 *     -> content  : "Foo"
		 *     -> end-tag  : "]]"
		 *
		 * 3. [[{{1x|Foo}}|Foo]] <-- tpl-attr mw:WikiLink
		 *    Dont bother setting tag widths since dp->sa['href'] will be
		 *    the expanded target and won't correspond to original source.
		 *    We dont always have access to the meta-tag that has the source.
		 *
		 * 4. [http://wp.org foo] <-- mw:ExtLink
		 *     -> start-tag: "[http://wp.org "
		 *     -> content  : "foo"
		 *     -> end-tag  : "]"
		 * -------------------------------------------------------------- */
		if ( !$dp ) {
			return null;
		} else {
			if ( WTUtils::usesWikiLinkSyntax( $node, $dp ) && !WTUtils::hasExpandedAttrsType( $node ) ) {
				if ( isset( $dp->stx ) && $dp->stx === "piped" ) {
					// this seems like some kind of a phan bug
					$href = $dp->sa['href'] ?? null;
					if ( $href ) {
						return [ strlen( $href ) + 3, 2 ];
					} else {
						return null;
					}
				} else {
					return [ 2, 2 ];
				}
			} elseif ( isset( $dp->tsr ) && WTUtils::usesExtLinkSyntax( $node, $dp ) ) {
				return [ $dp->extLinkContentOffsets->start - $dp->tsr->start, 1 ];
			} elseif ( WTUtils::usesURLLinkSyntax( $node, $dp ) ||
				WTUtils::usesMagicLinkSyntax( $node, $dp )
			) {
				return [ 0, 0 ];
			} else {
				return null;
			}
		}
	}

	/**
	 * Compute wikitext string lengths that contribute to this
	 * node's opening and closing tags.
	 *
	 * @param int[] $widths
	 * @param DOMElement $node
	 * @param DataParsoid $dp
	 * @return int[]
	 */
	private function computeTagWidths( array $widths, DOMElement $node, stdClass $dp ): array {
		if ( isset( $dp->extTagOffsets ) ) {
			return [
				$dp->extTagOffsets->openWidth,
				$dp->extTagOffsets->closeWidth
			];
		}

		$stWidth = $widths[0];
		$etWidth = $widths[1];

		if ( WTUtils::hasLiteralHTMLMarker( $dp ) ) {
			if ( !empty( $dp->selfClose ) ) {
				$etWidth = 0;
			}
		} elseif ( DOMUtils::hasTypeOf( $node, 'mw:LanguageVariant' ) ) {
			$stWidth = 2; // -{
			$etWidth = 2; // }-
		} else {
			$nodeName = $node->nodeName;
			// 'tr' tags not in the original source have zero width
			if ( $nodeName === 'tr' && !isset( $dp->startTagSrc ) ) {
				$stWidth = 0;
				$etWidth = 0;
			} else {
				$wtTagWidth = Consts::$WtTagWidths[$nodeName] ?? null;
				if ( $stWidth === null ) {
					// we didn't have a tsr to tell us how wide this tag was.
					if ( $nodeName === 'a' ) {
						DOMUtils::assertElt( $node );
						$wtTagWidth = $this->computeATagWidth( $node, $dp );
						$stWidth = $wtTagWidth ? $wtTagWidth[0] : null;
					} elseif ( $nodeName === 'li' || $nodeName === 'dd' ) {
						DOMUtils::assertElt( $node );
						$stWidth = $this->computeListEltWidth( $node );
					} elseif ( $wtTagWidth ) {
						$stWidth = $wtTagWidth[0];
					}
				}

				if ( $etWidth === null && $wtTagWidth ) {
					$etWidth = $wtTagWidth[1];
				}
			}
		}

		return [ $stWidth, $etWidth ];
	}

	/**
	 * @param Env $env
	 * @param mixed ...$args
	 */
	private function trace( Env $env, ...$args ): void {
		$env->log( "trace/dsr", function () use ( $args ) {
			$buf = '';
			foreach ( $args as $arg ) {
				$buf .= ( gettype( $arg ) === 'string' ? $arg : PHPUtils::jsonEncode( $arg ) );
			}
			return $buf;
		} );
	}

	/**
	 * TSR = "Tag Source Range".  Start and end offsets giving the location
	 * where the tag showed up in the original source.
	 *
	 * DSR = "DOM Source Range".  dsr->start and dsr->end are open and end,
	 * dsr->openWidth and dsr->closeWidth are widths of the container tag.
	 *
	 * TSR is set by the tokenizer. In most cases, it only applies to the
	 * specific tag (opening or closing).  However, for self-closing
	 * tags that the tokenizer generates, the TSR values applies to the entire
	 * DOM subtree (opening tag + content + closing tag).
	 *
	 * Ex: So [[foo]] will get tokenized to a SelfClosingTagTk(...) with a TSR
	 * value of [0,7].  The DSR algorithm will then use that info and assign
	 * the a-tag rooted at the <a href='...'>foo</a> DOM subtree a DSR value of
	 * [0,7,2,2], where 2 and 2 refer to the opening and closing tag widths.
	 *
	 * [s,e) -- if defined, start/end position of wikitext source that generated
	 *          node's subtree
	 *
	 * @param Frame $frame
	 * @param DOMNode $node node to process
	 * @param int|null $s start position, inclusive
	 * @param int|null $e end position, exclusive
	 * @param int $dsrCorrection
	 * @param array $opts
	 * @return array
	 */
	private function computeNodeDSR(
		Frame $frame, DOMNode $node, ?int $s, ?int $e, int $dsrCorrection, array $opts
	): array {
		$env = $frame->getEnv();
		if ( $e === null && !$node->hasChildNodes() ) {
			$e = $s;
		}

		$this->trace( $env, "BEG: ", $node->nodeName, " with [s, e]=", [ $s, $e ] );

		$savedEndTagWidth = null;
		$ce = $e;
		// Initialize $cs to $ce to handle the zero-children case properly
		// if this $node has no child content, then the start and end for
		// the child dom are indeed identical.  Alternatively, we could
		// explicitly code this check before everything and bypass this.
		$cs = $ce;
		$rtTestMode = $env->getSiteConfig()->rtTestMode();

		$child = $node->lastChild;
		while ( $child !== null ) {
			$prevChild = $child->previousSibling;
			$isMarkerTag = false;
			$origCE = $ce;
			$cType = $child->nodeType;
			$endTagInfo = null;
			$fosteredNode = false;
			$cs = null;

			// In edit mode, StrippedTag marker tags will be removed and wont
			// be around to miss in the filling gap.  So, absorb its width into
			// the DSR of its previous sibling.  Currently, this fix is only for
			// B and I tags where the fix is clear-cut and obvious.
			if ( !$rtTestMode ) {
				$next = $child->nextSibling;
				if ( $next && ( $next instanceof DOMElement ) ) {
					$ndp = DOMDataUtils::getDataParsoid( $next );
					if ( isset( $ndp->src ) &&
						 DOMUtils::hasTypeOf( $next, 'mw:Placeholder/StrippedTag' )
					) {
						if ( isset( Consts::$WTQuoteTags[$ndp->name] ) &&
							isset( Consts::$WTQuoteTags[$child->nodeName] ) ) {
							$correction = strlen( $ndp->src );
							$ce += $correction;
							$dsrCorrection = $correction;
							if ( Utils::isValidDSR( $ndp->dsr ?? null ) ) {
								// Record original DSR for the meta tag
								// since it will now get corrected to zero width
								// since child acquires its width->
								if ( !$ndp->tmp ) {
									$ndp->tmp = [];
								}
								$ndp->tmp->origDSR = new DomSourceRange( $ndp->dsr->start, $ndp->dsr->end, null, null );
							}
						}
					}
				}
			}

			$env->log( "trace/dsr", function () use ( $child, $cs, $ce ) {
				// slow, for debugging only
				$i = 0;
				foreach ( $child->parentNode->childNodes as $x ) {
					if ( $x === $child ) {
						break;
					}
					$i++;
				}
				return "     CHILD: <" . $child->parentNode->nodeName . ":" . $i .
					">=" .
					( $child instanceof DOMElement ? '' : ( DOMUtils::isText( $child ) ? '#' : '!' ) ) .
					( ( $child instanceof DOMElement ) ?
						( $child->nodeName === 'meta' ?
							DOMCompat::getOuterHTML( $child ) : $child->nodeName ) :
							PHPUtils::jsonEncode( $child->nodeValue ) ) .
					" with " . PHPUtils::jsonEncode( [ $cs, $ce ] );
			} );

			if ( $cType === XML_TEXT_NODE ) {
				if ( $ce !== null ) {
					// This code is replicated below. Keep both in sync.
					$cs = $ce - strlen( $child->textContent ) - WTUtils::indentPreDSRCorrection( $child );
				}
			} elseif ( $cType === XML_COMMENT_NODE ) {
				'@phan-var \DOMComment $child'; // @var \DOMComment $child
				if ( $ce !== null ) {
					// Decode HTML entities & re-encode as wikitext to find length
					$cs = $ce - WTUtils::decodedCommentLength( $child );
				}
			} elseif ( $cType === XML_ELEMENT_NODE ) {
				DOMUtils::assertElt( $child );
				$dp = DOMDataUtils::getDataParsoid( $child );
				$tsr = $dp->tsr ?? null;
				$oldCE = $tsr ? $tsr->end : null;
				$propagateRight = false;
				$stWidth = null;
				$etWidth = null;

				$fosteredNode = $dp->fostered ?? false;

				// In edit-mode, we are making dsr corrections to account for
				// stripped tags (end tags usually). When stripping happens,
				// in most common use cases, a corresponding end tag is added
				// back elsewhere in the DOM.
				//
				// So, when an autoInsertedEnd tag is encountered and a matching
				// dsr-correction is found, make a 1-time correction in the
				// other direction.
				//
				// Currently, this fix is only for
				// B and I tags where the fix is clear-cut and obvious.
				if ( !$rtTestMode && $ce !== null && !empty( $dp->autoInsertedEnd ) &&
					DOMUtils::isQuoteElt( $child )
				) {
					$correction = 3 + strlen( $child->nodeName );
					if ( $correction === $dsrCorrection ) {
						$ce -= $correction;
						$dsrCorrection = 0;
					}
				}

				if ( $child->nodeName === "meta" ) {
					// Unless they have been foster-parented,
					// meta marker tags have valid tsr info->
					if ( DOMUtils::matchTypeOf( $child, '#^mw:(EndTag|TSRMarker)$#D' ) ) {
						if ( DOMUtils::hasTypeOf( $child, "mw:EndTag" ) ) {
							// FIXME: This seems like a different function that is
							// tacked onto DSR computation, but there is no clean place
							// to do this one-off thing without doing yet another pass
							// over the DOM -- maybe we need a 'do-misc-things-pass'.
							//
							// Update table-end syntax using info from the meta tag
							$prev = $child->previousSibling;
							if ( $prev && $prev->nodeName === "table" ) {
								DOMUtils::assertElt( $prev );
								$prevDP = DOMDataUtils::getDataParsoid( $prev );
								if ( !WTUtils::hasLiteralHTMLMarker( $prevDP ) ) {
									if ( isset( $dp->endTagSrc ) ) {
										$prevDP->endTagSrc = $dp->endTagSrc;
									}
								}
							}
						}

						$isMarkerTag = true;
						// TSR info will be absent if the tsr-marker came
						// from a template since template tokens have all
						// their tsr info-> stripped->
						if ( $tsr ) {
							$endTagInfo = [
								'width' => $tsr->end - $tsr->start,
								'nodeName' => $child->getAttribute( "data-etag" ),
							];
							$cs = $tsr->end;
							$ce = $tsr->end;
							$propagateRight = true;
						}
					} elseif ( $tsr ) {
						if ( WTUtils::isTplMarkerMeta( $child ) ) {
							// If this is a meta-marker tag (for templates, extensions),
							// we have a new valid '$cs'. This marker also effectively resets tsr
							// back to the top-level wikitext source range from nested template
							// source range.
							$cs = $tsr->start;
							$ce = $tsr->end;
							$propagateRight = true;
						} else {
							// All other meta-tags: <includeonly>, <noinclude>, etc.
							$cs = $tsr->start;
							$ce = $tsr->end;
						}
					} elseif ( DOMUtils::matchTypeOf( $child, '#^mw:Placeholder(/\w*)?$#D' ) &&
						$ce !== null && $dp->src
					) {
						$cs = $ce - strlen( $dp->src );
					}
					if ( isset( $dp->extTagOffsets ) ) {
						$stWidth = $dp->extTagOffsets->openWidth;
						$etWidth = $dp->extTagOffsets->closeWidth;
						/** @phan-suppress-next-line PhanTypeObjectUnsetDeclaredProperty */
						unset( $dp->extTagOffsets );
					}
				} elseif ( DOMUtils::hasTypeOf( $child, "mw:Entity" ) && $ce !== null && $dp->src ) {
					$cs = $ce - strlen( $dp->src );
				} else {
					if ( DOMUtils::matchTypeOf( $child, '#^mw:Placeholder(/\w*)?$#D' ) &&
						$ce !== null && $dp->src
					) {
						$cs = $ce - strlen( $dp->src );
					} else {
						// Non-meta tags
						if ( $tsr && empty( $dp->autoInsertedStart ) ) {
							$cs = $tsr->start;
							if ( $this->tsrSpansTagDOM( $child, $dp ) ) {
								if ( $tsr->end !== null && $tsr->end > 0 ) {
									$ce = $tsr->end;
									$propagateRight = true;
								}
							} else {
								$stWidth = $tsr->end - $tsr->start;
							}

							$this->trace( $env, "     TSR: ", $tsr, "; cs: ", $cs, "; ce: ", $ce );
						} elseif ( $s && $child->previousSibling === null ) {
							$cs = $s;
						}
					}

					// Compute width of opening/closing tags for this dom $node
					$tagWidths = $this->computeTagWidths( [ $stWidth, $savedEndTagWidth ], $child, $dp );
					$stWidth = $tagWidths[0];
					$etWidth = $tagWidths[1];

					if ( !empty( $dp->autoInsertedStart ) ) {
						$stWidth = 0;
					}
					if ( !empty( $dp->autoInsertedEnd ) ) {
						$etWidth = 0;
					}

					$ccs = $cs !== null && $stWidth !== null ? $cs + $stWidth : null;
					$cce = $ce !== null && $etWidth !== null ? $ce - $etWidth : null;

					/* -----------------------------------------------------------------
					 * Process DOM rooted at '$child'.
					 *
					 * NOTE: You might wonder why we are not checking for the zero-$children
					 * case. It is strictly not necessary and you can set newDsr directly.
					 *
					 * But, you have 2 options: [$ccs, $ccs] or [$cce, $cce]. Setting it to
					 * [$cce, $cce] would be consistent with the RTL approach. We should
					 * then compare $ccs and $cce and verify that they are identical.
					 *
					 * But, if we handled the zero-child case like the other scenarios,
					 * we don't have to worry about the above decisions and checks.
					 * ----------------------------------------------------------------- */

					if ( WTUtils::isDOMFragmentWrapper( $child ) ||
						 DOMUtils::hasTypeOf( $child, 'mw:LanguageVariant' )
					) {
						// Eliminate artificial $cs/s mismatch warnings since this is
						// just a wrapper token with the right DSR but without any
						// nested subtree that could account for the DSR span.
						$newDsr = [ $ccs, $cce ];
					} elseif ( $child->nodeName === 'a'
						&& DOMUtils::assertElt( $child )
						&& WTUtils::usesWikiLinkSyntax( $child, $dp )
						&& ( !isset( $dp->stx ) || $dp->stx !== "piped" ) ) {
						/* -------------------------------------------------------------
						 * This check here eliminates artificial DSR mismatches on content
						 * text of the A-node because of entity expansion, etc.
						 *
						 * Ex: [[7%25 solution]] will be rendered as:
						 *    <a href=....>7% solution</a>
						 * If we descend into the text for the a-node, we'll have a 2-char
						 * DSR mismatch which will trigger artificial error warnings.
						 *
						 * In the non-piped link scenario, all dsr info is already present
						 * in the link target and so we get nothing new by processing
						 * content.
						 * ------------------------------------------------------------- */
						$newDsr = [ $ccs, $cce ];
					} else {
						$env->log( "trace/dsr", function () use ( $env, $cs, $ce, $stWidth, $etWidth, $ccs, $cce ) {
							return "     before-recursing:" .
								"[cs,ce]=" . PHPUtils::jsonEncode( [ $cs, $ce ] ) .
								"; [sw,ew]=" . PHPUtils::jsonEncode( [ $stWidth, $etWidth ] ) .
								"; subtree-[cs,ce]=" . PHPUtils::jsonEncode( [ $ccs,$cce ] );
						} );

						$this->trace( $env, "<recursion>" );
						$newDsr = $this->computeNodeDSR( $frame, $child, $ccs, $cce, $dsrCorrection, $opts );
						$this->trace( $env, "</recursion>" );
					}

					// $cs = min($child-dom-tree dsr->start - tag-width, current dsr->start)
					if ( $stWidth !== null && $newDsr[0] !== null ) {
						$newCs = $newDsr[0] - $stWidth;
						if ( $cs === null || ( !$tsr && $newCs < $cs ) ) {
							$cs = $newCs;
						}
					}

					// $ce = max($child-dom-tree dsr->end + tag-width, current dsr->end)
					if ( $etWidth !== null && $newDsr[1] !== null ) {
						$newCe = $newDsr[1] + $etWidth;
						if ( $newCe > $ce ) {
							$ce = $newCe;
						}
					}
				}

				if ( $cs !== null || $ce !== null ) {
					if ( $ce < 0 ) {
						if ( !$fosteredNode ) {
							$env->log( "warn/dsr/negative",
								"Negative DSR for node: " . $node->nodeName . "; resetting to zero" );
						}
						$ce = 0;
					}

					// Fostered $nodes get a zero-dsr width range.
					if ( $fosteredNode ) {
						// Reset to 0, if necessary.
						// This is critical to avoid duplication of fostered content in selser mode.
						if ( $origCE < 0 ) {
							$origCE = 0;
						}
						$dp->dsr = new DomSourceRange( $origCE, $origCE, null, null );
					} else {
						$dp->dsr = new DomSourceRange( $cs, $ce, $stWidth, $etWidth );
					}

					$env->log( "trace/dsr", function () use ( $frame, $child, $cs, $ce, $dp ) {
						$str = "     UPDATING " . $child->nodeName .
							" with " . PHPUtils::jsonEncode( [ $cs, $ce ] ) .
							"; typeof: " . ( $child->getAttribute( "typeof" ) ?? "" );
						// Set up 'dbsrc' so we can debug this
						if ( $cs !== null && $ce !== null ) {
							$dp->dbsrc = PHPUtils::safeSubstr( $frame->getSrcText(), $cs, $ce - $cs );
						}
						return $str;
					} );
				}

				// Propagate any required changes to the right
				// taking care not to cross-over into template content
				if ( $ce !== null &&
					( $propagateRight || $oldCE !== $ce || $e === null ) &&
					!WTUtils::isTplStartMarkerMeta( $child )
				) {
					$sibling = $child->nextSibling;
					$newCE = $ce;
					while ( $newCE !== null && $sibling && !WTUtils::isTplStartMarkerMeta( $sibling ) ) {
						$nType = $sibling->nodeType;
						if ( $nType === XML_TEXT_NODE ) {
							$newCE = $newCE + strlen( $sibling->textContent ) +
								WTUtils::indentPreDSRCorrection( $sibling );
						} elseif ( $nType === XML_COMMENT_NODE ) {
							'@phan-var \DOMComment $sibling'; // @var \DOMComment $sibling
							$newCE += WTUtils::decodedCommentLength( $sibling );
						} elseif ( $nType === XML_ELEMENT_NODE ) {
							DOMUtils::assertElt( $sibling );
							$siblingDP = DOMDataUtils::getDataParsoid( $sibling );
							if ( !isset( $siblingDP->dsr ) ) {
								$siblingDP->dsr = new DomSourceRange( null, null, null, null );
							}
							$sdsrStart = $siblingDP->dsr->start;
							if ( !empty( $siblingDP->fostered ) ||
								( $sdsrStart !== null && $sdsrStart === $newCE ) ||
								( $sdsrStart !== null && $sdsrStart < $newCE && isset( $siblingDP->tsr ) )
							) {
								// $sibling is fostered
								// => nothing to propagate past it
								// $sibling's dsr->start matches what we might propagate
								// => nothing will change
								// $sibling's dsr value came from tsr and it is not outside expected range
								// => stop propagation so you don't overwrite it
								break;
							}

							// Update and move right
							$env->log( "trace/dsr", function () use ( $frame, $newCE, $sibling, $siblingDP ) {
								$str = "     CHANGING ce.start of " . $sibling->nodeName .
									" from " . $siblingDP->dsr->start . " to " . $newCE;
								// debug info
								if ( $siblingDP->dsr->end ) {
									$siblingDP->dbsrc = PHPUtils::safeSubstr(
										$frame->getSrcText(), $newCE, $siblingDP->dsr->end - $newCE );
								}
								return $str;
							} );

							$siblingDP->dsr->start = $newCE;
							// If we have a dsr->end as well and since we updated
							// dsr->start, we have to ensure that the two values don't
							// introduce an inconsistency where dsr->start > dsr->end.
							// Since we are in a LTR pass and are pushing updates
							// forward, we are resolving it by updating dsr->end as
							// well. There could be scenarios where this would be
							// incorrect, but there is no universal fix here.
							if ( $siblingDP->dsr->end !== null && $newCE > $siblingDP->dsr->end ) {
								$siblingDP->dsr->end = $newCE;
							}
							$newCE = $siblingDP->dsr->end;

						} else {
							break;
						}
						$sibling = $sibling->nextSibling;
					}

					// Propagate new end information
					if ( !$sibling ) {
						$e = $newCE;
					}
				}
			}

			// Dont change state if we processed a fostered $node
			if ( $fosteredNode ) {
				$ce = $origCE;
			} else {
				// $ce for next $child = $cs of current $child
				$ce = $cs;

				// Save end-tag width from marker meta tag
				if ( $endTagInfo && $child->previousSibling &&
					$endTagInfo['nodeName'] === $child->previousSibling->nodeName ) {
					$savedEndTagWidth = $endTagInfo['width'];
				} else {
					$savedEndTagWidth = null;
				}
			}

			// No use for this marker tag after this.
			// Looks like DSR computation assumes that
			// these meta tags will be removed.
			if ( $isMarkerTag ) {
				// Collapse text $nodes to prevent n^2 effect in the LTR propagation pass
				// Example: enwiki:Colonization?oldid=718468597
				$nextChild = $child->nextSibling;
				if ( DOMUtils::isText( $prevChild ) && DOMUtils::isText( $nextChild ) ) {
					$prevText = $prevChild->nodeValue;
					$nextText = $nextChild->nodeValue;

					// Process prevText in place
					if ( $ce !== null ) {
						// indentPreDSRCorrection is not required here since
						// we'll never come down this branch (mw:TSRMarker won't exist
						// in indent-pres, and mw:EndTag markers won't have a text $node
						// for its previous sibling), but, for sake of maintenance sanity,
						// replicating code from above.
						$cs = $ce - strlen( $prevText ) - WTUtils::indentPreDSRCorrection( $prevChild );
						$ce = $cs;
					}

					// Update DOM
					$newNode = $node->ownerDocument->createTextNode( $prevText . $nextText );
					$node->replaceChild( $newNode, $prevChild );
					$node->removeChild( $nextChild );
					$prevChild = $newNode->previousSibling;
				}
				$node->removeChild( $child );
			}

			$child = $prevChild;
		}

		if ( $cs === null ) {
			$cs = $s;
		}

		// Detect errors
		if ( $s !== null && $cs !== $s && !$this->acceptableInconsistency( $opts, $node, $cs, $s ) ) {
			$env->log( "warn/dsr/inconsistent", "DSR inconsistency: cs/s mismatch for node:",
				$node->nodeName, "s:", $s, "; cs:", $cs );
		}

		$this->trace( $env, "END: ", $node->nodeName, ", returning: ", $cs, ", ", $e );

		return [ $cs, $e ];
	}

	/**
	 * Computes DSR ranges for every node of a DOM tree.
	 * This pass is only invoked on the top-level page.
	 *
	 * @param Env $env The environment/context for the parse pipeline
	 * @param DOMElement $root The root of the tree for which DSR has to be computed
	 * @param array $options Options governing DSR computation
	 * - sourceOffsets: [start, end] source offset. If missing, this defaults to
	 *                  [0, strlen($frame->getSrcText())]
	 * - attrExpansion: Is this an attribute expansion pipeline?
	 * @param bool $atTopLevel Are we running this on the top level?
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		$frame = $options['frame'] ?? $env->topFrame;
		$startOffset = $options['sourceOffsets']->start ?? 0;
		$endOffset = $options['sourceOffsets']->end ?? strlen( $frame->getSrcText() );
		$env->log( "trace/dsr", "------- tracing DSR computation -------" );

		// The actual computation buried in trace/debug stmts.
		$opts = [ 'attrExpansion' => $options['attrExpansion'] ?? false ];
		$this->computeNodeDSR( $frame, $root, $startOffset, $endOffset, 0, $opts );

		$dp = DOMDataUtils::getDataParsoid( $root );
		$dp->dsr = new DomSourceRange( $startOffset, $endOffset, 0, 0 );
		$env->log( "trace/dsr", "------- done tracing computation -------" );
	}
}
