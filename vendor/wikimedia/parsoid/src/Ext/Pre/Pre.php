<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Pre;

use DOMDocument;
use Wikimedia\Parsoid\Ext\DOMDataUtils;
use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Ext\Utils;
use Wikimedia\Parsoid\Utils\DOMCompat;

/**
 * The `<pre>` extension tag shadows the html pre tag, but has different
 * semantics.  It treats anything inside it as plaintext.
 */
class Pre extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => '<pre>',
			'tags' => [
				[
					'name' => 'pre',
					'handler' => self::class,
				]
			]
		];
	}

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $txt, array $extArgs
	): DOMDocument {
		$doc = $extApi->htmlToDom( '' ); // Empty doc
		$pre = $doc->createElement( 'pre' );

		$extApi->sanitizeArgs( $pre, $extArgs );
		DOMDataUtils::getDataParsoid( $pre )->stx = 'html';

		// Support nowikis in pre.  Do this before stripping newlines, see test,
		// "<pre> with <nowiki> inside (compatibility with 1.6 and earlier)"
		$txt = preg_replace( '/<nowiki\s*>(.*?)<\/nowiki\s*>/s', '$1', $txt );

		// Strip leading newline to match legacy php parser.  This is probably because
		// it doesn't do xml serialization accounting for `newlineStrippingElements`
		// Of course, this leads to indistinguishability between n=0 and n=1
		// newlines, but that only seems to affect parserTests output.  Rendering
		// is the same, and the newline is preserved for rt in the `extSrc`.
		$txt = preg_replace( '/^\n/', '', $txt, 1 );

		// `extSrc` will take care of rt'ing these
		$txt = Utils::decodeWtEntities( $txt );

		$pre->appendChild( $doc->createTextNode( $txt ) );
		DOMCompat::getBody( $doc )->appendChild( $pre );

		return $doc;
	}

}
