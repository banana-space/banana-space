<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;
use MediaWiki\Sparql\SparqlClient;
use MediaWiki\Sparql\SparqlException;
use Title;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Query\DeepcatFeature
 */
class DeepcatFeatureTest extends CirrusIntegrationTestCase {
	use SimpleKeywordFeatureTestTrait;

	/**
	 * @param array $expectInQuery
	 * @param array $result
	 * @return SparqlClient
	 */
	private function getSparqlClient( array $expectInQuery, array $result ) {
		/**
		 * @var SparqlClient $client
		 */
		$client = $this->getMockBuilder( SparqlClient::class )
			->disableOriginalConstructor()->getMock();
		// 2 calls we still test old and new parsing behaviors
		$client->expects( $this->atMost( 2 ) )->method( 'query' )->willReturnCallback(
			function ( $sparql ) use ( $expectInQuery, $result ) {
				foreach ( $expectInQuery as $expect ) {
					$this->assertStringContainsString( $expect, $sparql );
				}
				foreach ( $result as &$row ) {
					$row['out'] = $this->categoryToUrl( $row['out'] );
				}
				return $result;
			}
		);

		return $client;
	}

	public function provideQueries() {
		return [
			'two results' => [
				'Duck',
				[
					[ 'out' => 'Ducks' ],
					[ 'out' => 'Wigeons' ],
				],
				[
					'bool' => [
						'should' => [
							[
								'match' => [
									'category.lowercase_keyword' => [ 'query' => 'Ducks' ]
								]
							],
							[
								'match' => [
									'category.lowercase_keyword' => [ 'query' => 'Wigeons' ]
								]
							],
						]
					]
				]
			],
			"one result" => [
				'"Duck & duckling"',
				[
					[ 'out' => 'Wigeons' ],
				],
				[
					'bool' => [
						'should' => [
							[
								'match' => [
									'category.lowercase_keyword' => [ 'query' => 'Wigeons' ]
								]
							],
						]
					]
				]
			],
			"no result" => [
				'Ducks',
				[],
				null
			],
			'too many results' => [
				'Duck',
				[
					[ 'out' => 'Ducks' ],
					[ 'out' => 'Wigeons' ],
					[ 'out' => 'More ducks' ],
					[ 'out' => 'There is no such thing as too many ducks' ],
				],
				null
			],
			'url encoding' => [
				'Duck',
				[
					[ 'out' => 'Утки' ],
					[ 'out' => 'Vögel' ],
				],
				[
					'bool' => [
						'should' => [
							[
								'match' => [
									'category.lowercase_keyword' => [ 'query' => 'Утки' ]
								]
							],
							[
								'match' => [
									'category.lowercase_keyword' => [ 'query' => 'Vögel' ]
								]
							],
						]
					]
				]
			],
		];
	}

	/**
	 * Get category full URL
	 * @param string $cat
	 * @return string
	 */
	private function categoryToUrl( $cat ) {
		$title = Title::makeTitle( NS_CATEGORY, $cat );
		return $title->getFullURL( '', false, PROTO_CANONICAL );
	}

