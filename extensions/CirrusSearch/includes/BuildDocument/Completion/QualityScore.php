<?php

namespace CirrusSearch\BuildDocument\Completion;

use CirrusSearch\Util;

/**
 * Score that tries to reflect the quality of a page.
 * NOTE: Experimental
 *
 * This score makes the assumption that bigger is better.
 *
 * Small cities/village which have a high number of incoming links because they
 * link to each others ( see https://en.wikipedia.org/wiki/Villefort,_Loz%C3%A8re )
 * will be be discounted correctly because others variables are very low.
 *
 * On the other hand some pages like List will get sometimes a very high but unjustified
 * score.
 *
 * The boost templates feature might help but it's a System message that is not necessarily
 * configured by wiki admins.
 */
class QualityScore implements SuggestScoringMethod {
	// TODO: move these constants into a cirrus profile
	const INCOMING_LINKS_MAX_DOCS_FACTOR = 0.1;

	const EXTERNAL_LINKS_NORM = 20;
	const PAGE_SIZE_NORM = 50000;
	const HEADING_NORM = 20;
	const REDIRECT_NORM = 30;

	const INCOMING_LINKS_WEIGHT = 0.6;
	const EXTERNAL_LINKS_WEIGHT = 0.1;
	const PAGE_SIZE_WEIGHT = 0.1;
	const HEADING_WEIGHT = 0.2;
	const REDIRECT_WEIGHT = 0.1;

	// The final score will be in the range [0, SCORE_RANGE]
	const SCORE_RANGE = 10000000;

	/**
	 * Template boosts configured by the mediawiki admin.
	 *
	 * @var float[] array of key values, key is the template and value is a float
	 */
	private $boostTemplates;

	/**
	 * @var int the number of docs in the index
	 */
	protected $maxDocs;

	/**
	 * @var int normalisation factor for incoming links
	 */
	private $incomingLinksNorm;

	/**
	 * @param float[]|null $boostTemplates Array of key values, key is the template name, value the
	 *     boost factor. Defaults to Util::getDefaultBoostTemplates()
	 */
	public function __construct( $boostTemplates = null ) {
		$this->boostTemplates = $boostTemplates === null ? Util::getDefaultBoostTemplates() : $boostTemplates;
	}

	/**
	 * @inheritDoc
	 */
	public function score( array $doc ) {
		return intval( $this->intermediateScore( $doc ) * self::SCORE_RANGE );
	}

	protected function intermediateScore( array $doc ) {
		$incLinks = $this->scoreNormLog2( $doc['incoming_links'] ?? 0,
			$this->incomingLinksNorm );
		$pageSize = $this->scoreNormLog2( $doc['text_bytes'] ?? 0,
			self::PAGE_SIZE_NORM );
		$extLinks = $this->scoreNorm( isset( $doc['external_link'] )
			? count( $doc['external_link'] ) : 0, self::EXTERNAL_LINKS_NORM );
		$headings = $this->scoreNorm( isset( $doc['heading'] )
			? count( $doc['heading'] ) : 0, self::HEADING_NORM );
		$redirects = $this->scoreNorm( isset( $doc['redirect'] )
			? count( $doc['redirect'] ) : 0, self::REDIRECT_NORM );

		$score = $incLinks * self::INCOMING_LINKS_WEIGHT;

		$score += $extLinks * self::EXTERNAL_LINKS_WEIGHT;
		$score += $pageSize * self::PAGE_SIZE_WEIGHT;
		$score += $headings * self::HEADING_WEIGHT;
		$score += $redirects * self::REDIRECT_WEIGHT;

		// We have a standardized composite score between 0 and 1
		$score /= self::INCOMING_LINKS_WEIGHT + self::EXTERNAL_LINKS_WEIGHT +
				self::PAGE_SIZE_WEIGHT + self::HEADING_WEIGHT + self::REDIRECT_WEIGHT;

		return $this->boostTemplates( $doc, $score );
	}

	/**
	 * log2( ( value / norm ) + 1 ) => [0-1]
	 *
	 * @param float $value
	 * @param float $norm
	 * @return float between 0 and 1
	 */
	public function scoreNormLog2( $value, $norm ) {
		return log( $value > $norm ? 2 : ( $value / $norm ) + 1, 2 );
	}

	/**
	 * value / norm => [0-1]
	 *
	 * @param float $value
	 * @param float $norm
	 * @return float between 0 and 1
	 */
	public function scoreNorm( $value, $norm ) {
		return $value > $norm ? 1 : $value / $norm;
	}

