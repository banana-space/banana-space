<?php

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Html2Wt;

use DOMElement;
use DOMNode;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Core\DomSourceRange;
use Wikimedia\Parsoid\Html2Wt\DOMHandlers\DOMHandler;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;

class Separators {

	private const WS_COMMENTS_SEP_STRING = '( +)' .
		'(' . Utils::COMMENT_REGEXP_FRAGMENT . '[^\n]*' . ')?$';

	/**
	 * spaces + (comments and anything but newline)?
	 */
	private const WS_COMMENTS_SEP_REGEXP = '/' . self::WS_COMMENTS_SEP_STRING . '/D';

	/**
	 * multiple newlines followed by spaces + (comments and anything but newline)?
	 */
	private const NL_WS_COMMENTS_SEP_REGEXP = '/\n+' . self::WS_COMMENTS_SEP_STRING . '/D';

	/**
	 * @var SerializerState
	 */
	private $state;

	/**
	 * @var Env
	 */
	private $env;

	/**
	 * Clean up the constraints object to prevent excessively verbose output
	 * and clog up log files / test runs.
	 *
	 * @param array $constraints
	 * @return array
	 */
	private static function loggableConstraints( array $constraints ): array {
		$c = [
			'a' => $constraints['a'] ?? null,
			'b' => $constraints['b'] ?? null,
			'min' => $constraints['min'] ?? null,
			'max' => $constraints['max'] ?? null,
			'force' => $constraints['force'] ?? false,
		];
		if ( !empty( $constraints['constraintInfo'] ) ) {
			$constraintInfo = $constraints['constraintInfo'];
			$c['constraintInfo'] = [
				'onSOL' => $constraintInfo['onSOL'] ?? false,
				'sepType' => $constraintInfo['sepType'] ?? null,
				'nodeA' => $constraintInfo['nodeA']->nodeName ?? null,
				'nodeB' => $constraintInfo['nodeB']->nodeName ?? null,
			];
		}
		return $c;
	}

	/**
	 * @param DOMNode $n
	 * @return string|null
	 */
	private static function precedingSeparatorTxt( DOMNode $n ): ?string {
		// Given the CSS white-space property and specifically,
		// "pre" and "pre-line" values for this property, it seems that any
		// sane HTML editor would have to preserve IEW in HTML documents
		// to preserve rendering. One use-case where an editor might change
		// IEW drastically would be when the user explicitly requests it
		// (Ex: pretty-printing of raw source code).
		//
		// For now, we are going to exploit this.  This information is
		// only used to extrapolate DSR values and extract a separator
		// string from source, and is only used locally.  In addition,
		// the extracted text is verified for being a valid separator.
		//
		// So, at worst, this can create a local dirty diff around separators
		// and at best, it gets us a clean diff.

		$buf = '';
		$orig = $n;
		while ( $n ) {
			if ( DOMUtils::isIEW( $n ) ) {
				$buf .= $n->nodeValue;
			} elseif ( DOMUtils::isComment( $n ) ) {
				$buf .= '<!--';
				$buf .= $n->nodeValue;
				$buf .= '-->';
			} elseif ( $n !== $orig ) { // dont return if input node!
				return null;
			}

			$n = $n->previousSibling;
		}

		return $buf;
	}

