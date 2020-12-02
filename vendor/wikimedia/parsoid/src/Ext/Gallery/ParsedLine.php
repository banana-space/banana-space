<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use DOMElement;

/**
 * @class
 */
class ParsedLine {

	/**
	 * DOM node representing the thumbnail image.
	 * @var DOMElement
	 */
	public $thumb;

	/**
	 * DOM node representing the caption (if any).
	 * @var DOMElement|null
	 */
	public $gallerytext;

	/**
	 * The `typeof` the thumbnail image.
	 * @var string
	 */
	public $rdfaType;

	/**
	 * Construct a new ParsedLine object.
	 * @param DOMElement $thumb
	 * @param DOMElement|null $gallerytext
	 * @param string $rdfaType
	 */
	public function __construct(
		DOMElement $thumb, ?DOMElement $gallerytext, string $rdfaType
	) {
		$this->thumb = $thumb;
		$this->gallerytext = $gallerytext;
		$this->rdfaType = $rdfaType;
	}
}
