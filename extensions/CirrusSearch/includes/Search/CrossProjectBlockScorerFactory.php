<?php

namespace CirrusSearch\Search;

use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\SearchConfig;

/**
 * Factory that reads cirrus config and builds a CrossProjectBlockScorer
 */
class CrossProjectBlockScorerFactory {
	/**
	 * @param SearchConfig $searchConfig
	 * @return CrossProjectBlockScorer
	 */
	public static function load( SearchConfig $searchConfig ) {
		$profile = $searchConfig->getProfileService()
			->loadProfile( SearchProfileService::CROSS_PROJECT_BLOCK_SCORER );
		return static::loadScorer( $profile['type'], $profile['settings'] ?? [] );
	}

	public static function loadScorer( $type, array $config ) {
		switch ( $type ) {
			case 'composite':
				return new CompositeCrossProjectBlockScorer( $config );
			case 'random':
				return new RandomCrossProjectBlockScorer( $config );
			case 'recall':
				return new RecallCrossProjectBlockScorer( $config );
			case 'static':
				return new StaticCrossProjectBlockScorer( $config );
			default:
				throw new \RuntimeException( 'Unknown CrossProjectBlockScorer type : ' . $type );
		}
	}
}
