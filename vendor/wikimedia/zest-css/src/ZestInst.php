<?php

namespace Wikimedia\Zest;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMNodeList;
use Error;
use InvalidArgumentException;

/**
 * Zest.php (https://github.com/wikimedia/zest.php)
 * Copyright (c) 2019, C. Scott Ananian. (MIT licensed)
 * PHP port based on:
 *
 * Zest (https://github.com/chjj/zest)
 * A css selector engine.
 * Copyright (c) 2011-2012, Christopher Jeffrey. (MIT Licensed)
 * Domino version based on Zest v0.1.3 with bugfixes applied.
 */

class ZestInst {

	/**
	 * Helpers
	 */

	/*
$compareDocumentPosition = function ( $a, $b ) {
	return $a->compareDocumentPosition( $b );
};

$order = function ( $a, $b ) use ( &$compareDocumentPosition ) {
	return ( $compareDocumentPosition( $a, $b ) & 2 ) ? 1 : -1;
};
	*/

	private static function next( DOMNode $el ): ?DOMNode {
		while ( ( $el = $el->nextSibling ) && $el->nodeType !== 1 ) {
			// no op
		}
		return $el;
	}

	private static function prev( DOMNode $el ): ?DOMNode {
		while ( ( $el = $el->previousSibling ) && $el->nodeType !== 1 ) {
			// no op
		}
		return $el;
	}

	private static function child( DOMNode $el ): ?DOMNode {
		if ( $el = $el->firstChild ) {
			while ( $el->nodeType !== 1 && ( $el = $el->nextSibling ) ) {
				// no op
			}
		}
		return $el;
	}

	private static function lastChild( DOMNode $el ): ?DOMNode {
		if ( $el = $el->lastChild ) {
			while ( $el->nodeType !== 1 && ( $el = $el->previousSibling ) ) {
				// no op
			}
		}
		return $el;
	}

	private static function parentIsElement( DOMNode $n ): bool {
		if ( !$n->parentNode ) { return false;
  }
		$nodeType = $n->parentNode->nodeType;
		// The root `html` element (node type 9) can be a first- or
		// last-child, too.  But in PHP, if you load a document with
		// DOMDocument::loadHTML, your root DOMDocument will have node
		// type 13 (!) which is PHP's bespoke "XML_HTML_DOCUMENT_NODE"
		// and Not A Real Thing.  But we'll recognize it anyway...
		return $nodeType === 1 || $nodeType === 9 || $nodeType === 13;
	}

	private static function unichr( int $codepoint ): string {
		if ( extension_loaded( 'intl' ) ) {
			return \IntlChar::chr( $codepoint );
		} else {
			return mb_chr( $codepoint, "utf-8" );
		}
	}

	private static function unquote( string $str ): string {
		if ( !$str ) {
			return $str;
		}
		self::initRules();
		$ch = $str[ 0 ];
		if ( $ch === '"' || $ch === "'" ) {
			if ( substr( $str, - 1 ) === $ch ) {
				$str = substr( $str, 1, -1 );
			} else {
				// bad string.
				$str = substr( $str, 1 );
			}
			return preg_replace_callback( self::$rules->str_escape, function ( array $matches ) {
				$s = $matches[0];
				if ( !preg_match( '/^\\\(?:([0-9A-Fa-f]+)|([\r\n\f]+))/', $s, $m ) ) {
					return substr( $s, 1 );
				}
				if ( $m[ 2 ] ) {
					return ''; /* escaped newlines are ignored in strings. */
				}
				$cp = intval( $m[ 1 ], 16 );
				return self::unichr( $cp );
			}, $str );
		} elseif ( preg_match( self::$rules->ident, $str ) ) {
			return self::decodeid( $str );
		} else {
			// NUMBER, PERCENTAGE, DIMENSION, etc
			return $str;
		}
	}

	private static function decodeid( string $str ): string {
		return preg_replace_callback( self::$rules->escape, function ( array $matches ) {
			$s = $matches[0];
			if ( !preg_match( '/^\\\([0-9A-Fa-f]+)/', $s, $m ) ) {
				return $s[ 1 ];
			}
			$cp = intval( $m[ 1 ], 16 );
			return self::unichr( $cp );
		}, $str );
	}

	private static function makeInside( string $start, string $end ): string {
		$regex = preg_replace(
			'/>/', $end, preg_replace(
				'/</', $start, self::reSource( self::$rules->inside )
			)
		);
		return '/' . $regex . '/Su';
	}

	private static function reSource( string $regex ): string {
		// strip delimiter and flags from regular expression
		return preg_replace( '/(^\/)|(\/[a-z]*$)/Diu', '', $regex );
	}

	private static function replace( string $regex, string $name, string $val ): string {
		$regex = self::reSource( $regex );
		$regex = str_replace( $name, self::reSource( $val ), $regex );
		return '/' . $regex . '/Su';
	}

	private static function truncateUrl( string $url, int $num ): string {
		$url = preg_replace( '/^(?:\w+:\/\/|\/+)/', '', $url );
		$url = preg_replace( '/(?:\/+|\/*#.*?)$/', '', $url );
		return implode( '/', explode( '/', $url, $num ) );
	}

	private static function xpathQuote( string $s ): string {
		// Ugly-but-functional escape mechanism for xpath query
		$parts = explode( "'", $s );
		$parts = array_map( function ( string $ss ) {
			return "'$ss'";
		}, $parts );
		if ( count( $parts ) === 1 ) {
			return $parts[0];
		} else {
			return 'concat(' . implode( ',"\'",', $parts ) . ')';
		}
	}

