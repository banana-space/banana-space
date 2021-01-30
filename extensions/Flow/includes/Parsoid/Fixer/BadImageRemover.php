<?php

namespace Flow\Parsoid\Fixer;

use DOMElement;
use DOMNode;
use Flow\Conversion\Utils;
use Flow\Parsoid\Fixer;
use Title;

/**
 * Parsoid ignores bad_image_list. With good reason: bad images should only be
 * removed when rendering the content, not when it's created. This
 * class updates HTML content from Parsoid by deleting inappropriate images, as
 * defined by wfIsBadImage().
 *
 * Usage:
 *
 *	$badImageRemover = new BadImageRemover();
 *
 *	// Before outputting content
 *	$content = $badImageRemover->apply( $foo->getContent(), $title );
 */

class BadImageRemover implements Fixer {
	/**
	 * @var callable
	 */
	protected $isFiltered;

	/**
	 * @param callable $isFiltered (string, Title) returning bool. First
	 *  argument is the image name to check. Second argument is the page on
	 *  which the image occurs. Returns true when the image should be filtered.
	 */
	public function __construct( $isFiltered ) {
		$this->isFiltered = $isFiltered;
	}

	/**
	 * @return string
	 */
	public function getXPath() {
		return '//figure[starts-with(@typeof,"mw:Image")]//img[@resource] | ' .
			'//figure-inline[starts-with(@typeof,"mw:Image")]//img[@resource] | ' .
			'//span[starts-with(@typeof,"mw:Image")]//img[@resource]';
	}

	/**
	 * Receives an html string. It find all images and run them through
	 * wfIsBadImage() to determine if the image can be shown.
	 *
	 * @param DOMNode $node
	 * @param Title $title
	 * @throws \MWException
	 */
	public function apply( DOMNode $node, Title $title ) {
		if ( !$node instanceof DOMElement ) {
			return;
		}

		$resource = $node->getAttribute( 'resource' );
		if ( $resource === '' ) {
			return;
		}

		$image = Utils::createRelativeTitle( rawurldecode( $resource ), $title );
		if ( !$image ) {
			wfDebugLog( 'Flow', __METHOD__ . ': Could not construct title for node: ' .
				$node->ownerDocument->saveXML( $node ) );
			return;
		}

		if ( !( $this->isFiltered )( $image->getDBkey(), $title ) ) {
			return;
		}

		// Move up the DOM and remove the typeof="mw:Image" node
		$nodeToRemove = $node->parentNode;
		while ( $nodeToRemove instanceof DOMElement &&
			strpos( $nodeToRemove->getAttribute( 'typeof' ), 'mw:Image' ) !== 0
		) {
			$nodeToRemove = $nodeToRemove->parentNode;
		}
		if ( !$nodeToRemove ) {
			throw new \MWException( 'Did not find parent mw:Image to remove' );
		}
		$nodeToRemove->parentNode->removeChild( $nodeToRemove );
	}
}
