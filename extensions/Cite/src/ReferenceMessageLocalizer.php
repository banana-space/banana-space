<?php

namespace Cite;

use Language;
use Message;
use MessageLocalizer;
use MessageSpecifier;

/**
 * Interface abstracts everything a Cite needs to do with languages.
 */
class ReferenceMessageLocalizer implements MessageLocalizer {

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @param Language $language
	 */
	public function __construct( Language $language ) {
		$this->language = $language;
	}

	/**
	 * Transliterate numerals, add thousands separators and localize the decimal point.
	 *
	 * @param string $number
	 *
	 * @return string
	 */
	public function formatNum( string $number ) : string {
		return $this->language->formatNum( $number );
	}

	/**
	 * Transliterate numerals, without adding or changing separators.
	 *
	 * @param string $number
	 *
	 * @return string
	 */
	public function localizeDigits( string $number ) : string {
		return $this->language->formatNumNoSeparators( $number );
	}

	/**
	 * This is the method for getting translated interface messages.
	 *
	 * Note that it returns messages coerced to a specific language, the content language
	 * rather than the UI language.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Messages_API
	 * @see Message::__construct
	 *
	 * @param string|string[]|MessageSpecifier $key Message key, or array of keys,
	 *   or a MessageSpecifier.
	 * @param mixed ...$params Normal message parameters
	 *
	 * @return Message
	 */
	public function msg( $key, ...$params ) : Message {
		return wfMessage( $key, ...$params )->inLanguage( $this->language );
	}

}
