<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Search\Rescore\BoostFunctionBuilder;
use CirrusSearch\SearchConfig;
use Elastica\Query\AbstractQuery;

/**
 * Helper for writing tests of classes extending from
 * SimpleKeywordFeature
 */
trait SimpleKeywordFeatureTestTrait {

	/**
	 * @var KeywordFeatureAssertions
	 */
	private $kwAssertions;

	public function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );
		$this->kwAssertions = new KeywordFeatureAssertions( $this );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param array $expected
	 * @param string $term
	 */
	protected function assertWarnings( KeywordFeature $feature, $expected, $term ) {
		$this->kwAssertions->assertWarnings( $feature, $expected, $term );
	}

	/**
	 * Assert the value returned by KeywordFeature::getParsedValue
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param array|null $expected
	 * @param array|null $expectedWarnings (null to disable warnings check)
	 */
	protected function assertParsedValue(
		KeywordFeature $feature,
		$term,
		$expected,
		$expectedWarnings = null
	) {
		$this->kwAssertions->assertParsedValue( $feature, $term, $expected, $expectedWarnings );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param array $expected
	 * @param array|null $expectedWarnings (null to disable warnings check)
	 * @param SearchConfig|null $config (if null will run with an empty SearchConfig)
	 */
	protected function assertExpandedData(
		KeywordFeature $feature,
		$term,
		array $expected,
		array $expectedWarnings = null,
		SearchConfig $config = null
	) {
		$this->kwAssertions->assertExpandedData( $feature, $term, $expected, $expectedWarnings, $config );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param CrossSearchStrategy $expected
	 */
	protected function assertCrossSearchStrategy(
		KeywordFeature $feature,
		$term,
		CrossSearchStrategy $expected
	) {
		$this->kwAssertions->assertCrossSearchStrategy( $feature, $term, $expected );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param array|AbstractQuery|callable|null $filter
	 * @param array|null $warnings
	 * @param SearchConfig|null $config
	 */
	protected function assertFilter(
		KeywordFeature $feature,
		$term,
		$filter = null,
		array $warnings = null,
		SearchConfig $config = null
	) {
		$this->kwAssertions->assertFilter( $feature, $term, $filter, $warnings, $config );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 */
	protected function assertNoResultsPossible( KeywordFeature $feature, $term ) {
		$this->kwAssertions->assertNoResultsPossible( $feature, $term );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 */
	protected function assertNoHighlighting( KeywordFeature $feature, $term ) {
		$this->kwAssertions->assertNoHighlighting( $feature, $term );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param string|string[]|null $highlightField
	 * @param array|null $higlightQuery
	 */
	protected function assertHighlighting(
		KeywordFeature $feature,
		$term,
		$highlightField = null,
		array $higlightQuery = null
	) {
		$this->kwAssertions->assertHighlighting( $feature, $term, $highlightField, $higlightQuery );
	}

	/**
	 * Historical test to make sure that the keyword does not consume unrelated values
	 * @param KeywordFeature $feature
	 * @param string $term
	 */
	protected function assertNotConsumed( KeywordFeature $feature, $term ) {
		$this->kwAssertions->assertNotConsumed( $feature, $term );
	}

	/**
	 * Historical test to make sure that the keyword does not consume unrelated values
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param string $remaining
	 */
	protected function assertRemaining( KeywordFeature $feature, $term, $remaining ) {
		$this->kwAssertions->assertRemaining( $feature, $term, $remaining );
	}

	/**
	 * @param KeywordFeature $feature
	 * @param string $term
	 * @param callable|BoostFunctionBuilder|null $boostAssertions
	 * @param array|null $warnings
	 * @param SearchConfig|null $config
	 */
	protected function assertBoost(
		KeywordFeature $feature,
		$term, $boostAssertions = null,
		array $warnings = null,
		SearchConfig $config = null
	) {
		$this->kwAssertions->assertBoost( $feature, $term, $boostAssertions, $warnings, $config );
	}
}
