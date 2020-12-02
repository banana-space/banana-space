<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use DOMElement;

class SlideshowMode extends TraditionalMode {
	/**
	 * Create a SlideshowMode singleton.
	 * @param string|null $mode Only used by subclasses.
	 */
	protected function __construct( string $mode = null ) {
		parent::__construct( $mode ?? 'slideshow' );
	}

	/** @inheritDoc */
	protected function setAdditionalOptions( Opts $opts, DOMElement $ul ): void {
		$ul->setAttribute( 'data-showthumbnails', $opts->showthumbnails ? '1' : '' );
	}

	/** @inheritDoc */
	protected function perRow( Opts $opts, DOMElement $ul ): void {
		/* do nothing */
	}
}
