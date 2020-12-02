<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Ext\Utils;

/**
 * @class
 */
class Opts {
	/**
	 * Parse options from an attribute array.
	 * @param ParsoidExtensionAPI $extApi
	 * @param array<string,string> $attrs The attribute array
	 */
	public function __construct( ParsoidExtensionAPI $extApi, array $attrs ) {
		foreach ( $extApi->getSiteConfig()->galleryOptions() as $k => $v ) {
			$this->$k = $v;
		}

		if ( is_numeric( $attrs['perrow'] ?? null ) ) {
			$this->imagesPerRow = intval( $attrs['perrow'], 10 );
		}

		$maybeDim = Utils::parseMediaDimensions( $attrs['widths'] ?? '', true );
		if ( $maybeDim && Utils::validateMediaParam( $maybeDim['x'] ) ) {
			$this->imageWidth = $maybeDim['x'];
		}

		$maybeDim = Utils::parseMediaDimensions( $attrs['heights'] ?? '', true );
		if ( $maybeDim && Utils::validateMediaParam( $maybeDim['x'] ) ) {
			$this->imageHeight = $maybeDim['x'];
		}

		$mode = strtolower( $attrs['mode'] ?? '' );
		if ( Mode::byName( $mode ) !== null ) {
			$this->mode = $mode;
		}

		$this->showfilename = isset( $attrs['showfilename'] );
		$this->showthumbnails = isset( $attrs['showthumbnails'] );
		$this->caption = (bool)( $attrs['caption'] ?? false );

		// TODO: Good contender for T54941
		$validUlAttrs = $extApi->getValidHTMLAttributes( 'ul' );
		$this->attrs = [];
		foreach ( $attrs as $k => $v ) {
			if ( !isset( $validUlAttrs[$k] ) ) {
				continue;
			}
			if ( $k === 'style' ) {
				$v = $extApi->sanitizeCss( $v );
			}
			$this->attrs[$k] = $v;
		}
	}

	/** @var array<string,string> */
	public $attrs;

	/** @var int */
	public $imagesPerRow;

	/** @var int */
	public $imageWidth;

	/** @var int */
	public $imageHeight;

	/** @var string */
	public $mode;

	/** @var bool */
	public $showfilename;

	/** @var bool */
	public $showthumbnails;

	/** @var bool */
	public $caption;

}
