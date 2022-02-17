<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;

/**
 * Simple Match query builder, currently based on
 * FullTextQueryStringQueryBuilder to reuse its parsing logic.
 * It will only support queries that do not use the lucene QueryString syntax
 * and fallbacks to FullTextQueryStringQueryBuilder in such cases.
 * It generates only simple match/multi_match queries. It supports merging
 * multiple clauses into a dismax query with 'in_dismax'.
 */
class FullTextSimpleMatchQueryBuilder extends FullTextQueryStringQueryBuilder {
	/**
	 * @var bool true is the main used the experimental query
	 */
	private $usedExpQuery = false;

	/**
	 * @var float[]|array[] mixed array of field settings used for the main query
	 */
	private $fields;

	/**
	 * @var float[]|array[] mixed array of field settings used for the phrase rescore query
	 */
	private $phraseFields;

	/**
	 * @var float default weight to use for stems
	 */
	private $defaultStemWeight;

	/**
	 * @var string default multimatch query type
	 */
	private $defaultQueryType;

	/**
	 * @var string default multimatch min should match
	 */
	private $defaultMinShouldMatch;

	/**
	 * @var array[] dismax query settings
	 */
	private $dismaxSettings;

	/**
	 * @var array filter settings
	 */
	private $filter;

	public function __construct( SearchConfig $config, array $feature, array $settings ) {
		parent::__construct( $config, $feature );
		$this->fields = $settings['fields'];
		if ( isset( $settings['filter'] ) ) {
			$this->filter = $settings['filter'];
		} else {
			$this->filter = [ 'type' => 'default' ];
		}

		$this->phraseFields = $settings['phrase_rescore_fields'];
		$this->defaultStemWeight = $settings['default_stem_weight'];
		$this->defaultQueryType = $settings['default_query_type'];
		$this->defaultMinShouldMatch = $settings['default_min_should_match'];
		$this->dismaxSettings = $settings['dismax_settings'] ?? [];
	}

	/**
	 * Build the primary query used for full text search.
	 * If query_string syntax is not used the experimental query is built.
	 * We fallback to parent implementation otherwise.
	 *
	 * @param SearchContext $context
	 * @param string[] $fields
	 * @param string[] $nearMatchFields
	 * @param string $queryString
	 * @param string $nearMatchQuery
	 * @return \Elastica\Query\AbstractQuery
	 */
	protected function buildSearchTextQuery(
		SearchContext $context,
		array $fields,
		array $nearMatchFields,
		$queryString,
		$nearMatchQuery
	) {
		if ( $context->isSyntaxUsed( 'query_string' ) ) {
			return parent::buildSearchTextQuery( $context, $fields,
				$nearMatchFields, $queryString, $nearMatchQuery );
		}
		$context->addSyntaxUsed( 'full_text_simple_match', 5 );
		$this->usedExpQuery = true;
		$queryForMostFields = $this->buildExpQuery( $queryString );
		if ( !$nearMatchQuery ) {
			return $queryForMostFields;
		}

		// Build one query for the full text fields and one for the near match fields so that
		// the near match can run unescaped.
		$bool = new \Elastica\Query\BoolQuery();
		$bool->setMinimumShouldMatch( 1 );
		$bool->addShould( $queryForMostFields );
		$nearMatch = new \Elastica\Query\MultiMatch();
		$nearMatch->setFields( $nearMatchFields );
		$nearMatch->setQuery( $nearMatchQuery );
		$bool->addShould( $nearMatch );

		return $bool;
	}

	/**
	 * Builds the highlight query
	 * @param SearchContext $context
	 * @param string[] $fields
	 * @param string $queryText
	 * @param int $slop
	 * @return \Elastica\Query\AbstractQuery
	 */
	protected function buildHighlightQuery( SearchContext $context, array $fields, $queryText, $slop ) {
		$query = parent::buildHighlightQuery( $context, $fields, $queryText, $slop );
		if ( $this->usedExpQuery && $query instanceof \Elastica\Query\QueryString ) {
			// the exp query accepts more docs (stopwords in query are not required)
			$query->setDefaultOperator( 'OR' );
		}
		return $query;
	}

