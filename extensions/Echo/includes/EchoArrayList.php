<?php

/**
 * Implements the EchoContainmentList interface for php arrays.  Possible source
 * of arrays includes $wg* global variables initialized from extensions or global
 * wiki config.
 */
class EchoArrayList implements EchoContainmentList {
	/**
	 * @var array
	 */
	protected $list;

	/**
	 * @param array $list
	 */
	public function __construct( array $list ) {
		$this->list = $list;
	}

	/**
	 * @inheritDoc
	 */
	public function getValues() {
		return $this->list;
	}

	/**
	 * @inheritDoc
	 */
	public function getCacheKey() {
		return '';
	}
}
