<?php

namespace RemexHtml\DOM;

use RemexHtml\Tokenizer\Attributes;
use RemexHtml\TreeBuilder\Element;
use RemexHtml\TreeBuilder\TreeBuilder;
use RemexHtml\TreeBuilder\TreeHandler;

/**
 * A TreeHandler which constructs a DOMDocument
 */
class DOMBuilder implements TreeHandler {
	public $doctypeName;
	public $public;
	public $system;
	public $quirks;

	private $doc;
	private $errorCallback;
	private $isFragment;
	private $coerced;

	/**
	 * @param callable|null $errorCallback A function which is called on parse errors
	 */
	public function __construct( $errorCallback = null ) {
		$this->errorCallback = $errorCallback;
	}

	/**
	 * Get the constructed document or document fragment. In the fragment case,
	 * a DOMElement is returned, and the caller is expected to extract its
	 * inner contents, ignoring the wrapping element. This convention is
	 * convenient because the wrapping element gives libxml somewhere to put
	 * its namespace declarations. If we copied the children into a
	 * DOMDocumentFragment, libxml would invent new prefixes for the orphaned
	 * namespaces.
	 *
	 * @return DOMNode
	 */
	public function getFragment() {
		if ( $this->isFragment ) {
			return $this->doc->documentElement;
		} else {
			return $this->doc;
		}
	}

	/**
	 * Returns true if the document was coerced due to libxml limitations. We
	 * follow HTML 5.1 § 8.2.7 "Coercing an HTML DOM into an infoset".
	 *
	 * @return bool
	 */
	public function isCoerced() {
		return $this->coerced;
	}

	public function startDocument( $fragmentNamespace, $fragmentName ) {
		$impl = new \DOMImplementation;
		$this->isFragment = $fragmentNamespace !== null;
		$this->doc = $this->createDocument();
	}

	private function createDocument( $doctypeName = null, $public = null, $system = null ) {
		$impl = new \DOMImplementation;
		if ( $doctypeName === '' ) {
			$this->coerced = true;
			$doc = $impl->createDocument( null, null );
		} elseif ( $doctypeName === null ) {
			$doc = $impl->createDocument( null, null );
		} else {
			$doctype = $impl->createDocumentType( $doctypeName, $public, $system );
			$doc = $impl->createDocument( null, null, $doctype );
		}
		$doc->encoding = 'UTF-8';
		return $doc;
	}

	public function endDocument( $pos ) {
	}

	private function insertNode( $preposition, $refElement, $node ) {
		if ( $preposition === TreeBuilder::ROOT ) {
			$parent = $this->doc;
			$refNode = null;
		} elseif ( $preposition === TreeBuilder::BEFORE ) {
			$parent = $refElement->userData->parentNode;
			$refNode = $refElement->userData;
		} else {
			$parent = $refElement->userData;
			$refNode = null;
		}
		$parent->insertBefore( $node, $refNode );
	}

	/**
	 * Replace unsupported characters with a code of the form U123456.
	 *
	 * @param string $name
	 * @return string
	 */
	private function coerceName( $name ) {
		$coercedName = DOMUtils::coerceName( $name );
		if ( $name !== $coercedName ) {
			$this->coerced = true;
		}
		return $coercedName;
	}

	private function createNode( Element $element ) {
		try {
			$node = $this->doc->createElementNS(
				$element->namespace,
				$element->name );
		} catch ( \DOMException $e ) {
			// Attempt to escape the name so that it is more acceptable
			$node = $this->doc->createElementNS(
				$element->namespace,
				$this->coerceName( $element->name ) );
		}

		foreach ( $element->attrs->getObjects() as $attr ) {
			if ( $attr->namespaceURI === null
				&& strpos( $attr->localName, ':' ) !== false
			) {
				// FIXME: this apparently works to create a prefixed localName
				// in the null namespace, but this is probably taking advantage
				// of a bug in PHP's DOM library, and screws up in various
				// interesting ways. For example, attributes created in this
				// way can't be discovered via hasAttribute() or hasAttributeNS().
				$attrNode = $this->doc->createAttribute( $attr->localName );
				$attrNode->value = $attr->value;
				try {
					$node->setAttributeNodeNS( $attrNode );
				} catch ( \DOMException $e ) {
					$node->setAttributeNS(
						$attr->namespaceURI,
						$this->coerceName( $attr->qualifiedName ),
						$attr->value );
				}
			} else {
				try {
					$node->setAttributeNS(
						$attr->namespaceURI,
						$attr->qualifiedName,
						$attr->value );
				} catch ( \DOMException $e ) {
					$node->setAttributeNS(
						$attr->namespaceURI,
						$this->coerceName( $attr->qualifiedName ),
						$attr->value );
				}
			}
		}
		$element->userData = $node;
		return $node;
	}

