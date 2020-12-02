<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

/**
 * Represents a parser test
 */
class Article extends Item {
	/** @var string */
	public $title;

	/** @var string */
	public $text;

	/**
	 * @param array $articleProps key-value mapping of properties
	 */
	public function __construct( array $articleProps ) {
		parent::__construct( $articleProps['type'] );
		$this->title = $articleProps['title'];
		$this->text = $articleProps['text'];
	}
}
