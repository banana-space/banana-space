<?php

namespace CirrusSearch\Parser;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;

/**
 * @covers \CirrusSearch\Parser\BasicQueryClassifier
 * @group CirrusSearch
 */
class BasicQueryClassifierTest extends CirrusTestCase {

	public function provideQueries() {
		return [
			'simple' => [ 'foo', [ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ] ],
			'simple unquoted phrase' => [ 'foo bar', [ BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ] ],
			'empty' => [ '', [] ],
			'simple phrase' => [ '"hello world"', [ BasicQueryClassifier::SIMPLE_PHRASE ] ],
			'simple unbalanced phrase' => [ 'hello "world', [ BasicQueryClassifier::BOGUS_QUERY ] ],
			'words and simple phrase' => [ 'hello "world"', [ BasicQueryClassifier::BAG_OF_WORDS_WITH_PHRASE ] ],
			'wildcard' => [ 'hop*d', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'prefix' => [ 'hop*', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'fuzzy' => [ 'hop~', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'phrase prefix' => [ '"foo bar*"', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'complex phrase' => [ '"foo bar"~', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'complex phrase bis' => [ '"foo bar"~2~', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'keyword' => [ 'intitle:foo', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'boolean' => [ 'hello AND world', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'negation' => [ 'hello -world', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'negation explicit' => [ 'hello AND NOT world', [ BasicQueryClassifier::COMPLEX_QUERY ] ],
			'complex' => [
				'intitle:foo AND hello AND NOT world* AND "foo bar"~3~',
				[ BasicQueryClassifier::COMPLEX_QUERY ]
			],
			'complex & bogus' => [
				'intitle:foo AND hello AND NOT -world* AND "foo bar"~3~',
				[ BasicQueryClassifier::BOGUS_QUERY, BasicQueryClassifier::COMPLEX_QUERY ]
			],
		];
	}

	/**
	 * @dataProvider provideQueries
	 * @param string $query
	 * @param string|null $class
	 */
	public function test( $query, $classes ) {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() );
		$parsedQuery = $parser->parse( $query );
		$classifier = new BasicQueryClassifier();
		sort( $classes );
		$actualClasses = $classifier->classify( $parsedQuery );
		sort( $actualClasses );
		$this->assertEquals( $classes, $actualClasses );
	}

	public function testClasses() {
		$classifier = new BasicQueryClassifier();
		$this->assertEquals( [
				BasicQueryClassifier::SIMPLE_BAG_OF_WORDS,
				BasicQueryClassifier::SIMPLE_PHRASE,
				BasicQueryClassifier::BAG_OF_WORDS_WITH_PHRASE,
				BasicQueryClassifier::COMPLEX_QUERY,
				BasicQueryClassifier::BOGUS_QUERY,
			], $classifier->classes() );
	}
}