	/**
	 * Modify an existing score based on templates contained
	 * by the document.
	 *
	 * @param array $doc Document score is generated for
	 * @param float $score Current score between 0 and 1
	 * @return float Score after boosting templates
	 */
	public function boostTemplates( array $doc, $score ) {
		if ( !isset( $doc['template'] ) ) {
			return $score;
		}

		if ( $this->boostTemplates ) {
			$boost = 1;
			// compute the global boost
			foreach ( $this->boostTemplates as $k => $v ) {
				if ( in_array( $k, $doc['template'] ) ) {
					$boost *= $v;
				}
			}
			if ( $boost != 1 ) {
				return $this->boost( $score, $boost );
			}
		}
		return $score;
	}

	/**
	 * Boost the score :
	 *   boost value lower than 1 will decrease the score
	 *   boost value set to 1 will keep the score unchanged
	 *   boost value greater than 1 will increase the score
	 *
	 * score = 0.5, boost = 0.5 result is 0.375
	 * score = 0.1, boost = 2 result is 0.325
	 *
	 * @param float $score
	 * @param float $boost
	 * @return float adjusted score
	 */
	public function boost( $score, $boost ) {
		if ( $boost == 1 ) {
			return $score;
		}

		// Transform the boost to a value between -1 and 1
		$boost = $boost > 1 ? 1 - ( 1 / $boost ) : - ( 1 - $boost );
		// @todo: the 0.5 ratio is hardcoded we could maybe allow customization
		// here, this would be a way to increase the impact of template boost
		if ( $boost > 0 ) {
			return $score + ( ( ( 1 - $score ) / 2 ) * $boost );
		} else {
			return $score + ( ( $score / 2 ) * $boost );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredFields() {
		return [
			'incoming_links',
			'external_link',
			'text_bytes',
			'heading',
			'redirect',
			'template',
		];
	}

	/**
	 * @param int $maxDocs
	 */
	public function setMaxDocs( $maxDocs ) {
		$this->maxDocs = $maxDocs;
		// We normalize incoming links according to the size of the index
		$this->incomingLinksNorm = (int)( $maxDocs * self::INCOMING_LINKS_MAX_DOCS_FACTOR );
		if ( $this->incomingLinksNorm < 1 ) {
			// it's a very small wiki let's force the norm to 1
			$this->incomingLinksNorm = 1;
		}
	}

	/**
	 * Explain the score
	 * @param array $doc
	 * @return array
	 */
	public function explain( array $doc ) {
		$intermediateExplain = $this->intermediateExplain( $doc );
		return [
			'value' => (int)( $intermediateExplain['value'] * self::SCORE_RANGE ),
			'description' => 'Convert to an integer score: ' . $intermediateExplain['value'] . ' * ' . self::SCORE_RANGE,
			'details' => [ 'normalized_score' => $intermediateExplain ]
		];
	}

	/**
	 * @param array $doc
	 * @return array
	 */
	protected function intermediateExplain( array $doc ) {
		$incLinks = $this->explainScoreNormLog2( $doc['incoming_links'] ?? 0,
			$this->incomingLinksNorm, 'incoming_links' );
		$pageSize = $this->explainScoreNormLog2( $doc['text_bytes'] ?? 0,
			self::PAGE_SIZE_NORM, 'text_bytes' );
		$extLinks = $this->explainScoreNorm( isset( $doc['external_link'] )
			? count( $doc['external_link'] ) : 0, self::EXTERNAL_LINKS_NORM, 'external_links_count' );
		$headings = $this->explainScoreNorm( isset( $doc['heading'] )
			? count( $doc['heading'] ) : 0, self::HEADING_NORM, 'headings_count' );
		$redirects = $this->explainScoreNorm( isset( $doc['redirect'] )
			? count( $doc['redirect'] ) : 0, self::REDIRECT_NORM, 'redirects_count' );

		$details = [];
		$total = self::INCOMING_LINKS_WEIGHT + self::EXTERNAL_LINKS_WEIGHT +
				 self::PAGE_SIZE_WEIGHT + self::HEADING_WEIGHT + self::REDIRECT_WEIGHT;
		$details['incoming_links_weighted'] = $this->explainWeight( $incLinks, self::INCOMING_LINKS_WEIGHT,
			$total, 'incoming_links_normalized' );
		$details['external_links_weighted'] = $this->explainWeight( $extLinks, self::EXTERNAL_LINKS_WEIGHT,
			$total, 'external_links_count_normalized' );
		$details['text_bytes_weighted'] = $this->explainWeight( $pageSize, self::PAGE_SIZE_WEIGHT,
			$total, 'text_bytes_normalized' );
		$details['headings_count_weighted'] = $this->explainWeight( $headings, self::HEADING_WEIGHT,
			$total, 'headings_count_normalized' );
		$details['redirects_count_weighted'] = $this->explainWeight( $redirects, self::REDIRECT_WEIGHT,
			$total, 'redirects_count_normalized' );

		$score = 0;
		foreach ( $details as $detail ) {
			$score += $detail['value'];
		}
		$metadataExplain = [
			'value' => $score,
			'description' => 'weighted sum of document metadata',
			'details' => $details
		];

		if ( $this->boostTemplates ) {
			return $this->explainBoostTemplates( $metadataExplain, $doc );
		}
		return $metadataExplain;
	}

	/**
	 * @param array $doc
	 * @return array
	 */
	private function explainTemplateBoosts( array $doc ) {
		if ( !isset( $doc['template'] ) ) {
			return [
				'value' => 1,
				'description' => 'No templates'
			];
		}

		if ( $this->boostTemplates ) {
			$details = [];
			$boost = 1;
			// compute the global boost
			foreach ( $this->boostTemplates as $k => $v ) {
				if ( in_array( $k, $doc['template'] ) ) {
					$details["$k: boost for " . $v] = [
						'value' => $v,
						'description' => $k
					];
					$boost *= $v;
				}
			}
			if ( $details !== [] ) {
				return [
					'value' => $boost,
					'description' => 'Product of all template boosts',
					'details' => $details
				];
			}
			return [
				'value' => 1,
				'description' => "No templates match any boosted templates"
			];
		} else {
			return [
				'value' => 1,
				'description' => "No configured boosted templates"
			];
		}
	}

	/**
	 * @param array $metadataExplain
	 * @param array $doc
	 * @return array
	 */
	private function explainBoostTemplates( array $metadataExplain, array $doc ) {
		$boostExplain = $this->explainTemplateBoosts( $doc );
		$score = $metadataExplain['value'];
		$boost = $boostExplain['value'];
		$boostExplain = [
			'value' => $boost > 1 ? 1 - ( 1 / $boost ) : - ( 1 - $boost ),
			'description' => ( $boost > 1 ? "1-(1/boost)" : "-(1-boost)" ) . "; boost = $boost",
			'details' => [ 'template_boosts' => $boostExplain ]
		];
		$boost = $boostExplain['value'];

		if ( $boost > 0 ) {
			return [
				'value' => $score + ( ( ( 1 - $score ) / 2 ) * $boost ),
				'description' => "score + (((1-score)/2)*boost); score = $score, boost = $boost",
				'details' => [ $metadataExplain, $boostExplain ]
			];
		} else {
			return [
				'value' => $score + ( ( $score / 2 ) * $boost ),
				'description' => "score+(((1-score)/2)*boost); score = $score, boost = $boost",
				'details' => [ 'score' => $metadataExplain, 'boost' => $boostExplain ]
			];
		}
	}

	/**
	 * @param float|int $value
	 * @param float|int $norm
	 * @param string $valueName
	 * @return array
	 */
	private function explainScoreNormLog2( $value, $norm, $valueName ) {
		$score = $this->scoreNormLog2( $value, $norm );
		return [
			'value' => $score,
			'description' => "log₂((min($valueName,max)/max)+1); $valueName = $value, max = $norm",
		];
	}

	/**
	 * @param int|float $value
	 * @param int|float $norm
	 * @param string $valueName
	 * @return array
	 */
	private function explainScoreNorm( $value, $norm, $valueName ) {
		$score = $this->scoreNorm( $value, $norm );
		return [
			'value' => $score,
			'description' => "min($valueName,max)/max; $valueName = $value, max = $norm",
		];
	}

	/**
	 * @param array $detail
	 * @param float $weight
	 * @param float $allWeights
	 * @param string $valueName
	 * @return array
	 */
	protected function explainWeight( array $detail, $weight, $allWeights, $valueName ) {
		$value = $detail['value'];
		return [
			'value' => $value * $weight / $allWeights,
			'description' => "$valueName*weight/total; $valueName = $value, weight = $weight, total = $allWeights",
			'details' => [ $valueName => $detail ]
		];
	}
}
