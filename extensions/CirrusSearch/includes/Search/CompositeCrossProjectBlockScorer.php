<?php

namespace CirrusSearch\Search;

/**
 * Composite, weighted sum of a list of subscorers
 */
class CompositeCrossProjectBlockScorer extends CrossProjectBlockScorer {
	private $scorers = [];

	public function __construct( array $settings ) {
		parent::__construct( $settings );
		foreach ( $settings as $type => $subSettings ) {
			$weight = $subSettings['weight'] ?? 1;
			$scorerSettings = $subSettings['settings'] ?? [];
			$scorer = CrossProjectBlockScorerFactory::loadScorer( $type, $scorerSettings );
			$this->scorers[] = [
				'weight' => $weight,
				'scorer' => $scorer,
			];
		}
	}

	/**
	 * @param string $prefix
	 * @param CirrusSearchResultSet $results
	 * @return float
	 */
	public function score( $prefix, CirrusSearchResultSet $results ) {
		$score = 0;
		foreach ( $this->scorers as $scorer ) {
			$score += $scorer['weight'] * $scorer['scorer']->score( $prefix, $results );
		}
		return $score;
	}
}
