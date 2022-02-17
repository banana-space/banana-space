<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;

/**
 * @covers \CirrusSearch\Dispatch\CirrusDefaultSearchQueryRoute
 */
class CirrusDefaultSearchQueryRouteTest extends CirrusTestCase {
	public function tesSearchTextDefaultRoute() {
		$route = CirrusDefaultSearchQueryRoute::searchTextDefaultRoute();
		$score = $route->score( SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), "foo" ),
			$this->namespacePrefixParser() );
		$this->assertEquals( $score, SearchQueryDispatchService::CIRRUS_DEFAULTS_SCORE );
		$this->assertEquals( SearchQuery::SEARCH_TEXT, $route->getSearchEngineEntryPoint() );
		$this->assertEquals( SearchProfileService::CONTEXT_DEFAULT, $route->getProfileContext() );
	}
}
