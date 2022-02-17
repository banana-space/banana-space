<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * Saturation function based on x/(k+x), k is a parameter
 * to control how fast the function saturates.
 * NOTE: that satu is always 0.5 when x == k.
 * Parameter a is added to form a sigmoid : x^a/(k^a+x^a)
 * Based on http://research.microsoft.com/pubs/65239/craswell_sigir05.pdf
 * This function is suited to apply a new factor in a weighted sum.
 */
class SatuFunctionScoreBuilder extends FunctionScoreBuilder {
	/** @var float */
	private $k;
	/** @var float */
	private $a;
	/** @var string */
	private $field;

	/**
	 * @param SearchConfig $config
	 * @param float $weight
	 * @param array $profile
	 * @throws InvalidRescoreProfileException
	 */
	public function __construct( SearchConfig $config, $weight, $profile ) {
		parent::__construct( $config, $weight );
		if ( isset( $profile['k'] ) ) {
			$this->k = $this->getOverriddenFactor( $profile['k'] );
			if ( $this->k <= 0 ) {
				throw new InvalidRescoreProfileException( 'Param k must be > 0' );
			}
		} else {
			throw new InvalidRescoreProfileException( 'Param k is mandatory' );
		}

		if ( isset( $profile['a'] ) ) {
			$this->a = $this->getOverriddenFactor( $profile['a'] );
			if ( $this->a <= 0 ) {
				throw new InvalidRescoreProfileException( 'Param a must be > 0' );
			}
		} else {
			$this->a = 1;
		}

		if ( isset( $profile['field'] ) ) {
			$this->field = $profile['field'];
		} else {
			throw new InvalidRescoreProfileException( 'Param field is mandatory' );
		}
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
		$formula = "pow(doc['{$this->field}'].value , {$this->a}) / ";
		$formula .= "( pow(doc['{$this->field}'].value, {$this->a}) + ";
		$formula .= "pow({$this->k},{$this->a}))";

		return $formula;
	}
}
