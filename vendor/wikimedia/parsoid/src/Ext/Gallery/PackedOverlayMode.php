<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

/**
 * @class
 * @extends ~PackedMode
 */
class PackedOverlayMode extends PackedMode {
	/**
	 * Create a PackedOverlayMode singleton.
	 * @param string|null $mode Only used by subclasses.
	 */
	protected function __construct( string $mode = null ) {
		parent::__construct( $mode ?? 'packed-overlay' );
	}

	/** @inheritDoc */
	protected function useTraditionalGalleryText(): bool {
		return false;
	}
}
