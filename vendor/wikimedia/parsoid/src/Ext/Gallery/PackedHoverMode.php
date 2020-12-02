<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

/**
 * @class
 * @extends ~PackedMode
 */
class PackedHoverMode extends PackedMode {
	/**
	 * Create a PackedHoverMode singleton.
	 * @param string|null $mode Only used by subclasses.
	 */
	protected function __construct( string $mode = null ) {
		parent::__construct( $mode ?? 'packed-hover' );
	}

	/** @inheritDoc */
	protected function useTraditionalGalleryText(): bool {
		return false;
	}
}
