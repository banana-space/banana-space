<?php

namespace CirrusSearch\Parser\AST\Visitor;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\QueryParserFactory;
use HtmlArmor;

/**
 * @covers \CirrusSearch\Parser\AST\Visitor\QueryFixer
 */
class QueryFixerTest extends CirrusIntegrationTestCase {

	public function provideTest() {
		return [
			'simple words' => [
				'hello wolrd',
				'hello wolrd',
				'hello world',
				'hello world'
			],
			'sometimes nothing is extracted' => [
				'filetype:foo',
				null,
				null,
				null,
			],
			'longest is fixed' => [
				'foo hastemplate:bar hello wolrd',
				"hello wolrd",
				"hello world",
				'foo hastemplate:bar hello world'
			],
			'complex query with negated parts are not fixed' => [
				'fou "foo" NOT helloworld',
				null,
				null,
				null,
			],
			'dash negation inhibits the fixer' => [
				'hellow -wolrd',
				null,
				null,
				null,
			],
			'dash negation in front of keywords is OK' => [
				'-hastemplate:test hello wolrd',
				"hello wolrd",
				"hello world",
				"-hastemplate:test hello world",
			],
			'intitle is fixed (perhaps side-effect of legacy code)' => [
				'foo hastemplate:sep intitle:wolrd',
				"wolrd",
				"world",
				"foo hastemplate:sep intitle:world",
			],
			'inhibits the fixer on unacceptable in chars in keywords (legacy browsertest_176)' => [
				'intitle:hell\\? intitle:wolrd',
				null,
				null,
				null,
			],
			'words that requires escaping are ignored' => [
				'\\"hello hastemplate:sep \\\\wolrd wolr\\*d hastemplate:sep wolr\\\\?d',
				null,
				null,
				null
			],
			'tilde prefix is kept' => [
				'~hello wolrd',
				'hello wolrd',
				'hello world',
				'~hello world'
			],
			'ns prefix is kept' => [
				'file:hello wolrd',
				'hello wolrd',
				'hello world',
				'file:hello world'
			],
			'tilde & ns prefixes are kept' => [
				'~file:hello wolrd',
				'hello wolrd',
				'hello world',
				'~file:hello world'
			],
			'stripped qmark are left behind' => [
				'hello wolrd?',
				'hello wolrd',
				'hello world',
				'hello world '
			],
			'escaped qmark inhibits query fixer' => [
				'hello wolrd\\?',
				null,
				null,
				null,
			],
		];
	}

	/**
	 * @dataProvider provideTest
	 */
	public function test( $input, $extracted, $fixedString, $expectedFixedQuery ) {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [ 'CirrusSearchStripQuestionMarks' => 'all' ] ),
			$this->namespacePrefixParser() );
		$parsed = $parser->parse( $input );
		$fixer = new QueryFixer( $parsed );
		$this->assertEquals( $extracted, $fixer->getFixablePart() );
		$this->assertEquals( $expectedFixedQuery, $fixer->fix( $fixedString ) );
	}

	public function testEscape() {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [ 'CirrusSearchStripQuestionMarks' => 'all' ] ),
			$this->namespacePrefixParser() );
		$parsed = $parser->parse( 'intitle:< hello world intitle:>' );
		$fixer = new QueryFixer( $parsed );
		$this->assertNotNull( $fixer->getFixablePart() );
		$this->assertEquals( 'intitle:&lt; hello <em>world</em> intitle:&gt;',
			HtmlArmor::getHtml( $fixer->fix( new HtmlArmor( 'hello <em>world</em>' ) ) ) );
	}

	public function testBuild() {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [ 'CirrusSearchStripQuestionMarks' => 'all' ] ),
			$this->namespacePrefixParser() );
		$parsed = $parser->parse( 'foo bar' );
		$fixer = QueryFixer::build( $parsed );
		$this->assertSame( $fixer, QueryFixer::build( $parsed ) );

		$parsed = $parser->parse( 'foo bar' );
		$this->assertEquals( $fixer, QueryFixer::build( $parsed ) );
		$this->assertNotSame( $fixer, QueryFixer::build( $parsed ) );
	}
}
