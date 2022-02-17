<?php

namespace CirrusSearch\Search\Fetch;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\CirrusTestCaseTrait;
use CirrusSearch\Parser\FullTextKeywordRegistry;
use CirrusSearch\Search\FullTextResultsType;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Searcher;
use Elastica\Query\MatchAll;

/**
 * @covers \CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder
 */
class FetchPhaseConfigBuilderTest extends CirrusTestCase {
	public function testNewHighlightField() {
		$configDef = $this->newHashSearchConfig( [ 'CirrusSearchUseExperimentalHighlighter' => false ] );
		$configExp = $this->newHashSearchConfig( [ 'CirrusSearchUseExperimentalHighlighter' => true ] );
		$builders = [ new FetchPhaseConfigBuilder( $configDef ), new FetchPhaseConfigBuilder( $configExp ) ];
		foreach ( $builders as $builder ) {
			/** @var BaseHighlightedField $field */
			$field = $builder->newHighlightField( 'myName', 'myTarget', 321 );
			$this->assertEquals( 'myName', $field->getFieldName() );
			$this->assertEquals( 'myTarget', $field->getTarget() );
			$this->assertEquals( 321, $field->getPriority() );
		}
	}

	public function provideNewHighlightFieldWithFactory() {
		$tests = [];
		$configBase = $this->newHashSearchConfig( [
			'CirrusSearchFragmentSize' => 350,
			'CirrusSearchUseExperimentalHighlighter' => false
		] );
		$configExp = $this->newHashSearchConfig( [
			'CirrusSearchFragmentSize' => 350,
			'CirrusSearchUseExperimentalHighlighter' => true
		] );
		$factoryGroups = [
			SearchQuery::SEARCH_TEXT => [
				'title',
				'redirect.title',
				'category',
				'heading',
				'text',
				'auxiliary_text',
				'file_text',
				'source_text.plain'
			]
		];

		foreach ( $factoryGroups as $factoryGroup => $fields ) {
			foreach ( $fields as $field ) {
				$tests["$factoryGroup-$field-base"] = [
					CirrusTestCaseTrait::$FIXTURE_DIR . "/highlightFieldBuilder/$factoryGroup-$field-base.expected",
					$factoryGroup,
					$field,
					$configBase
				];
				$tests["$factoryGroup-$field-exp"] = [
					CirrusTestCaseTrait::$FIXTURE_DIR . "/highlightFieldBuilder/$factoryGroup-$field-exp.expected",
					$factoryGroup,
					$field,
					$configExp
				];
			}
		}
		return $tests;
	}

	/**
	 * @dataProvider provideNewHighlightFieldWithFactory
	 */
	public function testNewHighlightFieldWithFactory( $expectedFile, $factoryGroup, $fieldName, $config ) {
		$actualField = ( new FetchPhaseConfigBuilder( $config, $factoryGroup ) )
			->newHighlightField( $fieldName, 'myTarget', 123 );
		$this->assertFileContains( $expectedFile, CirrusIntegrationTestCase::encodeFixture( $actualField->toArray() ),
			CirrusIntegrationTestCase::canRebuildFixture() );
	}

	public function testAddField() {
		$builder = new FetchPhaseConfigBuilder( $this->newHashSearchConfig( [] ) );
		$field = $builder->newHighlightField( 'my_field', 'my_target', 123 );
		$builder->addHLField( $field );
		$this->assertSame( $field, $builder->getHLField( 'my_field' ) );
		$field2 = $builder->newHighlightField( 'my_field', 'my_target', 122 );
		$builder->addHLField( $field2 );
		$this->assertSame( $field, $builder->getHLField( "my_field" ),
			"Merge must be called (here keep the highest prio field" );
	}

	public function testNewRegexField() {
		$configDef = $this->newHashSearchConfig( [ 'CirrusSearchUseExperimentalHighlighter' => false ] );
		$builder = new FetchPhaseConfigBuilder( $configDef );
		$builder->addNewRegexHLField( 'test', 'target', '(foo|bar)', false, 123 );
		$f = $builder->getHLField( 'test' );
		$this->assertNull( $f );

		$configExp = $this->newHashSearchConfig( [ 'CirrusSearchUseExperimentalHighlighter' => true ] );
		$builder = new FetchPhaseConfigBuilder( $configExp );
		$builder->addNewRegexHLField( 'test', 'target', '(foo|bar)', false, 123 );
		$f = $builder->getHLField( 'test' );
		$this->assertNotNull( $f );
		$builder->addNewRegexHLField( 'test', 'target', '(baz|bat)', true, 123 );
		$this->assertNotNull( $f );
		$this->assertArrayHasKey( 'regex', $f->getOptions() );
		$this->assertEquals( [ '(foo|bar)', '(baz|bat)' ], $f->getOptions()['regex'] );
	}

