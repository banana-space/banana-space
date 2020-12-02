<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Core;

use DOMDocument;

use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Ext\ContentModelHandler as ExtContentModelHandler;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;

class ExtensionContentModelHandler extends ContentModelHandler {

	/**
	 * @var ExtContentModelHandler
	 */
	private $impl;

	/**
	 * @param ExtContentModelHandler $impl
	 */
	public function __construct( ExtContentModelHandler $impl ) {
		$this->impl = $impl;
	}

	/**
	 * @inheritDoc
	 */
	public function toDOM( Env $env ): DOMDocument {
		$extApi = new ParsoidExtensionAPI( $env );
		return $this->impl->toDOM(
			$extApi, $env->getPageConfig()->getPageMainContent()
		);
	}

	/**
	 * @inheritDoc
	 */
	public function fromDOM(
		Env $env, DOMDocument $doc, ?SelserData $selserData = null
	): string {
		$extApi = new ParsoidExtensionAPI( $env );
		// FIXME: `$selserData` is ignored?
		return $this->impl->fromDOM( $extApi, $doc );
	}

}
