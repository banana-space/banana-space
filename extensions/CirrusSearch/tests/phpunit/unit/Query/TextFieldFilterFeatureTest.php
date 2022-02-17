<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\TextFieldFilterFeature
 * @group CirrusSearch
 */
class TextFieldFilterFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function testNothing() {
		$feature = new TextFieldFilterFeature( 'phpunit_keyword', 'phpunit_doc_field' );
		$this->assertNotConsumed( $feature, 'phpunit_keyword:' );
		$this->assertNotConsumed( $feature, 'unrelated:' );
	}

	public function parseProviderMime() {
		return [
			'mime match phrase' => [
				[
					'match_phrase' => [
						'file_mime' => 'image/png',
					]
				],
				'filemime:"image/png"',
			],
			'mime match' => [
				[
					'match' => [
						'file_mime' => [
							'query' => 'pdf',
							'operator' => 'AND'
						],
					]
				],
				'filemime:pdf',
			]
		];
	}

	/**
	 * @dataProvider parseProviderMime
	 */
	public function testParseMime( $expected, $term ) {
		$feature = new TextFieldFilterFeature( 'filemime', 'file_mime' );
		if ( $expected !== null ) {
			$this->assertParsedValue( $feature, $term, null, [] );
			$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::allWikisStrategy() );
			$this->assertExpandedData( $feature, $term, [], [] );
		}
		$this->assertFilter( $feature, $term, $expected, [] );
	}
}
