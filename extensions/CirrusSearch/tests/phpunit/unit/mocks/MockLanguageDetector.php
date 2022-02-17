<?php

namespace CirrusSearch\Test;

use CirrusSearch\LanguageDetector\Detector;
use CirrusSearch\SearchConfig;

/**
 * Mock language detector implementation returns
 * CirrusSearchMockLanguage from SearchConfig
 */
class MockLanguageDetector implements Detector {
	/** @var string */
	private $mockLanguage;

	/**
	 * @param string $mockLanguage
	 */
	public function __construct( $mockLanguage ) {
		$this->mockLanguage = $mockLanguage;
	}

	/**
	 * @param SearchConfig $config
	 * @return Detector
	 */
	public static function build( SearchConfig $config ) {
		return new self( $config->get( 'CirrusSearchMockLanguage' ) );
	}

	/**
	 * Detect language
	 *
	 * @param string $text Text to detect language
	 * @return string|null Preferred language, or null if none found
	 */
	public function detect( $text ) {
		return $this->mockLanguage;
	}
}
