<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use DOMNode;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\Frame;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class WrapSections implements Wt2HtmlDOMProcessor {
	/**
	 * Get page source between the requested offsets
	 *
	 * @param Frame $frame
	 * @param int $s
	 * @param int $e
	 * @return string
	 */
	private function getSrc( Frame $frame, int $s, int $e ): string {
		return PHPUtils::safeSubstr( $frame->getSrcText(), $s, $e - $s );
	}

	/**
	 * Create a new section element
	 *
	 * @param array &$state
	 * @param DOMElement $rootNode
	 * @param array &$sectionStack
	 * @param array|null $tplInfo
	 * @param array|null $currSection
	 * @param DOMNode $node
	 * @param int $newLevel
	 * @param bool $pseudoSection
	 * @return array
	 */
	private function createNewSection( array &$state, DOMElement $rootNode, array &$sectionStack,
		?array $tplInfo, ?array $currSection, DOMNode $node, int $newLevel, bool $pseudoSection
	): array {
		/* Structure for regular (editable or not) sections
		 *   <section data-mw-section-id="..">
		 *     <h*>..</h*>
		 *     ..
		 *   </section>
		 *
		 * Lead sections and pseudo-sections won't have <h*> or <div> tags
		 */
		$section = [
			'level' => $newLevel,
			// useful during debugging, unrelated to the data-mw-section-id
			'debug_id' => $state['count']++,
			'container' => $state['doc']->createElement( 'section' )
		];

		/* Step 1. Get section stack to the right nesting level
		 * 1a. Pop stack till we have a higher-level section.
		 */
		$stack = &$sectionStack;
		while ( count( $stack ) > 0 && $newLevel <= PHPUtils::lastItem( $stack )['level'] ) {
			array_pop( $stack );
		}

		/* 1b. Push current section onto stack if it is a higher-level section */
		if ( $currSection && $newLevel > $currSection['level'] ) {
			$stack[] = $currSection;
		}

		/* Step 2: Add new section where it belongs: a parent section OR body */
		$parentSection = count( $stack ) > 0 ? PHPUtils::lastItem( $stack ) : null;
		if ( $parentSection ) {
			// print "Appending to " . $parentSection['debug_id'] . '\n';
			$parentSection['container']->appendChild( $section['container'] );
		} else {
			$rootNode->insertBefore( $section['container'], $node );
		}

		/* Step 3: Add <h*> to the <section> */
		$section['container']->appendChild( $node );

		/* Step 4: Assign data-mw-section-id attribute
		 *
		 * CX wants <section> tags with a distinguishing attribute so that
		 * it can differentiate between its internal use of <section> tags
		 * with what Parsoid adds. So, we will add a data-mw-section-id
		 * attribute always.
		 *
		 * data-mw-section-id = 0 for the lead section
		 * data-mw-section-id = -1 for non-editable sections
		 *     Note that templated content cannot be edited directly.
		 * data-mw-section-id = -2 for pseudo sections
		 * data-mw-section-id > 0 for everything else and this number
		 *     matches PHP parser / Mediawiki's notion of that section.
		 *
		 * The code here handles uneditable sections because of templating.
		 */
		if ( $pseudoSection ) {
			$section['container']->setAttribute( 'data-mw-section-id', '-2' );
		} elseif ( $state['inTemplate'] ) {
			$section['container']->setAttribute( 'data-mw-section-id', '-1' );
		} else {
			$section['container']->setAttribute( 'data-mw-section-id', (string)$state['sectionNumber'] );
		}

		/* Ensure that template continuity is not broken if the section
		 * tags aren't stripped by a client */
		if ( $tplInfo && $node !== $tplInfo['first'] ) {
			$section['container']->setAttribute( 'about', $tplInfo['about'] );
		}

		return $section;
	}

	/**
	 * Walk the DOM and add <section> wrappers where required.
	 * This is the workhorse code that wrapSections relies on.
	 *
	 * @param array &$state
	 * @param array|null $currSection
	 * @param DOMElement $rootNode
	 * @return int
	 */
	private function wrapSectionsInDOM( array &$state, ?array $currSection,
		DOMElement $rootNode ): int {
		$tplInfo = null;
		$sectionStack = [];
		$highestSectionLevel = 7;
		$node = $rootNode->firstChild;
		while ( $node ) {
			$next = $node->nextSibling;
			$addedNode = false;

			// Track entry into templated output
			if ( !$state['inTemplate'] && WTUtils::isFirstEncapsulationWrapperNode( $node ) ) {
				DOMUtils::assertElt( $node );
				$about = $node->getAttribute( 'about' ) ?? '';
				$aboutSiblings = WTUtils::getAboutSiblings( $node, $about );
				$state['inTemplate'] = true;
				$tplInfo = [
					'first' => $node,
					'about' => $about,
					'last' => end( $aboutSiblings )
				];
			}

			if ( preg_match( '/^h[1-6]$/D', $node->nodeName ) ) {
				DOMUtils::assertElt( $node ); // headings are elements
				$level = (int)$node->nodeName[1];

				// HTML <h*> tags don't get section numbers!
				if ( !WTUtils::isLiteralHTMLNode( $node ) ) {
					// This could be just `state.sectionNumber++` without the
					// complicated if-guard if T214538 were fixed in core;
					// see T213468 where this more-complicated behavior was
					// added to match core's eccentricities.
					$dp = DOMDataUtils::getDataParsoid( $node );
					if ( isset( $dp->tmp->headingIndex ) ) {
						$state['sectionNumber'] = $dp->tmp->headingIndex;
					}
					if ( $level < $highestSectionLevel ) {
						$highestSectionLevel = $level;
					}
					$currSection = $this->createNewSection( $state, $rootNode, $sectionStack,
						$tplInfo, $currSection, $node, $level, false );
					$addedNode = true;
				}
			} elseif ( $node instanceof DOMElement ) {
				// If we find a higher level nested section,
				// (a) Make current section non-editable
				// (b) There are 2 $options here.
				// Best illustrated with an example
				// Consider the wiktiext below.
				// <div>
				// =1=
				// b
				// </div>
				// c
				// =2=
				// 1. Create a new pseudo-section to wrap '$node'
				// There will be a <section> around the <div> which includes 'c'.
				// 2. Don't create the pseudo-section by setting '$currSection = null'
				// But, this can leave some content outside any top-level section.
				// 'c' will not be in any section.
				// The code below implements strategy 1.
				$nestedHighestSectionLevel = $this->wrapSectionsInDOM( $state, null, $node );
				if ( $currSection && $nestedHighestSectionLevel <= $currSection['level'] ) {
					$currSection['container']->setAttribute( 'data-mw-section-id', '-1' );
					$currSection = $this->createNewSection( $state, $rootNode, $sectionStack,
						$tplInfo, $currSection, $node, $nestedHighestSectionLevel, true );
					$addedNode = true;
				}
			}

			if ( $currSection && !$addedNode ) {
				$currSection['container']->appendChild( $node );
			}

			if ( $tplInfo && $tplInfo['first'] === $node ) {
				$tplInfo['firstSection'] = $currSection;
			}

			// Track exit from templated output
			if ( $tplInfo && $tplInfo['last'] === $node ) {
				// The opening $node and closing $node of the template
				// are in different sections! This might require resolution.
				if ( $currSection !== $tplInfo['firstSection'] ) {
					$tplInfo['lastSection'] = $currSection;
					$state['tplsAndExtsToExamine'][] = $tplInfo;
				}

				$tplInfo = null;
				$state['inTemplate'] = false;
			}

			$node = $next;
		}

		// The last section embedded in a non-body DOM element
		// should always be marked non-editable since it will have
		// the closing tag (ex: </div>) showing up in the source editor
		// which we cannot support in a visual editing $environment.
		if ( $currSection && !DOMUtils::isBody( $rootNode ) ) {
			$currSection['container']->setAttribute( 'data-mw-section-id', '-1' );
		}

		return $highestSectionLevel;
	}

	/**
	 * Get opening/closing DSR offset for the subtree rooted at $node.
	 * This handles scenarios where $node is a section or template wrapper
	 * and if a section, when it has leading/trailing non-element nodes
	 * that don't have recorded DSR values.
	 *
	 * @param array $tplInfo
	 * @param DOMElement $node
	 * @param bool $start
	 * @return int
	 */
	private function getDSR( array $tplInfo, DOMElement $node, bool $start ): int {
		if ( $node->nodeName !== 'section' ) {
			$nodeDsr = DOMDataUtils::getDataParsoid( $node )->dsr ?? null;
			$tmplDsr = DOMDataUtils::getDataParsoid( $tplInfo['first'] )->dsr;
			if ( $start ) {
				return $nodeDsr->start ?? $tmplDsr->start;
			} else {
				return $nodeDsr->end ?? $tmplDsr->end;
			}
		}

		$offset = 0;
		$c = $start ? $node->firstChild : $node->lastChild;
		while ( $c ) {
			if ( !( $c instanceof DOMElement ) ) {
				$offset += strlen( $c->textContent );
			} else {
				return $this->getDSR( $tplInfo, $c, $start ) + ( $start ? -$offset : $offset );
			}
			$c = $start ? $c->nextSibling : $c->previousSibling;
		}

		return -1;
	}

	/**
	 * Section wrappers and template/extension wrappers can conflict because
	 * of partial overlaps. This method identifies those conflicts and fixes up
	 * the template/extension encapsulation by expanding those ranges as necessary.
	 * This algorithm is not fully foolproof and there are known edge case bugs.
	 * See phabricator for these open bugs.
	 *
	 * @param array &$state
	 */
	private function resolveTplExtSectionConflicts( array &$state ) {
		foreach ( $state['tplsAndExtsToExamine'] as $tplInfo ) {
			// could be null
			if ( isset( $tplInfo['firstSection'] ) &&
				isset( $tplInfo['firstSection']['container'] )
			) {
				$s1 = $tplInfo['firstSection']['container'];
			} else {
				$s1 = null;
			}

			// guaranteed to be non-null
			$s2 = $tplInfo['lastSection']['container'];

			// Find a common ancestor of s1 and s2 (could be s1)
			$s2Ancestors = DOMUtils::pathToRoot( $s2 );
			$s1Ancestors = [];
			$ancestor = null;
			$i = 0;
			if ( $s1 ) {
				$ancestor = $s1;
				while ( !in_array( $ancestor, $s2Ancestors, true ) ) {
					$s1Ancestors[] = $ancestor;
					$ancestor = $ancestor->parentNode;
				}
				// ancestor is now the common ancestor of s1 and s2
				$s1Ancestors[] = $ancestor;
				$i = array_search( $ancestor, $s2Ancestors, true );
			}

			if ( !$s1 || $ancestor === $s1 ) {
				// Scenario 1: s1 is s2's ancestor OR s1 doesn't exist.
				// In either case, s2 only covers part of the transcluded content.
				// But, s2 could also include content that follows the transclusion.
				// If so, append the content of the section after the last $node
				// to data-mw.parts.
				if ( $tplInfo['last']->nextSibling ) {
					$newTplEndOffset = $this->getDSR( $tplInfo, $s2, false );
					// The next line will succeed because it traverses non-tpl content
					$tplDsr = &DOMDataUtils::getDataParsoid( $tplInfo['first'] )->dsr;
					$tplEndOffset = $tplDsr->end;
					$dmw = DOMDataUtils::getDataMw( $tplInfo['first'] );
					if ( DOMUtils::hasTypeOf( $tplInfo['first'], 'mw:Transclusion' ) ) {
						if ( $dmw->parts ) {
							$dmw->parts[] = $this->getSrc( $state['frame'], $tplEndOffset, $newTplEndOffset );
						}
					} else { /* Extension */
						// https://phabricator.wikimedia.org/T184779
						$dmw->extSuffix = $this->getSrc( $state['frame'], $tplEndOffset, $newTplEndOffset );
					}

					// Update DSR
					$tplDsr->end = $newTplEndOffset;

					// Set about attributes on all children of s2 - add span wrappers if required
					$span = null;
					for ( $n = $tplInfo['last']->nextSibling; $n; $n = $n->nextSibling ) {
						if ( $n instanceof DOMElement ) {
							$n->setAttribute( 'about', $tplInfo['about'] );
							$span = null;
						} else {
							if ( !$span ) {
								$span = $state['doc']->createElement( 'span' );
								$span->setAttribute( 'about', $tplInfo['about'] );
								$n->parentNode->replaceChild( $span, $n );
							}
							$span->appendChild( $n );
							$n = $span; // to ensure n->nextSibling is correct
						}
					}
				}
			} else {
				// Scenario 2: s1 and s2 are in different subtrees
				// Find children of the common ancestor that are on the
				// path from s1 -> ancestor and s2 -> ancestor
				Assert::invariant(
					count( $s1Ancestors ) >= 2 && $i >= 1,
					'Scenario assumptions violated.'
				);
				$newS1 = $s1Ancestors[count( $s1Ancestors ) - 2]; // length >= 2 since we know ancestors != s1
				$newS2 = $s2Ancestors[$i - 1]; // i >= 1 since we know s2 is not s1's ancestor
				$newAbout = $state['env']->newAboutId(); // new about id for the new wrapping layer

				// Ensure that all children from newS1 and newS2 have about attrs set
				for ( $n = $newS1; $n !== $newS2->nextSibling; $n = $n->nextSibling ) {
					$n->setAttribute( 'about', $newAbout );
				}

				// $newS2 is $s2, or its ancestor
				DOMUtils::assertElt( $s2 );
				DOMUtils::assertElt( $newS2 );

				// Update transclusion info
				$dsr1 = $this->getDSR( $tplInfo, $newS1, true );  // Traverses non-tpl content => will succeed
				$dsr2 = $this->getDSR( $tplInfo, $newS2, false ); // Traverses non-tpl content => will succeed
				$tplDP = DOMDataUtils::getDataParsoid( $tplInfo['first'] );
				$tplDsr = &$tplDP->dsr;
				$dmw = Utils::clone( DOMDataUtils::getDataMw( $tplInfo['first'] ) );
				if ( DOMUtils::hasTypeOf( $tplInfo['first'], 'mw:Transclusion' ) ) {
					if ( $dmw->parts ) {
						array_unshift( $dmw->parts, $this->getSrc( $state['frame'], $dsr1, $tplDsr->start ) );
						$dmw->parts[] = $this->getSrc( $state['frame'], $tplDsr->end, $dsr2 );
					}
					DOMDataUtils::setDataMw( $newS1, $dmw );
					DOMUtils::addTypeOf( $newS1, 'mw:Transclusion' );
					// Copy the template's parts-information object
					// which has white-space information for formatting
					// the transclusion and eliminates dirty-diffs.
					$dp = (object)[ 'pi' => $tplDP->pi, 'dsr' => new DomSourceRange( $dsr1, $dsr2, null, null ) ];
					DOMDataUtils::setDataParsoid( $newS1, $dp );
				} else { /* extension */
					// https://phabricator.wikimedia.org/T184779
					$dmw->extPrefix = $this->getSrc( $state['frame'], $dsr1, $tplDsr->start );
					$dmw->extSuffix = $this->getSrc( $state['frame'], $tplDsr->end, $dsr2 );
					DOMDataUtils::setDataMw( $newS1, $dmw );
					$newS1->setAttribute( 'typeof', $tplInfo['first']->getAttribute( 'typeof' ) );
					$dp = (object)[ 'dsr' => new DomSourceRange( $dsr1, $dsr2, null, null ) ];
					DOMDataUtils::setDataParsoid( $newS1, $dp );
				}
			}
		}
	}

	/**
	 * DOM Postprocessor entry function to walk DOM rooted at $root
	 * and add <section> wrappers as necessary.
	 * Implements the algorithm documented @ mw:Parsing/Notes/Section_Wrapping
	 *
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		if ( !$env->getWrapSections() ) {
			return;
		}

		$doc = $root->ownerDocument;
		$leadSection = [
			'container' => $doc->createElement( 'section' ),
			'debug_id' => 0,
			// lowest possible level since we don't want
			// any nesting of h-tags in the lead section
			'level' => 6,
			'lead' => true
		];
		$leadSection['container']->setAttribute( 'data-mw-section-id', '0' );

		// Global $state
		$state = [
			'env' => $env,
			'frame' => $options['frame'],
			'count' => 1,
			'doc' => $doc,
			'rootNode' => $root,
			'sectionNumber' => 0,
			'inTemplate' => false,
			'tplsAndExtsToExamine' => []
		];
		$this->wrapSectionsInDOM( $state, $leadSection, $root );

		// There will always be a lead section, even if sometimes it only
		// contains whitespace + comments.
		$root->insertBefore( $leadSection['container'], $root->firstChild );

		// Resolve template conflicts after all sections have been added to the DOM
		$this->resolveTplExtSectionConflicts( $state );
	}
}
