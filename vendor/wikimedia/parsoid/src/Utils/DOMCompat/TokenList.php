<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils\DOMCompat;

use DOMElement;
use Iterator;
use LogicException;

/**
 * Implements the parts of DOMTokenList interface which are used by Parsoid.
 * @note To improve performance, no effort is made to keep the TokenList in sync
 *   with the real class list if that is changed from elsewhere.
 * @see https://dom.spec.whatwg.org/#interface-domtokenlist
 */
class TokenList implements Iterator {

	/** @var DOMElement The node whose classes are listed. */
	protected $node;

	/** @var string Copy of the attribute text, used for change detection. */
	private $attribute = false;

	// Testing element existence with a list is less painful than returning numeric keys
	// with a map, so let's go with that.
	/** @var string[] */
	private $classList;

	/**
	 * @param DOMElement $node The node whose classes are listed.
	 */
	public function __construct( DOMElement $node ) {
		$this->node = $node;
		$this->lazyLoadClassList();
	}

	/**
	 * Return the number of CSS classes this element has.
	 * @return int
	 * @see https://dom.spec.whatwg.org/#dom-domtokenlist-length
	 */
	public function getLength(): int {
		$this->lazyLoadClassList();
		return count( $this->classList );
	}

	/**
	 * Checks if the element has a given CSS class.
	 * @param string $token
	 * @return bool
	 * @see https://dom.spec.whatwg.org/#dom-domtokenlist-contains
	 */
	public function contains( string $token ): bool {
		$this->lazyLoadClassList();
		return in_array( $token, $this->classList, true );
	}

	/**
	 * Add CSS classes to the element.
	 * @param string ...$tokens List of classes to add
	 * @see https://dom.spec.whatwg.org/#dom-domtokenlist-add
	 */
	public function add( string ...$tokens ): void {
		$this->lazyLoadClassList();
		$changed = false;
		foreach ( $tokens as $token ) {
			if ( !in_array( $token, $this->classList, true ) ) {
				$changed = true;
				$this->classList[] = $token;
			}
		}
		if ( $changed ) {
			$this->saveClassList();
		}
	}

	/**
	 * Remove CSS classes from the element.
	 * @param string ...$tokens List of classes to remove
	 * @see https://dom.spec.whatwg.org/#dom-domtokenlist-remove
	 */
	public function remove( string ...$tokens ): void {
		$this->lazyLoadClassList();
		$changed = false;
		foreach ( $tokens as $token ) {
			$index = array_search( $token, $this->classList, true );
			if ( $index !== false ) {
				array_splice( $this->classList, $index, 1 );
				$changed = true;
			}
		}
		if ( $changed ) {
			$this->saveClassList();
		}
	}

	/**
	 * @return string
	 */
	public function current() {
		$this->lazyLoadClassList();
		return current( $this->classList );
	}

	/**
	 * @return void
	 */
	public function next() {
		$this->lazyLoadClassList();
		next( $this->classList );
	}

	/**
	 * @return int|null
	 */
	public function key() {
		$this->lazyLoadClassList();
		return key( $this->classList );
	}

	/**
	 * @return bool
	 */
	public function valid() {
		$this->lazyLoadClassList();
		return key( $this->classList ) !== null;
	}

	/**
	 * @return void
	 */
	public function rewind() {
		$this->lazyLoadClassList();
		reset( $this->classList );
	}

	/**
	 * Set the classList property based on the class attribute of the wrapped element.
	 */
	private function lazyLoadClassList(): void {
		$attrib = $this->node->getAttribute( 'class' );
		if ( $attrib !== $this->attribute ) {
			$this->attribute = $attrib;
			$this->classList = preg_split( '/\s+/', $this->node->getAttribute( 'class' ), -1,
				PREG_SPLIT_NO_EMPTY );
		}
	}

	/**
	 * Set the class attribute of the wrapped element based on the classList property.
	 */
	private function saveClassList(): void {
		if ( $this->classList === null ) {
			throw new LogicException( 'no class list to set' );
		} elseif ( $this->classList === [] ) {
			$this->attribute = '';
			$this->node->removeAttribute( 'class' );
		} else {
			$this->attribute = implode( ' ', $this->classList );
			$this->node->setAttribute( 'class', $this->attribute );
		}
	}

}
