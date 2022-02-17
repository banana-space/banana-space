<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Query\ArticleTopicFeature
 * @group CirrusSearch
 */
class ArticleTopicFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function testGetTopicScores() {
		$rawTopicData = [ 'Culture.Visual arts.Visual arts*|123', 'History and Society.History|456' ];
		$topics = ArticleTopicFeature::getTopicScores( $rawTopicData );
		$this->assertSame( [ 'visual-arts' => 0.123, 'history' => 0.456 ], $topics );
	}

	public function parseProvider() {
		$term = function ( string $topic ) {
			return [
				'term' => [
					'ores_articletopics' => [
						'value' => $topic,
						'boost' => 1.0,
					],
				],
			];
		};
		$match = function ( array $query ) {
			return [ 'bool' => [ 'must' => [ $query ] ] ];
		};
		$filter = function ( array $query ) {
			return [ 'bool' => [
				'must' => [ [ 'match_all' => [] ] ],
				'filter' => [ [ 'bool' => [ 'must_not' => [ $query ] ] ] ],
			] ];
		};

		return [
			'basic search' => [
				'articletopic:stem',
				[ 'topics' => [ 'STEM.STEM*' ] ],
				$match( [
					'dis_max' => [
						'queries' => [ $term( 'STEM.STEM*' ) ],
					],
				] ),
			],
			'negated' => [
				'-articletopic:stem',
				[ 'topics' => [ 'STEM.STEM*' ] ],
				$filter( [
					'dis_max' => [
						'queries' => [ $term( 'STEM.STEM*' ) ],
					],
				] ),
			],
			'multiple topics' => [
				'articletopic:media|music',
				[ 'topics' => [ 'Culture.Media.Media*', 'Culture.Media.Music' ] ],
				$match( [
					'dis_max' => [
						'queries' => [
							$term( 'Culture.Media.Media*' ),
							$term( 'Culture.Media.Music' ),
						],
					],
				] ),
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( string $term, array $expectedParsedValue, array $expectedQuery ) {
		$config = new HashSearchConfig( [] );
		$context = new SearchContext( $config );
		$feature = new ArticleTopicFeature();

		$this->assertParsedValue( $feature, $term, $expectedParsedValue );
		$this->assertRemaining( $feature, $term, '' );
		$feature->apply( $context, $term );
		$actualQuery = $context->getQuery()->toArray();
		// MatchAll is converted to an stdClass instead of an array
		array_walk_recursive( $actualQuery, function ( &$node ) {
			if ( $node instanceof \stdClass ) {
				$node = [];
			}
		} );
		$this->assertSame( $expectedQuery, $actualQuery );
	}

	public function testParse_invalid() {
		$feature = new ArticleTopicFeature();
		$this->assertWarnings( $feature, [ [ 'cirrussearch-articletopic-invalid-topic',
			[ 'list' => [ 'foo' ], 'type' => 'comma' ], 1 ] ], 'articletopic:foo' );
		$this->assertNoResultsPossible( $feature, 'articletopic:foo' );
	}

}
