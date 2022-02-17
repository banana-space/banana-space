<?php

namespace CirrusSearch\BuildDocument\Completion;

use InvalidArgumentException;

/**
 * Create certain suggestion scoring method, by name.
 */
class SuggestScoringMethodFactory {
	/**
	 * @param string $scoringMethod the name of the scoring method
	 * @return SuggestScoringMethod
	 * @throws InvalidArgumentException
	 */
	public static function getScoringMethod( $scoringMethod ) {
		switch ( $scoringMethod ) {
			case 'incomingLinks':
				return new IncomingLinksScoringMethod();
			case 'quality':
				return new QualityScore();
			case 'popqual':
				return new PQScore();
		}
		throw new InvalidArgumentException( 'Unknown scoring method ' . $scoringMethod );
	}
}
