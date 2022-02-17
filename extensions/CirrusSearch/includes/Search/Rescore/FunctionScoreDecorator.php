<?php

namespace CirrusSearch\Search\Rescore;

use Elastica\Query\AbstractQuery;
use Elastica\Query\FunctionScore;

/**
 * This is useful to check if the function score is empty
 * Function score builders may not add any function if some
 * criteria are not met. If there's no function we should not
 * not build the rescore query.
 * @todo: find another pattern to deal with this problem and avoid
 * this strong dependency to FunctionScore::addFunction signature.
 */
class FunctionScoreDecorator extends FunctionScore {
	/** @var int */
	private $size = 0;

	/**
	 * @param string $functionType
	 * @param array|float $functionParams
	 * @param AbstractQuery|null $filter
	 * @param float|null $weight
	 * @return self
	 */
	public function addFunction( $functionType, $functionParams, AbstractQuery $filter = null,
		$weight = null
	) {
		$this->size ++;

		parent::addFunction( $functionType, $functionParams, $filter, $weight );
		return $this;
	}

	/**
	 * @return bool true if this function score is empty
	 */
	public function isEmptyFunction() {
		return $this->size == 0;
	}

	/**
	 * @return int the number of added functions.
	 */
	public function getSize() {
		return $this->size;
	}

	/**
	 * Default elastica behaviour is to use class name
	 * as property name. We must override this function
	 * to force the name to function_score
	 *
	 * @return string
	 */
	protected function _getBaseName() {
		return "function_score";
	}
}
