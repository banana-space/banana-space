<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use DOMDocument;
use Error;

use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

/**
 * See tests/parser/ParserTestParserHook.php in core.
 */
class ParserHook extends ExtensionTagHandler implements ExtensionModule {

	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DOMDocument {
		$extName = $extApi->getExtensionName();
		switch ( $extName ) {
			case 'tag':
			case 'tåg':
				return $extApi->htmlToDom( '<pre />' );

			case 'statictag':
				// FIXME: Choose a better DOM representation that doesn't mess with
				// newline constraints.
				return $extApi->htmlToDom( '<span />' );

			default:
				throw new Error( "Unexpected tag name: $extName in ParserHook" );
		}
	}

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'ParserHook',
			'tags' => [
				[ 'name' => 'tag', 'handler' => self::class ],
				[ 'name' => 'tåg', 'handler' => self::class ],
				[ 'name' => 'statictag', 'handler' => self::class ],
			],
			'domProcessors' => [
				ParserHookProcessor::class
			]
		];
	}
}