	/**
	 * Get descendants by ID.
	 * The PHP DOM doesn't provide this method for DOMElement, and the
	 * implementation in DOMDocument is broken.
	 *
	 * @param DOMDocument|DOMElement $context
	 * @param string $id
	 * @return array A list of the elements with the given ID. When there are more
	 *   than one, this method might return all of them or only the first one.
	 */
	public static function getElementsById( DOMNode $context, string $id ): array {
		$doc = ( $context instanceof \DOMDocument ) ?
			$context : $context->ownerDocument;
		// PHP doesn't provide an DOMElement-scoped version of
		// getElementById, so we can't call this directly on $context --
		// but that's okay because (1) IDs should be unique, and
		// (2) we verify the scope of the returned element below
		// anyway (to work around bugs with deleted-but-not-gc'ed
		// nodes).
		$r = $doc->getElementById( $id );
		// Note that $r could be null here because the
		// DOMDocument hasn't had an "id attribute" set, even if the id
		// exists in the document. See:
		// http://php.net/manual/en/domdocument.getelementbyid.php
		if ( $r !== null ) {
			// Verify that this node is actually rooted in the
			// document (or in the context), since the element
			// isn't removed from the index immediately when it
			// is deleted. (Also PHP's call is not scoped.)
			for ( $parent = $r; $parent; $parent = $parent->parentNode ) {
				if ( $parent === $context ) {
					return [ $r ];
				}
			}
			// It's possible a deleted-but-still-indexed element was
			// shadowing a later-added element, so we can't return
			// null here directly; fallback to a full search.
		}
		// Do an xpath search, which is still a full traversal of the tree
		// (sigh) but 25% faster than traversing it wholly in PHP.
		$xpath = new \DOMXPath( $doc );
		$query = './/*[@id=' . self::xpathQuote( $id ) . ']';
		return iterator_to_array( $xpath->query( $query, $context ) );
	}

	/**
	 * Get descendants by tag name.
	 * The PHP DOM doesn't provide this method for DOMElement, and the
	 * implementation in DOMDocument has performance issues.
	 *
	 * @param DOMDocument|DOMElement $context
	 * @param string $tagName
	 * @return DOMNodeList
	 */
	public static function getElementsByTagName( DOMNode $context, string $tagName ): DOMNodeList {
		// This *should* just be a call to PHP's `getElementByTagName`
		// function *BUT* PHP's implementation is 100x slower than using
		// XPath to get the same results (!)

		// XXX this assumes default PHP DOM implementation, which
		// reports lowercase tag names in DOMNode->tagName (even though
		// the DOM spec says it should report uppercase)
		$tagName = strtolower( $tagName );

		if ( $context instanceof DOMDocument ) {
			$doc = $context;
		} else {
			$doc = $context->ownerDocument;
		}
		$xpath = new \DOMXPath( $doc );
		$ns = $doc->documentElement->namespaceURI;
		if ( $tagName === '*' ) {
			$query = ".//*";
		} elseif ( $ns || !preg_match( '/^[_a-z][-.0-9_a-z]*$/S', $tagName ) ) {
			$query = './/*[local-name()=' . self::xpathQuote( $tagName ) . ']';
		} else {
			$query = ".//$tagName";
		}
		return $xpath->query( $query, $context );
	}

	private static function getElementsByClassName( DOMNode $context, string $className ): DOMNodeList {
		// PHP doesn't have an implementation of this method; use XPath
		// to quickly get results.  (It would be faster still if there was an
		// actual index, but this will be about 25% faster than doing the
		// tree traversal all in PHP.)
		if ( $context instanceof DOMDocument ) {
			$doc = $context;
		} else {
			$doc = $context->ownerDocument;
		}
		$xpath = new \DOMXPath( $doc );
		$quotedClassName = self::xpathQuote( " $className " );
		$query = ".//*[contains(concat(' ', normalize-space(@class), ' '), $quotedClassName)]";
		return $xpath->query( $query, $context );
	}

	/**
	 * Handle `nth` Selectors
	 */
	private static function parseNth( string $param ): object {
		$param = preg_replace( '/\s+/', '', $param );

		if ( $param === 'even' ) {
			$param = '2n+0';
		} elseif ( $param === 'odd' ) {
			$param = '2n+1';
		} elseif ( strpos( $param, 'n' ) === false ) {
			$param = '0n' . $param;
		}

		preg_match( '/^([+-])?(\d+)?n([+-])?(\d+)?$/', $param, $cap, PREG_UNMATCHED_AS_NULL );

		$group = intval( ( $cap[1] ?? '' ) . ( $cap[2] ?? '1' ), 10 );
		$offset = intval( ( $cap[3] ?? '' ) . ( $cap[4] ?? '0' ), 10 );
		return (object)[
			'group' => $group,
			'offset' => $offset,
		];
	}

	private static function nth( string $param, callable $test, bool $last ): callable {
		$param = self::parseNth( $param );
		$group = $param->group;
		$offset = $param->offset;
		$find = ( !$last ) ? [ self::class, 'child' ] : [ self::class, 'lastChild' ];
		$advance = ( !$last ) ? [ self::class, 'next' ] : [ self::class, 'prev' ];
		return function ( DOMNode $el ) use ( $find, $test, $offset, $group, $advance ): bool {
			if ( !self::parentIsElement( $el ) ) {
				return false;
			}

			$rel = call_user_func( $find, $el->parentNode );
			$pos = 0;

			while ( $rel ) {
				if ( call_user_func( $test, $rel, $el ) ) {
					$pos++;
				}
				if ( $rel === $el ) {
					$pos -= $offset;
					return ( $group && $pos ) ?
						( $pos % $group ) === 0 && ( $pos < 0 === $group < 0 ) :
						!$pos;
				}
				$rel = call_user_func( $advance, $rel );
			}
			return false;
		};
	}

