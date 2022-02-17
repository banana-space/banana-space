<?php

namespace CirrusSearch\LanguageDetector;

use CirrusSearch\SearchConfig;
use MediaWiki\Logger\LoggerFactory;

class LanguageDetectorFactory {
	/**
	 * SearchConfig
	 */
	private $config;

	/**
	 * @param SearchConfig $config
	 */
	public function __construct( SearchConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @return Detector[] array of detectors indexed by name
	 */
	public function getDetectors() {
		$detectors = [];
		foreach ( $this->config->get( 'CirrusSearchLanguageDetectors' ) as $name => $klass ) {
			if ( !class_exists( $klass ) ) {
				LoggerFactory::getInstance( 'CirrusSearch' )->info(
					"Unknown detector class for {name}: {class}",
					[
						"name" => $name,
						"class" => $klass,
					]
				);
				continue;
			}
			if ( !in_array( \CirrusSearch\LanguageDetector\Detector::class, class_implements( $klass ) ) ) {
				LoggerFactory::getInstance( 'CirrusSearch' )->info(
					"Bad detector class for {name}: {class}",
					[
						"name" => $name,
						"class" => $klass,
					]
				);
				continue;
			}
			$detectors[$name] = $klass::build( $this->config );
		}
		return $detectors;
	}
}