	/**
	 * Builds the phrase rescore query
	 * @param SearchContext $context
	 * @param string[] $fields
	 * @param string $queryText
	 * @param int $slop
	 * @return \Elastica\Query\AbstractQuery
	 */
	protected function buildPhraseRescoreQuery( SearchContext $context, array $fields, $queryText, $slop ) {
		if ( $this->usedExpQuery ) {
			$phrase = new \Elastica\Query\MultiMatch();
			$phrase->setParam( 'type', 'phrase' );
			$phrase->setParam( 'slop', $slop );
			$fields = [];
			foreach ( $this->phraseFields as $f => $b ) {
				$fields[] = "$f^$b";
			}
			$phrase->setFields( $fields );
			$phrase->setQuery( $queryText );
			return $this->maybeWrapWithTokenCountRouter( $queryText, $phrase );
		} else {
			return parent::buildPhraseRescoreQuery( $context, $fields, $queryText, $slop );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getMultiTermRewriteMethod() {
		// Use blended freq as a rewrite method. The
		// top_terms_boost_1024 method used by the parent is not well
		// suited for a weighted sum and for some reasons uses the
		// queryNorms which depends on the number of terms found by the
		// wildcard. Using this one we'll use the similarity configured
		// for this field instead of a constant score and in the case
		// of BM25 queryNorm is ignored (removed in lucene 7)
		return 'top_terms_blended_freqs_1024';
	}

	/**
	 * Generate an elasticsearch query by reading profile settings
	 * @param string $queryString the query text
	 * @return \Elastica\Query\AbstractQuery
	 */
	private function buildExpQuery( $queryString ) {
		$query = new \Elastica\Query\BoolQuery();
		$this->attachFilter( $this->filter, $queryString, $query );
		$dismaxQueries = [];

		foreach ( $this->fields as $f => $settings ) {
			$mmatch = new \Elastica\Query\MultiMatch();
			$mmatch->setQuery( $queryString );
			$queryType = $this->defaultQueryType;
			$minShouldMatch = $this->defaultMinShouldMatch;
			$stemWeight = $this->defaultStemWeight;
			$boost = 1;
			$fields = [ "$f.plain^1", "$f^$stemWeight" ];
			$in_dismax = null;

			if ( is_array( $settings ) ) {
				$boost = $settings['boost'] ?? $boost;
				$queryType = $settings['query_type'] ?? $queryType;
				$minShouldMatch = $settings['min_should_match'] ?? $minShouldMatch;
				if ( isset( $settings['is_plain'] ) && $settings['is_plain'] ) {
					$fields = [ $f ];
				} else {
					$fields = [ "$f.plain^1", "$f^$stemWeight" ];
				}
				$in_dismax = $settings['in_dismax'] ?? null;
			} else {
				$boost = $settings;
			}

			if ( $boost === 0 ) {
				continue;
			}

			$mmatch->setParam( 'boost', $boost );
			$mmatch->setMinimumShouldMatch( $minShouldMatch );
			$mmatch->setType( $queryType );
			$mmatch->setFields( $fields );
			$mmatch->setParam( 'boost', $boost );
			$mmatch->setQuery( $queryString );
			if ( $in_dismax ) {
				$dismaxQueries[$in_dismax][] = $mmatch;
			} else {
				$query->addShould( $mmatch );
			}
		}
		foreach ( $dismaxQueries as $name => $queries ) {
			$dismax = new \Elastica\Query\DisMax();
			if ( isset( $this->dismaxSettings[$name] ) ) {
				$settings = $this->dismaxSettings[$name];
				if ( isset( $settings['tie_breaker'] ) ) {
					$dismax->setTieBreaker( $settings['tie_breaker'] );
				}
				if ( isset( $settings['boost'] ) ) {
					$dismax->setBoost( $settings['boost'] );
				}
			}
			foreach ( $queries as $q ) {
				$dismax->addQuery( $q );
			}
			$query->addShould( $dismax );
		}
		return $query;
	}

	/**
	 * Attach the query filter to $boolQuery
	 *
	 * @param array $filterDef filter definition
	 * @param string $query query text
	 * @param \Elastica\Query\BoolQuery $boolQuery the query to attach the filter to
	 */
	private function attachFilter( array $filterDef, $query, \Elastica\Query\BoolQuery $boolQuery ) {
		if ( !isset( $filterDef['type'] ) ) {
			throw new \RuntimeException( "Cannot configure the filter clause, 'type' must be defined." );
		}
		$type = $filterDef['type'];
		$filter = null;

		switch ( $type ) {
		case 'default':
			$filter = $this->buildSimpleAllFilter( $filterDef, $query );
			break;
		case 'constrain_title':
			$filter = $this->buildTitleFilter( $filterDef, $query );
			break;
		default:
			throw new \RuntimeException( "Cannot build the filter clause: unknown filter type $type" );
		}

		$boolQuery->addFilter( $filter );
	}

	/**
	 * Builds a simple filter on all and all.plain when all terms must match
	 *
	 * @param array[] $options array containing filter options
	 * @param string $query
	 * @return \Elastica\Query\AbstractQuery
	 */
	private function buildSimpleAllFilter( $options, $query ) {
		$filter = new \Elastica\Query\BoolQuery();
		// FIXME: We can't use solely the stem field here
		// - Depending on languages it may lack stopwords,
		// A dedicated field used for filtering would be nice
		foreach ( [ 'all', 'all.plain' ] as $field ) {
			$m = new \Elastica\Query\MatchQuery();
			$m->setFieldQuery( $field, $query );
			$minShouldMatch = '100%';
			if ( isset( $options['settings'][$field]['minimum_should_match'] ) ) {
				$minShouldMatch = $options['settings'][$field]['minimum_should_match'];
			}
			if ( $minShouldMatch === '100%' ) {
				$m->setFieldOperator( $field, 'AND' );
			} else {
				$m->setFieldMinimumShouldMatch( $field, $minShouldMatch );
			}
			$filter->addShould( $m );
		}
		return $filter;
	}

	/**
	 * Builds a simple filter based on buildSimpleAllFilter + a constraint
	 * on title/redirect :
	 * (all:query OR all.plain:query) AND (title:query OR redirect:query)
	 * where the filter on title/redirect can be controlled by setting
	 * minimum_should_match to relax the constraint on title.
	 * (defaults to '3<80%')
	 *
	 * @param array[] $options array containing filter options
	 * @param string $query the user query
	 * @return \Elastica\Query\AbstractQuery
	 */
	private function buildTitleFilter( $options, $query ) {
		$filter = new \Elastica\Query\BoolQuery();
		$filter->addMust( $this->buildSimpleAllFilter( $options, $query ) );
		$minShouldMatch = '3<80%';
		if ( isset( $options['settings']['minimum_should_match'] ) ) {
			$minShouldMatch = $options['settings']['minimum_should_match'];
		}
		$titleFilter = new \Elastica\Query\BoolQuery();

		foreach ( [ 'title', 'redirect.title' ] as $field ) {
			$m = new \Elastica\Query\MatchQuery();
			$m->setFieldQuery( $field, $query );
			$m->setFieldMinimumShouldMatch( $field, $minShouldMatch );
			$titleFilter->addShould( $m );
		}
		$filter->addMust( $titleFilter );
		return $filter;
	}
}
