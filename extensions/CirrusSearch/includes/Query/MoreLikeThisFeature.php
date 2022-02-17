<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;

/**
 * Finds pages similar to another one.
 * (Non-greedy replacement of MoreLikeFeature)
 */
class MoreLikeThisFeature extends SimpleKeywordFeature {
	use MoreLikeTrait;

	/**
	 * @var SearchConfig
	 */
	private $config;

	public function __construct( SearchConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @inheritDoc
	 */
	public function getFeatureName( $key, $valueDelimiter ) {
		return "more_like";
	}

	/**
	 * @inheritDoc
	 */
	protected function getKeywords() {
		return [ "morelikethis" ];
	}

	/**
	 * @inheritDoc
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$context->setCacheTtl( $this->config->get( 'CirrusSearchMoreLikeThisTTL' ) );
		$titles = $this->doExpand( $key, $value, $context );
		if ( $titles === [] ) {
			$context->setResultsPossible( false );
			return [ null, true ];
		}
		$mlt = $this->buildMoreLikeQuery( $titles );
		if ( !$negated ) {
			$context->addNonTextQuery( $mlt );
			return [ null, false ];
		} else {
			return [ $mlt, false ];
		}
	}

	/**
	 * @return SearchConfig
	 */
	public function getConfig(): SearchConfig {
		return $this->config;
	}
}