	/**
	 * Simple Selectors which take no arguments.
	 * @var array<string,(callable(DOMNode):bool)>
	 */
	private $selectors0;

	/**
	 * Simple Selectors which take one argument.
	 * @var array<string,(callable(string):(callable(DOMNode):bool))>
	 */
	private $selectors1;

	/**
	 * Add a custom selector that takes no parameters.
	 * @param string $key Name of the selector
	 * @param callable(DOMNode):bool $func
	 *   The selector match function
	 */
	public function addSelector0( string $key, callable $func ) {
		$this->selectors0[$key] = $func;
	}

	/**
	 * Add a custom selector that takes 1 parameter, which is passed as a
	 * string.
	 * @param string $key Name of the selector
	 * @param callable(string):(callable(DOMNode):bool) $func
	 *   The selector match function
	 */
	public function addSelector1( string $key, callable $func ) {
		$this->selectors1[$key] = $func;
	}

	private function initSelectors() {
		$this->addSelector0( '*', function ( DOMNode $el ): bool {
			return true;
		} );
		$this->addSelector1( 'type', function ( string $type ): callable {
			$type = strtolower( $type );
			return function ( DOMNode $el ) use ( $type ): bool {
				return strtolower( $el->nodeName ) === $type;
			};
		} );
		$this->addSelector0( ':first-child', function ( DOMNode $el ): bool {
			return !self::prev( $el ) && self::parentIsElement( $el );
		} );
		$this->addSelector0( ':last-child', function ( DOMNode $el ): bool {
			return !self::next( $el ) && self::parentIsElement( $el );
		} );
		$this->addSelector0( ':only-child', function ( DOMNode $el ): bool {
			return !self::prev( $el ) && !self::next( $el )
				&& self::parentIsElement( $el );
		} );
		$this->addSelector1( ':nth-child', function ( string $param, bool $last = false ): callable {
			return self::nth( $param, function () {
				return true;
			}, $last );
		} );
		/** @suppress PhanParamTooMany */
		$this->addSelector1( ':nth-last-child', function ( string $param ): callable {
			return $this->selectors1[ ':nth-child' ]( $param, true );
		} );
		$this->addSelector0( ':root', function ( DOMNode $el ): bool {
			return $el->ownerDocument->documentElement === $el;
		} );
		$this->addSelector0( ':empty', function ( DOMNode $el ): bool {
			return !$el->firstChild;
		} );
		$this->addSelector1( ':not', function ( string $sel ) {
			$test = self::compileGroup( $sel );
			return function ( DOMNode $el ) use ( $test ): bool {
				return !call_user_func( $test, $el );
			};
		} );
		$this->addSelector0( ':first-of-type', function ( DOMNode $el ): bool {
			if ( !self::parentIsElement( $el ) ) {
				return false;
			}
			$type = $el->nodeName;
			while ( $el = self::prev( $el ) ) {
				if ( $el->nodeName === $type ) {
					return false;
				}
			}
			return true;
		} );
		$this->addSelector0( ':last-of-type', function ( DOMNode $el ): bool {
			if ( !self::parentIsElement( $el ) ) {
				return false;
			}
			$type = $el->nodeName;
			while ( $el = self::next( $el ) ) {
				if ( $el->nodeName === $type ) {
					return false;
				}
			}
			return true;
		} );
		$this->addSelector0( ':only-of-type', function ( DOMNode $el ): bool {
			return $this->selectors0[ ':first-of-type' ]( $el ) &&
				$this->selectors0[ ':last-of-type' ]( $el );
		} );
		$this->addSelector1( ':nth-of-type', function ( string $param, bool $last = false ): callable  {
			return self::nth( $param, function ( DOMNode $rel, DOMNode $el ) {
				return $rel->nodeName === $el->nodeName;
			}, $last );
		} );
		/** @suppress PhanParamTooMany */
		$this->addSelector1( ':nth-last-of-type', function ( string $param ): callable {
			return $this->selectors1[ ':nth-of-type' ]( $param, true );
		} );
		$this->addSelector0( ':checked', function ( DOMNode $el ): bool {
			'@phan-var DOMElement $el';
			// XXX these properties don't exist in the PHP DOM
			// return $el->checked || $el->selected;
			return $el->hasAttribute( 'checked' ) || $el->hasAttribute( 'selected' );
		} );
		$this->addSelector0( ':indeterminate', function ( DOMNode $el ): bool {
			return !$this->selectors0[ ':checked' ]( $el );
		} );
		$this->addSelector0( ':enabled', function ( DOMNode $el ): bool {
			'@phan-var DOMElement $el';
			// XXX these properties don't exist in the PHP DOM
			// return !$el->disabled && $el->type !== 'hidden';
			return !$el->hasAttribute( 'disabled' ) && $el->getAttribute( 'type' ) !== 'hidden';
		} );
		$this->addSelector0( ':disabled', function ( DOMNode $el ): bool {
			'@phan-var DOMElement $el';
			// XXX these properties don't exist in the PHP DOM
			// return !!$el->disabled;
			return $el->hasAttribute( 'disabled' );
		} );
		/*
		$this->addSelector0( ':target', function ( DOMNode $el ) use ( &$window ) {
			return $el->id === $window->location->hash->substring( 1 );
		});
		$this->addSelector0( ':focus', function ( DOMNode $el ) {
			return $el === $el->ownerDocument->activeElement;
		});
		*/
		$this->addSelector1( ':is', function ( string $sel ): callable {
			return self::compileGroup( $sel );
		} );
		// :matches is an older name for :is; see
		// https://github.com/w3c/csswg-drafts/issues/3258
		$this->addSelector1( ':matches', function ( string $sel ): callable {
			return $this->selectors1[ ':is' ]( $sel );
		} );
		$this->addSelector1( ':nth-match', function ( string $param, bool $last = false ): callable {
			$args = preg_split( '/\s*,\s*/', $param );
			$arg = array_shift( $args );
			$test = self::compileGroup( implode( ',', $args ) );

			return self::nth( $arg, $test, $last );
		} );
		/** @suppress PhanParamTooMany */
		$this->addSelector1( ':nth-last-match', function ( string $param ): callable {
			return $this->selectors1[ ':nth-match' ]( $param, true );
		} );
		/*
		$this->addSelector0( ':links-here', function ( DOMNode $el ) use ( &$window ) {
			return $el . '' === $window->location . '';
		});
		*/
		$this->addSelector1( ':lang', function ( string $param ): callable {
			return function ( DOMNode $el ) use ( $param ): bool {
				'@phan-var DOMElement $el';
				while ( $el ) {
					// PHP DOM doesn't have 'lang' property
					$lang = $el->getAttribute( 'lang' );
					if ( $lang ) {
						return strpos( $lang, $param ) === 0;
					}
					$el = $el->parentNode;
				}
				return false;
			};
		} );
		$this->addSelector1( ':dir', function ( string $param ): callable {
			return function ( DOMNode $el ) use ( $param ): bool {
				'@phan-var DOMElement $el';
				while ( $el ) {
					$dir = $el->getAttribute( 'dir' );
					if ( $dir ) {
						return $dir === $param;
					}
					$el = $el->parentNode;
				}
				return false;
			};
		} );
		$this->addSelector0( ':scope', function ( DOMNode $el, $con = null ): bool {
			$context = $con ?? $el->ownerDocument;
			if ( $context->nodeType === 9 ) {
				return $el === $context->documentElement;
			}
			return $el === $context;
		} );
		/*
		$this->addSelector0( ':any-link', function ( DOMNode $el ):bool {
			return gettype( $el->href ) === 'string';
		});
		$this->addSelector( ':local-link', function ( DOMNode $el ) use ( &$window ) {
			if ( $el->nodeName ) {
				return $el->href && $el->host === $window->location->host;
			}
			// XXX this is really selector1 not selector0
			$param = +$el + 1;
			return function ( DOMNode $el ) use ( &$window, $param ) {
				if ( !$el->href ) { return;  }

				$url = $window->location . '';
				$href = $el . '';

				return self::truncateUrl( $url, $param ) === self::truncateUrl( $href, $param );
			};
		});
		$this->addSelector0( ':default', function ( DOMNode $el ):bool {
			return !!$el->defaultSelected;
		});
		$this->addSelector0( ':valid', function ( DOMNode $el ):bool {
			return $el->willValidate || ( $el->validity && $el->validity->valid );
		});
		*/
		$this->addSelector0( ':invalid', function ( DOMNode $el ):bool {
			return !$this->selectors0[ ':valid' ]( $el );
		} );
		/*
		$this->addSelector0( ':in-range', function ( DOMNode $el ):bool {
			return $el->value > $el->min && $el->value <= $el->max;
		});
		*/
		$this->addSelector0( ':out-of-range', function ( DOMNode $el ): bool {
			return !$this->selectors0[ ':in-range' ]( $el );
		} );
		$this->addSelector0( ':required', function ( DOMNode $el ): bool {
			'@phan-var DOMElement $el';
			return $el->hasAttribute( 'required' );
		} );
		$this->addSelector0( ':optional', function ( DOMNode $el ): bool {
			return !$this->selectors0[ ':required' ]( $el );
		} );
		$this->addSelector0( ':read-only', function ( DOMNode $el ): bool {
			'@phan-var DOMElement $el';
			if ( $el->hasAttribute( 'readOnly' ) ) {
				return true;
			}

			$attr = $el->getAttribute( 'contenteditable' );
			$name = strtolower( $el->nodeName );

			$name = $name !== 'input' && $name !== 'textarea';

			return ( $name || $el->hasAttribute( 'disabled' ) ) && $attr == null;
		} );
		$this->addSelector0( ':read-write', function ( DOMNode $el ): bool {
			return !$this->selectors0[ ':read-only' ]( $el );
		} );
		$this->addSelector0( ':hover', function ( DOMNode $el ): bool {
			throw new Error( ':hover is not supported.' );
		} );
		$this->addSelector0( ':active', function ( DOMNode $el ): bool {
			throw new Error( ':active is not supported.' );
		} );
		$this->addSelector0( ':link', function ( DOMNode $el ): bool {
			throw new Error( ':link is not supported.' );
		} );
		$this->addSelector0( ':visited', function ( DOMNode $el ): bool {
			throw new Error( ':visited is not supported.' );
		} );
		$this->addSelector0( ':column', function ( DOMNode $el ): bool {
			throw new Error( ':column is not supported.' );
		} );
		$this->addSelector0( ':nth-column', function ( DOMNode $el ): bool {
			throw new Error( ':nth-column is not supported.' );
		} );
		$this->addSelector0( ':nth-last-column', function ( DOMNode $el ): bool {
			throw new Error( ':nth-last-column is not supported.' );
		} );
		$this->addSelector0( ':current', function ( DOMNode $el ): bool {
			throw new Error( ':current is not supported.' );
		} );
		$this->addSelector0( ':past', function ( DOMNode $el ): bool {
			throw new Error( ':past is not supported.' );
		} );
		$this->addSelector0( ':future', function ( DOMNode $el ): bool {
			throw new Error( ':future is not supported.' );
		} );
		// Non-standard, for compatibility purposes.
		$this->addSelector1( ':contains', function ( string $param ): callable {
			return function ( DOMNode $el ) use ( $param ): bool {
				$text = $el->textContent;
				return strpos( $text, $param ) !== false;
			};
		} );
		$this->addSelector1( ':has', function ( string $param ): callable {
			return function ( DOMNode $el ) use ( $param ): bool {
				'@phan-var DOMElement $el';
				return count( self::find( $param, $el ) ) > 0;
			};
		} );
		// Potentially add more pseudo selectors for
		// compatibility with sizzle and most other
		// selector engines (?).
	}

