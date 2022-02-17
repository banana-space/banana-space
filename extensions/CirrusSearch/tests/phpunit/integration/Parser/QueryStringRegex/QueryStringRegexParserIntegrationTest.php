<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\QueryParser;
use CirrusSearch\Parser\QueryParserFactory;
use CirrusSearch\SearchConfig;

/**
 * @covers \CirrusSearch\Parser\AST\BooleanClause
 * @covers \CirrusSearch\Parser\AST\ParsedQuery
 * @covers \CirrusSearch\Parser\AST\ParsedNode
 * @covers \CirrusSearch\Parser\AST\ParseWarning
 * @covers \CirrusSearch\Parser\AST\ParsedBooleanNode
 * @covers \CirrusSearch\Parser\AST\NegatedNode
 * @covers \CirrusSearch\Parser\AST\KeywordFeatureNode
 * @covers \CirrusSearch\Parser\AST\PrefixNode
 * @covers \CirrusSearch\Parser\AST\PhrasePrefixNode
 * @covers \CirrusSearch\Parser\AST\PhraseQueryNode
 * @covers \CirrusSearch\Parser\AST\WordsQueryNode
 * @covers \CirrusSearch\Parser\AST\EmptyQueryNode
 * @covers \CirrusSearch\Parser\AST\WildcardNode
 * @covers \CirrusSearch\Parser\AST\FuzzyNode
 * @covers \CirrusSearch\Parser\AST\NamespaceHeaderNode
 * @covers \CirrusSearch\Parser\QueryStringRegex\PhraseQueryParser
 * @covers \CirrusSearch\Parser\QueryStringRegex\NonPhraseParser
 * @covers \CirrusSearch\Parser\QueryStringRegex\OffsetTracker
 * @covers \CirrusSearch\Parser\QueryStringRegex\KeywordParser
 * @covers \CirrusSearch\Parser\QueryStringRegex\QueryStringRegexParser
 * @covers \CirrusSearch\Parser\QueryStringRegex\Token
 * @covers \CirrusSearch\Parser\FTQueryClassifiersRepository
 * @covers \CirrusSearch\Parser\BasicQueryClassifier
 * @group CirrusSearch
 */
class QueryStringRegexParserIntegrationTest extends CirrusIntegrationTestCase {

	/**
	 * @dataProvider provideRefImplQueries
	 * @param array $expected
	 * @param array $config
	 * @param string $queryString
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	public function testRefImplFixtures( array $expected, $queryString, array $config = [] ) {
		$this->assertQuery( $expected, $queryString, $config );
	}

	/**
	 * @param array $expected
	 * @param string $queryString
	 * @param array $config
	 * @throws \CirrusSearch\Parser\ParsedQueryClassifierException
	 */
	public function assertQuery( array $expected, $queryString, array $config = [] ) {
		$config = new HashSearchConfig(
			$config + [ 'CirrusSearchStripQuestionMarks' => 'all' ],
			[ HashSearchConfig::FLAG_INHERIT ]
		);
		$parser = $this->buildParser( $config );
		$parsedQuery = $parser->parse( $queryString );
		$actual = $parsedQuery->toArray();
		$this->assertEquals( $expected, $actual, true );
	}

	public function provideRefImplQueries() {
		return $this->provideQueries( 'ref_impl_fixtures.json' );
	}

	public function provideQueries( $filename ) {
		$file = 'regexParser/' . $filename;
		$tests = CirrusIntegrationTestCase::loadFixture( $file );
		if ( getenv( 'REGEN_PARSER_TESTS' ) === $filename || getenv( 'REGEN_PARSER_TESTS' ) === 'all' ) {
			$ntests = [];
			foreach ( $tests as $name => $data ) {
				$ntest = [];
				$ntest['query'] = $data['query'];
				$config = [];
				if ( !empty( $data['config'] ) ) {
					$config = $data['config'];
					$ntest['config'] = $config;
				}
				$query = $this->parse( $data['query'], $config );
				$ntest['expected'] = $query->toArray();
				$ntests[$name] = $ntest;
			}
			CirrusIntegrationTestCase::saveFixture( $file, $ntests );
			return [];
		}
		$unittests = [];
		foreach ( $tests as $test => $data ) {
			if ( !isset( $data['expected'] ) ) {
				$this->fail( "Expected data not found for test $test, please regenerate this fixture " .
					"file by setting REGEN_PARSER_TESTS=$filename" );
			}
			$unittests[$test] = [
				$data['expected'],
				$data['query'],
				isset( $data['config'] ) ? $data['config'] : []
			];
		}
		return $unittests;
	}

	private function parse( $query, $config ) {
		$config = new HashSearchConfig(
			$config + [ 'CirrusSearchStripQuestionMarks' => 'all' ],
			[ HashSearchConfig::FLAG_INHERIT ]
		);

		return $this->buildParser( $config )->parse( $query );
	}

	/**
	 * @param SearchConfig $config
	 * @return QueryParser
	 */
	public function buildParser( $config ) {
		$parser = QueryParserFactory::newFullTextQueryParser( $config, $this->namespacePrefixParser() );
		$this->assertInstanceOf( QueryStringRegexParser::class, $parser );
		return $parser;
	}
}
