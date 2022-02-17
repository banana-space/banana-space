<?php

namespace CirrusSearch\Search\Rescore;

class ByKeywordTemplateBoostFunction implements BoostFunctionBuilder {

	/**
	 * @var \CirrusSearch\Search\Rescore\BoostedQueriesFunction
	 */
	private $queries;

	/**
	 * @param array $boostTemplates
	 */
	public function __construct( array $boostTemplates ) {
		$queries = [];
		$weights = [];
		foreach ( $boostTemplates as $name => $weight ) {
			$match = new \Elastica\Query\MatchQuery();
			$match->setFieldQuery( 'template', $name );
			$weights[] = $weight;
			$queries[] = $match;
		}

		$this->queries = new BoostedQueriesFunction( $queries, $weights );
	}

	/**
	 * Append functions to the function score $container
	 *
	 * @param \Elastica\Query\FunctionScore $container
	 */
	public function append( \Elastica\Query\FunctionScore $container ) {
		$this->queries->append( $container );
	}
}