	/** @return callable(DOMNode):bool */
	private function selectorsAttr( string $key, string $op, string $val, bool $i ): callable {
		$op = $this->operators[ $op ];
		return function ( DOMNode $el ) use ( $key, $i, $op, $val ): bool {
			/* XXX: the below all assumes a more complete PHP DOM than we have
			switch ( $key ) {
			#case 'for':
			#	$attr = $el->htmlFor; // Not supported in PHP DOM
			#	break;
			case 'class':
				// PHP DOM doesn't support $el->className
				// className is '' when non-existent
				// getAttribute('class') is null
				if ($el->hasAttributes() && $el->hasAttribute( 'class' ) ) {
					$attr = $el->getAttribute( 'class' );
				} else {
					$attr = null;
				}
				break;
			case 'href':
			case 'src':
				$attr = $el->getAttribute( $key, 2 );
				break;
			case 'title':
				// getAttribute('title') can be '' when non-existent sometimes?
				if ($el->hasAttribute('title')) {
					$attr = $el->getAttribute( 'title' );
				} else {
					$attr = null;
				}
				break;
				// careful with attributes with special getter functions
			case 'id':
			case 'lang':
			case 'dir':
			case 'accessKey':
			case 'hidden':
			case 'tabIndex':
			case 'style':
				if ( $el->getAttribute ) {
					$attr = $el->getAttribute( $key );
					break;
				}
				// falls through
			default:
				if ( $el->hasAttribute && !$el->hasAttribute( $key ) ) {
					break;
				}
				$attr = ( $el[ $key ] != null ) ?
					$el[ $key ] :
					$el->getAttribute && $el->getAttribute( $key );
				break;
			}
			*/
			// This is our simple PHP DOM version
			'@phan-var DOMElement $el';
			if ( $el->hasAttributes() && $el->hasAttribute( $key ) ) {
				$attr = $el->getAttribute( $key );
			} else {
				$attr = null;
			}
			// End simple PHP DOM version
			if ( $attr == null ) {
				return false;
			}
			$attr = $attr . '';
			if ( $i ) {
				$attr = strtolower( $attr );
				$val = strtolower( $val );
			}
			return call_user_func( $op, $attr, $val );
		};
	}

