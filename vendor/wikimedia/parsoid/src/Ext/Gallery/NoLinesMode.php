<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Gallery;

use Wikimedia\Parsoid\Ext\PHPUtils;

class NoLinesMode extends TraditionalMode {
	/**
	 * Create a NoLinesMode singleton.
	 * @param string|null $mode Only used by subclasses.
	 */
	protected function __construct( string $mode = null ) {
		parent::__construct( $mode ?? 'nolines' );
		$this->padding = PHPUtils::arrayToObject( [ 'thumb' => 0, 'box' => 5, 'border' => 4 ] );
	}
}
