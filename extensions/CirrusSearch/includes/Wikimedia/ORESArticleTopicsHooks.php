<?php

namespace CirrusSearch\Wikimedia;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Query\ArticleTopicFeature;
use CirrusSearch\SearchConfig;
use Config;
use MediaWiki\MediaWikiServices;
use SearchEngine;

/**
 * Functionality related to the (Wikimedia-specific) articletopic search feature.
 * @package CirrusSearch\Wikimedia
 * @see ArticleTopicFeature
 */
class ORESArticleTopicsHooks {
	const FIELD_NAME = 'ores_articletopics';
	const FIELD_SIMILARITY = 'ores_articletopics_similarity';
	const FIELD_INDEX_ANALYZER = 'ores_articletopics';
	const FIELD_SEARCH_ANALYZER = 'keyword';
	const WMF_EXTRA_FEATURES = 'CirrusSearchWMFExtraFeatures';
	const CONFIG_OPTIONS = 'ores_articletopics';
	const BUILD_OPTION = 'build';
	const USE_OPTION = 'use';
	const MAX_SCORE_OPTION = 'max_score';

	/**
	 * Configure the similarity needed for the article topics field
	 * @param array &$similarity similarity settings to update
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchSimilarityConfig
	 */
	public static function onCirrusSearchSimilarityConfig( array &$similarity ) {
		self::configureOresArticleTopicsSimilarity( $similarity,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Visible for testing.
	 * @param array &$similarity similarity settings to update
	 * @param Config $config current configuration
	 */
	public static function configureOresArticleTopicsSimilarity(
		array &$similarity,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}
		$maxScore = self::maxScore( $config );
		$similarity[self::FIELD_SIMILARITY] = [
			'type' => 'scripted',
			// no weight=>' script we do not want doc independent weighing
			'script' => [
				// apply boost close to docFreq to force int->float conversion
				'source' => "return (doc.freq*query.boost)/$maxScore;"
			]
		];
	}

	/**
	 * Define mapping for the ores_articletopics field.
	 * @param array &$fields array of field definitions to update
	 * @param SearchEngine $engine the search engine requesting field definitions
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SearchIndexFields
	 */
	public static function onSearchIndexFields( array &$fields, SearchEngine $engine ) {
		if ( !( $engine instanceof CirrusSearch ) ) {
			return;
		}
		self::configureOresArticleTopicsFieldMapping( $fields,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Visible for testing
	 * @param \SearchIndexField[] &$fields array of field definitions to update
	 * @param Config $config the wiki configuration
	 */
	public static function configureOresArticleTopicsFieldMapping(
		array &$fields,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}

		$fields[self::FIELD_NAME] = new ORESArticleTopicsField(
			self::FIELD_NAME,
			self::FIELD_NAME,
			self::FIELD_INDEX_ANALYZER,
			self::FIELD_SEARCH_ANALYZER,
			self::FIELD_SIMILARITY
		);
	}

	/**
	 * Configure default analyzer for the ores_articletopics field.
	 * @param array &$config analysis settings to update
	 * @param AnalysisConfigBuilder $analysisConfigBuilder unneeded
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchAnalysisConfig
	 */
	public static function onCirrusSearchAnalysisConfig( array &$config, AnalysisConfigBuilder $analysisConfigBuilder ) {
		self::configureOresArticleTopicsFieldAnalysis( $config,
			MediaWikiServices::getInstance()->getMainConfig() );
	}

	/**
	 * Make ArticleTopicFeature (articletopic: search keyword) available.
	 * @param SearchConfig $config
	 * @param array &$extraFeatures Array holding KeywordFeature objects
	 * @see https://www.mediawiki.org/wiki/Extension:CirrusSearch/Hooks/CirrusSearchAddQueryFeatures
	 */
	public static function onCirrusSearchAddQueryFeatures( SearchConfig $config, array &$extraFeatures ) {
		if ( self::canUse( $config ) ) {
			// articletopic keyword, matches by ORES topic scores
			$extraFeatures[] = new ArticleTopicFeature();
		}
	}

	/**
	 * Visible only for testing
	 * @param array &$analysisConfig panalysis settings to update
	 * @param Config $config the wiki configuration
	 * @internal
	 */
	public static function configureOresArticleTopicsFieldAnalysis(
		array &$analysisConfig,
		Config $config
	) {
		if ( !self::canBuild( $config ) ) {
			return;
		}
		$maxScore = self::maxScore( $config );
		$analysisConfig['analyzer'][self::FIELD_INDEX_ANALYZER] = [
			'type' => 'custom',
			'tokenizer' => 'keyword',
			'filter' => [
				'ores_articletopics_term_freq',
			]
		];
		$analysisConfig['filter']['ores_articletopics_term_freq'] = [
			'type' => 'term_freq',
			// must be a char that never appears in the topic names/ids
			'split_char' => '|',
			// max score (clamped), we assume that orig_ores_score * 1000
			'max_tf' => $maxScore,
		];
	}

	/**
	 * Check whether articletopic data should be processed.
	 * @param Config $config
	 * @return bool
	 */
	private static function canBuild( Config $config ): bool {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$oresArticleTopicsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (bool)( $oresArticleTopicsOptions[self::BUILD_OPTION] ?? false );
	}

	/**
	 * Check whether articletopic data is available for searching.
	 * @param Config $config
	 * @return bool
	 */
	private static function canUse( Config $config ): bool {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$oresArticleTopicsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (bool)( $oresArticleTopicsOptions[self::USE_OPTION] ?? false );
	}

	private static function maxScore( Config $config ): int {
		$extraFeatures = $config->get( self::WMF_EXTRA_FEATURES );
		$oresArticleTopicsOptions = $extraFeatures[self::CONFIG_OPTIONS] ?? [];
		return (int)( $oresArticleTopicsOptions[self::MAX_SCORE_OPTION] ?? 1000 );
	}
}