	/**
	 * Attribute Operators
	 * @var array<string,(callable(string,string):bool)>
	 */
	private $operators;

	/**
	 * Add a custom operator
	 * @param string $key Name of the operator
	 * @param callable(string,string):bool $func
	 *   The operator match function
	 */
	public function addOperator( string $key, callable $func ) {
		$this->operators[$key] = $func;
	}

	private function initOperators() {
		$this->addOperator( '-', function ( string $attr, string $val ): bool {
			return true;
		} );
		$this->addOperator( '=', function ( string $attr, string $val ): bool {
			return $attr === $val;
		} );
		$this->addOperator( '*=', function ( string $attr, string $val ): bool {
			return strpos( $attr, $val ) !== false;
		} );
		$this->addOperator( '~=', function ( string $attr, string $val ): bool {
			$attrLen = strlen( $attr );
			$valLen = strlen( $val );
			for ( $s = 0;  $s < $attrLen;  $s = $i + 1 ) {
				$i = strpos( $attr, $val, $s );
				if ( $i === false ) {
					return false;
				}
				$j = $i + $valLen;
				$f = ( $i === 0 ) ? ' ' : $attr[ $i - 1 ];
				$l = ( $j >= $attrLen ) ? ' ' : $attr[ $j ];
				if ( $f === ' ' && $l === ' ' ) {
					return true;
				}
			}
			return false;
		} );
		$this->addOperator( '|=', function ( string $attr, string $val ): bool {
			$i = strpos( $attr, $val );
			if ( $i !== 0 ) {
				return false;
			}
			$j = $i + strlen( $val );
			if ( $j >= strlen( $attr ) ) {
				return true;
			}
			$l = $attr[ $j ];
			return $l === '-';
		} );
		$this->addOperator( '^=', function ( string $attr, string $val ): bool {
			return strpos( $attr, $val ) === 0;
		} );
		$this->addOperator( '$=', function ( string $attr, string $val ): bool {
			$i = strrpos( $attr, $val );
			return $i !== false && $i + strlen( $val ) === strlen( $attr );
		} );
		// non-standard
		$this->addOperator( '!=', function ( string $attr, string $val ): bool {
			return $attr !== $val;
		} );
	}

	/**
	 * Combinator Logic
	 * @var array<string,(callable(callable(DOMNode):bool):(callable(DOMNode):(?DOMNode)))>
	 */
	private $combinators;

	/**
	 * Add a custom combinator
	 * @param string $key Name of the combinator
	 * @param callable(callable(DOMNode):bool):(callable(DOMNode):(?DOMNode)) $func
	 *   The combinator match function
	 */
	public function addCombinator( string $key, callable $func ) {
		$this->combinators[$key] = $func;
	}

