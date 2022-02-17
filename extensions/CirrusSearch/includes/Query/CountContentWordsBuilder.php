<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;
use CirrusSearch\Search\SingleAggResultsType;
use Elastica\Aggregation\Sum;

/**
 * Build a query to sum up the word count of all articles
 */
class CountContentWordsBuilder {
	// The count doesn't change all that quickly. Re-run the query
	// no more than daily per-wiki.
	const CACHE_SECONDS = 86400;

	/**
	 * @param SearchContext $context the search context
	 */
	public function build( SearchContext $context ) {
		$context->addSyntaxUsed( 'sum_word_count' );
		$context->setResultsType( new SingleAggResultsType( 'word_count' ) );
		$context->setRescoreProfile( 'empty' );
		$context->addAggregation(
			( new Sum( 'word_count' ) )->setField( 'text.word_count' ) );
		$context->setCacheTtl( self::CACHE_SECONDS );
	}
}
