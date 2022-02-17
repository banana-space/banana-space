<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\Util;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Query\FullTextQueryStringQueryBuilder
 */
class FullTextQueryStringQueryBuilderTest extends CirrusTestCase {

	public function syntaxUsedProvider() {
		return [
			'basic term uses no special syntax' => [
				'foo',
				[],
			],
			'using AND marks the query_string syntax' => [
				'foo AND bar',
				[ 'query_string' ],
			],
			'using - at start marks the query_string syntax' => [
				'-foo',
				[ 'query_string' ],
			],
			'escaping - uses no syntax' => [
				'\\-foo',
				[],
			],
			'token starting with ! is query_string syntax' => [
				'!foo bar',
				[ 'query_string' ],
			],
			'escaped token starting with ! is not query_string syntax' => [
				'\\!foo bar',
				[],
			],
			'using - in middle marks the query_string syntax' => [
				'foo -bar',
				[ 'query_string' ],
			],
			'escaping - in middle uses no syntax' => [
				'foo \\-bar',
				[],
			],
			'using - inside a word uses no syntax' => [
				'foo-bar',
				[],
			],
			'using + marks the query_string syntax' => [
				'+foo',
				[ 'query_string' ],
			],
			'escaping + uses no syntax' => [
				'\\+foo',
				[],
			],
			'using a quote is query_string syntax' => [
				'foo "bar"',
				[ 'query_string' ],
			],
			'a fully quoted string is query_string syntax' => [
				'"foo bar"',
				[ 'query_string' ],
			],
			'having AND not on its own uses no syntax' => [
				'fooAND bar',
				[],
			],
			'having AND in the middle of a word uses no syntax' => [
				'AMANDA',
				[],
			],
			'NOT at the begining of a query is query_string syntax' => [
				'NOT foo bar',
				[ 'query_string' ],
			],
			'using OR marks the query_string syntax' => [
				'foo OR bar',
				[ 'query_string' ],
			],
			'an escaped ? inside a string is query_string syntax' => [
				'catapu\\?t',
				[ 'query_string' ],
			],
			'an unescaped ? inside a string has no syntax used' => [
				'catapu?t',
				[]
			],
			'marking a fuzzy query at end of string is query_string syntax' => [
				'foo~',
				[ 'query_string' ],
			],
			'marking a fuzzy query in middle of string is query_string syntax' => [
				'foo~ bar',
				[ 'query_string' ],
			],
			'marking a fuzzy query with number at end of string is query_string syntax' => [
				'foo~0',
				[ 'query_string' ],
			],
			'marking a fuzzy query with number in middle of string is query_string syntax' => [
				'foo~0 bar',
				[ 'query_string' ],
			],
			'fuzzy after a space isnt query_string syntax' => [
				'catapult ~/',
				[],
			],
			'weird edge case shouldnt be seen as using query_string syntax' => [
				'catapult ||---',
				[],
			],
			'imbalanced quotes get balanced and are considered query_string syntax' => [
				'test "imbalanced quotes phrase',
				[ 'query_string' ],
			],
		];
	}

	/**
	 * @dataProvider syntaxUsedProvider
	 * @param string $term
	 * @param array $expected
	 */
	public function testSyntaxUsed( $term, $expected ) {
		// To make things a little more consistent with how it is
		// seen from the actual search interface, apply question
		// mark stripping which is normally done in Searcher::searchText()
		$term = Util::stripQuestionMarks( $term, 'all' );

		$config = new HashSearchConfig( [
			'wgLanguageCode' => 'en',
			'CirrusSearchWeights' => [
				'title' => 20,
				'redirect' => 15,
				'category' => 8,
				'heading' => 5,
				'opening_text' => 3,
				'text' => 1,
				'auxiliary_text' => 0.5,
				'file_text' => 0.5
			],
			'CirrusSearchPhraseSlop' => [
				'precise' => 0,
				'default' => 0,
				'boost' => 1
			],
		] );
		$builder = new FullTextQueryStringQueryBuilder( $config, [] );
		$searchContext = new SearchContext( $config );
		$builder->build( $searchContext, $term );
		$actual = $searchContext->getSyntaxUsed();

		// These are returned for everything (TODO: why?)
		$expected[] = 'full_text';
		$expected[] = 'full_text_querystring';

		// sort for stability
		sort( $actual );
		sort( $expected );
		$this->assertEquals( $expected, $actual );
	}
}
