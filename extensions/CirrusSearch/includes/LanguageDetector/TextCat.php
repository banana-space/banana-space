<?php

namespace CirrusSearch\LanguageDetector;

use CirrusSearch\SearchConfig;
use MediaWiki\Logger\LoggerFactory;

/**
 * Try to detect language with TextCat text categorizer
 */
class TextCat implements Detector {

	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @param SearchConfig $config
	 */
	public function __construct( SearchConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Detect language
	 *
	 * @param string $text Text to detect language
	 * @return string|null Preferred language, or null if none found
	 */
	public function detect( $text ) {
		$dirs = $this->config->getElement( 'CirrusSearchTextcatModel' );
		if ( !$dirs ) {
			return null;
		}
		if ( !is_array( $dirs ) ) { // backward compatibility
			$dirs = [ $dirs ];
		}
		foreach ( $dirs as $dir ) {
			if ( !is_dir( $dir ) ) {
				LoggerFactory::getInstance( 'CirrusSearch' )->warning(
					"Bad directory for TextCat model: {dir}",
					[ "dir" => $dir ]
				);
			}
		}

		$textcat = new \TextCat( $dirs );

		$textcatConfig = $this->config->getElement( 'CirrusSearchTextcatConfig' );
		if ( $textcatConfig ) {
			if ( isset( $textcatConfig['maxNgrams'] ) ) {
				$textcat->setMaxNgrams( intval( $textcatConfig['maxNgrams'] ) );
			}
			if ( isset( $textcatConfig['maxReturnedLanguages'] ) ) {
				$textcat->setMaxReturnedLanguages( intval( $textcatConfig['maxReturnedLanguages'] ) );
			}
			if ( isset( $textcatConfig['resultsRatio'] ) ) {
				$textcat->setResultsRatio( floatval( $textcatConfig['resultsRatio'] ) );
			}
			if ( isset( $textcatConfig['minInputLength'] ) ) {
				$textcat->setMinInputLength( intval( $textcatConfig['minInputLength'] ) );
			}
			if ( isset( $textcatConfig['maxProportion'] ) ) {
				$textcat->setMaxProportion( floatval( $textcatConfig['maxProportion'] ) );
			}
			if ( isset( $textcatConfig['langBoostScore'] ) ) {
				$textcat->setLangBoostScore( floatval( $textcatConfig['langBoostScore'] ) );
			}

			if ( isset( $textcatConfig['numBoostedLangs'] ) &&
				$this->config->getElement( 'CirrusSearchTextcatLanguages' )
			) {
				$textcat->setBoostedLangs( array_slice(
					$this->config->getElement( 'CirrusSearchTextcatLanguages' ),
					0, $textcatConfig['numBoostedLangs'] ) );
			}
		}
		$languages = $textcat->classify( $text, $this->config->getElement( 'CirrusSearchTextcatLanguages' ) );
		if ( !empty( $languages ) ) {
			// For now, just return the best option
			// TODO: think what else we could do
			reset( $languages );
			return key( $languages );
		}

		return null;
	}

	/**
	 * @param SearchConfig $config
	 * @return Detector
	 */
	public static function build( SearchConfig $config ) {
		return new self( $config );
	}
}
