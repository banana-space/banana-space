<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Cite;

use DOMDocument;
use DOMElement;
use stdClass;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * Helper class used by `<references>` implementation.
 */
class RefGroup {

	/**
	 * @var string
	 */
	public $name;

	/**
	 * @var stdClass[]
	 */
	public $refs;

	/**
	 * @var stdClass[]
	 */
	public $indexByName;

	/**
	 * RefGroup constructor.
	 * @param string $group
	 */
	public function __construct( string $group = '' ) {
		$this->name = $group;
		$this->refs = [];
		$this->indexByName = [];
	}

	/**
	 * Generate leading linkbacks
	 * @param ParsoidExtensionAPI $extApi
	 * @param string $href
	 * @param string|null $group
	 * @param string $text
	 * @param DOMDocument $ownerDoc
	 * @return DOMElement
	 */
	private static function createLinkback(
		ParsoidExtensionAPI $extApi,
		string $href, ?string $group, string $text, DOMDocument $ownerDoc
	): DOMElement {
		$a = $ownerDoc->createElement( 'a' );
		$s = $ownerDoc->createElement( 'span' );
		$textNode = $ownerDoc->createTextNode( $text . ' ' );
		$a->setAttribute( 'href', $extApi->getPageUri() . '#' . $href );
		$s->setAttribute( 'class', 'mw-linkback-text' );
		if ( $group ) {
			$a->setAttribute( 'data-mw-group', $group );
		}
		$s->appendChild( $textNode );
		$a->appendChild( $s );
		return $a;
	}

	/**
	 * @param ParsoidExtensionAPI $extApi
	 * @param DOMElement $refsList
	 * @param stdClass $ref
	 */
	public function renderLine(
		ParsoidExtensionAPI $extApi, DOMElement $refsList, stdClass $ref
	): void {
		$ownerDoc = $refsList->ownerDocument;

		// Generate the li and set ref content first, so the HTML gets parsed.
		// We then append the rest of the ref nodes before the first node
		$li = $ownerDoc->createElement( 'li' );
		$refDir = $ref->dir;
		$refTarget = $ref->target;
		$refContentId = $ref->contentId;
		$refGroup = $ref->group;
		DOMUtils::addAttributes( $li, [
				'about' => '#' . $refTarget,
				'id' => $refTarget,
				'class' => ( $refDir === 'rtl' || $refDir === 'ltr' ) ? 'mw-cite-dir-' . $refDir : null
			]
		);
		$reftextSpan = $ownerDoc->createElement( 'span' );
		DOMUtils::addAttributes(
			$reftextSpan,
			[
				'id' => 'mw-reference-text-' . $refTarget,
				'class' => 'mw-reference-text',
			]
		);
		if ( $refContentId ) {
			$content = $extApi->getContentDOM( $refContentId );
			// The data-mw and data-parsoid attributes aren't needed on the ref content
			// in the references section. The content wrapper will remain in the original
			// site where the <ref> tag showed up and will retain data-parsoid & data-mw.
			ParsoidExtensionAPI::migrateChildrenBetweenDocs( $content, $reftextSpan, false );
		}
		$li->appendChild( $reftextSpan );

		if ( count( $ref->linkbacks ) === 1 ) {
			$linkback = self::createLinkback( $extApi, $ref->id, $refGroup, "↑", $ownerDoc );
			$linkback->setAttribute( 'rel', 'mw:referencedBy' );
			$li->insertBefore( $linkback, $reftextSpan );
		} else {
			// 'mw:referencedBy' span wrapper
			$span = $ownerDoc->createElement( 'span' );
			$span->setAttribute( 'rel', 'mw:referencedBy' );
			$li->insertBefore( $span, $reftextSpan );

			foreach ( $ref->linkbacks as $i => $lb ) {
				$span->appendChild(
					self::createLinkback( $extApi, $lb, $refGroup, (string)( $i + 1 ), $ownerDoc )
				);
			}
		}

		// Space before content node
		$li->insertBefore( $ownerDoc->createTextNode( ' ' ), $reftextSpan );

		// Add it to the ref list
		$refsList->appendChild( $li );
	}
}
