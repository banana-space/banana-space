<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * Builds a script score boost documents on the timestamp field.
 * Can be initialized by config for full text and by special syntax in user query
 */
class PreferRecentFunctionScoreBuilder extends FunctionScoreBuilder {

	/**
	 * @var float
	 */
	private $halfLife;

	/**
	 * @var float
	 */
	private $decayPortion;

	/**
	 * @param SearchConfig $config
	 * @param float $weight
	 * @param float $halfLife
	 * @param float $decayPortion
	 */
	public function __construct( SearchConfig $config, $weight, $halfLife, $decayPortion ) {
		parent::__construct( $config, $weight );
		$this->halfLife = $halfLife;
		$this->decayPortion = $decayPortion;
	}

	public function append( FunctionScore $functionScore ) {
		if ( !( $this->halfLife > 0 && $this->decayPortion > 0 ) ) {
			return;
		}
		// Convert half life for time in days to decay constant for time in milliseconds.
		$decayConstant = log( 2 ) / $this->halfLife / 86400000;
		$parameters = [
			'decayConstant' => $decayConstant,
			'decayPortion' => $this->decayPortion,
			'nonDecayPortion' => 1 - $this->decayPortion,
			'now' => time() * 1000,
		];

		// e^ct where t is last modified time - now which is negative
		$exponentialDecayExpression = "exp(decayConstant * (doc['timestamp'].value - now))";
		if ( $this->decayPortion !== 1.0 ) {
			$exponentialDecayExpression =
				"$exponentialDecayExpression * decayPortion + nonDecayPortion";
		}
		$functionScore->addScriptScoreFunction( new \Elastica\Script\Script( $exponentialDecayExpression,
			$parameters, 'expression' ), null, $this->weight );
	}
}
