<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileException;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;

/**
 * @covers \CirrusSearch\Dispatch\DefaultSearchQueryDispatchService
 */
class DefaultSearchQueryDispatchServiceTest extends CirrusTestCase {

	public function testEmpty() {
		$expected = CirrusDefaultSearchQueryRoute::searchTextDefaultRoute();
		$service = new DefaultSearchQueryDispatchService(
			[ SearchQuery::SEARCH_TEXT => [ CirrusDefaultSearchQueryRoute::searchTextDefaultRoute() ] ]
		);
		$actual = $service->bestRoute( SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo',
			$this->namespacePrefixParser() )->build() );
		$this->assertSame( $expected, $actual );
	}

	public function testBestWithOrdering() {
		$routes = [ 'searchText' => [
			CirrusDefaultSearchQueryRoute::searchTextDefaultRoute(),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'bestFor0', 0.3 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'weakestFor0', 0.2 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 1 ], [], 'unrelated', 0.5 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'tooLate', 0.3 )
		] ];
		$service = new DefaultSearchQueryDispatchService( $routes );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 0 ] )
			->build();
		$this->assertEquals( 'bestFor0', $service->bestRoute( $query )->getProfileContext() );
	}

	public function testMax() {
		$routes = [ 'searchText' => [
			CirrusDefaultSearchQueryRoute::searchTextDefaultRoute(),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'firstFor0', 0.3 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'weakestFor0', 0.2 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 1 ], [], 'unrelated', 1.0 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'bestFor0', 1.0 )
		] ];
		$service = new DefaultSearchQueryDispatchService( $routes );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 0 ] )
			->build();
		$this->assertEquals( 'bestFor0', $service->bestRoute( $query )->getProfileContext() );
	}

	public function testAmbiguousMax() {
		$routes = [ 'searchText' => [
			CirrusDefaultSearchQueryRoute::searchTextDefaultRoute(),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'firstFor0', 1.0 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'weakestFor0', 0.2 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 1 ], [], 'unrelated', 1.0 ),
			new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT, [ 0 ], [], 'bestFor0', 1.0 )
		] ];
		$service = new DefaultSearchQueryDispatchService( $routes );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 0 ] )
			->build();
		try {
			$service->bestRoute( $query );
			$this->fail( "Invalid configuration must produce a SearchProfileException" );
		} catch ( SearchProfileException $e ) {
			$this->assertStringContainsString( 'firstFor0', $e->getMessage() );
			$this->assertStringContainsString( 'bestFor0', $e->getMessage() );
		}
	}
}
