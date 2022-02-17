<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Parser\AST\PhraseQueryNode;
use CirrusSearch\Search\Escaper;

/**
 * @covers \CirrusSearch\Parser\QueryStringRegex\PhraseQueryParser
 * @group CirrusSearch
 */
class PhraseQueryParserTest extends CirrusTestCase {
	/**
	 * @dataProvider provideQueries
	 * @param string $lang
	 * @param string $query
	 * @param int $start
	 * @param int $end
	 * @param PhraseQueryNode|null $expectedNode
	 */
	public function test( $query, $start, $end, $expectedNode ) {
		$escaper = new Escaper( 'en' );
		$parser = new PhraseQueryParser( $escaper );
		$node = $parser->parse( $query, $start, $end );
		$this->assertEquals( $expectedNode, $node );
	}

	public function provideQueries() {
		return [
			'no phrase' => [
				'foo bar',
				0,
				strlen( 'foo bar' ),
				null
			],
			'phrase not at start offset' => [
				'foo bar "foo bar"',
				0,
				strlen( 'foo bar "foo bar"' ),
				null
			],
			'phrase with consumed part' => [
				'foo bar "foo bar"',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo ' ),
				null
			],
			'phrase at offset' => [
				'foo bar "foo \" bar"',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo \" bar"' ),
				new PhraseQueryNode( strlen( 'foo bar ' ),
					strlen( 'foo bar "foo \" bar"' ),
					'foo " bar',
					-1,
					false,
					false )
			],
			'phrase at offset with slop' => [
				'foo bar "foo \" bar"~2',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo \" bar"~2' ),
				new PhraseQueryNode( strlen( 'foo bar ' ),
					strlen( 'foo bar "foo \" bar"~2' ),
					'foo " bar',
					2,
					false,
					false )
			],
			'phrase at offset with slop&stem' => [
				'foo bar "foo \" bar"~2~',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo \" bar"~2~' ),
				new PhraseQueryNode( strlen( 'foo bar ' ),
					strlen( 'foo bar "foo \" bar"~2~' ),
					'foo " bar',
					2,
					true,
					false )
			],
			'phrase at offset with stem' => [
				'foo bar "foo \" bar"~',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo \" bar"~' ),
				new PhraseQueryNode( strlen( 'foo bar ' ),
					strlen( 'foo bar "foo \" bar"~' ),
					'foo " bar',
					-1,
					true,
					false )
			],
			'collapsed phrase at offset' => [
				'foo bar"foo \" bar"',
				strlen( 'foo bar' ),
				strlen( 'foo bar"foo \" bar"' ),
				new PhraseQueryNode( strlen( 'foo bar' ),
					strlen( 'foo bar"foo \" bar"' ),
					'foo " bar',
					-1,
					false )
			],
			'negated phrase not at start offset' => [
				'foo bar -"foo bar"',
				0,
				strlen( 'foo bar -"foo bar"' ),
				null
			],
			'negated phrase with consumed part' => [
				'foo bar -"foo bar"',
				strlen( 'foo bar ' ),
				strlen( 'foo bar -"foo ' ),
				null
			],
			'negated phrase at offset' => [
				'foo bar -"foo \" bar"',
				strlen( 'foo bar ' ),
				strlen( 'foo bar -"foo \" bar"' ),
				new NegatedNode(
					strlen( 'foo bar ' ),
					strlen( 'foo bar -"foo \" bar"' ),
					new PhraseQueryNode( strlen( 'foo bar -' ),
						strlen( 'foo bar -"foo \" bar"' ),
						'foo " bar',
						-1,
						false ),
					'-'
				),
			],
			'escaped escape sequence' => [
				'foo bar "foo \\\\" bar',
				strlen( 'foo bar ' ),
				strlen( 'foo bar "foo \\\\" bar' ),
				new PhraseQueryNode( strlen( 'foo bar ' ),
					strlen( 'foo bar "foo \\\\"' ),
					'foo \\',
					-1,
					false,
					false )
			],
		];
	}
}
