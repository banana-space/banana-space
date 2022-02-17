<?php

namespace CirrusSearch;

use CirrusSearch\BuildDocument\Completion\IncomingLinksScoringMethod;
use CirrusSearch\BuildDocument\Completion\PQScore;
use CirrusSearch\BuildDocument\Completion\QualityScore;

/**
 * test suggest scoring functions.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @group CirrusSearch
 * @group Standalone
 */
class SuggestScoringTest extends CirrusTestCase {
	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 */
	public function testQualityScoreNormFunctions() {
		$qs = new QualityScore( [] );
		$qs->setMaxDocs( 10000 );
		for ( $i = 0; $i < 1000; $i++ ) {
			$value = mt_rand( 0, 1000000 );
			$norm = mt_rand( 1, 1000000 );
			$score = $qs->scoreNorm( $value, $norm );
			$this->assertLessThanOrEqual( 1, $score,
				"scoreNorm cannot produce a score greater than 1" );
			$this->assertGreaterThanOrEqual( 0, $score,
				"scoreNorm cannot produce a score lower than 0" );

			$score = $qs->scoreNormLog2( $value, $norm );
			$this->assertLessThanOrEqual( 1, $score,
				"scoreNormLog2 cannot produce a score greater than 1" );
			$this->assertGreaterThanOrEqual( 0, $score,
				"scoreNormLog2 cannot produce a score lower than 0" );
		}

		// Edges
		$score = $qs->scoreNorm( 1, 1 );
		$this->assertLessThanOrEqual( 1, $score,
			"scoreNorm cannot produce a score greater than 1" );
		$this->assertGreaterThanOrEqual( 0, $score,
			"scoreNorm cannot produce a score lower than 0" );

		$score = $qs->scoreNorm( 0, 1 );
		$this->assertLessThanOrEqual( 1, $score,
			"scoreNorm cannot produce a score greater than 1" );
		$this->assertGreaterThanOrEqual( 0, $score,
			"scoreNorm cannot produce a score lower than 0" );

		$score = $qs->scoreNormLog2( 1, 1 );
		$this->assertLessThanOrEqual( 1, $score,
			"scoreNormLog2 cannot produce a score greater than 1" );
		$this->assertGreaterThanOrEqual( 0, $score,
			"scoreNormLog2 cannot produce a score lower than 0" );

		$score = $qs->scoreNormLog2( 0, 1 );
		$this->assertLessThanOrEqual( 1, $score,
			"scoreNormLog2 cannot produce a score greater than 1" );
		$this->assertGreaterThanOrEqual( 0, $score,
			"scoreNormLog2 cannot produce a score lower than 0" );
	}

	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 */
	public function testQualityScoreBoostFunction() {
		$qs = new QualityScore( [] );
		for ( $i = 0; $i < 1000; $i++ ) {
			$score = (float)mt_rand() / (float)mt_getrandmax();
			$boost = (float)mt_rand( 0, 10000 ) / mt_rand( 1, 10000 );
			$res = $qs->boost( $score, $boost );
			$this->assertLessThanOrEqual( 1, $score,
				"boost cannot produce a score greater than 1" );
			$this->assertGreaterThanOrEqual( 0, $score,
				"boost cannot produce a score lower than 0" );
			if ( $boost > 1 ) {
				$this->assertGreaterThan( $score, $res, "With a boost ($boost) greater than 1 the" .
					" boosted score must be greater than the original." );
			} elseif ( $boost < 1 ) {
				$this->assertLessThan( $score, $res, "With a boost ($boost) less than 1 the " .
					"boosted score must be less than the original." );
			} else {
				$this->assertEquals( $score, $res, "When boost is 1 the score remains unchanged." );
			}
		}
		for ( $i = 1; $i < 1000; $i++ ) {
			// The same boost value must keep original score ordering
			$score1 = 0.1;
			$score2 = 0.5;

			$boost = $i;

			$res1 = $qs->boost( $score1, $boost );
			$res2 = $qs->boost( $score2, $boost );

			$this->assertGreaterThan( $res1, $res2, "A boost cannot 'overboost' a score" );
			$res1 = $qs->boost( $score1, (float)1 / (float)$boost );
			$res2 = $qs->boost( $score2, (float)1 / (float)$boost );
			$this->assertGreaterThan( $res1, $res2, "A boost cannot 'overboost' a score" );
		}

		// Edges
		$res = $qs->boost( 1, 1 );
		$this->assertEquals( $res, 1, "When boost is 1 the score remains unchanged." );
		$res = $qs->boost( 1, 0 );
		$this->assertEquals( $res, 0.5, "When boost is 0 the score is divided by 2." );
		$res = $qs->boost( 1,  2 ^ 31 - 1 );
		$this->assertEquals( $res, 1,
			"When score is 1 and boost is very high the score is still 1." );
		$res = $qs->boost( 0,  0 );
		$this->assertEquals( $res, 0, "When score is 0 and boost is 0 the score is still 0." );
	}

	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 */
	public function testQualityScoreBoostTemplates() {
		$goodDoc = [
			'template' => [ 'Good' ]
		];

		$badDoc = [
			'template' => [ 'Bad' ]
		];

		$mixedDoc = [
			'template' => [ 'Good', 'Bad' ]
		];

		$neutralDoc = [
			'template' => [ 'Neutral' ]
		];

		$qs = new QualityScore( [ 'Good' => 2, 'Bad' => 0.5 ] );

		$score = 0.5;
		$res = $qs->boostTemplates( $goodDoc, $score );
		$this->assertGreaterThan( $score, $res, "A good doc gets a better score" );

		$res = $qs->boostTemplates( $badDoc, $score );
		$this->assertLessThan( $score, $res, "A bad doc gets a lower score" );

		$res = $qs->boostTemplates( $mixedDoc, $score );
		$this->assertEquals( $score, $res, "A mixed doc gets the same score" );

		$res = $qs->boostTemplates( $neutralDoc, $score );
		$this->assertEquals( $res, $score, "A neutral doc gets the same score" );
	}

	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 */
	public function testQualityScoreRanking() {
		$maxDocs = 10000000;
		$qs = new QualityScore( [ 'Good' => 2, 'Bad' => 0.5 ] );
		$qs->setMaxDocs( $maxDocs );
		$veryGoodArticle = [
			'incoming_links' => 120340,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => '230000',
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Good' ]
		];

		$goodArticle = [
			'incoming_links' => 120340,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => '230000',
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => []
		];

		$goodButBadArticle = [
			'incoming_links' => 120340,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => '230000',
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Bad' ]
		];

		$this->assertLessThan( $qs->score( $veryGoodArticle ), $qs->score( $goodArticle ),
			"Same values but a boosted template give a better score" );
		$this->assertLessThan( $qs->score( $goodArticle ), $qs->score( $goodButBadArticle ),
			"Same values but without a negative boosted template give a better score" );

		$page1 = [
			'incoming_links' => $maxDocs * QualityScore::INCOMING_LINKS_MAX_DOCS_FACTOR,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => '230000',
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Good' ]
		];

		$page2 = [
			'incoming_links' => $maxDocs * QualityScore::INCOMING_LINKS_MAX_DOCS_FACTOR + 1,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => '230000',
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Good' ]
		];
		$this->assertEquals( $qs->score( $page1 ), $qs->score( $page2 ),
			"Having more incoming links than the norm give the same score" );

		$page1 = [
			'incoming_links' => $maxDocs * QualityScore::INCOMING_LINKS_MAX_DOCS_FACTOR,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => QualityScore::PAGE_SIZE_NORM,
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Good' ]
		];

		$page2 = [
			'incoming_links' => $maxDocs * QualityScore::INCOMING_LINKS_MAX_DOCS_FACTOR,
			'external_link' => array_fill( 0, 200, null ),
			'text_bytes' => QualityScore::PAGE_SIZE_NORM + 1,
			'heading' => array_fill( 0, 30, null ),
			'redirect' => array_fill( 0, 100, null ),
			'template' => [ 'Good' ]
		];

		$this->assertEquals( $qs->score( $page1 ), $qs->score( $page2 ),
			"Having more text_bytes than the norm give the same score" );
	}

	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 */
	public function testQualityScoreWithRandomValues() {
		$maxDocs = 10000000;
		$qs = new QualityScore( [ 'Good' => 2, 'Bad' => 0.5 ] );
		$qs->setMaxDocs( $maxDocs );

		for ( $i = 0; $i < 1000; $i++ ) {
			$page = [
				'incoming_links' => mt_rand( 0, 2 ^ 31 - 1 ),
				'external_link' => array_fill( 0, mt_rand( 1, 2000 ), null ),
				'text_bytes' => mt_rand( 1, 400000 ),
				'heading' => array_fill( 0, mt_rand( 1, 1000 ), null ),
				'redirect' => array_fill( 0, mt_rand( 1, 1000 ), null ),
				'template' => mt_rand( 0, 1 ) == 1 ? [ 'Good' ] : [ 'Bad' ]
			];
			$this->assertGreaterThan( 0, $qs->score( $page ), "Score is always greater than 0" );
			$this->assertLessThan( QualityScore::SCORE_RANGE, $qs->score( $page ),
				"Score is always lower than " . QualityScore::SCORE_RANGE );
			$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );
		}

