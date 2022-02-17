<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\SearchConfig;
use Elastica\Query\FunctionScore;

/**
 * A function score that builds a script_score.
 * see: https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-script-score
 * NOTE: only lucene expression script engine is supported.
 */
class ScriptScoreFunctionScoreBuilder extends FunctionScoreBuilder {
	/**
	 * @var string the script
	 */
	private $script;

	/**
	 * @param SearchConfig $config
	 * @param float $weight
	 * @param string $script
	 */
	public function __construct( SearchConfig $config, $weight, $script ) {
		parent::__construct( $config, $weight );
		$this->script = $script;
	}

	public function append( FunctionScore $functionScore ) {
		$functionScore->addScriptScoreFunction( new \Elastica\Script\Script( $this->script, null,
			'expression' ), null, $this->weight );
	}
}
