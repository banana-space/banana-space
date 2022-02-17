<?php

namespace CirrusSearch\Query;

use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchContext;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\MultiMatch;

/**
 *
 * Build a query suited for autocomplete on titles+redirects
 */
class PrefixSearchQueryBuilder {
	use QueryBuilderTraits;

	/**
	 * @param SearchContext $searchContext the search context
	 * @param string $term the original search term
	 * @param array|null $variants list of variants
	 * @throws \ApiUsageException if the query is too long
	 */
	public function build( SearchContext $searchContext, $term, $variants = null ) {
		$this->checkTitleSearchRequestLength( $term );
		$searchContext->setOriginalSearchTerm( $term );
		$searchContext->setProfileContext( SearchProfileService::CONTEXT_PREFIXSEARCH );
		$searchContext->addSyntaxUsed( 'prefix' );
		if ( strlen( $term ) > 0 ) {
			if ( $searchContext->getConfig()->get( 'CirrusSearchPrefixSearchStartsWithAnyWord' ) ) {
				$searchContext->addFilter( $this->wordPrefixQuery( $term, $variants ) );
			} else {
				// TODO: weights should be a profile?
				$weights = $searchContext->getConfig()->get( 'CirrusSearchPrefixWeights' );
				$searchContext->setMainQuery( $this->keywordPrefixQuery( $term, $variants, $weights ) );
			}
		}
	}

	private function wordPrefixQuery( $term, $variants ) {
		$buildMatch = function ( $searchTerm ) {
			$match = new MatchQuery();
			// TODO: redirect.title?
			$match->setField( 'title.word_prefix', [
				'query' => $searchTerm,
				'analyzer' => 'plain',
				'operator' => 'and',
			] );
			return $match;
		};
		$query = new BoolQuery();
		$query->setMinimumShouldMatch( 1 );
		$query->addShould( $buildMatch( $term ) );
		foreach ( $variants as $variant ) {
			// This is a filter we don't really care about
			// discounting variant matches.
			$query->addShould( $buildMatch( $variant ) );
		}
		return $query;
	}

	private function keywordPrefixQuery( $term, $variants, $weights ) {
		// Elasticsearch seems to have trouble extracting the proper terms to highlight
		// from the default query we make so we feed it exactly the right query to highlight.
		$buildMatch = function ( $searchTerm, $weight ) use ( $weights ) {
			$query = new MultiMatch();
			$query->setQuery( $searchTerm );
			$query->setFields( [
				'title.prefix^' . ( $weights['title'] * $weight ),
				'redirect.title.prefix^' . ( $weights['redirect'] * $weight ),
				'title.prefix_asciifolding^' . ( $weights['title_asciifolding'] * $weight ),
				'redirect.title.prefix_asciifolding^' . ( $weights['redirect_asciifolding'] * $weight ),
			] );
			return $query;
		};
		$query = new BoolQuery();
		$query->setMinimumShouldMatch( 1 );
		$weight = 1;
		$query->addShould( $buildMatch( $term, $weight ) );
		if ( $variants ) {
			foreach ( $variants as $variant ) {
				$weight *= 0.2;
				$query->addShould( $buildMatch( $variant, $weight ) );
			}
		}
		return $query;
	}
}
