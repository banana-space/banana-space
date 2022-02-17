<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Parser\QueryStringRegex\SearchQueryParseException;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Searcher;
use Elastica\ResultSet as ElasticaResultSet;
use HtmlArmor;
use ISearchResultSet;
use MediaWiki\Logger\LoggerFactory;

trait FallbackMethodTrait {

	/**
	 * Check the number of total hits stored in $resultSet
	 * and return true if it's greater or equals than $threshold
	 * NOTE: inter wiki results are check
	 *
	 * @param CirrusSearchResultSet $resultSet
	 * @param int $threshold (defaults to 1).
	 *
	 * @see \ISearchResultSet::getInterwikiResults()
	 * @see \ISearchResultSet::SECONDARY_RESULTS
	 * @return bool
	 */
	public function resultsThreshold( CirrusSearchResultSet $resultSet, $threshold = 1 ) {
		if ( $resultSet->getTotalHits() >= $threshold ) {
			return true;
		}
		foreach ( $resultSet->getInterwikiResults( ISearchResultSet::SECONDARY_RESULTS ) as $resultSet ) {
			if ( $resultSet->getTotalHits() >= $threshold ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if any result in the response is fully highlighted on the title field
	 * @param \Elastica\ResultSet $results
	 * @return bool true if a result has its title fully highlighted
	 */
	public function resultContainsFullyHighlightedMatch( ElasticaResultSet $results ) {
		foreach ( $results as $result ) {
			$highlights = $result->getHighlights();
			// TODO: Should we check redirects as well?
			// If the whole string is highlighted then return true
			$regex = '/' . Searcher::HIGHLIGHT_PRE_MARKER . '.*?' . Searcher::HIGHLIGHT_POST_MARKER . '/';
			if ( isset( $highlights[ 'title' ] ) &&
				 !trim( preg_replace( $regex, '', $highlights[ 'title' ][ 0 ] ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * If all conditions are met execute a search query using the $suggestedQuery and returns its results.
	 * Conditions are:
	 *  - SearchQuery::isAllowRewrite() must be true on the original query
	 *  - The original query must be a simple bag of words
	 *  - FallbackRunnerContext::costlyCallAllowed() must be true
	 *  - number of displayable results must not exceed $resultsThreshold
	 *
	 * @param FallbackRunnerContext $context
	 * @param SearchQuery $originalQuery
	 * @param string $suggestedQuery
	 * @param HtmlArmor|string|null $suggestedQuerySnippet
	 * @param int $resultsThreshold
	 * @return FallbackStatus
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 * @see SearchQuery::isAllowRewrite()
	 * @see FallbackRunnerContext::costlyCallAllowed()
	 * @see FallbackMethodTrait::resultsThreshold()
	 */
	public function maybeSearchAndRewrite(
		FallbackRunnerContext $context,
		SearchQuery $originalQuery,
		string $suggestedQuery,
		$suggestedQuerySnippet = null,
		int $resultsThreshold = 1
	): FallbackStatus {
		$previousSet = $context->getPreviousResultSet();
		if ( !$originalQuery->isAllowRewrite()
			 || !$context->costlyCallAllowed()
			 || $this->resultsThreshold( $previousSet, $resultsThreshold )
			 || !$originalQuery->getParsedQuery()->isQueryOfClass( BasicQueryClassifier::SIMPLE_BAG_OF_WORDS )
		) {
			// Only provide the suggestion, not the results of the suggestion.
			return FallbackStatus::suggestQuery( $suggestedQuery, $suggestedQuerySnippet );
		}

		try {
			$rewrittenQuery = SearchQueryBuilder::forRewrittenQuery( $originalQuery, $suggestedQuery,
					$context->getNamespacePrefixParser() )->build();
		} catch ( SearchQueryParseException $e ) {
			LoggerFactory::getInstance( 'CirrusSearch' )
				->warning( "Cannot parse rewritten query", [ 'exception' => $e ] );
			// Suggest the user submits the suggested query directly
			return FallbackStatus::suggestQuery( $suggestedQuery, $suggestedQuerySnippet );
		}
		$searcher = $context->makeSearcher( $rewrittenQuery );
		$status = $searcher->search( $rewrittenQuery );
		if ( $status->isOK() && $status->getValue() instanceof CirrusSearchResultSet ) {
			/**
			 * @var CirrusSearchResultSet $newResults
			 */
			$newResults = $status->getValue();
			return FallbackStatus::replaceLocalResults( $newResults, $suggestedQuery, $suggestedQuerySnippet );
		} else {
			// The suggested query returned no results, there doesn't seem to be any benefit
			// to sugesting it to the user.
			return FallbackStatus::noSuggestion();
		}
	}
}