	private function initCombinators() {
		$this->addCombinator( ' ', function ( callable $test ): callable {
			return function ( DOMNode $el ) use ( $test ): ?DOMNode {
				while ( $el = $el->parentNode ) {
					if ( call_user_func( $test, $el ) ) {
						return $el;
					}
				}
				return null;
			};
		} );
		$this->addCombinator( '>', function ( callable $test ): callable {
			return function ( DOMNode $el ) use ( $test ): ?DOMNode {
				if ( $el = $el->parentNode ) {
					if ( call_user_func( $test, $el ) ) {
						return $el;
					}
				}
				return null;
			};
		} );
		$this->addCombinator( '+', function ( callable $test ): callable {
			return function ( DOMNode $el ) use ( $test ): ?DOMNode {
				if ( $el = self::prev( $el ) ) {
					if ( call_user_func( $test, $el ) ) {
						return $el;
					}
				}
				return null;
			};
		} );
		$this->addCombinator( '~', function ( callable $test ): callable {
			return function ( DOMNode $el ) use ( $test ): ?DOMNode {
				while ( $el = self::prev( $el ) ) {
					if ( call_user_func( $test, $el ) ) {
						return $el;
					}
				}
				return null;
			};
		} );
		$this->addCombinator( 'noop', function ( callable $test ): callable {
			return function ( DOMNode $el ) use ( $test ): ?DOMNode {
				if ( call_user_func( $test, $el ) ) {
					return $el;
				}
				return null;
			};
		} );
	}

	private static function makeRef( callable $test, string $name ): ZestFunc {
		$node = null;
		$ref = new ZestFunc( function ( DOMNode $el ) use ( &$node, &$ref ) : bool {
			$doc = $el->ownerDocument;
			$nodes = self::getElementsByTagName( $doc, '*' );
			$i = count( $nodes );

			while ( $i-- ) {
				$node = $nodes->item( $i );
				if ( call_user_func( $ref->test->func, $el ) ) {
					$node = null;
					return true;
				}
			}

			$node = null;
			return false;
		} );

		$ref->combinator = function ( DOMNode $el ) use ( &$node, $name, $test ): ?DOMNode {
			if ( !$node || !( $node instanceof DOMElement ) ) {
				return null;
			}

			$attr = $node->getAttribute( $name ) ?: '';
			if ( $attr !== '' && $attr[ 0 ] === '#' ) {
				$attr = substr( $attr, 1 );
			}

			$id = $node->getAttribute( 'id' ) ?: '';
			if ( $attr === $id && call_user_func( $test, $node ) ) {
				return $node;
			}
			return null;
		};

		return $ref;
	}

	/**
	 * Grammar
	 */

	private static $rules;

	public static function initRules() {
		self::$rules = (object)[
		'escape' => '/\\\(?:[^0-9A-Fa-f\r\n]|[0-9A-Fa-f]{1,6}[\r\n\t ]?)/',
		'str_escape' => '/(escape)|\\\(\n|\r\n?|\f)/',
		'nonascii' => '/[\x{00A0}-\x{FFFF}]/',
		'cssid' => '/(?:(?!-?[0-9])(?:escape|nonascii|[-_a-zA-Z0-9])+)/',
		'qname' => '/^ *(cssid|\*)/',
		'simple' => '/^(?:([.#]cssid)|pseudo|attr)/',
		'ref' => '/^ *\/(cssid)\/ */',
		'combinator' => '/^(?: +([^ \w*.#\\\]) +|( )+|([^ \w*.#\\\]))(?! *$)/',
		'attr' => '/^\[(cssid)(?:([^\w]?=)(inside))?\]/',
		'pseudo' => '/^(:cssid)(?:\((inside)\))?/',
		'inside' => "/(?:\"(?:\\\\\"|[^\"])*\"|'(?:\\\\'|[^'])*'|<[^\"'>]*>|\\\\[\"'>]|[^\"'>])*/",
		'ident' => '/^(cssid)$/',
		];
		self::$rules->cssid = self::replace( self::$rules->cssid, 'nonascii', self::$rules->nonascii );
		self::$rules->cssid = self::replace( self::$rules->cssid, 'escape', self::$rules->escape );
		self::$rules->qname = self::replace( self::$rules->qname, 'cssid', self::$rules->cssid );
		self::$rules->simple = self::replace( self::$rules->simple, 'cssid', self::$rules->cssid );
		self::$rules->ref = self::replace( self::$rules->ref, 'cssid', self::$rules->cssid );
		self::$rules->attr = self::replace( self::$rules->attr, 'cssid', self::$rules->cssid );
		self::$rules->pseudo = self::replace( self::$rules->pseudo, 'cssid', self::$rules->cssid );
		self::$rules->inside = self::replace( self::$rules->inside, "[^\"'>]*", self::$rules->inside );
		self::$rules->attr = self::replace( self::$rules->attr, 'inside', self::makeInside( '\[', '\]' ) );
		self::$rules->pseudo = self::replace( self::$rules->pseudo, 'inside', self::makeInside( '\(', '\)' ) );
		self::$rules->simple = self::replace( self::$rules->simple, 'pseudo', self::$rules->pseudo );
		self::$rules->simple = self::replace( self::$rules->simple, 'attr', self::$rules->attr );
		self::$rules->ident = self::replace( self::$rules->ident, 'cssid', self::$rules->cssid );
		self::$rules->str_escape = self::replace( self::$rules->str_escape, 'escape', self::$rules->escape );
	}

	/**
	 * Compiling
	 */