	/**
	 * @dataProvider provideQueries
	 * @param string $term
	 * @param array $result
	 * @param array $filters
	 */
	public function testFilter( $term, $result, $filters ) {
		$maxRes = 3;
		$config = new \HashConfig( [
			'CirrusSearchCategoryDepth' => '3',
			'CirrusSearchCategoryMax' => $maxRes,
			'CirrusSearchCategoryEndpoint' => 'http://acme.test/sparql'
		] );
		$sparqlQuery = [
			'bd:serviceParam mediawiki:start <' . $this->categoryToUrl( trim( $term, '"' ) ) . '>',
			'bd:serviceParam mediawiki:depth 3 ',
			'LIMIT 4'
		];
		$query = "deepcat:$term";

		if ( count( $result ) > $maxRes ) {
			$expectedData = [];
			$warnings = [ [ 'cirrussearch-feature-deepcat-toomany' ] ];
		} else {
			$expectedData = array_map(
				function ( $data ) {
					return $data['out'];
				},
				$result
			);
			$warnings = [];
		}
		$client = $this->getSparqlClient( $sparqlQuery, $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::hostWikiOnlyStrategy() );
		$this->assertParsedValue( $feature, $query, null, [] );
		$this->assertExpandedData( $feature, $query, $expectedData, $warnings );

		// Rebuild the client to comply atMost assertion on the query method
		$client = $this->getSparqlClient( $sparqlQuery, $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertFilter( $feature, $query, $filters, $warnings );
	}

	public function testTooManyCats() {
		$config = new \HashConfig( [
			'CirrusSearchCategoryDepth' => '3',
			'CirrusSearchCategoryMax' => 3,
			'CirrusSearchCategoryEndpoint' => 'http://acme.test/sparql'
		] );

		$sparqlQuery = [
			'bd:serviceParam mediawiki:start <' . $this->categoryToUrl( 'Ducks' ) . '>',
			'bd:serviceParam mediawiki:depth 3 ',
			'LIMIT 4'
		];
		$result = [
			[ 'out' => 'Ducks' ],
			[ 'out' => 'Wigeons' ],
			[ 'out' => 'More ducks' ],
			[ 'out' => 'There is no such thing as too many ducks' ],
		];

		$query = "deepcat:Ducks";
		$client = $this->getSparqlClient( $sparqlQuery, $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertFilter( $feature, "deepcat:Ducks", null, [ [ 'cirrussearch-feature-deepcat-toomany' ] ] );
		$client = $this->getSparqlClient( $sparqlQuery, $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertNoResultsPossible( $feature, $query );
		$client = $this->getSparqlClient( $sparqlQuery, $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertExpandedData( $feature, "deepcat:Ducks", [], [ [ 'cirrussearch-feature-deepcat-toomany' ] ] );
	}

	/**
	 * @dataProvider provideQueries
	 * @param $term
	 * @param $result
	 * @param $filters
	 */
	public function testFilterNoEndpoint( $term, $result, $filters ) {
		$config = new \HashConfig( [
			'CirrusSearchCategoryDepth' => '3',
			'CirrusSearchCategoryMax' => 100,
			'CirrusSearchCategoryEndpoint' => null
		] );

		$client = $this->getSparqlClient( [], $result );
		$feature = new DeepcatFeature( $config, $client );
		$query = "deepcat:$term";
		$this->assertFilter( $feature, $query, null, [ [ 'cirrussearch-feature-deepcat-endpoint' ] ] );
		$client = $this->getSparqlClient( [], $result );
		$feature = new DeepcatFeature( $config, $client );
		$this->assertExpandedData( $feature, $query, [], [ [ 'cirrussearch-feature-deepcat-endpoint' ] ] );
	}

	public function testSparqlError() {
		$config = new \HashConfig( [
			'CirrusSearchCategoryDepth' => '3',
			'CirrusSearchCategoryMax' => 100,
			'CirrusSearchCategoryEndpoint' => 'http://acme.test/sparql'
		] );
		$client = $this->getMockBuilder( SparqlClient::class )
			->disableOriginalConstructor()->getMock();
		// 3 runs:
		// 1: for asserting expandData
		// 2: for asserting old & new parsing techniques
		$client->expects( $this->exactly( 3 ) )->method( 'query' )->willReturnCallback(
			function () {
				throw new SparqlException( "Bad SPARQL error!" );
			}
		);
		$feature = new DeepcatFeature( $config, $client );
		$filter = [
			'bool' => [
				'should' => [ [
					'match' => [
						'category.lowercase_keyword' => [ 'query' => 'Test' ]
					]
				] ]
			]
		];
		$this->assertFilter( $feature, "deepcat:Test", $filter, [ [ 'cirrussearch-feature-deepcat-exception' ] ] );
		// When there is no endpoint we emit a simple incategory
		$this->assertExpandedData( $feature, "deepcat:Test", [ "Test" ], [ [ 'cirrussearch-feature-deepcat-exception' ] ] );
	}

}