	public function testGetHighlightConfig() {
		$config = $this->newHashSearchConfig( [ 'CirrusSearchFragmentSize' => 123 ] );
		$builder = new FetchPhaseConfigBuilder( $config, SearchQuery::SEARCH_TEXT );
		$f1 = $builder->newHighlightField( 'text', HighlightedField::TARGET_MAIN_SNIPPET, 123 );
		$f2 = $builder->newHighlightField( 'auxiliary_text', HighlightedField::TARGET_MAIN_SNIPPET, 123 );
		$builder->addHLField( $f1 );
		$builder->addHLField( $f2 );

		$this->assertEquals(
			[
				'pre_tags' => [ Searcher::HIGHLIGHT_PRE_MARKER ],
				'post_tags' => [ Searcher::HIGHLIGHT_POST_MARKER ],
				'fields' => [ $f1->getFieldName() => $f1->toArray(), $f2->getFieldName() => $f2->toArray() ],
				'highlight_query' => ( new MatchAll() )->toArray()
			],
			$builder->buildHLConfig( new MatchAll() )
		);

		$this->assertEquals(
			[
				'pre_tags' => [ Searcher::HIGHLIGHT_PRE_MARKER ],
				'post_tags' => [ Searcher::HIGHLIGHT_POST_MARKER ],
				'fields' => [ $f1->getFieldName() => $f1->toArray(), $f2->getFieldName() => $f2->toArray() ],
			],
			$builder->buildHLConfig()
		);
	}

