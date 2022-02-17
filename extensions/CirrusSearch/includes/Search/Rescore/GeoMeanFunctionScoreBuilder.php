<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * Utility function to compute a weighted geometric mean.
 * According to https://en.wikipedia.org/wiki/Weighted_geometric_mean
 * this is equivalent to exp ( w1*ln(value1)+w2*ln(value2) / (w1 + w2) ) ^ impact
 * impact is applied as a power factor because this function is applied in a
 * multiplication.
 * Members can use only LogScaleBoostFunctionScoreBuilder or SatuFunctionScoreBuilder
 * these are the only functions that normalize the value in the [0,1] range.
 */
class GeoMeanFunctionScoreBuilder extends FunctionScoreBuilder {
	/** @var float */
	private $impact;
	/** @var array[] */
	private $scriptFunctions = [];
	/** @var float */
	private $epsilon = 0.0000001;

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

		if ( isset( $profile['epsilon'] ) ) {
			$this->epsilon = $this->getOverriddenFactor( $profile['epsilon'] );
		}

		if ( !isset( $profile['members'] ) || !is_array( $profile['members'] ) ) {
			throw new InvalidRescoreProfileException( 'members must be an array of arrays' );
		}
		foreach ( $profile['members'] as $member ) {
			if ( !is_array( $member ) ) {
				throw new InvalidRescoreProfileException( "members must be an array of arrays" );
			}
			if ( !isset( $member['weight'] ) ) {
				$weight = 1;
			} else {
				$weight = $this->getOverriddenFactor( $member['weight'] );
			}
			$function = [ 'weight' => $weight ];
			switch ( $member['type'] ) {
				case 'satu':
					$function['script'] =
						new SatuFunctionScoreBuilder( $this->config, 1,
							$member['params'] );
					break;
				case 'logscale_boost':
					$function['script'] =
						new LogScaleBoostFunctionScoreBuilder( $this->config, 1,
							$member['params'] );
					break;
				default:
					throw new InvalidRescoreProfileException( "Unsupported function in {$member['type']}." );
			}
			$this->scriptFunctions[] = $function;
		}
		if ( count( $this->scriptFunctions ) < 2 ) {
			throw new InvalidRescoreProfileException( "At least 2 members are needed to compute a geometric mean." );
		}
	}

	/**
	 * Build a weighted geometric mean using a logarithmic arithmetic mean.
	 * exp(w1*ln(value1)+w2*ln(value2) / (w1+w2))
	 * NOTE: We need to use an epsilon value in case value is 0.
	 *
	 * @return string|null the script
	 */
	public function getScript() {
		$formula = "pow(";
		$formula .= "exp((";
		$first = true;
		$sumWeight = 0;
		foreach ( $this->scriptFunctions as $func ) {
			if ( $first ) {
				$first = false;
			} else {
				$formula .= " + ";
			}
			$sumWeight += $func['weight'];
			$formula .= "{$func['weight']}*ln(max(";

			$formula .= $func['script']->getScript();

			$formula .= ", {$this->epsilon}))";
		}
		if ( $sumWeight == 0 ) {
			return null;
		}
		$formula .= ")";
		$formula .= "/ $sumWeight )";
		$formula .= ", {$this->impact})"; // pow(

		return $formula;
	}

	public function append( FunctionScore $functionScore ) {
		$formula = $this->getScript();
		if ( $formula != null ) {
			$functionScore->addScriptScoreFunction( new \Elastica\Script\Script( $formula, null,
				'expression' ), null, $this->weight );
		}
	}
}