	/**
	 * Helper for updateSeparatorConstraints.
	 *
	 * Collects, checks and integrates separator newline requirements to a simple
	 * min, max structure.
	 *
	 * @param SerializerState $state
	 * @param DOMNode $nodeA
	 * @param array $aCons
	 * @param DOMNode $nodeB
	 * @param array $bCons
	 * @return array
	 */
	private static function getSepNlConstraints(
		SerializerState $state, DOMNode $nodeA, array $aCons, DOMNode $nodeB, array $bCons
	): array {
		$env = $state->getEnv();

		$nlConstraints = [
			'min' => $aCons['min'] ?? null,
			'max' => $aCons['max'] ?? null,
			'force' => ( $aCons['force'] ?? false ) ?: $bCons['force'] ?? false,
			'constraintInfo' => [],
		];

		// now figure out if this conflicts with the nlConstraints so far
		if ( isset( $bCons['min'] ) ) {
			if ( $nlConstraints['max'] !== null && $nlConstraints['max'] < $bCons['min'] ) {
				// Conflict, warn and let nodeB win.
				$env->log(
					'info/html2wt',
					'Incompatible constraints 1:',
					$nodeA->nodeName,
					$nodeB->nodeName,
					self::loggableConstraints( $nlConstraints )
				);
				$nlConstraints['min'] = $bCons['min'];
				$nlConstraints['max'] = $bCons['min'];
			} else {
				$nlConstraints['min'] = max( $nlConstraints['min'] ?? 0, $bCons['min'] );
			}
		}

		if ( isset( $bCons['max'] ) ) {
			if ( $nlConstraints['min'] !== null && $nlConstraints['min'] > $bCons['max'] ) {
				// Conflict, warn and let nodeB win.
				$env->log(
					'info/html2wt',
					'Incompatible constraints 2:',
					$nodeA->nodeName,
					$nodeB->nodeName,
					self::loggableConstraints( $nlConstraints )
				);
				$nlConstraints['min'] = $bCons['max'];
				$nlConstraints['max'] = $bCons['max'];
			} elseif ( $nlConstraints['max'] !== null ) {
				$nlConstraints['max'] = min( $nlConstraints['max'], $bCons['max'] );
			} else {
				$nlConstraints['max'] = $bCons['max'];
			}
		}

		if ( $nlConstraints['max'] === null ) {
			// Anything more than two lines will trigger paragraphs, so default to
			// two if nothing is specified.
			$nlConstraints['max'] = 2;
		}

		return $nlConstraints;
	}

