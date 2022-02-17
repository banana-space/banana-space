<?php

namespace CirrusSearch\Search\Fetch;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\CirrusTestCaseTrait;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\SearchConfig;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;

/**
 * @covers \CirrusSearch\Search\Fetch\HighlightedField
 * @covers \CirrusSearch\Search\Fetch\BaseHighlightedField
 * @covers \CirrusSearch\Search\Fetch\ExperimentalHighlightedFieldBuilder
 */
class HighlightedFieldBuilderTest extends CirrusTestCase {
	public function provideTestFactories() {
		$tests = [];
		$config = $this->newHashSearchConfig( [
			'CirrusSearchFragmentSize' => 350,
		] );
		$baseFactories = BaseHighlightedField::getFactories();
		$expFactories = ExperimentalHighlightedFieldBuilder::getFactories();
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
					$baseFactories,
					$factoryGroup,
					$field,
					$config
				];
				$tests["$factoryGroup-$field-exp"] = [
					CirrusTestCaseTrait::$FIXTURE_DIR . "/highlightFieldBuilder/$factoryGroup-$field-exp.expected",
					$expFactories,
					$factoryGroup,
					$field,
					$config
				];
			}
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestFactories
	 */
	public function testFactories( $expectedFile, $factories, $factoryGroup, $fieldName, SearchConfig $config ) {
		$this->assertArrayHasKey( $factoryGroup, $factories );
		$this->assertArrayHasKey( $fieldName, $factories[$factoryGroup] );
		$this->assertIsCallable( $factories[$factoryGroup][$fieldName] );
		/** @var BaseHighlightedField $actualField */
		$actualField = ( $factories[$factoryGroup][$fieldName] ) ( $config, $fieldName, 'dummyTarget', 1234 );
		$this->assertFileContains( $expectedFile, CirrusIntegrationTestCase::encodeFixture( $actualField->toArray() ),
			CirrusIntegrationTestCase::canRebuildFixture() );
	}

	public function testSetters() {
		$expField = new ExperimentalHighlightedFieldBuilder( 'myfield', 'mytarget', 123 );
		$baseField = new BaseHighlightedField( 'myfield', BaseHighlightedField::FVH_HL_TYPE, 'mytarget', 123 );
		$this->assertEquals( $expField->getHighlighterType(), ExperimentalHighlightedFieldBuilder::EXPERIMENTAL_HL_TYPE );
		$this->assertEquals( BaseHighlightedField::FVH_HL_TYPE, $baseField->getHighlighterType() );
		foreach ( [ $expField, $baseField ] as $field ) {
			/** @var $field BaseHighlightedField */
			$this->assertEquals( BaseHighlightedField::TYPE, $field->getType() );
			$this->assertEquals( 'myfield', $field->getFieldName() );
			$this->assertEquals( 'mytarget', $field->getTarget() );
			$this->assertEquals( 123, $field->getPriority() );
			$this->assertNull( $field->getOrder() );
			$field->setOrder( 'score' );
			$this->assertEquals( 'score', $field->getOrder() );

			$this->assertNull( $field->getHighlightQuery() );
			$field->setHighlightQuery( new MatchAll() );
			$this->assertEquals( new MatchAll(), $field->getHighlightQuery() );

			$this->assertEmpty( $field->getOptions() );
			$field->setOptions( [ 'foo' => 'bar', 'baz' => 'bat' ] );
			$field->addOption( 'foo', 'overwrittenBar' );
			$this->assertEquals( [ 'foo' => 'overwrittenBar', 'baz' => 'bat' ], $field->getOptions() );

			$this->assertNull( $field->getNoMatchSize() );
			$field->setNoMatchSize( 22 );
			$this->assertEquals( 22, $field->getNoMatchSize() );

			$this->assertNull( $field->getNumberOfFragments() );
			$field->setNumberOfFragments( 34 );
			$this->assertEquals( 34, $field->getNumberOfFragments() );

			$this->assertNull( $field->getFragmenter() );
			$field->setFragmenter( 'scan' );
			$this->assertEquals( 'scan', $field->getFragmenter() );

			$this->assertNull( $field->getFragmentSize() );
			$field->setFragmentSize( 45 );
			$this->assertEquals( 45, $field->getFragmentSize() );

			$this->assertEmpty( $field->getMatchedFields() );
			$field->addMatchedField( 'foo' );
			$field->addMatchedField( 'bar' );
			$this->assertEquals( [ 'foo', 'bar' ], $field->getMatchedFields() );
		}
	}

	public function testSkipIfLastMatched() {
		$expField = new ExperimentalHighlightedFieldBuilder( 'myfield', 'mytarget', 123 );
		$baseField = new BaseHighlightedField( 'myfield', 'mytarget', 123 );

		$expField->skipIfLastMatched();
		$this->assertEquals( [ 'skip_if_last_matched' => true ], $expField->getOptions() );

		$baseField->skipIfLastMatched();
		$this->assertEmpty( $baseField->getOptions() );
	}

	public function testRegex() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchRegexMaxDeterminizedStates' => 233,
			'LanguageCode' => 'testLangCode',
			'CirrusSearchFragmentSize' => 345,
		] );
		$field = ExperimentalHighlightedFieldBuilder::newRegexField(
			$config,
			'testField',
			'testTarget',
			'(foo|bar)',
			false,
			456 );
		$options = $field->getOptions();
		$this->assertArrayHasKey( 'regex', $options );
		$this->assertArrayHasKey( 'regex_flavor', $options );
		$this->assertArrayHasKey( 'locale', $options );
		$this->assertArrayHasKey( 'skip_query', $options );
		$this->assertArrayHasKey( 'regex_case_insensitive', $options );
		$this->assertArrayHasKey( 'max_determinized_states', $options );
		$this->assertEquals( [ '(foo|bar)' ], $options['regex'] );
		$this->assertEquals( 'lucene', $options['regex_flavor'] );
		$this->assertEquals( 'testLangCode', $options['locale'] );
		$this->assertTrue( $options['skip_query'] );
		$this->assertSame( false, $options['regex_case_insensitive'] );
		$this->assertEquals( 233, $options['max_determinized_states'] );
		$this->assertNull( $field->getNoMatchSize() );

		$field2 = ExperimentalHighlightedFieldBuilder::newRegexField(
			$config,
			'testField',
			'testTarget',
			'(baz|bat)',
			true,
			456 );

		$field = $field->merge( $field2 );
		$options = $field->getOptions();
		$this->assertEquals( [ '(foo|bar)', '(baz|bat)' ], $options['regex'] );
		$this->assertTrue( $options['regex_case_insensitive'] );

		$field3 = ExperimentalHighlightedFieldBuilder::newRegexField(
			$config,
			'testField3',
			'testTarget',
			'(baz|bat)',
			true,
			456 );

		try {
			$field->merge( $field3 );
			$this->fail();
		} catch ( \InvalidArgumentException $iae ) {
		}

		// Test a hack where we forcibly keep the regex even if we have the same field to highlight
		// Usecase is: insource:test insource:/test/
		// Without proper priority management we need to force keep the regex over the simple insource:word highlight
		$initialField = new ExperimentalHighlightedFieldBuilder( 'testField', 'testTarget', 2 );
		$initialField = $initialField->merge( $field );
		$this->assertSame( $field, $initialField );
		$initialField = $field->merge( $initialField );
		$this->assertSame( $field, $initialField );

		$sourcePlainSpecial = ExperimentalHighlightedFieldBuilder::newRegexField(
			$config,
			'source_text.plain',
			'testTarget',
			'(foo|bar)',
			true,
			456 );
		$this->assertEquals( 345, $sourcePlainSpecial->getNoMatchSize() );
	}

	public function testMerge() {
		$fields = [
			[
				new BaseHighlightedField( 'test', BaseHighlightedField::FVH_HL_TYPE, 'test', 123 ),
				new BaseHighlightedField( 'test', BaseHighlightedField::FVH_HL_TYPE, 'test', 123 )
			],
			[
				new ExperimentalHighlightedFieldBuilder( 'test', 'test', 123 ),
				new ExperimentalHighlightedFieldBuilder( 'test', 'test', 123 )
			],
		];
		foreach ( $fields as $couple ) {
			list( $field1, $field2 ) = $couple;
			$field1->setHighlightQuery( new MatchAll() );
			$field2->setHighlightQuery( new MatchAll() );
			$field1 = $field1->merge( $field2 );
			$expectedQuery = new BoolQuery();
			$expectedQuery->addShould( new MatchAll() );
			$expectedQuery->addShould( new MatchAll() );
			$this->assertEquals( $expectedQuery, $field1->getHighlightQuery() );

			$expectedQuery->addShould( new MatchAll() );
			$field1->merge( $field2 );
			$this->assertEquals( $expectedQuery, $field1->getHighlightQuery() );
		}
	}

	public function testInvalidMerge() {
		$this->assertMergeFailure( new BaseHighlightedField( 'field1', 'hltype', 'target', 123 ),
			new BaseHighlightedField( 'field2', 'hltype', 'target', 123 ),
			"Rejecting nonsense merge: Refusing to merge two HighlightFields with different field names: " .
			"[field2] != [field1]" );
	}

	public function testMergeOnPrio() {
		$this->assertMergeOnPrio(
			new BaseHighlightedField( 'field1', 'hltype', 'target', 123 ),
			new BaseHighlightedField( 'field1', 'hltype2', 'target', 123 ),
			"highlightType" );

		$this->assertMergeOnPrio(
			new BaseHighlightedField( 'field1', 'hltype', 'target', 123 ),
			new BaseHighlightedField( 'field1', 'hltype', 'target2', 123 ),
			"different target" );

		$fieldCouples = [
			[
				new BaseHighlightedField( 'test', 'hltype', 'target', 123 ),
				new BaseHighlightedField( 'test', 'hltype', 'target', 124 )
			],
			[
				new ExperimentalHighlightedFieldBuilder( 'test', 'target', 123 ),
				new ExperimentalHighlightedFieldBuilder( 'test', 'target', 124 )
			],
		];

		foreach ( $fieldCouples as $couple ) {
			/** @var BaseHighlightedField $f1 */
			/** @var BaseHighlightedField $f2 */
			list( $f1, $f2 ) = $couple;
			$this->assertMergeOnPrio( $f1, $f2, 'query' );
			$f1->setHighlightQuery( new MatchAll() );
			$this->assertMergeOnPrio( $f1, $f2, 'query' );
			$f2->setHighlightQuery( new MatchAll() );

			$f1->addMatchedField( 'foo' );
			$this->assertMergeOnPrio( $f1, $f2, 'matched_fields' );
			$f2->addMatchedField( 'foo' );

			$f1->setFragmenter( 'foo' );
			$this->assertMergeOnPrio( $f1, $f2, 'fragmenter' );
			$f2->setFragmenter( 'foo' );

			$f1->setNumberOfFragments( 3 );
			$this->assertMergeOnPrio( $f1, $f2, 'number_of_fragments' );
			$f2->setNumberOfFragments( 3 );

			$f1->setNoMatchSize( 123 );
			$this->assertMergeOnPrio( $f1, $f2, 'no_match_size' );
			$f2->setNoMatchSize( 123 );

			$f1->setOptions( [ 'foo' => 'bar' ] );
			$this->assertMergeOnPrio( $f1, $f2, 'options' );
			$f2->setOptions( [ 'foo' => 'bar' ] );

			$this->assertSame( $f1, $f1->merge( $f2 ) );
		}
	}

	public function assertMergeFailure( BaseHighlightedField $f1, BaseHighlightedField $f2, $msg ) {
		try {
			$f1->merge( $f2 );
			$this->fail( "Expected InvalidArumentException with message $msg" );
		} catch ( \InvalidArgumentException $iae ) {
			$this->assertStringContainsString( $msg, $iae->getMessage() );
		}
	}

	private function assertMergeOnPrio( BaseHighlightedField $f1, BaseHighlightedField $f2, $testedField ) {
		$bestField = $f1->getPriority() >= $f2->getPriority() ? $f1 : $f2;
		// clone the field and check equality
		// assertSame/assertEqual without cloning is invalid as the field can mutate during a merge operation.
		$bestField = clone $bestField;
		$this->assertEquals( $bestField, $f1->merge( $f2 ),
			"Should keep the highest prio field when merging two fields with different $testedField" );
	}
}
