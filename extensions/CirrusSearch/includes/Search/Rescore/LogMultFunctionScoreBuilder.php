<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * simple log(factor*field+2)^impact
 * Useful to control the impact when applied in a multiplication.
 */
class LogMultFunctionScoreBuilder extends FunctionScoreBuilder {
	/** @var float */
	private $impact;
	/** @var float */
	private $factor;
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
		if ( isset( $profile['impact'] ) ) {
			$this->impact = $this->getOverriddenFactor( $profile['impact'] );
			if ( $this->impact <= 0 ) {
				throw new InvalidRescoreProfileException( 'Param impact must be > 0' );
			}
		} else {
			throw new InvalidRescoreProfileException( 'Param impact is mandatory' );
		}

		if ( isset( $profile['factor'] ) ) {
			$this->factor = $this->getOverriddenFactor( $profile['factor'] );
			if ( $this->factor <= 0 ) {
				throw new InvalidRescoreProfileException( 'Param factor must be > 0' );
			}
		} else {
			$this->factor = 1;
		}

		if ( isset( $profile['field'] ) ) {
			$this->field = $profile['field'];
		} else {
			throw new InvalidRescoreProfileException( 'Param field is mandatory' );
		}
	}

	public function append( FunctionScore $functionScore ) {
		$formula = "pow(log10({$this->factor} * doc['{$this->field}'].value + 2), {$this->impact})";
		$functionScore->addScriptScoreFunction( new \Elastica\Script\Script( $formula, null,
			'expression' ), null, $this->weight );
	}
}