	/**
	 * Create a separator given a (potentially empty) separator text and newline
	 * constraints.
	 *
	 * @param SerializerState $state
	 * @param string $sep
	 * @param array $nlConstraints
	 * @return string
	 */
	private static function makeSeparator(
		SerializerState $state, string $sep, array $nlConstraints
	): string {
		$origSep = $sep;

		// Split on comment/ws-only lines, consuming subsequent newlines since
		// those lines are ignored by the PHP parser
		// Ignore lines with ws and a single comment in them
		$splitRe = implode( [ "#(?:\n(?:[ \t]*?",
				Utils::COMMENT_REGEXP_FRAGMENT,
				"[ \t]*?)+(?=\n))+|",
				Utils::COMMENT_REGEXP_FRAGMENT,
				"#"
			] );
		$sepNlCount = preg_match_all( '/\n/', implode( preg_split( $splitRe, $sep ) ) );
		$minNls = $nlConstraints['min'] ?? 0;

		if ( $state->atStartOfOutput && $minNls > 0 ) {
			// Skip first newline as we are in start-of-line context
			$minNls--;
		}

		if ( $minNls > 0 && $sepNlCount < $minNls ) {
			// Append newlines
			$nlBuf = [];
			for ( $i = 0; $i < ( $minNls - $sepNlCount ); $i++ ) {
				$nlBuf[] = "\n";
			}

			/* ------------------------------------------------------------------
			 * The following two heuristics try to do a best-guess on where to
			 * add the newlines relative to nodeA and nodeB that best matches
			 * wikitext output expectations.
			 *
			 * 1. In a parent-child separator scenario, where the first child of
			 *    nodeA is not an element, it could have contributed to the separator.
			 *    In that case, the newlines should be prepended because they
			 *    usually correspond to the parent's constraints,
			 *    and the separator was plucked from the child.
			 *
			 *    Try html2wt on this snippet:
			 *
			 *    a<p><!--cmt-->b</p>
			 *
			 * 2. In a sibling scenario, if nodeB is a literal-HTML element, nodeA is
			 *    forcing the newline and hence the newline should be emitted right
			 *    after it.
			 *
			 *    Try html2wt on this snippet:
			 *
			 *    <p>foo</p>  <p data-parsoid='{"stx":"html"}'>bar</p>
			 * -------------------------------------------------------------------- */
			$constraintInfo = $nlConstraints['constraintInfo'] ?? [];
			$sepType = $constraintInfo['sepType'] ?? null;
			$nodeA = $constraintInfo['nodeA'] ?? null;
			$nodeB = $constraintInfo['nodeB'] ?? null;
			if (
				$sepType === 'parent-child' &&
				!DOMUtils::isContentNode( DOMUtils::firstNonDeletedChild( $nodeA ) ) &&
				!(
					isset( WikitextConstants::$HTML['ChildTableTags'][$nodeB->nodeName] ) &&
					!WTUtils::isLiteralHTMLNode( $nodeB )
				)
			) {
				$sep = implode( $nlBuf ) . $sep;
			} elseif ( $sepType === 'sibling' && WTUtils::isLiteralHTMLNode( $nodeB ) ) {
				$sep = implode( $nlBuf ) . $sep;
			} else {
				$sep .= implode( $nlBuf );
			}
		} elseif ( isset( $nlConstraints['max'] ) && $sepNlCount > $nlConstraints['max'] ) {
			// Strip some newlines outside of comments
			// Capture separators in a single array with a capturing version of
			// the split regexp, so that we can work on the non-separator bits
			// when stripping newlines.
			$allBits = preg_split( '#(' . PHPUtils::reStrip( $splitRe, '#' ) . ')#',
				$sep, -1, PREG_SPLIT_DELIM_CAPTURE );
			$newBits = [];
			$n = $sepNlCount;

			while ( $n > $nlConstraints['max'] ) {
				$bit = array_pop( $allBits );
				while ( $bit && preg_match( $splitRe, $bit ) ) {
					// skip comments
					$newBits[] = $bit;
					$bit = array_pop( $allBits );
				}
				while ( $n > $nlConstraints['max'] && preg_match( '/\n/', $bit ) ) {
					$bit = preg_replace( '/\n([^\n]*)/', '$1', $bit, 1 );
					$n--;
				}
				$newBits[] = $bit;
			}
			$newBits = array_merge( $allBits, array_reverse( $newBits ) );
			$sep = implode( $newBits );
		}

		$state->getEnv()->log(
			'debug/wts/sep',
			'make-new   |',
			function () use ( $nlConstraints, $sepNlCount, $minNls, $sep, $origSep ) {
				$constraints = Utils::clone( $nlConstraints );
				unset( $constraints['constraintInfo'] );
				return PHPUtils::jsonEncode( $sep ) . ', ' . PHPUtils::jsonEncode( $origSep ) . ', ' .
					$minNls . ', ' . $sepNlCount . ', ' . PHPUtils::jsonEncode( $constraints );
			}
		);

		return $sep;
	}

	/**
	 * Merge two constraints.
	 * @param Env $env
	 * @param array $oldConstraints
	 * @param array $newConstraints
	 * @return array
	 */
	private static function mergeConstraints(
		Env $env, array $oldConstraints, array $newConstraints
	): array {
		$res = [
			'min' => max( $oldConstraints['min'] ?? 0, $newConstraints['min'] ?? 0 ),
			'max' => min( $oldConstraints['max'] ?? 2, $newConstraints['max'] ?? 2 ),
			'force' => ( $oldConstraints['force'] ?? false ) ?: $newConstraints['force'] ?? false,
			'constraintInfo' => [],
		];

		if ( $res['min'] > $res['max'] ) {
			// If oldConstraints.force is set, older constraints win
			if ( empty( $oldConstraints['force'] ) ) {
				// let newConstraints win, but complain
				if ( isset( $newConstraints['max'] ) && $newConstraints['max'] > $res['min'] ) {
					$res['max'] = $newConstraints['max'];
				} elseif ( !empty( $newConstraints['min'] ) && $newConstraints['min'] < $res['min'] ) {
					$res['min'] = $newConstraints['min'];
				}
			}
			$res['max'] = $res['min'];
			$env->log(
				'info/html2wt',
				'Incompatible constraints (merge):',
				$res,
				self::loggableConstraints( $oldConstraints ),
				self::loggableConstraints( $newConstraints )
			);
		}

		return $res;
	}

