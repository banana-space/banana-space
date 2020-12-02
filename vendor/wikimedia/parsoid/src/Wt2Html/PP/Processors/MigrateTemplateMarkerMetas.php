<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Config\WikitextConstants;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\WTUtils;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class MigrateTemplateMarkerMetas implements Wt2HtmlDOMProcessor {
	/**
	 * This will move the start/end-meta closest to the content
	 * that the template/extension produced and improve accuracy
	 * of finding dom ranges and wrapping templates.
	 *
	 * If the last child of a node is a start-meta,
	 * move it up and make it the parent's right sibling.
	 *
	 * If the first child of a node is an end-meta,
	 * move it up and make it the parent's left sibling.
	 *
	 * @param DOMNode $node
	 * @param Env $env
	 */
	private function doMigrate( DOMNode $node, Env $env ): void {
		$c = $node->firstChild;
		while ( $c ) {
			$sibling = $c->nextSibling;
			if ( $c->hasChildNodes() ) {
				$this->doMigrate( $c, $env );
			}
			$c = $sibling;
		}

		// No migration out of BODY
		if ( DOMUtils::isBody( $node ) ) {
			return;
		}

		$firstChild = DOMUtils::firstNonSepChild( $node );
		if ( $firstChild && WTUtils::isTplEndMarkerMeta( $firstChild ) ) {
			// We can migrate the meta-tag across this node's start-tag barrier only
			// if that start-tag is zero-width, or auto-inserted.
			$tagWidth = WikitextConstants::$WtTagWidths[$node->nodeName] ?? null;
			DOMUtils::assertElt( $node );
			if ( ( $tagWidth && $tagWidth[0] === 0 && !WTUtils::isLiteralHTMLNode( $node ) ) ||
				!empty( DOMDataUtils::getDataParsoid( $node )->autoInsertedStart )
			) {
				$sentinel = $firstChild;
				do {
					$firstChild = $node->firstChild;
					$node->parentNode->insertBefore( $firstChild, $node );
				} while ( $sentinel !== $firstChild );
			}
		}

		$lastChild = DOMUtils::lastNonSepChild( $node );
		if ( $lastChild && WTUtils::isTplStartMarkerMeta( $lastChild ) ) {
			// We can migrate the meta-tag across this node's end-tag barrier only
			// if that end-tag is zero-width, or auto-inserted.
			$tagWidth = WikitextConstants::$WtTagWidths[$node->nodeName] ?? null;
			DOMUtils::assertElt( $node );
			if ( ( $tagWidth && $tagWidth[1] === 0 &&
				// Except, don't migrate out of a table since the end meta
				!WTUtils::isLiteralHTMLNode( $node ) ) ||
				// marker may have been fostered and this is more likely to
				// result in a flipped range that isn't enclosed.
				( !empty( DOMDataUtils::getDataParsoid( $node )->autoInsertedEnd ) &&
				$node->nodeName !== 'table' )
			) {
				$sentinel = $lastChild;
				do {
					$lastChild = $node->lastChild;
					$node->parentNode->insertBefore( $lastChild, $node->nextSibling );
				} while ( $sentinel !== $lastChild );
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		$this->doMigrate( $root, $env );
	}
}
