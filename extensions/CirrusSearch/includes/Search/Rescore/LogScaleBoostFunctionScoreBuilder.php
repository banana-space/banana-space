<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * Normalize values in the [0,1] range
 * Allows to set:
 * - a scale
 * - midpoint
 * It will generate a log scale factor where :
 * - f(0) = 0
 * - f(scale) = 1
 * - f(midpoint) = 0.5
 *
 * Based on log10( a . x + 1 ) / log10( a . M + 1 )
 * a: a factor used to adjust the midpoint
 * M: the max value used to scale
 *
 */
class LogScaleBoostFunctionScoreBuilder extends FunctionScoreBuilder {
	/** @var string */
	private $field;
	/** @var float */
	private $midpoint;
	/** @var float */
	private $scale;

	/**
	 * @param SearchConfig $config
	 * @param float $weight
	 * @param array $profile
	 * @throws InvalidRescoreProfileException
	 */
	public function __construct( SearchConfig $config, $weight, $profile ) {
		parent::__construct( $config, $weight );

		if ( isset( $profile['midpoint'] ) ) {
			$this->midpoint = $this->getOverriddenFactor( $profile['midpoint'] );
		} else {
			throw new InvalidRescoreProfileException( 'midpoint is mandatory' );
		}

		if ( isset( $profile['scale'] ) ) {
			$this->scale = $this->getOverriddenFactor( $profile['scale'] );
		} else {
			throw new InvalidRescoreProfileException( 'scale is mandatory' );
		}

		if ( isset( $profile['field'] ) ) {
			$this->field = $profile['field'];
		} else {
			throw new InvalidRescoreProfileException( 'field is mandatory' );
		}
	}

	/**
	 * find the factor to adjust the scale center,
	 * it's like finding the log base to have f(N) = 0.5
	 *
	 * @param float $M
	 * @param float $N
	 * @return float
	 * @throws InvalidRescoreProfileException
	 */
	private function findCenterFactor( $M, $N ) {
		// Neutral point is found by resolving
		// log10( x . N + 1 ) / log10( x . M + 1 ) = 0.5
		// it's equivalent to resolving:
		// N²x² + (2N - M)x + 1 = 0
		// so we we use the quadratic formula:
		// (-(2N-M) + sqrt((2N-M)²-4N²)) / 2N²
		if ( 4 * $N >= $M ) {
			throw new InvalidRescoreProfileException( 'The midpoint point cannot be higher than scale/4' );
		}

		return ( - ( 2 * $N - $M ) + sqrt( ( 2 * $N - $M ) * ( 2 * $N - $M ) - 4 * $N * $N ) ) /
			   ( 2 * $N * $N );
	}

	public function append( FunctionScore $functionScore ) {
		$formula = $this->getScript();

		$functionScore->addScriptScoreFunction( new \Elastica\Script\Script( $formula, null,
			'expression' ), null, $this->weight );
	}

	/**
	 * @return string
	 */
	public function getScript() {
		$midFactor = $this->findCenterFactor( $this->scale, $this->midpoint );
		$formula = "log10($midFactor * min(doc['{$this->field}'].value,{$this->scale}) + 1)";
		$formula .= "/log10($midFactor * {$this->scale} + 1)";

		return $formula;
	}
}
