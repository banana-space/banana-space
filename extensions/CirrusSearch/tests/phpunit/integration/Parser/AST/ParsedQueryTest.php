<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\QueryParserFactory;

/**
 * @covers \CirrusSearch\Parser\AST\ParsedQuery
 * @group CirrusSearch
 */
class ParsedQueryTest extends CirrusIntegrationTestCase {

	public function provideQueriesForTestingCrossSearchStrategy() {
		return [
			'simple' => [
				'',
				CrossSearchStrategy::allWikisStrategy()
			],
			'words' => [
				'foo bar',
				CrossSearchStrategy::allWikisStrategy()
			],
			'one keyword' => [
				'intitle:foo',
				CrossSearchStrategy::allWikisStrategy()
			],
			'multiple keywords with host wiki only strategy' => [
				'intitle:foo incategory:test incategory:id:123',
				CrossSearchStrategy::hostWikiOnlyStrategy()
			]
		];
	}

	/**
	 * @dataProvider provideQueriesForTestingCrossSearchStrategy
	 * @covers \CirrusSearch\Parser\AST\ParsedQuery::getCrossSearchStrategy()
	 */
	public function testCrossSearchStrategy( $query, CrossSearchStrategy $expectedStratery ) {
		$parser = QueryParserFactory::newFullTextQueryParser( $this->newHashSearchConfig( [] ), $this->namespacePrefixParser() );
		$pQuery = $parser->parse( $query );
		$this->assertEquals( $expectedStratery, $pQuery->getCrossSearchStrategy() );
	}

	public function provideTestFeaturesUsed() {
		return [
			'none' => [
				'query',
				[]
			],
			'simple' => [
				'intitle:test',
				[ 'intitle' ],
			],
			'multiple' => [
				'intitle:test intitle:foo incategory:test',
				[ 'intitle', 'incategory' ],
			],
			'morelike' => [
				'morelike:test',
				[ 'more_like' ],
			],
			'regex' => [
				'intitle:/test/ insource:/test/',
				[ 'regex' ],
			]
		];
	}

	/**
	 * @dataProvider provideTestFeaturesUsed
	 * @param string $query
	 * @param string[] $features
	 */
	public function testFeaturesUsed( $query, array $features ) {
		$config = new HashSearchConfig( [ 'CirrusSearchEnableRegex' => true ] );
		$parser = QueryParserFactory::newFullTextQueryParser( $config, $this->namespacePrefixParser() );
		$parsedQuery = $parser->parse( $query );
		$this->assertArrayEquals( $features, $parsedQuery->getFeaturesUsed() );
	}

	public function provideTestNsHeader() {
		return [
			'none' => [ 'foobar', null, 0 ],
			'simple' => [ 'help:foobar', NS_HELP, strlen( 'help:' ) ],
			'all' => [ 'all:fobar', 'all', strlen( 'all:' ) ],
			'tilde & all' => [ '~all:fobar', 'all', strlen( 'all:' ) ],
			'tilde & simple' => [ '~help:fobar', NS_HELP, strlen( 'help:' ) ],
		];
	}

