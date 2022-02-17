<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\OtherIndexesUpdater;
use CirrusSearch\SearchConfig;
use CirrusSearch\Util;
use Elastica\Query\FunctionScore;

/**
 * Builds a set of functions with boosted templates
 * Uses a weight function with a filter for each template.
 * The list of boosted templates is read from SearchContext
 */
class BoostTemplatesFunctionScoreBuilder extends FunctionScoreBuilder {

	/**
	 * @var BoostedQueriesFunction
	 */
	private $boostedQueries;

	/**
	 * @param SearchConfig $config
	 * @param int[]|null $requestedNamespaces
	 * @param bool $localSearch
	 * @param bool $withDefaultBoosts false to disable the use of default boost templates
	 * @param float $weight
	 */
	public function __construct( SearchConfig $config, $requestedNamespaces, $localSearch, $withDefaultBoosts, $weight ) {
		parent::__construct( $config, $weight );
		// Use the boosted templates from extra indexes if available
		$queries = [];
		$weights = [];
		if ( $withDefaultBoosts ) {
			$boostTemplates = Util::getDefaultBoostTemplates( $config );
			if ( $boostTemplates ) {
				foreach ( $boostTemplates as $name => $boostWeight ) {
					$match = new \Elastica\Query\MatchQuery();
					$match->setFieldQuery( 'template', $name );
					$weights[] = $boostWeight * $this->weight;
					$queries[] = $match;
				}
			}
		}

		$otherIndices = [];
		if ( $requestedNamespaces && !$localSearch ) {
			$otherIndices = OtherIndexesUpdater::getExtraIndexesForNamespaces(
				$config,
				$requestedNamespaces
			);
		}

		$extraIndexBoostTemplates = [];
		foreach ( $otherIndices as $extraIndex ) {
			list( $wiki, $boostTemplates ) = $extraIndex->getBoosts();
			if ( $boostTemplates ) {
				$extraIndexBoostTemplates[$wiki] = $boostTemplates;
			}
		}

		foreach ( $extraIndexBoostTemplates as $wiki => $boostTemplates ) {
			foreach ( $boostTemplates as $name => $boostWeight ) {
				$bool = new \Elastica\Query\BoolQuery();
				$bool->addMust( ( new \Elastica\Query\MatchQuery() )->setFieldQuery( 'wiki', $wiki ) );
				$bool->addMust( ( new \Elastica\Query\MatchQuery() )->setFieldQuery( 'template',
					$name ) );
				$weights[] = $boostWeight * $this->weight;
				$queries[] = $bool;
			}
		}
		$this->boostedQueries = new BoostedQueriesFunction( $queries, $weights );
	}

	public function append( FunctionScore $functionScore ) {
		$this->boostedQueries->append( $functionScore );
	}
}
