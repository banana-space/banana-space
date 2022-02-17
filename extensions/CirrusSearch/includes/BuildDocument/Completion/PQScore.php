<?php

namespace CirrusSearch\BuildDocument\Completion;

/**
 * Score that combines QualityScore and the pageviews statistics (popularity)
 */
class PQScore extends QualityScore {
	const QSCORE_WEIGHT = 1;
	const POPULARITY_WEIGHT = 0.4;
	// 0.04% of the total page views is the max we accept
	// @todo: tested on enwiki values only
	const POPULARITY_MAX = 0.0004;

	/**
	 * @return string[]
	 */
	public function getRequiredFields() {
		return array_merge( parent::getRequiredFields(), [ 'popularity_score' ] );
	}

	/**
	 * @param array $doc
	 * @return int
	 */
	public function score( array $doc ) {
		$score = $this->intermediateScore( $doc ) * self::QSCORE_WEIGHT;
		$pop = $doc['popularity_score'] ?? 0;
		if ( $pop > self::POPULARITY_MAX ) {
			$pop = 1;
		} else {
			$logBase = 1 + self::POPULARITY_MAX * $this->maxDocs;
			// log₁(x) is undefined
			if ( $logBase > 1 ) {
				// @fixme: rough log scale by using maxDocs...
				$pop = log( 1 + ( $pop * $this->maxDocs ), $logBase );
			} else {
				$pop = 0;
			}
		}

		$score += $pop * self::POPULARITY_WEIGHT;
		$score /= self::QSCORE_WEIGHT + self::POPULARITY_WEIGHT;
		return intval( $score * self::SCORE_RANGE );
	}

	public function explain( array $doc ) {
		$qualityExplain = $this->intermediateExplain( $doc );
		$pop = $doc['popularity_score'] ?? 0;
		$popLogBaseExplain = [
			'value' => 1 + self::POPULARITY_MAX * $this->maxDocs,
			'description' => '1+popularity_max*max_docs; popularity_max = ' . self::POPULARITY_MAX .
				', max_docs = ' . $this->maxDocs,
		];

		if ( $popLogBaseExplain['value'] > 1 ) {
			$popExplain = [
				'value' => log(
					1 + ( min( $pop, self::POPULARITY_MAX ) * $this->maxDocs ), $popLogBaseExplain['value']
				),
				'description' => "log(1+(min(popularity,popularity_max)*max_docs), pop_logbase); popularity = $pop, " .
					 "popularity_max = " . self::POPULARITY_MAX . ", max_docs = {$this->maxDocs}, " .
					 "pop_logbase = {$popLogBaseExplain['value']}",
				'details' => [ 'pop_logbase' => $popLogBaseExplain ]
			];
		} else {
			$popExplain = [
				'value' => 0,
				'description' => 'log base 1 is undefined',
				'details' => [ 'pop_logbase' => $popLogBaseExplain ]
			];
		}

		$totalW = self::QSCORE_WEIGHT + self::POPULARITY_WEIGHT;
		$wPop = $this->explainWeight( $popExplain, self::POPULARITY_WEIGHT, $totalW, 'popularity' );
		$wQua = $this->explainWeight( $qualityExplain, self::QSCORE_WEIGHT, $totalW, 'quality' );
		$details = [
			'popularity_weighted' => $wPop,
			'page_quality' => $wQua,
		];
		$innerExp = [
			'value' => $wPop['value'] + $wQua['value'],
			'description' => "Weighted sum of doc quality score and popularity",
			'details' => $details
		];
		return [
			'value' => (int)( $innerExp['value'] * self::SCORE_RANGE ),
			'description' => 'Convert to an integer score: ' . $innerExp['value'] . ' * ' . self::SCORE_RANGE,
			'details' => [ $innerExp ]
		];
	}
}