	private function compile( string $sel ): ZestFunc {
		$sel = preg_replace( '/^\s+|\s+$/', '', $sel );
		$test = null;
		$filter = [];
		$buff = [];
		$subject = null;
		$qname = null;
		$cap = null;
		$op = null;
		$ref = null;

		while ( $sel ) {
			if ( preg_match( self::$rules->qname, $sel, $cap ) ) {
				$sel = substr( $sel, strlen( $cap[0] ) );
				$qname = self::decodeid( $cap[ 1 ] );
				$buff[] = $this->tokQname( $qname );
			} elseif ( preg_match( self::$rules->simple, $sel, $cap, PREG_UNMATCHED_AS_NULL ) ) {
				$sel = substr( $sel, strlen( $cap[0] ) );
				$qname = '*';
				$buff[] = $this->tokQname( $qname );
				$buff[] = $this->tok( $cap );
			} else {
				throw new InvalidArgumentException( 'Invalid selector.' );
			}

			while ( preg_match( self::$rules->simple, $sel, $cap, PREG_UNMATCHED_AS_NULL ) ) {
				$sel = substr( $sel, strlen( $cap[0] ) );
				$buff[] = $this->tok( $cap );
			}

			if ( $sel && $sel[ 0 ] === '!' ) {
				$sel = substr( $sel, 1 );
				$subject = self::makeSubject();
				$subject->qname = $qname;
				$buff[] = $subject->simple;
			}

			if ( preg_match( self::$rules->ref, $sel, $cap ) ) {
				$sel = substr( $sel, strlen( $cap[0] ) );
				$ref = self::makeRef( self::makeSimple( $buff ), self::decodeid( $cap[ 1 ] ) );
				$filter[] = $ref->combinator;
				$buff = [];
				continue;
			}

			if ( preg_match( self::$rules->combinator, $sel, $cap, PREG_UNMATCHED_AS_NULL ) ) {
				$sel = substr( $sel, strlen( $cap[0] ) );
				$op = $cap[ 1 ] ?? $cap[ 2 ] ?? $cap[ 3 ];
				if ( $op === ',' ) {
					$filter[] = $this->combinators['noop']( self::makeSimple( $buff ) );
					break;
				}
			} else {
				$op = 'noop';
			}

			if ( !isset( $this->combinators[ $op ] ) ) {
				throw new InvalidArgumentException( 'Bad combinator: ' . $op );
			}
			$filter[] = $this->combinators[ $op ]( self::makeSimple( $buff ) );
			$buff = [];
		}

		$test = self::makeTest( $filter );
		$test->qname = $qname;
		$test->sel = $sel;

		if ( $subject ) {
			$subject->lname = $test->qname;

			$subject->test = $test;
			// @phan-suppress-next-line PhanPluginDuplicateExpressionAssignment
			$subject->qname = $subject->qname;
			$subject->sel = $test->sel;
			$test = $subject;
		}

		if ( $ref ) {
			$ref->test = $test;
			$ref->qname = $test->qname;
			$ref->sel = $test->sel;
			$test = $ref;
		}

		return $test;
	}

	/** @return callable(DOMNode):bool */
	private function tokQname( string $cap ): callable {
		// qname
		if ( $cap === '*' ) {
			return $this->selectors0['*'];
		} else {
			return $this->selectors1['type']( $cap );
		}
	}

	/** @return callable(DOMNode):bool */
	private function tok( array $cap ): callable {
		// class/id
		if ( $cap[ 1 ] ) {
			return $cap[ 1 ][ 0 ] === '.'
			// XXX unescape here?  or in attr?
				? $this->selectorsAttr( 'class', '~=', self::decodeid( substr( $cap[ 1 ], 1 ) ), false ) :
				$this->selectorsAttr( 'id', '=', self::decodeid( substr( $cap[ 1 ], 1 ) ), false );
		}

		// pseudo-name
		// inside-pseudo
		if ( $cap[ 2 ] ) {
			$id = self::decodeid( $cap[ 2 ] );
			if ( isset( $cap[3] ) && $cap[ 3 ] ) {
				if ( !isset( $this->selectors1[ $id ] ) ) {
					throw new InvalidArgumentException( "Unknown Selector: $id" );
				}
				return $this->selectors1[ $id ]( self::unquote( $cap[ 3 ] ) );
			} else {
				if ( !isset( $this->selectors0[ $id ] ) ) {
					throw new InvalidArgumentException( "Unknown Selector: $id" );
				}
				return $this->selectors0[ $id ];
			}
		}

		// attr name
		// attr op
		// attr value
		if ( $cap[ 4 ] ) {
			$value = $cap[ 6 ] ?? '';
			$i = preg_match( "/[\"'\\s]\\s*I\$/i", $value );
			if ( $i ) {
				$value = preg_replace( '/\s*I$/i', '', $value, 1 );
			}
			return $this->selectorsAttr( self::decodeid( $cap[ 4 ] ), $cap[ 5 ] ?? '-', self::unquote( $value ), (bool)$i );
		}

		throw new InvalidArgumentException( 'Unknown Selector.' );
	}

	// Returns true if all $func return true
	private static function makeSimple( array $func ): callable {
		$l = count( $func );

		// Potentially make sure
		// `el` is truthy.
		if ( $l < 2 ) {
			return $func[ 0 ];
		}

		return function ( DOMNode $el ) use ( $l, $func ): bool {
			for ( $i = 0;  $i < $l;  $i++ ) {
				if ( !call_user_func( $func[ $i ], $el ) ) {
					return false;
				}
			}
			return true;
		};
	}