		// Edges
		$page = [
			'incoming_links' => $maxDocs * QualityScore::INCOMING_LINKS_MAX_DOCS_FACTOR,
			'external_link' => array_fill( 0, QualityScore::EXTERNAL_LINKS_NORM, null ),
			'text_bytes' => QualityScore::PAGE_SIZE_NORM,
			'heading' => array_fill( 0, QualityScore::HEADING_NORM, null ),
			'redirect' => array_fill( 0, QualityScore::REDIRECT_NORM, null ),
			'template' => []
		];
		$this->assertEquals( QualityScore::SCORE_RANGE, $qs->score( $page ),
			"Highest score is " . QualityScore::SCORE_RANGE );
		$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );

		$page = [
			'incoming_links' => 0,
			'external_link' => [],
			'text_bytes' => 0,
			'heading' => [],
			'redirect' => [],
			'template' => []
		];
		$this->assertSame( 0, $qs->score( $page ), "Lowest score is 0" );
		$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );

		$page = [];
		$this->assertSame( 0, $qs->score( $page ), "Score of a broken article is 0" );
		$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );

		// A very small wiki
		$qs = new QualityScore( [] );
		$qs->setMaxDocs( 1 );
		$page = [
			'incoming_links' => 1,
			'external_link' => array_fill( 0, QualityScore::EXTERNAL_LINKS_NORM, null ),
			'text_bytes' => QualityScore::PAGE_SIZE_NORM,
			'heading' => array_fill( 0, QualityScore::HEADING_NORM, null ),
			'redirect' => array_fill( 0, QualityScore::REDIRECT_NORM, null ),
			'template' => []
		];
		$this->assertEquals( QualityScore::SCORE_RANGE, $qs->score( $page ),
			"With very small wiki the highest score is also " . QualityScore::SCORE_RANGE );
		$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );

		// The scoring function should not fail with 0 page
		$qs = new QualityScore( [] );
		$page = [
			'incoming_links' => 1,
			'external_link' => array_fill( 0, QualityScore::EXTERNAL_LINKS_NORM, null ),
			'text_bytes' => QualityScore::PAGE_SIZE_NORM,
			'heading' => array_fill( 0, QualityScore::HEADING_NORM, null ),
			'redirect' => array_fill( 0, QualityScore::REDIRECT_NORM, null ),
			'template' => []
		];
		$this->assertEquals( QualityScore::SCORE_RANGE, $qs->score( $page ),
			"With a zero page wiki the highest score is also " . QualityScore::SCORE_RANGE );
		$this->assertEqualsWithDelta( $qs->explain( $page )['value'], $qs->score( $page ),
			2, "Explanation matches" );
	}

	/**
	 * @covers \CirrusSearch\BuildDocument\Completion\PQScore
	 * @covers \CirrusSearch\BuildDocument\Completion\QualityScore
	 * @covers \CirrusSearch\BuildDocument\Completion\IncomingLinksScoringMethod
	 */
	public function testRobustness() {
		$templates = [ 'Good' => 2, 'Bad' => 0.5 ];
		$all_templates = array_keys( $templates );
		$all_templates += [ 'Foo', 'Bar' ];
		for ( $i = 0; $i < 5000; $i++ ) {
			$scorers = [];
			$scorers[] = new PQScore( [ 'Good' => 2, 'Bad' => 0.5 ] );
			$scorers[] = new QualityScore( [ 'Good' => 2, 'Bad' => 0.5 ] );
			$scorers[] = new IncomingLinksScoringMethod();
			$tmpl = [];
			for ( $j = mt_rand( 0, count( $all_templates ) - 1 ); $j >= 0; $j-- ) {
				$tmpl[] = $all_templates[$j];
			}
			$page = [];
			$page['incoming_links'] = mt_rand( 0, 1 ) ? mt_rand( 0, 200 ) : null;
			$page['external_link'] = $this->randomArray( 200 );
			$page['text_bytes'] = mt_rand( 0, 1 ) ? (string)mt_rand( 0, 230000 ) : null;
			$page['heading'] = $this->randomArray( 30 );
			$page['redirect'] = $this->randomArray( 100 );
			$page['popularity_score'] = mt_rand( 0, 1 ) ? 1 / mt_rand( 1, 1800000 ) : null;
			$page['templates'] = mt_rand( 0, 1 ) ? $tmpl : null;

			$maxDocs = mt_rand( 0, 100 );
			foreach ( $scorers as $scorer ) {
				$scorer->setMaxDocs( $maxDocs );
				$score = $scorer->score( $page );
				$pagedebug = print_r( $page, true );

				$this->assertIsInt( $score, 'Score is always an integer for ' .
					get_class( $scorer ) . " with these values $pagedebug" );
				$this->assertGreaterThanOrEqual( 0, $score, 'Score is always positive ' .
					get_class( $scorer ) . " with these values $pagedebug" );
				$this->assertLessThanOrEqual( QualityScore::SCORE_RANGE, $score,
					"Score is always lower than QualityScore::SCORE_RANGE " . get_class( $scorer ) .
					" with these values $pagedebug" );
				$this->assertEqualsWithDelta( $scorer->explain( $page )['value'], $scorer->score( $page ),
					2, get_class( $scorer ) . " : explain gives same score with these values $pagedebug" );
			}
		}
	}

	/**
	 * @param int $max max element in the array
	 * @return array|null randomly null or an array of size [0, $max]
	 */
	private function randomArray( $max ) {
		if ( mt_rand( 0, 1 ) ) {
			$size = mt_rand( 0, $max );
			if ( $size === 0 ) {
				return [];
			}
			return array_fill( 0, $size, null );
		}
		return null;
	}
}