	public function characters( $preposition, $refElement, $text, $start, $length,
		$sourceStart, $sourceLength
	) {
		$node = $this->doc->createTextNode( substr( $text, $start, $length ) );
		$this->insertNode( $preposition, $refElement, $node );
	}

	public function insertElement( $preposition, $refElement, Element $element, $void,
		$sourceStart, $sourceLength
	) {
		if ( $element->userData ) {
			$node = $element->userData;
		} else {
			$node = $this->createNode( $element );
		}
		$this->insertNode( $preposition, $refElement, $node );
	}

	public function endTag( Element $element, $sourceStart, $sourceLength ) {
	}

	public function doctype( $name, $public, $system, $quirks, $sourceStart, $sourceLength ) {
		if ( !$this->doc->firstChild ) {
			$impl = $this->doc->implementation;
			$this->doc = $this->createDocument( $name, $public, $system );
		}
		$this->doctypeName = $name;
		$this->public = $public;
		$this->system = $system;
		$this->quirks = $quirks;
	}

	public function comment( $preposition, $refElement, $text, $sourceStart, $sourceLength ) {
		$node = $this->doc->createComment( $text );
		$this->insertNode( $preposition, $refElement, $node );
	}

	public function error( $text, $pos ) {
		if ( $this->errorCallback ) {
			call_user_func( $this->errorCallback, $text, $pos );
		}
	}

	public function mergeAttributes( Element $element, Attributes $attrs, $sourceStart ) {
		$node = $element->userData;
		foreach ( $attrs->getObjects() as $name => $attr ) {
			if ( $attr->namespaceURI === null
				&& strpos( $attr->localName, ':' ) !== false
			) {
				// As noted in createNode(), we can't use hasAttribute() here.
				// However, we can use the return value of setAttributeNodeNS()
				// instead.
				$attrNode = $this->doc->createAttribute( $attr->localName );
				$attrNode->value = $attr->value;
				try {
					$replaced = $node->setAttributeNodeNS( $attrNode );
				} catch ( \DOMException $e ) {
					$attrNode = $this->doc->createAttribute(
						$this->coerceName( $attr->localName ) );
					$attrNode->value = $attr->value;
					$replaced = $node->setAttributeNodeNS( $attrNode );
				}
				if ( $replaced ) {
					// Put it back how it was
					$node->setAttributeNodeNS( $replaced );
				}
			} elseif ( $attr->namespaceURI === null ) {
				try {
					if ( !$node->hasAttribute( $attr->localName ) ) {
						$node->setAttribute( $attr->localName, $attr->value );
					}
				} catch ( \DOMException $e ) {
					$name = $this->coerceName( $attr->localName );
					if ( !$node->hasAttribute( $name ) ) {
						$node->setAttribute( $name, $attr->value );
					}
				}
			} else {
				try {
					if ( !$node->hasAttributeNS( $attr->namespaceURI, $attr->localName ) ) {
						$node->setAttributeNS( $attr->namespaceURI,
							$attr->localName, $attr->value );
					}
				} catch ( \DOMException $e ) {
					$name = $this->coerceName( $attr->localName );
					if ( !$node->hasAttributeNS( $attr->namespaceURI, $name ) ) {
						$node->setAttributeNS( $attr->namespaceURI, $name, $attr->value );
					}
				}
			}
		}
	}

	public function removeNode( Element $element, $sourceStart ) {
		$node = $element->userData;
		$node->parentNode->removeChild( $node );
	}

	public function reparentChildren( Element $element, Element $newParent, $sourceStart ) {
		$this->insertElement( TreeBuilder::UNDER, $element, $newParent, false, $sourceStart, 0 );
		$node = $element->userData;
		$newParentNode = $newParent->userData;
		while ( $node->firstChild !== $newParentNode ) {
			$newParentNode->appendChild( $node->firstChild );
		}
	}
}
