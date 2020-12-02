<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMDocument;
use DOMElement;
use DOMNode;
use stdClass;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

/**
 * Non-IEW (inter-element-whitespace) can only be found in <td> <th> and
 * <caption> tags in a table.  If found elsewhere within a table, such
 * content will be moved out of the table and be "adopted" by the table's
 * sibling ("foster parent"). The content that gets adopted is "fostered
 * content".
 *
 * http://www.w3.org/TR/html5/syntax.html#foster-parent
 * @module
 */
class MarkFosteredContent implements Wt2HtmlDOMProcessor {
	/**
	 * Create a new DOM node with attributes.
	 *
	 * @param DOMDocument $document
	 * @param string $type
	 * @param array $attrs
	 * @return DOMElement
	 */
	private static function createNodeWithAttributes(
		DOMDocument $document, string $type, array $attrs
	): DOMElement {
		$node = $document->createElement( $type );
		DOMUtils::addAttributes( $node, $attrs );
		return $node;
	}

	/**
	 * Cleans up transclusion shadows, keeping track of fostered transclusions
	 *
	 * @param DOMNode $node
	 * @return bool
	 */
	private static function removeTransclusionShadows( DOMNode $node ): bool {
		$sibling = null;
		$fosteredTransclusions = false;
		if ( $node instanceof DOMElement ) {
			if ( DOMUtils::isMarkerMeta( $node, 'mw:TransclusionShadow' ) ) {
				$node->parentNode->removeChild( $node );
				return true;
			} elseif ( !empty( DOMDataUtils::getDataParsoid( $node )->tmp->inTransclusion ) ) {
				$fosteredTransclusions = true;
			}
			$node = $node->firstChild;
			while ( $node ) {
				$sibling = $node->nextSibling;
				if ( self::removeTransclusionShadows( $node ) ) {
					$fosteredTransclusions = true;
				}
				$node = $sibling;
			}
		}
		return $fosteredTransclusions;
	}

	/**
	 * Inserts metas around the fosterbox and table
	 *
	 * @param Env $env
	 * @param DOMNode $fosterBox
	 * @param DOMElement $table
	 */
	private static function insertTransclusionMetas(
		Env $env, DOMNode $fosterBox, DOMElement $table
	): void {
		$aboutId = $env->newAboutId();

		// You might be asking yourself, why is $table->dataParsoid->tsr->end always
		// present? The earlier implementation searched the table's siblings for
		// their tsr->start. However, encapsulation doesn't happen when the foster box,
		// and thus the table, are in the transclusion.
		$s = self::createNodeWithAttributes( $fosterBox->ownerDocument, 'meta', [
				'about' => $aboutId,
				'id' => substr( $aboutId, 1 ),
				'typeof' => 'mw:Transclusion',
			]
		);
		DOMDataUtils::setDataParsoid( $s, (object)[
				'tsr' => Utils::clone( DOMDataUtils::getDataParsoid( $table )->tsr ),
				'tmp' => PHPUtils::arrayToObject( [ 'fromFoster' => true ] ),
			]
		);
		$fosterBox->parentNode->insertBefore( $s, $fosterBox );

		$e = self::createNodeWithAttributes( $table->ownerDocument, 'meta', [
				'about' => $aboutId,
				'typeof' => 'mw:Transclusion/End',
			]
		);

		$sibling = $table->nextSibling;
		$beforeText = null;

		// Skip past the table end, mw:shadow and any transclusions that
		// start inside the table. There may be newlines and comments in
		// between so keep track of that, and backtrack when necessary.
		while ( $sibling ) {
			if ( !WTUtils::isTplStartMarkerMeta( $sibling ) &&
				( WTUtils::hasParsoidAboutId( $sibling ) ||
					DOMUtils::isMarkerMeta( $sibling, 'mw:EndTag' ) ||
					DOMUtils::isMarkerMeta( $sibling, 'mw:TransclusionShadow' )
				)
			) {
				$sibling = $sibling->nextSibling;
				$beforeText = null;
			} elseif ( DOMUtils::isComment( $sibling ) || DOMUtils::isText( $sibling ) ) {
				if ( !$beforeText ) {
					$beforeText = $sibling;
				}
				$sibling = $sibling->nextSibling;
			} else {
				break;
			}
		}

		$table->parentNode->insertBefore( $e, $beforeText ?: $sibling );
	}

