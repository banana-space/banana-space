<?php

namespace RemexHtml\Tokenizer;

/**
 * An Attributes implementation which defers interpretation of regex match
 * results until the caller requires them.
 *
 * This should not be directly instantiated outside of Tokenizer.
 */
class LazyAttributes implements Attributes {
	private $tokenizer;
	private $data;
	private $attributes;
	private $attrObjects;

	public function __construct( $data, callable $interpreter ) {
		$this->interpreter = $interpreter;
		$this->data = $data;
	}

	private function init() {
		if ( $this->attributes === null ) {
			$func = $this->interpreter;
			$this->attributes = $func( $this->data );
			$this->interpreter = null;
		}
	}

	public function offsetExists( $offset ) {
		if ( $this->attributes === null ) {
			$this->init();
		}
		return isset( $this->attributes[$offset] );
	}

	public function &offsetGet( $offset ) {
		if ( $this->attributes === null ) {
			$this->init();
		}
		return $this->attributes[$offset];
	}

	public function offsetSet( $offset, $value ) {
		if ( $this->attributes === null ) {
			$this->init();
		}
		$this->attributes[$offset] = $value;
	}

	public function offsetUnset( $offset ) {
		if ( $this->attributes === null ) {
			$this->init();
		}
		unset( $this->attributes[$offset] );
	}

	public function getValues() {
		if ( $this->attributes === null ) {
			$this->init();
		}
		return $this->attributes;
	}

	public function getObjects() {
		if ( $this->attrObjects === null ) {
			if ( $this->attributes === null ) {
				$this->init();
			}
			$result = [];
			foreach ( $this->attributes as $name => $value ) {
				$result[$name] = new Attribute( $name, null, null, $name, $value );
			}
			$this->attrObjects = $result;
		}
		return $this->attrObjects;
	}

	public function count() {
		return is_object( $this->data ) ? $this->data->count() : count( $this->data );
	}

	public function getIterator() {
		if ( $this->attributes === null ) {
			$this->init();
		}
		return new \ArrayIterator( $this->attributes );
	}

	public function merge( Attributes $other ) {
		if ( $this->attributes === null ) {
			$this->init();
		}
		foreach ( $other as $name => $value ) {
			if ( !isset( $this->attributes[$name] ) ) {
				$this->attributes[$name] = $value;
			}
		}
	}
}