	/**
	 * @param DOMNode $node
	 * @return string
	 */
	public static function debugOut( DOMNode $node ): string {
		$value = '';
		if ( $node instanceof DOMElement ) {
			$value = DOMCompat::getOuterHTML( $node );
		}
		if ( !$value ) {
			$value = $node->nodeValue;
		}
		return mb_substr( PHPUtils::jsonEncode( $value ), 0, 40 );
	}

	/**
	 * Figure out separator constraints and merge them with existing constraints
	 * in state so that they can be emitted when the next content emits source.
	 *
	 * @param DOMNode $nodeA
	 * @param DOMHandler $sepHandlerA
	 * @param DOMNode $nodeB
	 * @param DOMHandler $sepHandlerB
	 */
	public function updateSeparatorConstraints(
		DOMNode $nodeA, DOMHandler $sepHandlerA, DOMNode $nodeB, DOMHandler $sepHandlerB
	): void {
		$state = $this->state;

		// Non-element DOM nodes will have a null dom handler
		if ( $nodeB->parentNode === $nodeA ) {
			// parent-child separator, nodeA parent of nodeB
			'@phan-var \DOMElement $nodeA'; // @var \DOMElement $nodeA
			$sepType = 'parent-child';
			$aCons = $sepHandlerA->firstChild( $nodeA, $nodeB, $state );
			$bCons = $nodeB instanceof DOMElement ? $sepHandlerB->before( $nodeB, $nodeA, $state ) : [];
			$nlConstraints = self::getSepNlConstraints( $state, $nodeA, $aCons, $nodeB, $bCons );
		} elseif ( $nodeA->parentNode === $nodeB ) {
			// parent-child separator, nodeB parent of nodeA
			'@phan-var \DOMElement $nodeB'; // @var \DOMElement $nodeA
			$sepType = 'child-parent';
			$aCons = $nodeA instanceof DOMElement ? $sepHandlerA->after( $nodeA, $nodeB, $state ) : [];
			$bCons = $sepHandlerB->lastChild( $nodeB, $nodeA, $state );
			$nlConstraints = self::getSepNlConstraints( $state, $nodeA, $aCons, $nodeB, $bCons );
		} else {
			// sibling separator
			$sepType = 'sibling';
			$aCons = $nodeA instanceof DOMElement ? $sepHandlerA->after( $nodeA, $nodeB, $state ) : [];
			$bCons = $nodeB instanceof DOMElement ? $sepHandlerB->before( $nodeB, $nodeA, $state ) : [];
			$nlConstraints = self::getSepNlConstraints( $state, $nodeA, $aCons, $nodeB, $bCons );
		}

		if ( !empty( $state->sep->constraints ) ) {
			// Merge the constraints
			$state->sep->constraints = self::mergeConstraints(
				$this->env,
				$state->sep->constraints,
				$nlConstraints
			);
		} else {
			$state->sep->constraints = $nlConstraints;
		}

		$this->env->log(
			'debug/wts/sep',
			function () use ( $sepType, $nodeA, $nodeB, $state ) {
				return 'constraint' . ' | ' . $sepType . ' | <' . $nodeA->nodeName . ',' . $nodeB->nodeName .
					'>' . ' | ' . PHPUtils::jsonEncode( $state->sep->constraints ) . ' | ' .
					self::debugOut( $nodeA ) . ' | ' . self::debugOut( $nodeB );
			}
		);

		$state->sep->constraints['constraintInfo'] = [
			'onSOL' => $state->onSOL,
			// force SOL state when separator is built/emitted
			'forceSOL' => $sepHandlerB->isForceSOL(),
			'sepType' => $sepType,
			'nodeA' => $nodeA,
			'nodeB' => $nodeB,
		];
	}

