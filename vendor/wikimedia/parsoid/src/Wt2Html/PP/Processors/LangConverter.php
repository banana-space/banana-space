<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Wt2Html\PP\Processors;

use DOMElement;
use Wikimedia\Parsoid\Config\Env;
use Wikimedia\Parsoid\Language\LanguageConverter;
use Wikimedia\Parsoid\Wt2Html\Wt2HtmlDOMProcessor;

class LangConverter implements Wt2HtmlDOMProcessor {
	/**
	 * @inheritDoc
	 */
	public function run(
		Env $env, DOMElement $root, array $options = [], bool $atTopLevel = false
	): void {
		LanguageConverter::maybeConvert(
			$env,
			$root->ownerDocument,
			$env->getHtmlVariantLanguage(),
			$env->getWtVariantLanguage()
		);
	}
}
