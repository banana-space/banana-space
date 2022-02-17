<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\LanguageFeature
 * @group CirrusSearch
 */
class LanguageFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function provideQueries() {
		$tooMany = array_map(
			function ( $l ) {
				return (string)$l;
			},
			range( 1, LanguageFeature::QUERY_LIMIT + 20 )
		);
		$actualLangs = array_slice( $tooMany, 0, LanguageFeature::QUERY_LIMIT );
		return [
			'simple' => [
				'inlanguage:fr',
				[ 'langs' => [ 'fr' ] ],
				[ 'match' => [ 'language' => [ 'query' => 'fr' ] ] ],
				[]
			],
			'multiple' => [
				'inlanguage:fr,en',
				[ 'langs' => [ 'fr', 'en' ] ],
				[ 'bool' => [ 'should' => [
					[ 'match' => [ 'language' => [ 'query' => 'fr' ] ] ],
					[ 'match' => [ 'language' => [ 'query' => 'en' ] ] ],
				] ] ],
				[]
			],
			'too many' => [
				'inlanguage:' . implode( ',', $tooMany ),
				[ 'langs' => $actualLangs ],
				[ 'bool' => [ 'should' => array_map(
					function ( $l ) {
						return [ 'match' => [ 'language' => [ 'query' => (string)$l ] ] ];
					},
					range( 1, LanguageFeature::QUERY_LIMIT )
				) ] ],
				[ [ 'cirrussearch-feature-too-many-conditions', 'inlanguage', LanguageFeature::QUERY_LIMIT ] ]
			],
		];
	}

	/**
	 * @dataProvider provideQueries()
	 * @param string $term
	 * @param array $expected
	 * @param $filter
	 * @param array $warnings
	 */
	public function testTooManyLanguagesWarning( $term, $expected, array $filter, $warnings ) {
		$feature = new LanguageFeature();
		$this->assertParsedValue( $feature, $term, $expected, $warnings );
		$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::allWikisStrategy() );
		$this->assertExpandedData( $feature, $term, [], [] );
		$this->assertWarnings( $feature, $warnings, $term );
		$this->assertFilter( $feature, $term, $filter, $warnings );
	}
}