	/**
	 * Separators constructor.
	 *
	 * @param Env $env
	 * @param SerializerState $state
	 */
	public function __construct( Env $env, SerializerState $state ) {
		$this->env = $env;
		$this->state = $state;
	}

	/**
	 * @param SerializerState $state
	 * @param string $sep
	 * @param array $nlConstraints
	 * @return string
	 */
	private function makeSepIndentPreSafe(
		SerializerState $state, string $sep, array $nlConstraints
	): string {
		$constraintInfo = $nlConstraints['constraintInfo'] ?? [];
		$sepType = $constraintInfo['sepType'] ?? null;
		$nodeA = $constraintInfo['nodeA'] ?? null;
		$nodeB = $constraintInfo['nodeB'] ?? null;
		$forceSOL = ( $constraintInfo['forceSOL'] ?? false ) && $sepType !== 'child-parent';
		$origNodeB = $nodeB;

		// Ex: "<div>foo</div>\n <span>bar</span>"
		//
		// We also should test for onSOL state to deal with HTML like
		// <ul> <li>foo</li></ul>
		// and strip the leading space before non-indent-pre-safe tags
		if ( !$state->inPHPBlock && !$state->inIndentPre &&
			( preg_match( self::NL_WS_COMMENTS_SEP_REGEXP, $sep ) ||
				preg_match( self::WS_COMMENTS_SEP_REGEXP, $sep ) &&
				( !empty( $constraintInfo['onSOL'] ) || $forceSOL )
			)
		) {
			// 'sep' is the separator before 'nodeB' and it has leading spaces on a newline.
			// We have to decide whether that leading space will trigger indent-pres in wikitext.
			// The decision depends on where this separator will be emitted relative
			// to 'nodeA' and 'nodeB'.

			$isIndentPreSafe = false;

			// Example sepType scenarios:
			//
			// 1. sibling
			// <div>foo</div>
			// <span>bar</span>
			// The span will be wrapped in an indent-pre if the leading space
			// is not stripped since span is not a block tag
			//
			// 2. child-parent
			// <span>foo
			// </span>bar
			// The " </span>bar" will be wrapped in an indent-pre if the
			// leading space is not stripped since span is not a block tag
			//
			// 3. parent-child
			// <div>foo
			// <span>bar</span>
			// </div>
			//
			// In all cases, only block-tags prevent indent-pres.
			// (except for a special case for <br> nodes)
			if ( $nodeB && WTSUtils::precedingSpaceSuppressesIndentPre( $nodeB, $origNodeB ) ) {
				$isIndentPreSafe = true;
			} elseif ( $sepType === 'sibling' || $nodeA && DOMUtils::atTheTop( $nodeA ) ) {
				Assert::invariant( !DOMUtils::atTheTop( $nodeA ) || $sepType === 'parent-child', __METHOD__ );

				// 'nodeB' is the first non-separator child of 'nodeA'.
				//
				// Walk past sol-transparent nodes in the right-sibling chain
				// of 'nodeB' till we establish indent-pre safety.
				while ( $nodeB &&
					( DOMUtils::isDiffMarker( $nodeB ) || WTUtils::emitsSolTransparentSingleLineWT( $nodeB ) )
				) {
					$nodeB = $nodeB->nextSibling;
				}

				$isIndentPreSafe = !$nodeB || WTSUtils::precedingSpaceSuppressesIndentPre( $nodeB, $origNodeB );
			}

			// Check whether nodeB is nested inside an element that suppresses
			// indent-pres.
			//
			// 1. Walk up past zero-wikitext width nodes in the ancestor chain
			// of 'nodeB' till we establish indent-pre safety.
			// If nodeB uses HTML syntax, obviously it is not zero width!
			//
			// 2. Check if the ancestor is a weak/strong indent-pre suppressing tag.
			// - Weak indent-pre suppressing tags only suppress indent-pres
			// within immediate children.
			// - Strong indent-pre suppressing tags suppress indent-pres
			// in entire DOM subtree rooted at that node.

			if ( $nodeB && !DOMUtils::atTheTop( $nodeB ) ) {
				$parentB = $nodeB->parentNode; // could be nodeA
				while ( WTUtils::isZeroWidthWikitextElt( $parentB ) ) {
					$parentB = $parentB->parentNode;
				}

				if ( isset( WikitextConstants::$WeakIndentPreSuppressingTags[$parentB->nodeName] ) ) {
					$isIndentPreSafe = true;
				} else {
					while ( !DOMUtils::atTheTop( $parentB ) ) {
						if ( isset( WikitextConstants::$StrongIndentPreSuppressingTags[$parentB->nodeName] ) &&
								( $parentB->nodeName !== 'p' || WTUtils::isLiteralHTMLNode( $parentB ) ) ) {
							$isIndentPreSafe = true;
						}
						$parentB = $parentB->parentNode;
					}
				}
			}

			$stripLeadingSpace = ( !empty( $constraintInfo['onSOL'] ) || $forceSOL ) &&
				$nodeB && isset( WikitextConstants::$SolSpaceSensitiveTags[$nodeB->nodeName] );
			if ( !$isIndentPreSafe || $stripLeadingSpace ) {
				// Wrap non-nl ws from last line, but preserve comments.
				// This avoids triggering indent-pres.
				$sep = preg_replace_callback(
					self::WS_COMMENTS_SEP_REGEXP,
					function ( $matches ) use ( $stripLeadingSpace, $state ) {
						$rest = $matches[2] ?? '';
						if ( $stripLeadingSpace ) {
							// No other option but to strip the leading space
							return $rest;
						} else {
							// Since we nowiki-ed, we are no longer in sol state
							$state->onSOL = false;
							$state->hasIndentPreNowikis = true;
							return '<nowiki>' . $matches[1] . '</nowiki>' . $rest;
						}
					},
					$sep
				);
			}
		}

		$state->getEnv()->log(
			'debug/wts/sep',
			'ipre-safe  |',
			function () use ( $sep, $nlConstraints ) {
				$constraints = Utils::clone( $nlConstraints );
				unset( $constraints['constraintInfo'] );
				return PHPUtils::jsonEncode( $sep ) . ', ' . PHPUtils::jsonEncode( $constraints );
			}
		);

		return $sep;
	}

