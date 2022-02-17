<?php

namespace CirrusSearch\Search\Rescore;

use Elastica\Query\FunctionScore;

/**
 * Builds a function that boosts incoming links
 * formula is log( incoming_links + 2 )
 */
class IncomingLinksFunctionScoreBuilder implements BoostFunctionBuilder {
	public function append( FunctionScore $functionScore ) {
		$functionScore->addFunction( 'field_value_factor', [
			'field' => 'incoming_links',
			'modifier' => 'log2p',
			'missing' => 0,
		] );
	}
}
