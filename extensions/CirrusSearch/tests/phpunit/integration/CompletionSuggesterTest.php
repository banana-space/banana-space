<?php

namespace CirrusSearch;

use CirrusSearch\BuildDocument\Completion\SuggestBuilder;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\CompSuggestQueryBuilder;
use CirrusSearch\Search\CompletionResultsCollector;
use CirrusSearch\Search\SearchContext;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;

/**
 * Completion Suggester Tests
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @group CirrusSearch
 * @covers \CirrusSearch\Search\CompletionResultsCollector
 * @covers \CirrusSearch\Query\CompSuggestQueryBuilder
 */
class CompletionSuggesterTest extends CirrusIntegrationTestCase {

	/**
	 * @dataProvider provideQueries
	 */
	public function testQueries( $config, $limit, $search, $variants, $expectedProfiles, $expectedQueries ) {
		$config = new HashSearchConfig( $config );
		$compSuggestBuilder = new CompSuggestQueryBuilder(
			new SearchContext( $config ),
			// Need to explicitly access the profile like that
			// hook for setting user preference by default is run too early
			// and have set this to "fuzzy"
			$config->getProfileService()
				->loadProfileByName( SearchProfileService::COMPLETION,
					$config->get( 'CirrusSearchCompletionSettings' ) ),
			$limit
		);

		$suggest = $compSuggestBuilder->build( $search, $variants );
		$profiles = $compSuggestBuilder->getMergedProfiles();
		$this->assertEquals( $expectedProfiles, $profiles );
		$this->assertEquals( $expectedQueries, $suggest->toArray()['suggest'] );
	}

	public function provideQueries() {
		$simpleProfile = [
			'plain' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'discount' => 1.0,
				'fetch_limit_factor' => 2,
			],
		];

