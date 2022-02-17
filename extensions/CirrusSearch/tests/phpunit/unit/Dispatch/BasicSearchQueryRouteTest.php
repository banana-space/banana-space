<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;

/**
 *
 */
class BasicSearchQueryRouteTest extends CirrusTestCase {

	/**
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::getProfileContext
	 */
	public function testGetProfileContext() {
		$context = 'a not so random but weird context';
		$route = new BasicSearchQueryRoute( 'foo', [], [], $context, 1.0 );
		$this->assertSame( $context, $route->getProfileContext() );
	}

	/**
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::getSearchEngineEntryPoint
	 */
	public function testGetSearchEngineEntryPoint() {
		$searchEngineEntryPoint = 'a not so random but weird search engine entry point';
		$route = new BasicSearchQueryRoute( $searchEngineEntryPoint, [], [], 'foo', 1.0 );
		$this->assertSame( $searchEngineEntryPoint, $route->getSearchEngineEntryPoint() );
	}

	/**
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::score
	 */
	public function testGetScore() {
		$route = new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [], [], 'foo', 0.4 );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->build();
		$this->assertSame( 0.4, $route->score( $query ) );
	}

	/**
	 * @return array
	 */
	public function provideTestNamespacesRouting() {
		return [
			'simple match' => [
				[ 1 ],
				[ 1 ],
				true
			],
			'simple no match' => [
				[ 1 ],
				[ 0 ],
				false
			],
			'contained match' => [
				[ 0, 1 ],
				[ 1 ],
				true
			],
			'fully equal' => [
				[ 0, 1 ],
				[ 0, 1 ],
				true
			],
			'one unsupported' => [
				[ 0, 1 ],
				[ 0, 1, 2 ],
				false
			],
			'all accepted' => [
				[],
				[ 0, 1, 2 ],
				true
			],
			'all provided' => [
				[ 0 ],
				[],
				false
			],
		];
	}

	/**
	 *
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::score
	 * @dataProvider provideTestNamespacesRouting
	 */
	public function testNamespacesRouting( $acceptedNs, $queryNs, $acceptRoute ) {
		$route = new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, $acceptedNs, [], 'foo', 1.0 );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( $queryNs )
			->build();
		$expectedScore = $acceptRoute ? 1.0 : 0.0;
		$this->assertSame( $expectedScore, $route->score( $query ) );
	}

	public function provideTestQueryClassRouting() {
		return [
			'simple match' => [
				[ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ],
				'foo',
				true,
			],
			'simple no match' => [
				[ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ],
				'"foo"',
				false,
			],
			'multiple match' => [
				[ BasicQueryClassifier::SIMPLE_PHRASE, BasicQueryClassifier::SIMPLE_PHRASE ],
				'"foo"',
				true,
			],
			'multiple no match' => [
				[ BasicQueryClassifier::SIMPLE_PHRASE, BasicQueryClassifier::SIMPLE_PHRASE ],
				'"foo" bar',
				false,
			],
			'multiple classes one match' => [
				[ BasicQueryClassifier::COMPLEX_QUERY ],
				'foo AND bar AND',
				true,
			],
			'multiple classes all match' => [
				[ BasicQueryClassifier::BOGUS_QUERY, BasicQueryClassifier::COMPLEX_QUERY ],
				'foo AND bar AND',
				true,
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::score
	 * @dataProvider provideTestQueryClassRouting
	 */
	public function testQueryClassRouting( $acceptedClasses, $query, $acceptRoute ) {
		$route = new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [], $acceptedClasses, 'foo', 1.0 );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), $query, $this->namespacePrefixParser() )
			->build();
		$expectedScore = $acceptRoute ? 1.0 : 0.0;
		$this->assertSame( $expectedScore, $route->score( $query ) );
	}

	public function provideTestNamespacesAndQueryClassRouting() {
		$testCases = [];
		foreach ( $this->provideTestNamespacesRouting() as $nsTest => $nsOptions ) {
			foreach ( $this->provideTestQueryClassRouting() as $clTest => $clOptions ) {
				$testCases[$nsTest . ' + ' . $clTest] = [
					$nsOptions[0],
					$clOptions[0],
					$nsOptions[1],
					$clOptions[1],
					$nsOptions[2] && $clOptions[2],
				];
			}
		}
		return $testCases;
	}

	/**
	 * @covers \CirrusSearch\Dispatch\BasicSearchQueryRoute::score
	 * @dataProvider provideTestNamespacesAndQueryClassRouting
	 */
	public function testNamespacesAndQueryClassRouting( $acceptedNs, $acceptedClasses, $queryNs, $query, $acceptRoute ) {
		$route = new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, $acceptedNs,
			$acceptedClasses, 'foo', 1.0 );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), $query, $this->namespacePrefixParser() )
			->setInitialNamespaces( $queryNs )
			->build();
		$expectedScore = $acceptRoute ? 1.0 : 0.0;
		$this->assertSame( $expectedScore, $route->score( $query ) );
	}
}