	// Returns the element that all $func return
	private static function makeTest( array $func ): ZestFunc {
		if ( count( $func ) < 2 ) {
			return new ZestFunc( function ( DOMNode $el ) use ( $func ): bool {
				return (bool)call_user_func( $func[ 0 ], $el );
			} );
		}
		return new ZestFunc( function ( DOMNode $el ) use ( $func ): bool {
			$i = count( $func );
			while ( $i-- ) {
				if ( !( $el = call_user_func( $func[ $i ], $el ) ) ) {
					return false;
				}
			}
			return true;
		} );
	}

	private static function makeSubject(): ZestFunc {
		$target = null;

		$subject = new ZestFunc( function ( DOMNode $el ) use ( &$subject, &$target ): bool {
			$node = $el->ownerDocument;
			$scope = self::getElementsByTagName( $node, $subject->lname );
			$i = count( $scope );

			while ( $i-- ) {
				if ( call_user_func( $subject->test->func, $scope->item( $i ) ) && $target === $el ) {
					$target = null;
					return true;
				}
			}

			$target = null;
			return false;
		} );

		$subject->simple = function ( DOMNode $el ): bool {
			$target = $el;
			return true;
		};

		return $subject;
	}

	/**
	 * @return callable(DOMNode):bool
	 */
	private function compileGroup( string $sel ): callable {
		$test = $this->compile( $sel );
		$tests = [ $test ];

		while ( $test->sel ) {
			$test = $this->compile( $test->sel );
			$tests[] = $test;
		}

		if ( count( $tests ) < 2 ) {
			return $test->func;
		}

		return function ( DOMNode $el ) use ( $tests ): bool {
			for ( $i = 0, $l = count( $tests );  $i < $l;  $i++ ) {
				if ( call_user_func( $tests[ $i ]->func, $el ) ) {
					return true;
				}
			}
			return false;
		};
	}

	/**
	 * Selection
	 */

	// $node should be a DOMDocument or a DOMElement

	/** @param DOMDocument|DOMElement $node */
	private function findInternal( string $sel, DOMNode $node ): array {
		$results = [];
		$test = $this->compile( $sel );
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$scope = self::getElementsByTagName( $node, $test->qname );
		$i = 0;
		$el = null;

		foreach ( $scope as $el ) {
			if ( call_user_func( $test->func, $el ) ) {
				$results[] = $el;
			}
		}

		if ( $test->sel ) {
			while ( $test->sel ) {
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$test = $this->compile( $test->sel );
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
				$scope = self::getElementsByTagName( $node, $test->qname );
				foreach ( $scope as $el ) {
					if ( call_user_func( $test->func, $el ) && !in_array( $el, $results, true ) ) {
						$results[] = $el;
					}
				}
			}
			// $results->sort( $order );//XXX
		}

		return $results;
	}

	/**
	 * Find elements matching a CSS selector underneath $context.
	 * @param string $sel The CSS selector string
	 * @param DOMDocument|DOMElement $context The scope for the search
	 * @return array Elements matching the CSS selector
	 */
	public function find( string $sel, DOMNode $context ): array {
		/* when context isn't a DocumentFragment and the selector is simple: */
		if ( $context->nodeType !== 11 && strpos( $sel, ' ' ) === false ) {
			// https://www.w3.org/TR/CSS21/syndata.html#value-def-identifier
			// Valid identifiers starting with a hyphen or with escape
			// sequences will be handled correctly by the fall-through case.
			if ( $sel[ 0 ] === '#' /*&& $context->rooted*/ && preg_match( '/^#[A-Za-z_](?:[-A-Za-z0-9_]|[^\0-\237])*$/Su', $sel ) ) {
				// Note that the PHP implementation can't detect the case
				// where there are multiple elements with the same ID. Alas.
				/*
				if ( $context->doc->_hasMultipleElementsWithId ) {
					$id = $sel->substring( 1 );
					if ( !$context->doc->_hasMultipleElementsWithId( $id ) ) {
						$r = $context->doc->getElementById( $id );
						return ( $r ) ? [ $r ] : [];
					}
				}
				*/
				$id = substr( $sel, 1 );
				return self::getElementsById( $context, $id );
			}
			if ( $sel[ 0 ] === '.' && preg_match( '/^\.\w+$/', $sel ) ) {
				return iterator_to_array( self::getElementsByClassName( $context, substr( $sel, 1 ) ) );
			}
			if ( preg_match( '/^\w+$/', $sel ) ) {
				return iterator_to_array( self::getElementsByTagName( $context, $sel ) );
			}
		}
		/* do things the hard/slow way */
		return $this->findInternal( $sel, $context );
	}

	/**
	 * Determine whether an element matches the given selector.
	 * @param DOMNode $el The element to be tested
	 * @param string $sel The CSS selector string
	 * @return bool True iff the element matches the selector
	 */
	public function matches( DOMNode $el, string $sel ): bool {
		$test = new ZestFunc( function ( DOMNode $el ):bool {
			return true;
		} );
		$test->sel = $sel;
		do {
			$test = $this->compile( $test->sel );
			if ( call_user_func( $test->func, $el ) ) {
				return true;
			}
		} while ( $test->sel );
		return false;
	}

	/** @var ?ZestInst */
	private static $singleton = null;

	function __construct() {
		$z = self::$singleton;
		$this->selectors0 = $z ? $z->selectors0 : [];
		$this->selectors1 = $z ? $z->selectors1 : [];
		$this->operators = $z ? $z->operators : [];
		$this->combinators = $z ? $z->combinators : [];
		if ( !$z ) {
			$this->initRules();
			$this->initSelectors();
			$this->initOperators();
			$this->initCombinators();
			self::$singleton = $this;
			// Now create another instance so that backing arrays are cloned
			// @phan-suppress-next-line PhanPossiblyInfiniteRecursionSameParams
			self::$singleton = new ZestInst;
		}
	}
}
