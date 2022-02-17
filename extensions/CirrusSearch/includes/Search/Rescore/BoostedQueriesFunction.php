<?php

namespace CirrusSearch\Search\Rescore;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;
use Wikimedia\Assert\Assert;

/**
 * Simple list of weighted queries.
 * @see FunctionScore::addWeightFunction()
 */
class BoostedQueriesFunction implements BoostFunctionBuilder {

	/**
	 * @var AbstractQuery[]
	 */
	private $boostedQueries;

	/**
	 * @var float[]
	 */
	private $weights;

	/**
	 * Build a BoostedQueriesFunction using a list of queries and its associated weights.
	 * @param AbstractQuery[] $boostedQueries
	 * @param float[] $weights
	 */
	public function __construct( array $boostedQueries, array $weights ) {
		Assert::parameter( count( $boostedQueries ) === count( $weights ), '$weights',
			'$weights must have the same number of elements as $boostedQueries' );
		$this->boostedQueries = $boostedQueries;
		$this->weights = $weights;
	}

	/**
	 * Append functions to the function score $container
	 *
	 * @param FunctionScore $container
	 */
	public function append( FunctionScore $container ) {
		$mi = new \MultipleIterator( \MultipleIterator::MIT_NEED_ALL | \MultipleIterator::MIT_KEYS_NUMERIC );
		$mi->attachIterator( new \ArrayIterator( $this->boostedQueries ) );
		$mi->attachIterator( new \ArrayIterator( $this->weights ) );

		foreach ( $mi as $queryAndWeight ) {
			list( $query, $weight ) = $queryAndWeight;
			$container->addWeightFunction( $weight, $query );
		}
	}
}