	/**
	 * Serializing auto inserted content should invalidate the original separator
	 * @param DOMElement $node
	 * @return DomSourceRange|null
	 */
	private static function handleAutoInserted( DOMElement $node ): ?DomSourceRange {
		$dp = DOMDataUtils::getDataParsoid( $node );
		if ( !isset( $dp->dsr ) ) {
			return null;
		}

		$dsr = clone $dp->dsr;
		if ( !empty( $dp->autoInsertedStart ) ) {
			$dsr->openWidth = null;
		}
		if ( !empty( $dp->autoInsertedEnd ) ) {
			$dsr->closeWidth = null;
		}
		return $dsr;
	}

	/**
	 * Emit a separator based on the collected (and merged) constraints
	 * and existing separator text. Called when new output is triggered.
	 * @param DOMNode $node
	 * @return string|null
	 * @suppress PhanTypeMismatchArgument Mixing DOMNode and DOMElement
	 */
	public function buildSep( DOMNode $node ): ?string {
		$state = $this->state;
		$origNode = $node;
		$prevNode = $state->sep->lastSourceNode;
		$sep = null;
		$dsrA = null;
		$dsrB = null;

		/* ----------------------------------------------------------------------
		 * Assuming we have access to the original source, we can use it only if:
		 * - If we are in selser mode AND
		 *   . this node is not part of a subtree that has been marked 'modified'
		 *     (massively edited, either in actuality or because DOMDiff is not smart enough).
		 *   . neither node is adjacent to a deleted block node
		 *     (see the extensive comment in SSP.emitChunk in wts.SerializerState.js)
		 *
		 * In other scenarios, DSR values on "adjacent" nodes in the edited DOM
		 * may not reflect deleted content between them.
		 * ---------------------------------------------------------------------- */
		$again = ( $node === $prevNode );
		$origSepUsable = !$again && $state->selserMode && !$state->inModifiedContent &&
			!WTSUtils::nextToDeletedBlockNodeInWT( $prevNode, true ) &&
			!WTSUtils::nextToDeletedBlockNodeInWT( $node, false ) &&
			WTSUtils::origSrcValidInEditedContext( $state->getEnv(), $prevNode ) &&
			WTSUtils::origSrcValidInEditedContext( $state->getEnv(), $node );

		if ( $origSepUsable ) {
			if ( !DOMUtils::isElt( $prevNode ) ) {
				// Check if this is the last child of a zero-width element, and use
				// that for dsr purposes instead. Typical case: text in p.
				if ( !$prevNode->nextSibling && $prevNode->parentNode && $prevNode->parentNode !== $node &&
					( DOMDataUtils::getDataParsoid( $prevNode->parentNode )->dsr ?? null ) &&
					( DOMDataUtils::getDataParsoid( $prevNode->parentNode )->dsr->closeWidth ?? null ) === 0
				) {
					$dsrA = self::handleAutoInserted( $prevNode->parentNode );
				} elseif ( $prevNode->previousSibling &&
					$prevNode->previousSibling instanceof DOMElement &&
					// FIXME: Not sure why we need this check because data-parsoid
					// is loaded on all nodes. mw:Diffmarker maybe? But, if so, why?
					// Should be fixed.
					!empty( DOMDataUtils::getDataParsoid( $prevNode->previousSibling )->dsr ) &&
					// Don't extrapolate if the string was potentially changed
					!DiffUtils::directChildrenChanged( $node->parentNode, $this->env )
				) {
					$endDsr = DOMDataUtils::getDataParsoid( $prevNode->previousSibling )->dsr->end ?? null;
					$correction = null;
					if ( is_int( $endDsr ) ) {
						if ( DOMUtils::isComment( $prevNode ) ) {
							'@phan-var \DOMComment $prevNode'; // @var \DOMComment $prevNode
							$correction = WTUtils::decodedCommentLength( $prevNode );
						} else {
							$correction = strlen( $prevNode->nodeValue );
						}
						$dsrA = new DomSourceRange(
							$endDsr,
							$endDsr + $correction + WTUtils::indentPreDSRCorrection( $prevNode ),
							0,
							0
						);
					}
				}
			} else {
				$dsrA = self::handleAutoInserted( $prevNode );
			}

			if ( !$dsrA ) {
				// nothing to do -- no reason to compute dsrB if dsrA is null
			} elseif ( !DOMUtils::isElt( $node ) ) {
				// If this is the child of a zero-width element
				// and is only preceded by separator elements, we
				// can use the parent for dsr after correcting the dsr
				// with the separator run length.
				//
				// 1. text in p.
				// 2. ws-only child of a node with auto-inserted start tag
				// Ex: "<span> <s>x</span> </s>" --> <span> <s>x</s*></span><s*> </s>
				// 3. ws-only children of a node with auto-inserted start tag
				// Ex: "{|\n|-\n <!--foo--> \n|}"
				$parentNode = $node->parentNode;
				/** @var DOMElement $parentNode */
				DOMUtils::assertElt( $parentNode );

				$npDP = DOMDataUtils::getDataParsoid( $parentNode );
				if ( $parentNode !== $prevNode && isset( $npDP->dsr ) && $npDP->dsr->openWidth === 0 ) {
					$sepTxt = self::precedingSeparatorTxt( $node );
					if ( $sepTxt !== null ) {
						$dsrB = $npDP->dsr;
						if ( is_int( $dsrB->start ) && strlen( $sepTxt ) > 0 ) {
							$dsrB = clone $dsrB;
							$dsrB->start += strlen( $sepTxt );
						}
					}
				}
			} else {
				if ( $prevNode->parentNode === $node ) {
					/** @var DOMElement $node */
					DOMUtils::assertElt( $node );
					// FIXME: Maybe we shouldn't set dsr in the dsr pass if both aren't valid?
					//
					// When we are in the lastChild sep scenario and the parent doesn't have
					// useable dsr, if possible, walk up the ancestor nodes till we find
					// a dsr-bearing node
					//
					// This fix is needed to handle trailing newlines in this wikitext:
					// [[File:foo.jpg|thumb|300px|foo\n{{1x|A}}\n{{1x|B}}\n{{1x|C}}\n\n]]
					while ( !$node->nextSibling && !DOMUtils::atTheTop( $node ) &&
						( empty( DOMDataUtils::getDataParsoid( $node )->dsr ) ||
							DOMDataUtils::getDataParsoid( $node )->dsr->start === null ||
							DOMDataUtils::getDataParsoid( $node )->dsr->end === null
						)
					) {
						$node = $node->parentNode;
					}
				}

				// The top node could be a document fragment, which is not
				// an element, and so getDataParsoid will return `null`.
				$dsrB = $node instanceof DOMElement ? self::handleAutoInserted( $node ) : null;
			}

			// FIXME: Maybe we shouldn't set dsr in the dsr pass if both aren't valid?
			if ( Utils::isValidDSR( $dsrA ) && Utils::isValidDSR( $dsrB ) ) {
				// Figure out containment relationship
				if ( $dsrA->start <= $dsrB->start ) {
					if ( $dsrB->end <= $dsrA->end ) {
						if ( $dsrA->start === $dsrB->start && $dsrA->end === $dsrB->end ) {
							// Both have the same dsr range, so there can't be any
							// separators between them
							$sep = '';
						} elseif ( ( $dsrA->openWidth ?? null ) !== null ) {
							// B in A, from parent to child
							$sep = $state->getOrigSrc( $dsrA->innerStart(), $dsrB->start );
						}
					} elseif ( $dsrA->end <= $dsrB->start ) {
						// B following A (siblingish)
						$sep = $state->getOrigSrc( $dsrA->end, $dsrB->start );
					} elseif ( ( $dsrB->closeWidth ?? null ) !== null ) {
						// A in B, from child to parent
						$sep = $state->getOrigSrc( $dsrA->end, $dsrB->innerEnd() );
					}
				} elseif ( $dsrA->end <= $dsrB->end ) {
					if ( ( $dsrB->closeWidth ?? null ) !== null ) {
						// A in B, from child to parent
						$sep = $state->getOrigSrc( $dsrA->end, $dsrB->innerEnd() );
					}
				} else {
					$this->env->log( 'info/html2wt', 'dsr backwards: should not happen!' );
				}
			}
		}

		$this->env->log(
			'debug/wts/sep',
			function () use ( $prevNode, $origNode, $sep, $state ) {
				return 'maybe-sep  | ' . 'prev:' . ( $prevNode ? $prevNode->nodeName : '--none--' ) .
					', node:' . ( $origNode->nodeName ?? '--none--' ) .
					', sep: ' . PHPUtils::jsonEncode( $sep ) .
					', state.sep.src: ' . PHPUtils::jsonEncode( $state->sep->src ?? null );
			}
		);

		// 1. Verify that the separator is really one (has to be whitespace and comments)
		// 2. If the separator is being emitted before a node that emits sol-transparent WT,
		// go through makeSeparator to verify indent-pre constraints are met.
		$sepConstraints = $state->sep->constraints ?? [ 'max' => 0 ];
		if ( $sep === null ||
			!WTSUtils::isValidSep( $sep ) ||
			( $state->sep->src && $state->sep->src !== $sep )
		) {
			if ( !empty( $state->sep->constraints ) || !empty( $state->sep->src ) ) {
				// TODO: set modified flag if start or end node (but not both) are
				// modified / new so that the selser can use the separator
				$sep = self::makeSeparator( $state, $state->sep->src ?? '', $sepConstraints );
			} else {
				$sep = null;
			}
		}

		if ( $sep !== null ) {
			$sep = self::makeSepIndentPreSafe( $state, $sep, $sepConstraints );
		}
		return $sep;
	}
}