		$simpleFuzzy = $simpleProfile + [
			'plain-fuzzy' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				],
				'discount' => 0.5,
				'fetch_limit_factor' => 1.5
			]
		];

		$profile = [
			'test-simple' => [
				'fst' => $simpleProfile
			],
			'test-fuzzy' => [
				'fst' => $simpleFuzzy,
			]
		];

		return [
			"simple" => [
				[
					'CirrusSearchCompletionSettings' => 'test-simple',
					'CirrusSearchCompletionProfiles' => $profile,
				],
				10,
				' complete me ',
				null,
				$simpleProfile, // The profile remains unmodified here
				[
					'plain' => [
						'prefix' => 'complete me ', // keep trailing white spaces
						'completion' => [
							'field' => 'suggest',
							'size' => 20, // effect of fetch_limit_factor
						],
					],
				],
			],
			"simple with fuzzy" => [
				[
					'CirrusSearchCompletionSettings' => 'test-fuzzy',
					'CirrusSearchCompletionProfiles' => $profile,
				],
				10,
				' complete me ',
				null,
				$simpleFuzzy, // The profiles remains unmodified here
				[
					'plain' => [
						'prefix' => 'complete me ', // keep trailing white spaces
						'completion' => [
							'field' => 'suggest',
							'size' => 20, // effect of fetch_limit_factor
						],
					],
					'plain-fuzzy' => [
						'prefix' => 'complete me ', // keep trailing white spaces
						'completion' => [
							'field' => 'suggest',
							'size' => 15.0, // effect of fetch_limit_factor
							// fuzzy config is simply copied from the profile
							'fuzzy' => [
								'fuzziness' => 'AUTO',
								'prefix_length' => 1,
								'unicode_aware' => true,
							],
						],
					],
				],
			],
			"simple with variants" => [
				[
					'CirrusSearchCompletionSettings' => 'test-simple',
					'CirrusSearchCompletionProfiles' => $profile,
				],
				10,
				' complete me ',
				[ ' variant1 ', ' complete me ', ' variant2 ' ],
				// Profile is updated with extra variant setup
				// to include an extra discount
				// ' complete me ' variant duplicate will be ignored
				$simpleProfile + [
					'plain-variant-1' => [
						'field' => 'suggest',
						'min_query_len' => 0,
						'discount' => 1.0 * CompSuggestQueryBuilder::VARIANT_EXTRA_DISCOUNT,
						'fetch_limit_factor' => 2,
						'fallback' => true, // extra key added, not used for now
					],
					'plain-variant-2' => [
						'field' => 'suggest',
						'min_query_len' => 0,
						'discount' => 1.0 * ( CompSuggestQueryBuilder::VARIANT_EXTRA_DISCOUNT / 2 ),
						'fetch_limit_factor' => 2,
						'fallback' => true, // extra key added, not used for now
					]
				],
				[
					'plain' => [
						'prefix' => 'complete me ', // keep trailing white spaces
						'completion' => [
							'field' => 'suggest',
							'size' => 20, // effect of fetch_limit_factor
						],
					],
					'plain-variant-1' => [
						'prefix' => 'variant1 ',
						'completion' => [
							'field' => 'suggest',
							'size' => 20, // effect of fetch_limit_factor
						],
					],
					'plain-variant-2' => [
						'prefix' => 'variant2 ',
						'completion' => [
							'field' => 'suggest',
							'size' => 20, // effect of fetch_limit_factor
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider provideMinMaxQueries
	 */
	public function testMinMaxDefaultProfile( $len, $query ) {
		$config = new HashSearchConfig( [
			'CirrusSearchCompletionSettings' => 'fuzzy',
		], [ HashSearchConfig::FLAG_INHERIT ] );
		// Test that we generate at most 4 profiles
		$completion = new CompSuggestQueryBuilder(
			new SearchContext( $config ),
			$config->getProfileService()
				->loadProfile( SearchProfileService::COMPLETION ),
		1 );
		$suggest = $completion->build( $query, [] )->toArray()['suggest'];
		$profiles = $completion->getMergedProfiles();
		// Unused profiles are kept
		$this->assertCount( count( $config->getProfileService()
			->loadProfileByName( SearchProfileService::COMPLETION, 'fuzzy' )['fst'] ), $profiles );
		// Never run more than 4 suggest query (without variants)
		$this->assertLessThanOrEqual( 4, count( $suggest ) );
		// small queries
		$this->assertGreaterThanOrEqual( 2, count( $suggest ) );

		if ( $len < 3 ) {
			// We do not run fuzzy for small queries
			$this->assertCount( 2, $suggest );
			foreach ( $suggest as $key => $value ) {
				$this->assertArrayNotHasKey( 'fuzzy', $value );
			}
		}
		foreach ( $suggest as $key => $value ) {
			// Make sure the query is truncated otherwise elastic won't send results
			$this->assertTrue( mb_strlen( $value['prefix'] ) < SuggestBuilder::MAX_INPUT_LENGTH );
		}
		foreach ( array_keys( $suggest ) as $sug ) {
			// Makes sure we have the corresponding profile
			$this->assertArrayHasKey( $sug, $profiles );
		}
	}

	public function provideMinMaxQueries() {
		$queries = [];
		// The completion should not count extra spaces
		// This is to avoid enbling costly fuzzy profiles
		// by cheating with spaces
		$query = '  ';
		for ( $i = 0; $i < 100; $i++ ) {
			$test = "Query length {$i}";
			$queries[$test] = [ $i, $query . '   ' ];
			$query .= '';
		}
		return $queries;
	}

	/**
	 * @dataProvider provideResponse
	 */
	public function testOffsets( ResultSet $results, $limit, $offset, $first, $last, $size, $hardLimit ) {
		$config = new HashSearchConfig( [
			'CirrusSearchCompletionSuggesterHardLimit' => $hardLimit,
			'CirrusSearchCompletionSettings' => 'fuzzy',
		] );
		$builder = new CompSuggestQueryBuilder(
			new SearchContext( $config ),
			$config->getProfileService()->loadProfileByName( SearchProfileService::COMPLETION, 'fuzzy' ),
			$limit, $offset );
		$builder->build( "ignored", null );
		$log = $this->getMockBuilder( CompletionRequestLog::class )
			->disableOriginalConstructor()
			->getMock();
		if ( $builder->areResultsPossible() ) {
			$collector = new CompletionResultsCollector( $builder->getLimit(), $offset );
			$builder->postProcess( $collector, $results, "wiki_titlesuggest" );
			$suggestions = $collector->logAndGetSet( $log );
		} else {
			$suggestions = \SearchSuggestionSet::emptySuggestionSet();
		}
		$this->assertEquals( $size, $suggestions->getSize() );
		if ( $size > 0 ) {
			$suggestions = $suggestions->getSuggestions();
			$firstS = reset( $suggestions );
			$lastS = end( $suggestions );
			$this->assertEquals( $first, $firstS->getText() );
			$this->assertEquals( $last, $lastS->getText() );
		}
	}

	public function provideResponse() {
		$suggestions = [];
		$max = 200;
		for ( $i = 1; $i <= $max; $i++ ) {
			$score = $max - $i;
			$suggestions[] = [
				'_id' => $i . 't',
				'text' => "Title$i",
				'_score' => $score,
			];
		}

		$suggestData = [ [
					'prefix' => 'Tit',
					'options' => $suggestions
				] ];

		$data = [
			'suggest' => [
				'plain' => $suggestData,
				'plain_fuzzy_2' => $suggestData,
				'plain_stop' => $suggestData,
				'plain_stop_fuzzy_2' => $suggestData,
			],
		];
		$resp = new ResultSet( new Response( $data ), new Query(), [] );
		return [
			'Simple offset 0' => [
				$resp,
				5, 0, 'Title1', 'Title5', 5, 50
			],
			'Simple offset 5' => [
				$resp,
				5, 5, 'Title6', 'Title10', 5, 50
			],
			'Reach ES limit' => [
				$resp,
				5, $max - 3, 'Title198', 'Title200', 3, 300
			],
			'Reach Cirrus limit' => [
				$resp,
				5, 47, 'Title48', 'Title50', 3, 50
			],
			'Out of Cirrus bounds' => [
				$resp,
				5, 67, null, null, 0, 50
			],
			'Out of elastic results' => [
				$resp,
				5, 200, null, null, 0, 300
			],
			'Empty index' => [
				new ResultSet( new Response( [] ), new Query(), [] ),
				5, 200, null, null, 0, 50
			],
		];
	}

	/**
	 * test bad responses caused by:
	 * https://github.com/elastic/elasticsearch/issues/32467
	 */
	public function testBadResponseOnFetchFailure() {
		$suggestions = [];
		for ( $i = 1; $i <= 10; $i++ ) {
			$suggestions[] = [
				'text' => "Title$i",
			];
		}

		$suggestData = [
			[
				'prefix' => 'Tit',
				'options' => $suggestions
			]
		];

		$data = [
			'suggest' => [
				'plain' => $suggestData,
				'plain_fuzzy_2' => $suggestData,
				'plain_stop' => $suggestData,
				'plain_stop_fuzzy_2' => $suggestData,
			],
		];
		$resp = new ResultSet( new Response( $data ), new Query(), [] );

		$config = new HashSearchConfig( [
			'CirrusSearchCompletionSettings' => 'fuzzy',
		] );
		$builder =
			new CompSuggestQueryBuilder( new SearchContext( $config ), $config->getProfileService()
				->loadProfileByName( SearchProfileService::COMPLETION, 'fuzzy' ), 10, 0 );
		$builder->build( "ignored", null );
		$collector = new CompletionResultsCollector( $builder->getLimit(), 0 );
		try {
			$builder->postProcess( $collector, $resp, "wiki_titlesuggest" );
			$this->fail( "Reading an invalid response should produce an exception" );
		} catch ( \Elastica\Exception\RuntimeException $re ) {
			$this->assertEquals( "Invalid response returned from the backend (probable shard failure " .
				"during the fetch phase)", $re->getMessage() );
		}
	}
}
