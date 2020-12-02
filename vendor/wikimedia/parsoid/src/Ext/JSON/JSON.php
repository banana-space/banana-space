<?php
/**
 * This implements the "json" content model as an extension, to allow editing
 * JSON data structures using Visual Editor.  It represents the JSON
 * structure as a nested table.
 */

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\JSON;

use DOMDocument;
use DOMElement;
use Wikimedia\Assert\Assert;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Ext\ContentModelHandler;
use Wikimedia\Parsoid\Ext\DOMUtils;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Ext\PHPUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * Native Parsoid implementation of the "json" contentmodel.
 * @class
 */
class JSON extends ContentModelHandler implements ExtensionModule {
	private const PARSE_ERROR_HTML = '<!DOCTYPE html><html>'
		. '<body>'
		. "<table data-mw='{\"errors\":[{\"key\":\"bad-json\"}]}' typeof=\"mw:Error\">"
		. '</body>';

	/**
	 * @var DOMDocument
	 */
	protected $document;

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'JSON content',
			'contentModels' => [
				'json' => self::class,
			],
		];
	}

	/**
	 * @param DOMElement $parent
	 * @param array|object|string $val
	 */
	private function rootValueTable( DOMElement $parent, $val ): void {
		if ( is_array( $val ) ) {
			// Wrap arrays in another array so they're visually boxed in a
			// container.  Otherwise they are visually indistinguishable from
			// a single value.
			self::arrayTable( $parent, [ $val ] );
			return;
		}

		if ( $val && is_object( $val ) ) {
			self::objectTable( $parent, (array)$val );
			return;
		}

		DOMCompat::setInnerHTML( $parent,
			'<table class="mw-json mw-json-single-value"><tbody><tr><td>' );
		self::primitiveValue( DOMCompat::querySelector( $parent, 'td' ), $val );
	}

	/**
	 * @param DOMElement $parent
	 * @param array $val
	 */
	private function objectTable( DOMElement $parent, array $val ): void {
		DOMCompat::setInnerHTML( $parent,
			'<table class="mw-json mw-json-object"><tbody>' );
		$tbody = $parent->firstChild->firstChild;
		DOMUtils::assertElt( $tbody );
		$keys = array_keys( $val );
		if ( count( $keys ) ) {
			foreach ( $val as $k => $v ) {
				self::objectRow( $tbody, (string)$k, $v );
			}
		} else {
			DOMCompat::setInnerHTML( $tbody,
				'<tr><td class="mw-json-empty">' );
		}
	}

	/**
	 * @param DOMElement $parent
	 * @param string|null $key
	 * @param mixed $val
	 */
	private function objectRow( DOMElement $parent, ?string $key, $val ): void {
		$tr = $this->document->createElement( 'tr' );
		if ( $key !== null ) {
			$th = $this->document->createElement( 'th' );
			$th->textContent = $key;
			$tr->appendChild( $th );
		}
		self::valueCell( $tr, $val );
		$parent->appendChild( $tr );
	}

	/**
	 * @param DOMElement $parent
	 * @param array $val
	 */
	private function arrayTable( DOMElement $parent, array $val ): void {
		DOMCompat::setInnerHTML( $parent,
			'<table class="mw-json mw-json-array"><tbody>' );
		$tbody = $parent->firstChild->firstChild;
		DOMUtils::assertElt( $tbody );
		if ( count( $val ) ) {
			foreach ( $val as $v ) {
				self::objectRow( $tbody, null, $v );
			}
		} else {
			DOMCompat::setInnerHTML( $tbody,
				'<tr><td class="mw-json-empty">' );
		}
	}

	/**
	 * @param DOMElement $parent
	 * @param mixed $val
	 */
	private function valueCell( DOMElement $parent, $val ): void {
		$td = $this->document->createElement( 'td' );
		if ( is_array( $val ) ) {
			self::arrayTable( $td, $val );
		} elseif ( $val && is_object( $val ) ) {
			self::objectTable( $td, (array)$val );
		} else {
			DOMCompat::getClassList( $td )->add( 'value' );
			self::primitiveValue( $td, $val );
		}
		$parent->appendChild( $td );
	}

	/**
	 * @param DOMElement $parent
	 * @param string|int|bool|null $val
	 */
	private function primitiveValue( DOMElement $parent, $val ): void {
		if ( $val === null ) {
			DOMCompat::getClassList( $parent )->add( 'mw-json-null' );
			$parent->textContent = 'null';
			return;
		} elseif ( is_bool( $val ) ) {
			DOMCompat::getClassList( $parent )->add( 'mw-json-boolean' );
			$parent->textContent = [ 'false', 'true' ][$val === true];
			return;
		} elseif ( is_int( $val ) || is_float( $val ) ) {
			DOMCompat::getClassList( $parent )->add( 'mw-json-number' );
		} elseif ( is_string( $val ) ) {
			DOMCompat::getClassList( $parent )->add( 'mw-json-string' );
		}
		$parent->textContent = (string)$val;
	}

	/**
	 * JSON to HTML.
	 * Implementation matches that from includes/content/JsonContent.php in
	 * mediawiki core, except that we distinguish value types.
	 * @param ParsoidExtensionAPI $API
	 * @param string $jsonText
	 * @return DOMDocument
	 */
	public function toDOM( ParsoidExtensionAPI $API, string $jsonText ): DOMDocument {
		$this->document = $API->htmlToDom( '<!DOCTYPE html><html><body>' );
		$src = null;

// PORT-FIXME When production moves to PHP 7.3, re-enable this try catch code
/*		try {
			$src = json_decode( $jsonText, false, 6, JSON_THROW_ON_ERROR );
			self::rootValueTable( DOMCompat::getBody( $this->document ), $src );
		} catch ( Exception $e ) {
			$this->document = $API->htmlToDom( self::PARSE_ERROR_HTML );
		}
*/
		$src = json_decode( $jsonText, false, 6 );
		if ( $src === null && json_last_error() !== JSON_ERROR_NONE ) {
			$this->document = $API->htmlToDom( self::PARSE_ERROR_HTML );
		} else {
			self::rootValueTable( DOMCompat::getBody( $this->document ), $src );
		}
/* end of PHP 7.2 compatible error handling code, remove whem enabling 7.3+ try catch code */

		// We're responsible for running the standard DOMPostProcessor on our
		// resulting document.
		$API->postProcessDOM( $this->document );

		return $this->document;
	}

	/**
	 * RootValueTableFrom
	 * @param DOMElement $el
	 * @return array|false|int|string|null
	 */
	private function rootValueTableFrom( DOMElement $el ) {
		if ( DOMCompat::getClassList( $el )->contains( 'mw-json-single-value' ) ) {
			return self::primitiveValueFrom( DOMCompat::querySelector( $el, 'tr > td' ) );
		} elseif ( DOMCompat::getClassList( $el )->contains( 'mw-json-array' ) ) {
			return self::arrayTableFrom( $el )[0];
		} else {
			return self::objectTableFrom( $el );
		}
	}

	/**
	 * @param DOMElement $el
	 * @return array
	 */
	private function objectTableFrom( DOMElement $el ) {
		Assert::invariant( DOMCompat::getClassList( $el )->contains( 'mw-json-object' ),
			'Expected mw-json-object' );
		$tbody = $el;
		if ( $tbody->firstChild ) {
			$child = $tbody->firstChild;
			DOMUtils::assertElt( $child );
			if ( $child->tagName === 'tbody' ) {
				$tbody = $child;
			}
		}
		$rows = $tbody->childNodes;
		$obj = [];
		$empty = count( $rows ) === 0;
		if ( !$empty ) {
			$child = $rows->item( 0 )->firstChild;
			DOMUtils::assertElt( $child );
			if ( DOMCompat::getClassList( $child )->contains( 'mw-json-empty' ) ) {
				$empty = true;
			}
		}
		if ( !$empty ) {
			for ( $i = 0; $i < count( $rows ); $i++ ) {
				$item = $rows->item( $i );
				DOMUtils::assertElt( $item );
				self::objectRowFrom( $item, $obj, null );
			}
		}
		return $obj;
	}

	/**
	 * @param DOMElement $tr
	 * @param array &$obj
	 * @param int|null $key
	 */
	private function objectRowFrom( DOMElement $tr, array &$obj, ?int $key ) {
		$td = $tr->firstChild;
		if ( $key === null ) {
			$key = $td->textContent;
			$td = $td->nextSibling;
		}
		DOMUtils::assertElt( $td );
		$obj[$key] = self::valueCellFrom( $td );
	}

	/**
	 * @param DOMElement $el
	 * @return array
	 */
	private function arrayTableFrom( DOMElement $el ): array {
		Assert::invariant( DOMCompat::getClassList( $el )->contains( 'mw-json-array' ),
			'Expected ms-json-array' );
		$tbody = $el;
		if ( $tbody->firstChild ) {
			$child = $tbody->firstChild;
			DOMUtils::assertElt( $child );
			if ( $child->tagName === 'tbody' ) {
				$tbody = $child;
			}
		}
		$rows = $tbody->childNodes;
		$arr = [];
		$empty = count( $rows ) === 0;
		if ( !$empty ) {
			$child = $rows->item( 0 )->firstChild;
			DOMUtils::assertElt( $child );
			if ( DOMCompat::getClassList( $child )->contains( 'mw-json-empty' ) ) {
				$empty = true;
			}
		}
		if ( !$empty ) {
			for ( $i = 0; $i < count( $rows ); $i++ ) {
				$item = $rows->item( $i );
				DOMUtils::assertElt( $item );
				self::objectRowFrom( $item, $arr, $i );
			}
		}
		return $arr;
	}

	/**
	 * @param DOMElement $el
	 * @return array|object|false|float|int|string|null
	 */
	private function valueCellFrom( DOMElement $el ) {
		Assert::invariant( $el->tagName === 'td', 'Expected tagName = td' );
		$table = $el->firstChild;
		if ( $table && DOMUtils::isElt( $table ) ) {
			DOMUtils::assertElt( $table );
			if ( DOMCompat::getClassList( $table )->contains( 'mw-json-array' ) ) {
				return self::arrayTableFrom( $table );
			} elseif ( DOMCompat::getClassList( $table )->contains( 'mw-json-object' ) ) {
				return self::objectTableFrom( $table );
			}
		} else {
			return self::primitiveValueFrom( $el );
		}
	}

	/**
	 * @param DOMElement $el
	 * @return false|float|int|string|null
	 */
	private function primitiveValueFrom( DOMElement $el ) {
		if ( DOMCompat::getClassList( $el )->contains( 'mw-json-null' ) ) {
			return null;
		} elseif ( DOMCompat::getClassList( $el )->contains( 'mw-json-boolean' ) ) {
			return [ false, true ][preg_match( '/true/', $el->textContent )];
		} elseif ( DOMCompat::getClassList( $el )->contains( 'mw-json-number' ) ) {
			return floatval( $el->textContent );
		} elseif ( DOMCompat::getClassList( $el )->contains( 'mw-json-string' ) ) {
			return (string)$el->textContent;
		} else {
			return null; // shouldn't happen.
		}
	}

	/**
	 * DOM to JSON.
	 * @param ParsoidExtensionAPI $API
	 * @param DOMDocument $doc
	 * @param SelserData|null $selserData
	 * @return string
	 */
	public function fromDOM(
		ParsoidExtensionAPI $API, DOMDocument $doc, ?SelserData $selserData = null
	): string {
		$body = DOMCompat::getBody( $doc );
		Assert::invariant( DOMUtils::isBody( $body ), 'Expected a body node.' );
		$t = $body->firstChild;
		DOMUtils::assertElt( $t );
		Assert::invariant( $t && $t->tagName === 'table',
			'Expected tagName = table' );
		self::rootValueTableFrom( $t );
		return PHPUtils::jsonEncode( self::rootValueTableFrom( $t ) );
	}

}
