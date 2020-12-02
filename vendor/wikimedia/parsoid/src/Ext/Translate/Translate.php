<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Ext\Translate;

use Wikimedia\Parsoid\Ext\ExtensionModule;
use Wikimedia\Parsoid\Ext\ExtensionTagHandler;

/**
 * This is effectively a stub at this point.
 * The JS version doesn't do any work so this version doesn't either.
 * It just intercepts the tags which causes the ExtensionHandler to
 * add a generic extension span wrapper around its contents.
 *
 * So, the PHP version mimics that and hence doesn't implement the
 * sourceToDom method.
 */
class Translate extends ExtensionTagHandler implements ExtensionModule {
	/** @inheritDoc */
	public function getConfig(): array {
		return [
			'name' => 'Translate',
			'tags' => [
				[
					'name' => 'translate',
					'handler' => self::class
				],
				[
					'name' => 'tvar',
					'handler' => self::class
				]
			]
		];
	}

}