	/**
	 * @param DOMDocument $doc
	 * @param bool $inPTag
	 * @return DOMElement
	 */
	private static function getFosterContentHolder( DOMDocument $doc, bool $inPTag ): DOMElement {
		$fosterContentHolder = $doc->createElement( $inPTag ? 'span' : 'p' );
		DOMDataUtils::setDataParsoid(
			$fosterContentHolder,
			(object)[ 'fostered' => true, 'tmp' => new stdClass ]
		);
		return $fosterContentHolder;
	}

	/**
	 * Searches for FosterBoxes and does two things when it hits one:
	 * - Marks all nextSiblings as fostered until the accompanying table.
	 * - Wraps the whole thing (table + fosterbox) with transclusion metas if
	 *   there is any fostered transclusion content.
	 *
	 * @param DOMNode $node
	 * @param Env $env
	 */
	private static function processRecursively( DOMNode $node, Env $env ): void {
		$c = $node->firstChild;

		while ( $c ) {
			$sibling = $c->nextSibling;
			$fosteredTransclusions = false;

			if ( DOMUtils::hasNameAndTypeOf( $c, 'table', 'mw:FosterBox' ) ) {
				$inPTag = DOMUtils::hasAncestorOfName( $c->parentNode, 'p' );
				$fosterContentHolder = self::getFosterContentHolder( $c->ownerDocument, $inPTag );

				// mark as fostered until we hit the table
				while ( $sibling && ( !DOMUtils::isElt( $sibling ) || $sibling->nodeName !== 'table' ) ) {
					$next = $sibling->nextSibling;
					if ( $sibling instanceof DOMElement ) {
						// TODO: Note the similarity here with the p-wrapping pass.
						// This can likely be combined in some more maintainable way.
						if (
							DOMUtils::isRemexBlockNode( $sibling ) ||
							WTUtils::emitsSolTransparentSingleLineWT( $sibling )
						) {
							// Block nodes don't need to be wrapped in a p-tag either.
							// Links, includeonly directives, and other rendering-transparent
							// nodes dont need wrappers. sol-transparent wikitext generate
							// rendering-transparent nodes and we use that helper as a proxy here.
							DOMDataUtils::getDataParsoid( $sibling )->fostered = true;

							// If the foster content holder is not empty,
							// close it and get a new content holder.
							if ( $fosterContentHolder->hasChildNodes() ) {
								$sibling->parentNode->insertBefore( $fosterContentHolder, $sibling );
								$fosterContentHolder = self::getFosterContentHolder( $sibling->ownerDocument, $inPTag );
							}
						} else {
							$fosterContentHolder->appendChild( $sibling );
						}

						if ( self::removeTransclusionShadows( $sibling ) ) {
							$fosteredTransclusions = true;
						}
					} else {
						$fosterContentHolder->appendChild( $sibling );
					}
					$sibling = $next;
				}

				$table = $sibling;

				// we should be able to reach the table from the fosterbox
				Assert::invariant(
					$table && $table instanceof DOMElement && $table->nodeName === 'table',
					"Table isn't a sibling. Something's amiss!"
				);

				if ( $fosterContentHolder->hasChildNodes() ) {
					$table->parentNode->insertBefore( $fosterContentHolder, $table );
				}

				// we have fostered transclusions
				// wrap the whole thing in a transclusion
				if ( $fosteredTransclusions ) {
					self::insertTransclusionMetas( $env, $c, $table );
				}

				// remove the foster box
				$c->parentNode->removeChild( $c );

			} elseif ( DOMUtils::isMarkerMeta( $c, 'mw:TransclusionShadow' ) ) {
				$c->parentNode->removeChild( $c );
			} elseif ( DOMUtils::isElt( $c ) ) {
				if ( $c->hasChildNodes() ) {
					self::processRecursively( $c, $env );
				}
			}

			$c = $sibling;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		self::processRecursively( $root, $env );
	}
}
