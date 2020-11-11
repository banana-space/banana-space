<?php

namespace RemexHtml\Tokenizer;

/**
 * An Attributes implementation which is a simple array proxy.
 */
class PlainAttributes implements Attributes {
	protected $data;
	protected $attrObjects;

	public function __construct( $data = [] ) {
		$this->data = $data;
	}

	public function merge( Attributes $other ) {
		foreach ( $other as $name => $value ) {
			if ( !isset( $this[$name] ) ) {
				$this[$name] = $value;
			}
		}
	}

	public function offsetExists( $key ) {
		return isset( $this->data[$key] );
	}

	public function offsetGet( $key ) {
		return $this->data[$key];
	}

	public function offsetSet( $key, $value ) {
		$this->data[$key] = $value;
	}

	public function offsetUnset( $key ) {
		unset( $this->data[$key] );
	}

	public function getIterator() {
		return new ArrayIterator( $this->data );
	}

	public function getValues() {
		return $this->data;
	}

	public function getObjects() {
		if ( $this->attrObjects === null ) {
			$result = [];
			foreach ( $this->data as $name => $value ) {
				$result[$name] = new Attribute( $name, null, null, $name, $value );
			}
			$this->attrObjects = $result;
		}
		return $this->attrObjects;
	}

	public function count() {
		return count( $this->data );
	}
}
