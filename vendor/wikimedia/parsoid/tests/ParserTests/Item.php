<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

class Item {
	/** @var string */
	public $type;

	/**
	 * @param string $type
	 */
	public function __construct( string $type ) {
		$this->type = $type;
	}
}