	/**
	 * @dataProvider provideTestNsHeader
	 * @param string $query
	 * @param int|null $expectedNsHeader
	 * @param int $queryStartOffset
	 */
	public function testNsHeader( $query, $expectedNsHeader, $queryStartOffset ) {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() );
		$pq = $parser->parse( $query );
		$this->assertEquals( $expectedNsHeader, $pq->getNamespaceHeader() !== null ? $pq->getNamespaceHeader()->getNamespace() : null );
		$this->assertEquals( $queryStartOffset, $pq->getRoot()->getStartOffset() );
	}

	public function provideTestActualNamespace() {
		return [
			'initial kept' => [
				'foobar',
				[],
				[],
			],
			'initial kept (all ns unified as empty array)' => [
				'foobar',
				null,
				[],
			],
			'initial kept when given a specific list' => [
				'foobar',
				[ NS_HELP ],
				[ NS_HELP ],
			],
			'initial appended when given a prefix keyword' => [
				'prefix:help:test',
				[ NS_MAIN ],
				[ NS_MAIN, NS_HELP ],
			],
			'initial appended when NS_MAIN is used in a prefix keyword' => [
				'prefix:test',
				[ NS_HELP ],
				[ NS_HELP, NS_MAIN ],
			],
			'duplicates are not appended' => [
				'prefix:help:test',
				[ NS_MAIN, NS_HELP ],
				[ NS_MAIN, NS_HELP ],
			],
			'if all provided nothing need to be appended' => [
				'prefix:help:test',
				[],
				[],
			],
			'ns header will override the intial ones' => [
				'help:foobar',
				[ NS_MAIN ],
				[ NS_HELP ],
			],
			'ns header will override the intial ones if set to all' => [
				'help:foobar',
				[],
				[ NS_HELP ],
			],
			'"all" header will override the intial ones' => [
				'all:foobar',
				[ NS_HELP ],
				[],
			],
			'ns header can be appended with the prefix keyword' => [
				'help:prefix:foobar',
				[],
				[ NS_HELP, NS_MAIN ],
			],
			'prefix keyword does not add duplicate to ns header' => [
				'help:prefix:help:foobar',
				[],
				[ NS_HELP ],
			],
			'prefix keyword with all means all' => [
				'help:prefix:all:foobar',
				[],
				[],
			],
			'no need to append anything if ns header is all' => [
				'all:prefix:help:foobar',
				[],
				[],
			],
			'a leading ~ does not disable namespace prefix' => [
				'~help:test',
				[ NS_MAIN ],
				[ NS_HELP ],
			]
		];
	}

	/**
	 * @dataProvider provideTestActualNamespace
	 * @covers \CirrusSearch\Parser\AST\NamespaceHeaderNode
	 * @param $query
	 * @param $initialNamespaces
	 * @param $expectedActualNamespace
	 */
	public function testActualNamespace( $query, $initialNamespaces, $expectedActualNamespace ) {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() );
		$pq = $parser->parse( $query );
		$this->assertEquals( $expectedActualNamespace, $pq->getActualNamespaces( $initialNamespaces ) );
		if ( $expectedActualNamespace !== [] ) {
			$this->assertEquals( array_merge( $expectedActualNamespace, [ NS_CATEGORY ] ),
				$pq->getActualNamespaces( $initialNamespaces, [ NS_CATEGORY ] ) );
		} else {
			$this->assertEquals( [], $pq->getActualNamespaces( $initialNamespaces, [ NS_CATEGORY ] ) );
		}
	}

	public function provideTestLeadingTilde() {
		return [
			'none' => [ 'foobar', false ],
			'simple' => [ '~foobar', true ],
			'a leading space discards this feature' => [ ' ~foobar', false ],
		];
	}

	/**
	 * @dataProvider provideTestLeadingTilde
	 * @param $query
	 * @param $hasLeadingTilde
	 */
	public function testLeadingTilde( $query, $hasLeadingTilde ) {
		$parser = QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() );
		$pq = $parser->parse( $query );
		$this->assertEquals( $hasLeadingTilde, $pq->hasCleanup( ParsedQuery::TILDE_HEADER ) );
	}

	public function testGetQueryWithoutNsHeader() {
		$this->assertEquals( 'foobar',
			QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() )
				->parse( 'help:foobar' )->getQueryWithoutNsHeader() );
		$this->assertEquals( 'foobar',
			QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() )
				->parse( '~help:foobar' )->getQueryWithoutNsHeader() );
		$this->assertEquals( '-foobar',
			QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() )
				->parse( '~help:-foobar' )->getQueryWithoutNsHeader() );
		$this->assertEquals( ' NOT foobar',
			QueryParserFactory::newFullTextQueryParser( new HashSearchConfig( [] ), $this->namespacePrefixParser() )
				->parse( 'all: NOT foobar' )->getQueryWithoutNsHeader() );
	}
}
