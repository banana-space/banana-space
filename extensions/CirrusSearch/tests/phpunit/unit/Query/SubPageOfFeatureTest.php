<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Search\Fetch\HighlightedField;
use Elastica\Query\MatchQuery;
use Elastica\Query\MultiMatch;

/**
 * @covers \CirrusSearch\Query\SubPageOfFeature
 * @group CirrusSearch
 */
class SubPageOfFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function provideQueries() {
		return [
			'simple' => [
				'subpageof:test',
				'test/'
			],
			'simple quoted' => [
				'subpageof:"test hello"',
				'test hello/'
			],
			'simple quoted with trailing /' => [
				'subpageof:"test hello/"',
				'test hello/'
			],
			'simple empty' => [
				'subpageof:""',
				null,
			],
			'allow wildcard to act as classic prefix query' => [
				'subpageof:"test*"',
				'test'
			],
			'Negated is not highlighted' => [
				'-subpageof:test',
				'test/',
				false,
			]
		];
	}

	/**
	 * @dataProvider provideQueries()
	 * @param $query
	 * @param $filterValue
	 * @param bool $expectHighlighting
	 */
	public function test( $query, $filterValue, $expectHighlighting = true ) {
		$feature = new SubPageOfFeature();
		$this->assertExpandedData( $feature, $query, [], [] );
		$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::allWikisStrategy() );
		$filterCallback = null;
		if ( $filterValue !== null ) {
			$this->assertParsedValue( $feature, $query, [ 'prefix' => $filterValue ], [] );
			$filterCallback = function ( MultiMatch $match ) use ( $filterValue ) {
				$this->assertEqualsCanonicalizing( [ 'title.prefix', 'redirect.title.prefix' ],
					$match->getParam( 'fields' ), "fields of the multimatch query should match" );
				$this->assertEquals( $filterValue, $match->getParam( 'query' ) );
				return true;
			};
			if ( $expectHighlighting ) {
				$this->assertHighlighting( $feature, $query, [ 'title.prefix', 'redirect.title.prefix' ],
					[
						[
							'query' => new MatchQuery( 'title.prefix', $filterValue ),
							'target' => HighlightedField::TARGET_TITLE_SNIPPET,
							'priority' => HighlightedField::EXPERT_SYNTAX_PRIORITY,
							'number_of_fragments' => 1,
							'fragment_size' => 10000,
						],
						[
							'query' => new MatchQuery( 'redirect.title.prefix', $filterValue ),
							'skip_if_last_matched' => true,
							'target' => HighlightedField::TARGET_REDIRECT_SNIPPET,
							'priority' => HighlightedField::EXPERT_SYNTAX_PRIORITY,
							'number_of_fragments' => 1,
							'fragment_size' => 10000,
						]
					]
				);
			} else {
				$this->assertNoHighlighting( $feature, $query );
			}
		} else {
			$this->assertParsedValue( $feature, $query, null );
		}
		$this->assertFilter( $feature, $query, $filterCallback, [] );
	}
}
