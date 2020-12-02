<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use DOMDocument;

use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Utils\DOMCompat;

class StyleTag extends ExtensionTagHandler implements ExtensionModule {
	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DOMDocument {
		$doc = $extApi->htmlToDom( '' ); // Empty doc
		$style = $doc->createElement( 'style' );
		DOMCompat::setInnerHTML( $style, $content );
		$extApi->sanitizeArgs( $style, $args );
		DOMCompat::getBody( $doc )->appendChild( $style );
		return $doc;
	}

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'StyleTag',
			'tags' => [
				[ 'name' => 'style', 'handler' => self::class ],
			],
		];
	}
}
