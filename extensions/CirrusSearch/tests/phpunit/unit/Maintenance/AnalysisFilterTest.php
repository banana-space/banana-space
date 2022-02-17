<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Maintenance\AnalysisFilter
 */
class AnalysisFilterTest extends CirrusTestCase {
	public static function usedAnalyzersProvider() {
		return [
			'empty' => [ [], [] ],
			'type with no properties' => [ [], [
				'example_type' => [
					'properties' => [],
				],
			] ],
			'read field analyzer' => [ [ 'hello' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'analyzer' => 'hello'
						],
					],
				],
			] ],
			'read field search analyzer' => [ [ 'world' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'analyzer' => 'world'
						],
					],
				],
			] ],
			'read subfield analyzer' => [ [ 'analysis' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'fields' => [
								'my_subfield' => [
									'analyzer' => 'analysis',
								]
							]
						],
					],
				],
			] ],
			'read subfield search_analyzer' => [ [ 'chains' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'fields' => [
								'my_subfield' => [
									'search_analyzer' => 'chains',
								]
							]
						],
					],
				],
			] ],
			'read subproperty analyzer' => [ [ 'could be' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'properties' => [
								'my_subfield' => [
									'analyzer' => 'could be',
								]
							]
						],
					],
				],
			] ],
			'read subproperty analyzer' => [ [ 'filtered' ], [
				'example_type' => [
					'properties' => [
						'title' => [
							'properties' => [
								'my_subfield' => [
									'search_analyzer' => 'filtered',
								]
							]
						],
					],
				],
			] ],
			'properties with sub fields' => [
				[ 'text', 'text_search', 'aa_plain', 'aa_plain_search', 'ab_plain', 'ab_plain_search' ],
				[
					'my_type' => [
						'properties' => [
							'title' => [
								'analyzer' => 'text',
								'search_analyzer' => 'text_search',
							],
							'labels' => [
								'properties' => [
									'aa' => [
										'type' => 'text',
										'index' => false,
										'fields' => [
											'plain' => [
												'analyzer' => 'aa_plain',
												'search_analyzer' => 'aa_plain_search',
											],
										],
									],
									'ab' => [
										'type' => 'text',
										'index' => false,
										'fields' => [
											'plain' => [
												'analyzer' => 'ab_plain',
												'search_analyzer' => 'ab_plain_search',
											],
										],
									],
								],
							],
						],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider usedAnalyzersProvider
	 */
	public function testFindUsedAnalyzersInMappings( $analyzerNames, $mappings ) {
		$filter = new AnalysisFilter();
		$found = $filter->findUsedAnalyzersInMappings( $mappings )->values();

		asort( $analyzerNames );
		asort( $found );
		$this->assertEquals( array_values( $analyzerNames ), array_values( $found ) );
	}

	/**
	 * @dataProvider usedAnalyzersProvider
	 */
	public function testPushAnalyzerAliasesIntoMappings( $analyzerNames, $mappings ) {
		$aliases = array_combine( $analyzerNames, array_map( 'strrev', $analyzerNames ) );
		$filter = new AnalysisFilter();
		$updated = $filter->pushAnalyzerAliasesIntoMappings( $mappings, $aliases );
		$found = $filter->findUsedAnalyzersInMappings( $updated )->values();

		$expected = array_unique( array_values( $aliases ) );
		asort( $expected );
		asort( $found );
		$this->assertEquals( $expected, $found );
	}

	public static function filterUnusedProvider() {
		return [
			'empty' => [
				[ 'analyzer' => [] ], [], [ 'analyzer' => [] ]
			],
			'doesnt remove used analyzer' => [ [
					'analyzer' => [ 'still here' ],
				],
				[ 'still here' ],
				[
					'analyzer' => [
						'still here' => [],
					],
				],
			],
			'removes unused analyzer' => [ [
					'analyzer' => [ 'still here' ],
				],
				[ 'still here' ],
				[
					'analyzer' => [
						'still here' => [],
						'missing' => [],
					],
				],
			],
			'removes unused filter/char_filter/tokenizer' => [ [
					'analyzer' => [ 'still here' ],
					'filter' => [],
					'char_filter' => [],
					'tokenizer' => [],
				],
				[ 'still here' ],
				[
					'analyzer' => [
						'still here' => [],
					],
					'filter' => [
						'not me' => [],
					],
					'char_filter' => [
						'or me' => [],
					],
					'tokenizer' => [
						'me either' => [],
					],
				],
			],
			'keeps used filter/char_filter/tokenizer' => [ [
					'analyzer' => [ 'still here' ],
					'filter' => [ 'and me' ],
					'char_filter' => [ 'reporting' ],
					'tokenizer' => [ 'rainbows' ],
				],
				[ 'still here' ],
				[
					'analyzer' => [
						'still here' => [
							'tokenizer' => 'rainbows',
							'filter' => [ 'and me' ],
							'char_filter' => [ 'reporting' ],
						],
					],
					'filter' => [
						'and me' => [],
					],
					'char_filter' => [
						'reporting' => [],
					],
					'tokenizer' => [
						'rainbows' => [],
					],
				],
			],
			'removes items referenced by removed analyzer' => [ [
					'analyzer' => [ 'still here' ],
					'filter' => [],
					'char_filter' => [],
					'tokenizer' => [],
				],
				[ 'still here' ],
				[
					'analyzer' => [
						'still here' => [],
						'delete me' => [
							'tokenizer' => 'rainbows',
							'filter' => [ 'and me' ],
							'char_filter' => [ 'reporting' ],
						],
					],
					'filter' => [
						'and me' => [],
					],
					'char_filter' => [
						'reporting' => [],
					],
					'tokenizer' => [
						'rainbows' => [],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider filterUnusedProvider
	 */
	public function testFilterUnusedAnalysisChain( $expected, $usedAnalyzers, $analysis ) {
		$filter = new AnalysisFilter();
		$updated = $filter->filterUnusedAnalysisChain( $analysis, new Set( $usedAnalyzers ) );
		foreach ( $expected as $key => $values ) {
			if ( count( $values ) ) {
				$this->assertArrayHasKey( $key, $updated );
				$found = array_keys( $updated[$key] );
				$this->assertEquals( $values, $found, $key );
			} elseif ( isset( $updated[$key] ) ) {
				$this->assertCount( 0, $updated[$key] );
			} else {
				// silence risky test warning
				$this->assertArrayNotHasKey( $key, $updated );
			}
		}
	}

	public static function deduplicateProvider() {
		return [
			'empty' => [ [
				], [
					'analyzer' => [],
				]
			],
			'simple example' => [ [
					'a' => 'a',
					'b' => 'a',
				], [
					'analyzer' => [
						'a' => [
							'tokenizer' => 'whitespace',
						],
						'b' => [
							'tokenizer' => 'whitespace',
						],
					],
				],
			],
			'deduplication is stable (part 1)' => [ [
					'a' => 'a',
					'b' => 'a',
				], [
					'analyzer' => [
						'a' => [ 'foo' => 'bar' ],
						'b' => [ 'foo' => 'bar' ],
					]
				],
			],
			'deduplication is stable (part 2)' => [ [
					'a' => 'a',
					'b' => 'a',
				], [
					'analyzer' => [
						'b' => [ 'foo' => 'bar' ],
						'a' => [ 'foo' => 'bar' ],
					]
				],
			],
			'filter and char_filter order is respected' => [ [
					'a' => 'a',
					'b' => 'b',
					'c' => 'c',
					'd' => 'd',
				], [
					'analyzer' => [
						'a' => [
							'filter' => [ 'filter_a', 'filter_b' ],
						],
						'b' => [
							'filter' => [ 'filter_b', 'filter_a' ],
						],
						'c' => [
							'char_filter' => [ 'char_filter_b', 'char_filter_a' ],
						],
						'd' => [
							'char_filter' => [ 'char_filter_a', 'char_filter_b' ],
						],
					],
					'char_filter' => [
						'char_filter_a' => [ 'foo' => 'bar' ],
						'char_filter_b' => [ 'bar' => 'foo' ],
					],
					'filter' => [
						'filter_a' => [ 'foo' => 'bar' ],
						'filter_b' => [ 'bar' => 'foo' ],
					],
				]
			],
			'applies deduplication at multiple levels' => [ [
					'a' => 'a',
					'b' => 'a',
					'c' => 'c',
				], [
					'analyzer' => [
						'a' => [
							'tokenizer' => 'foo',
							'filter' => [ 'too many' ],
							'char_filter' => [ 'some_filter_a', 'unrelated' ],
						],
						'b' => [
							'char_filter' => [ 'some_filter_b', 'unrelated' ],
							'tokenizer' => 'bar',
							'filter' => [ 'random strings' ],
						],
						'c' => [
							'char_filter' => [ 'some_filter_b' ],
							'tokenizer' => 'bar',
							'filter' => [ 'random strings' ],
						],
					],
					'tokenizer' => [
						'foo' => [
							'looks' => 'the same',
							'but in' => 'different order',
						],
						'bar' => [
							'but in' => 'different order',
							'looks' => 'the same',
						],
					],
					'char_filter' => [
						'unrelated' => [ 'other' => 'things' ],
						'some_filter_a' => [ 'qwerty' => 'azerty' ],
						'some_filter_b' => [ 'qwerty' => 'azerty' ],
					],
					'filter' => [
						'too many' => [ 'things'  => 'to write' ],
						'random strings' => [ 'things' => 'to write' ],
					],
				],
			],
		];
	}

	/**
	 * @dataProvider deduplicateProvider
	 */
	public function testDeduplicateAnalysisConfig( $expected, $analysis ) {
		$filter = new AnalysisFilter();
		$aliases = $filter->deduplicateAnalysisConfig( $analysis );
		$this->assertEquals( $expected, $aliases );
	}

	/**
	 * @covers \CirrusSearch\Maintenance\AnalysisFilter::filterAnalysis
	 */
	public function testPrimaryEntrypoint() {
		$filter = new AnalysisFilter();
		$initialAnalysis = [
			'filter' => [
				'icu_normalizer' => [],
			],
			'char_filter' => [
				'word_break_helper' => [],
			],
			'tokenizer' => [],
			'analyzer' => [
				'aa_plain' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'char_filter' => [ 'word_break_helper' ],
					'filter' => [ 'icu_normalizer' ],
				],
				'aa_plain_search' => [
					'tokenizer' => 'snowball',
				],
				'ab_plain' => [
					'type' => 'custom',
					'tokenizer' => 'standard',
					'char_filter' => [ 'word_break_helper' ],
					'filter' => [ 'icu_normalizer' ],
				],
				'ab_plain_search' => [
					'tokenizer' => 'snowball',
				],
				'text' => [
					'tokenizer' => 'whitespace',
				],
				'text_search' => [
					'tokenizer' => 'whitespace',
				],
			],
		];
		$initialMappings = [
			'my_type' => [
				'properties' => [
					'title' => [
						'analyzer' => 'text',
						'search_analyzer' => 'text_search',
					],
					'labels' => [
						'properties' => [
							'aa' => [
								'type' => 'text',
								'index' => false,
								'fields' => [
									'plain' => [
										'analyzer' => 'aa_plain',
										'search_analyzer' => 'aa_plain_search',
									],
								],
							],
							'ab' => [
								'type' => 'text',
								'index' => false,
								'fields' => [
									'plain' => [
										'analyzer' => 'ab_plain',
										'search_analyzer' => 'ab_plain_search',
									],
								],
							],
						],
					],
				],
			],
		];
		list( $analysis, $mappings ) = $filter->filterAnalysis(
			$initialAnalysis, $initialMappings, true );

		$this->assertArrayHasKey( 'aa', $mappings['my_type']['properties']['labels']['properties'] );
		$this->assertArrayHasKey( 'ab', $mappings['my_type']['properties']['labels']['properties'] );

		$debug = print_r( $analysis['analyzer'], true );
		$this->assertArrayHasKey( 'aa_plain', $analysis['analyzer'], $debug );
		$this->assertArrayNotHasKey( 'ab_plain', $analysis['analyzer'], $debug );
		$this->assertArrayHasKey( 'text', $analysis['analyzer'], $debug );
		$this->assertArrayNotHasKey( 'text_search', $analysis['analyzer'], $debug );

		$debug = print_r( $mappings['my_type']['properties'], true );
		$title = $mappings['my_type']['properties']['title'];
		$this->assertEquals( 'text', $title['analyzer'], $debug );
		$this->assertEquals( 'text', $title['search_analyzer'], $debug );

		$aa = $mappings['my_type']['properties']['labels']['properties']['aa']['fields']['plain'];
		$this->assertEquals( 'aa_plain', $aa['analyzer'], $debug );
		$this->assertEquals( 'aa_plain_search', $aa['search_analyzer'], $debug );

		$ab = $mappings['my_type']['properties']['labels']['properties']['ab']['fields']['plain'];
		$this->assertEquals( 'aa_plain', $ab['analyzer'], $debug );
		$this->assertEquals( 'aa_plain_search', $ab['search_analyzer'], $debug );
	}
}
