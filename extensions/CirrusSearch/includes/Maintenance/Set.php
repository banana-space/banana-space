<?php

namespace CirrusSearch\Maintenance;

/**
 * Simple set implementation so it's clear what the array
 * is being used for when values are only stored as keys.
 */
class Set implements \Countable {
	private $elements = [];

	public function __construct( array $elements = [] ) {
		$this->addAll( $elements );
	}

	/**
	 * @return int Number of elements in the set
	 */
	public function count() {
		return count( $this->elements );
	}

	/**
	 * @param string $element Element to add to set
	 * @return self
	 */
	public function add( $element ) {
		$this->elements[$element] = true;
		return $this;
	}

	/**
	 * @param string[] $elements Elements to add to set
	 * @return self
	 */
	public function addAll( array $elements ) {
		foreach ( $elements as $element ) {
			$this->add( $element );
		}
		return $this;
	}

	/**
	 * @param Set $other Set to union into this one
	 * @return self
	 */
	public function union( Set $other ) {
		$this->elements += $other->elements;
		return $this;
	}

	/**
	 * @param string $element Value to test
	 * @return bool True when the set contains $element
	 */
	public function contains( $element ) {
		return array_key_exists( $element, $this->elements );
	}

	/**
	 * @return string[] Elements of the set
	 */
	public function values() {
		return array_keys( $this->elements );
	}
}
