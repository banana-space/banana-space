<?php

namespace Wikimedia\Parsoid\Language;

use DOMElement;

/**
 * A simple {@link LanguageGuesser} that returns the same "source language" for every node.
 * Appropriate for wikis which by convention are written in a single variant.
 */
class ConstantLanguageGuesser extends LanguageGuesser {

	/** @var string */
	private $langCode;

	/**
	 * ConstantLanguageGuesser constructor.
	 * @param string $langCode
	 */
	public function __construct( string $langCode ) {
		$this->langCode = $langCode;
	}

	/** @inheritDoc */
	public function guessLang( DOMElement $node ): string {
		return $this->langCode;
	}

}
