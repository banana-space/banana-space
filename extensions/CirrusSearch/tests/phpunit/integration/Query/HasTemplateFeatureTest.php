<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;

/**
 * @covers \CirrusSearch\Query\HasTemplateFeature
 * @group CirrusSearch
 */
class HasTemplateFeatureTest extends CirrusIntegrationTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		return [
			'basic usage' => [
				[ 'match' => [
					'template' => [
						'query' => 'Template:Coord',
					],
				] ],
				[ 'templates' => [ 'Template:Coord' ],  'case_sensitive' => false ],
				'hastemplate:Coord',
			],
			'calling out Template NS directly' => [
				[ 'match' => [
					'template' => [
						'query' => 'Template:Coord',
					],
				] ],
				[ 'templates' => [ 'Template:Coord' ], 'case_sensitive' => false ],
				'hastemplate:Template:Coord',
			],
			'with namespace' => [
				[ 'match' => [
					'template' => [
						'query' => 'User talk:Zomg',
					],
				] ],
				[ 'templates' => [ 'User_talk:Zomg' ], 'case_sensitive' => false ],
				'hastemplate:User_talk:Zomg',
			],
			'using colon prefix to indicate NS_MAIN' => [
				[ 'match' => [
					'template' => [
						'query' => 'Main page',
					],
				] ],
				[ 'templates' => [ 'Main_page' ], 'case_sensitive' => false ],
				'hastemplate::Main_page',
			],
			'multiple templates' => [
				[
					'bool' => [
						'should' => [
							[
								'match' => [
									'template.keyword' => [
										'query' => 'Template:Coord',
									],
								],
							],
							[
								'match' => [
									'template.keyword' => [
										'query' => 'Template:Main Page',
									],
								],
							]
						]
					]
				],
				[ 'templates' => [ 'Template:Coord', 'Template:Main Page' ], 'case_sensitive' => true ],
				'hastemplate:"Coord|Main Page"',
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( array $expected, array $expectedParsedValue, $term ) {
		$feature = new HasTemplateFeature();
		$this->assertParsedValue( $feature, $term, $expectedParsedValue, [] );
		$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::allWikisStrategy() );
		$this->assertExpandedData( $feature, $term, [], [] );
		$this->assertFilter( $feature, $term, $expected, [] );
	}

	public function testParseLimit() {
		$feature = new HasTemplateFeature();
		$q = implode( '|', range( 1, HasTemplateFeature::MAX_CONDITIONS + 1 ) );
		$parsedValue = array_map(
			function ( $v ) {
				return "Template:$v";
			},
			range( 1, HasTemplateFeature::MAX_CONDITIONS )
		);
		$this->assertParsedValue( $feature, 'hastemplate:' . $q, [ 'templates' => $parsedValue, 'case_sensitive' => false ],
			[ [
				'cirrussearch-feature-too-many-conditions',
				'hastemplate',
				HasTemplateFeature::MAX_CONDITIONS
		] ] );
	}
}
