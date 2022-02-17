<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\Test\MockSimpleKeywordFeature;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 */
class SimpleKeywordFeatureTest extends CirrusTestCase {
	public function applyProvider() {
		return [
			'unquoted value' => [
				// expected doApply calls
				[
					[ 'mock', 'unquoted', 'unquoted', false ],
				],
				// expected remaining term
				'',
				// input term
				'mock:unquoted'
			],
			'quoted value' => [
				// expected doApply calls
				[
					[ 'mock', 'some stuff', '"some stuff"', false ],
				],
				// expected remaining term
				'',
				// input term
				'mock:"some stuff"'
			],
			'quoted value with escaped quotes' => [
				// expected doApply calls
				[
					[ 'mock', 'some "stuff"', '"some \\"stuff\\""', false ],
				],
				// expected remaining term
				'',
				// input term
				'mock:"some \\"stuff\\""'
			],
			'quoted value wrapped whole in escaped quotes' => [
				[
					[ 'mock', '"some stuff"', '"\\"some stuff\\""', false ],
				],
				// expected remaining term
				'',
				// input term
				'mock:"\\"some stuff\\""',
			],
			'keyword doesnt have to be a prefix' => [
				// expected doApply calls
				[
					[ 'mock', 'stuff', 'stuff', false ],
				],
				// expected remaining term
				'unrelated ',
				// input term
				'unrelated mock:stuff',
			],
			'multiple keywords' => [
				// expected doApply calls
				[
					[ 'mock', 'foo', 'foo', false ],
					[ 'mock', 'bar', '"bar"', false ],
				],
				// expected remaining term
				'extra pieces ',
				// input term
				'extra mock:foo pieces mock:"bar"'
			],
			'negation' => [
				// expected doApply calls
				[
					[ 'mock', 'things', 'things', true ],
				],
				// expected remaining term
				'',
				// input term
				'-mock:things'
			],
			'negation on alias' => [
				// expected doApply calls
				[
					[ 'mock2', 'things', 'things', true ],
				],
				// expected remaining term
				'',
				// input term
				'-mock2:things'
			],
			'handles space between keyword and value' => [
				// expected doApply calls
				[
					[ 'mock', 'value', 'value', false ],
				],
				// expected remaining term
				'',
				// input term
				'mock: value',
			],
			'eats single extra space after the value' => [
				// expected doApply calls
				[
					[ 'mock', 'value', 'value', false ],
				],
				// expected remaining term
				'unrelated',
				// input term
				'mock:value unrelated',
			],
			'doesnt trigger on prefixed keyword' => [
				// expected doApply calls
				[],
				// expected remaining term
				'somemock:value',
				// input term
				'somemock:value',
			],
			'doesnt trigger on prefixed keyword with term before it' => [
				// expected doApply calls
				[],
				// expected remaining term
				'foo somemock:value',
				// input term
				'foo somemock:value',
			],
			'doesnt get confused with empty quoted value' => [
				// expected doApply calls
				[
					[ 'mock', '', '""', false ],
				],
				// expected remaining term
				'links to catapult""',
				// input term
				'mock:"" links to catapult""',
			],
			'doesnt get confused with empty quoted value missing trailing space' => [
				// expected doApply calls
				[
					[ 'mock', '', '""', false ],
				],
				// expected remaining term
				'links to catapult""',
				// input term
				'mock:""links to catapult""',
			],
			'treats closing quote as end of value' => [
				[
					[ 'mock', 'foo', '"foo"', false ],
				],
				'links to catapult',
				'mock:"foo"links to catapult',
			],
			'odd but expected handling of single escaped quote' => [
				[
					[ 'mock', '\\', '\\', false ],
				],
				'"foo',
				'mock:\"foo'
			],
			'appropriate way to pass single escaped quote if needed' => [
				[
					[ 'mock', '"foo', '"\\"foo"', false ],
				],
				'',
				'mock:"\"foo"',
			],
		];
	}

	/**
	 * @dataProvider applyProvider
	 */
	public function testApply( $expectedArgs, $expectedTerm, $term ) {
		$context = $this->getMockBuilder( SearchContext::class )
			->disableOriginalConstructor()
			->getMock();

		$feature = new MockSimpleKeywordFeature();
		$this->assertEquals(
			$expectedTerm,
			$feature->apply( $context, $term )
		);

		$this->assertEquals( $expectedArgs, $feature->getApplyCallArguments() );
	}
}
