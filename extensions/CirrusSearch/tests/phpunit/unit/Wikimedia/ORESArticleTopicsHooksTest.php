<?php

namespace CirrusSearch\Wikimedia;

use CirrusSearch\HashSearchConfig;
use CirrusSearch\Query\ArticleTopicFeature;

/**
 * @covers \CirrusSearch\Wikimedia\ORESArticleTopicsHooks
 */
class ORESArticleTopicsHooksTest extends \MediaWikiUnitTestCase {
	public function testConfigureOresArticleTopicsSimilarity() {
		$sim = [];
		$maxScore = 17389;
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => true,
					ORESArticleTopicsHooks::MAX_SCORE_OPTION => $maxScore,
					]
				]
		] );
		ORESArticleTopicsHooks::configureOresArticleTopicsSimilarity( $sim, $config );
		$this->assertArrayHasKey( ORESArticleTopicsHooks::FIELD_SIMILARITY, $sim );
		$this->assertStringContainsString( $maxScore,
			$sim[ORESArticleTopicsHooks::FIELD_SIMILARITY]['script']['source'] );
	}

	public function testConfigureOresArticleTopicsSimilarityDisabled() {
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$sim = [];
		ORESArticleTopicsHooks::configureOresArticleTopicsSimilarity( $sim, $config );
		$this->assertSame( [], $sim );
	}

	public function testConfigureOresArticleTopicsFieldMapping() {
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => true,
				]
			]
		] );
		$searchEngine = $this->createMock( \SearchEngine::class );
		/**
		 * @var \SearchIndexField $fields
		 */
		$fields = [];
		ORESArticleTopicsHooks::configureOresArticleTopicsFieldMapping( $fields, $config );
		$this->assertArrayHasKey( ORESArticleTopicsHooks::FIELD_NAME, $fields );
		$field = $fields[ORESArticleTopicsHooks::FIELD_NAME];
		$this->assertInstanceOf( ORESArticleTopicsField::class, $field );
		$mapping = $field->getMapping( $searchEngine );
		$this->assertSame( 'text', $mapping['type'] );
		$this->assertSame( ORESArticleTopicsHooks::FIELD_SEARCH_ANALYZER, $mapping['search_analyzer'] );
		$this->assertSame( ORESArticleTopicsHooks::FIELD_INDEX_ANALYZER, $mapping['analyzer'] );
		$this->assertSame( ORESArticleTopicsHooks::FIELD_SIMILARITY, $mapping['similarity'] );
	}

	public function testConfigureOresArticleTopicsFieldMappingDisabled() {
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$fields = [];
		ORESArticleTopicsHooks::configureOresArticleTopicsFieldMapping( $fields, $config );
		$this->assertSame( [], $fields );
	}

	public function testConfigureOresArticleTopicsFieldAnalysis() {
		$maxScore = 41755;
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => true,
					ORESArticleTopicsHooks::MAX_SCORE_OPTION => $maxScore,
				]
			]
		] );
		$analysisConfig = [];
		ORESArticleTopicsHooks::configureOresArticleTopicsFieldAnalysis( $analysisConfig, $config );
		$this->assertArrayHasKey( 'analyzer', $analysisConfig );
		$this->assertArrayHasKey( 'filter', $analysisConfig );
		$analyzers = $analysisConfig['analyzer'];
		$filters = $analysisConfig['filter'];
		$this->assertArrayHasKey( ORESArticleTopicsHooks::FIELD_INDEX_ANALYZER, $analyzers );
		$this->assertArrayHasKey( 'ores_articletopics_term_freq', $filters );
		$this->assertSame( $maxScore, $filters['ores_articletopics_term_freq']['max_tf'] );
	}

	public function testConfigureOresArticleTopicsFieldAnalysisDisabled() {
		$config = new \HashConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::BUILD_OPTION => false,
				]
			]
		] );
		$analysisConfig = [];
		ORESArticleTopicsHooks::configureOresArticleTopicsFieldAnalysis( $analysisConfig, $config );
		$this->assertSame( [], $analysisConfig );
	}

	public function testOnCirrusSearchAddQueryFeatures() {
		$config = new HashSearchConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::USE_OPTION => false,
				],
			],
		] );
		$extraFeatures = [];
		ORESArticleTopicsHooks::onCirrusSearchAddQueryFeatures( $config, $extraFeatures );
		$this->assertEmpty( $extraFeatures );

		$config = new HashSearchConfig( [
			ORESArticleTopicsHooks::WMF_EXTRA_FEATURES => [
				ORESArticleTopicsHooks::CONFIG_OPTIONS => [
					ORESArticleTopicsHooks::USE_OPTION => true,
				],
			],
		] );
		ORESArticleTopicsHooks::onCirrusSearchAddQueryFeatures( $config, $extraFeatures );
		$this->assertNotEmpty( $extraFeatures );
		$this->assertInstanceOf( ArticleTopicFeature::class, $extraFeatures[0] );
	}
}