	/**
	 * @dataProvider fullTextHighlightingConfigurationTestCases
	 */
	public function testFullTextHighlightingConfiguration(
		$useExperimentalHighlighter,
		$query,
		array $expected
	) {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchUseExperimentalHighlighter' => $useExperimentalHighlighter,
			'CirrusSearchFragmentSize' => 150,
			'LanguageCode' => 'testlocale',
			'CirrusSearchEnableRegex' => true,
			'CirrusSearchWikimediaExtraPlugin' => [ 'regex' => [ 'use' => true ] ],
			'CirrusSearchRegexMaxDeterminizedStates' => 20000,
		] );
		$fetchPhaseBuilder = new FetchPhaseConfigBuilder( $config, SearchQuery::SEARCH_TEXT );
		$type = new FullTextResultsType( $fetchPhaseBuilder, $query !== null, $this->newTitleHelper() );
		if ( $query ) {
			// TODO: switch to new parser.
			$context = new SearchContext( $config, [], null, null, $fetchPhaseBuilder );
			foreach ( ( new FullTextKeywordRegistry( $config, $this->namespacePrefixParser() ) )->getKeywords() as $kw ) {
				$kw->apply( $context, $query );
			}
		}
		$this->assertEquals( $expected, $type->getHighlightingConfiguration( [] ) );
	}

	public static function fullTextHighlightingConfigurationTestCases() {
		$boostBefore = [
			20 => 2,
			50 => 1.8,
			200 => 1.5,
			1000 => 1.2,
		];

		return [
			'default configuration' => [
				false,
				null,
				[
					'pre_tags' => [ json_decode( '"\uE000"' ) ],
					'post_tags' => [ json_decode( '"\uE001"' ) ],
					'fields' => [
						'title' => [
							'number_of_fragments' => 0,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'title', 'title.plain' ],
						],
						'redirect.title' => [
							'number_of_fragments' => 1,
							'fragment_size' => 10000,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'redirect.title', 'redirect.title.plain' ],
						],
						'category' => [
							'number_of_fragments' => 1,
							'fragment_size' => 10000,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'category', 'category.plain' ],
						],
						'heading' => [
							'number_of_fragments' => 1,
							'fragment_size' => 10000,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'heading', 'heading.plain' ],
						],
						'text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'fvh',
							'order' => 'score',
							'no_match_size' => 150,
							'matched_fields' => [ 'text', 'text.plain' ],
						],
						'auxiliary_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'auxiliary_text', 'auxiliary_text.plain' ],
						],
						'file_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'fvh',
							'order' => 'score',
							'matched_fields' => [ 'file_text', 'file_text.plain' ],
						],
					],
				]
			],
			'default configuration with experimental highlighter' => [
				true,
				null,
				[
					'pre_tags' => [ json_decode( '"\uE000"' ) ],
					'post_tags' => [ json_decode( '"\uE001"' ) ],
					'fields' => [
						'title' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'matched_fields' => [ 'title', 'title.plain' ],
							'fragmenter' => 'none',
						],
						'redirect.title' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'redirect.title', 'redirect.title.plain' ],
							'fragmenter' => 'none',
						],
						'category' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'category', 'category.plain' ],
							'fragmenter' => 'none',
						],
						'heading' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'heading', 'heading.plain' ],
							'fragmenter' => 'none',
						],
						'text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
							],
							'no_match_size' => 150,
							'matched_fields' => [ 'text', 'text.plain' ],
							'fragmenter' => 'scan',
						],
						'auxiliary_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
								'skip_if_last_matched' => true,
							],
							'matched_fields' => [ 'auxiliary_text', 'auxiliary_text.plain' ],
							'fragmenter' => 'scan',
						],
						'file_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
								'skip_if_last_matched' => true,
							],
							'matched_fields' => [ 'file_text', 'file_text.plain' ],
							'fragmenter' => 'scan',
						],
					],
				],
			],
			'source configuration with experimental-highlighter' => [
				true,
				'insource:/(some|thing)/',
				[
					'pre_tags' => [ json_decode( '"\uE000"' ) ],
					'post_tags' => [ json_decode( '"\uE001"' ) ],
					'fields' => [
						'source_text.plain' => [
							'type' => 'experimental',
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'options' => [
								'regex' => [ '(some|thing)' ],
								'locale' => 'testlocale',
								'regex_flavor' => 'lucene',
								'skip_query' => true,
								'regex_case_insensitive' => false,
								'max_determinized_states' => 20000,
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
							],
							'no_match_size' => 150,
							'fragmenter' => 'scan',
						],
						'title' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'matched_fields' => [ 'title', 'title.plain' ],
							'fragmenter' => 'none',
						],
						'redirect.title' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'redirect.title', 'redirect.title.plain' ],
							'fragmenter' => 'none',
						],
						'category' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'category', 'category.plain' ],
							'fragmenter' => 'none',
						],
						'heading' => [
							'number_of_fragments' => 1,
							'type' => 'experimental',
							'order' => 'score',
							'options' => [ 'skip_if_last_matched' => true ],
							'matched_fields' => [ 'heading', 'heading.plain' ],
							'fragmenter' => 'none',
						],
						'text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
							],
							'no_match_size' => 150,
							'matched_fields' => [ 'text', 'text.plain' ],
							'fragmenter' => 'scan',
						],
						'auxiliary_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
								'skip_if_last_matched' => true,
							],
							'matched_fields' => [ 'auxiliary_text', 'auxiliary_text.plain' ],
							'fragmenter' => 'scan',
						],
						'file_text' => [
							'number_of_fragments' => 1,
							'fragment_size' => 150,
							'type' => 'experimental',
							'options' => [
								'top_scoring' => true,
								'boost_before' => $boostBefore,
								'max_fragments_scored' => 5000,
								'skip_if_last_matched' => true,
							],
							'matched_fields' => [ 'file_text', 'file_text.plain' ],
							'fragmenter' => 'scan',
						],
					],
				],
			],
		];
	}

	public function testGetHLFieldsPerTarget() {
		$fetchPhaseConfig = new FetchPhaseConfigBuilder( $this->newHashSearchConfig( [] ) );
		$text = $fetchPhaseConfig->newHighlightField( 'text', 'my_target_1', 1 );
		$text_plain = $fetchPhaseConfig->newHighlightField( 'text.plain', 'my_target_1', 2 );
		$heading = $fetchPhaseConfig->newHighlightField( 'heading', 'my_target_2', 1 );
		$fetchPhaseConfig->addHLField( $text );
		$fetchPhaseConfig->addHLField( $text_plain );
		$fetchPhaseConfig->addHLField( $heading );
		$expected = [
			'my_target_1' => [ $text_plain, $text ],
			'my_target_2' => [ $heading ]
		];
		$actual = $fetchPhaseConfig->getHLFieldsPerTargetAndPriority();

		foreach ( $expected as $k => $v ) {
			$this->assertArrayHasKey( $k, $actual, "Must return the target $k" );
			$this->assertEquals( $v, $actual[$k], "Must have the fields properly ordered" );
		}
	}

	public function testDefaultFullTextTargets() {
		$fetchPhaseConfig = new FetchPhaseConfigBuilder( $this->newHashSearchConfig( [] ) );
		$fetchPhaseConfig->configureDefaultFullTextFields();
		$expectedTargets = [
			HighlightedField::TARGET_MAIN_SNIPPET,
			HighlightedField::TARGET_REDIRECT_SNIPPET,
			HighlightedField::TARGET_CATEGORY_SNIPPET,
			HighlightedField::TARGET_TITLE_SNIPPET,
			HighlightedField::TARGET_SECTION_SNIPPET
		];
		$targets = array_keys( $fetchPhaseConfig->getHLFieldsPerTargetAndPriority() );
		$this->assertEqualsCanonicalizing(
			$targets,
			$expectedTargets,
			"All the expected targets must be set"
		);
	}
}
