<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use DOMDocument;

use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class RawHTML extends ExtensionTagHandler implements ExtensionModule {
	/** @inheritDoc */
	public function sourceToDom(
		ParsoidExtensionAPI $extApi, string $content, array $args
	): DOMDocument {
		return $extApi->htmlToDom( $content );
	}

	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'RawHTML',
			'tags' => [
				[ 'name' => 'html', 'handler' => self::class ],
			],
		];
	}
}
