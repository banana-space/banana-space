<?php

namespace CirrusSearch\Dispatch;

use CirrusSearch\Profile\SearchProfileException;
use CirrusSearch\Search\SearchQuery;
use Wikimedia\Assert\Assert;

class DefaultSearchQueryDispatchService implements SearchQueryDispatchService {
	/**
	 * List of routes per search engine entry point
	 * @var SearchQueryRoute[][] indexed by search engine entry point
	 */
	private $routes;

	/**
	 * @param SearchQueryRoute[][] $routes
	 */
	public function __construct( array $routes ) {
		$this->routes = $routes;
	}

	/**
	 * @param SearchQuery $query
	 * @return SearchQueryRoute
	 */
	public function bestRoute( SearchQuery $query ): SearchQueryRoute {
		Assert::parameter( isset( $this->routes[$query->getSearchEngineEntryPoint()] ), 'query',
			"Unsupported search engine entry point {$query->getSearchEngineEntryPoint()}" );

		$routes = $this->routes[$query->getSearchEngineEntryPoint()];

		$bestScore = 0.0;

		/** @var SearchQueryRoute $best */
		$best = null;
		foreach ( $routes as $route ) {
			$score = $route->score( $query );
			Assert::postcondition( $score >= 0 && $score <= 1.0, "SearchQueryRoute scores must be between 0.0 and 1.0" );
			if ( $score === 0.0 ) {
				continue;
			}
			if ( $score === 1.0 ) {
				if ( $bestScore === 1.0 ) {
					throw new SearchProfileException( "Two competing contexts " .
						// @phan-suppress-next-line PhanNonClassMethodCall $best always set when reaching this line
						"{$route->getProfileContext()} and {$best->getProfileContext()} " .
						" produced the max score" );
				}
				$bestScore = $score;
				$best = $route;
			} elseif ( $score > $bestScore ) {
				$best = $route;
				$bestScore = $score;
			}
		}
		Assert::postcondition( $best !== null,
			"No route to backend, make sure a default SearchQueryRoute is added for {$query->getSearchEngineEntryPoint()}" );
		return $best;
	}
}
