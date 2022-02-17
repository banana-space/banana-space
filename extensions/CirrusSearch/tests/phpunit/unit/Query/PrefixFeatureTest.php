<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\QueryParserFactory;
use CirrusSearch\Query\Builder\FilterBuilder;
use CirrusSearch\Search\SearchContext;
use Elastica\Query\AbstractQuery;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchQuery;
use Elastica\Query\Term;

/**
 * @covers \CirrusSearch\Query\PrefixFeature
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 * @group CirrusSearch
 */
class PrefixFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		return [
			'simple' => [
				'prefix:test',
				'test',
				NS_MAIN,
				'',
			],
			'simple quoted' => [
				'prefix:"foo bar"',
				"foo bar",
				NS_MAIN,
				'',
			],
			'simple quoted empty will only set the NS_MAIN filter' => [
				'prefix:""',
				null,
				NS_MAIN,
				'',
			],
			'simple namespaced' => [
				'prefix:help:test',
				'test',
				NS_HELP,
				'',
			],
			'simple quoted & namespaced can trim quotes' => [
				'prefix:help:"foo bar"',
				'foo bar',
				NS_HELP,
				'',
			],
			'simple all quoted & namespaced can trim quotes' => [
				'prefix:"help:foo bar"',
				'foo bar',
				NS_HELP,
				'',
			],
			'simple quoted empty & namespaced is not completely ignored' => [
				'prefix:help:""',
				null,
				NS_HELP,
				'',
			],
			'combined' => [
				'foo prefix:test',
				'test',
				NS_MAIN,
				// trailing space explicitly added by SimpleKeywordFeature
				'foo ',
			],
			'combined quoted' => [
				'baz prefix:"foo bar"',
				"foo bar",
				NS_MAIN,
				'baz ',
			],
			'combined quoted empty only sets NS_MAIN' => [
				'foo prefix:""',
				null,
				NS_MAIN,
				'foo ',
			],
			'combined namespaced' => [
				'foo prefix:help:test',
				'test',
				NS_HELP,
				'foo ',
			],
			'combined quoted & namespaced can trim the title' => [
				'foo prefix:help:"test"',
				'test',
				NS_HELP,
				'foo ',
			],
			'combined all quoted & namespaced can trim the title' => [
				'foo prefix:"help:test"',
				'test',
				NS_HELP,
				'foo ',
			],
			'combined quoted empty & namespaced' => [
				'foo prefix:help:""',
				null,
				NS_HELP,
				'foo ',
			],
			'prefix is greedy' => [
				'foo prefix:foo bar',
				'foo bar',
				NS_MAIN,
				'foo ',
			],
			'prefix does not need to convert _ to space since it is handled by elastic' => [
				'foo prefix:foo_bar',
				'foo_bar',
				NS_MAIN,
				'foo ',
			],
			'prefix can also be used as a simple namespace filter' => [
				'foo prefix:help:',
				null,
				NS_HELP,
				'foo ',
			],
			'prefix can also be used to open on all namespaces' => [
				'foo prefix:all:',
				null,
				null, // null is all
				'foo ',
			],
			'prefix does not misinterpret a trailing :' => [
				'foo prefix:Help:Wikipedia:',
				'Wikipedia:',
				NS_HELP, // null is everything
				'foo ',
			],
			'prefix does not trim quotes if the query is ambiguous regarding greedy behaviors' => [
				'foo prefix:"foo bar" test',
				'"foo bar" test',
				NS_MAIN,
				'foo ',
			],
			'prefix does not ignore negation' => [
				'foo -prefix:"foo bar"',
				'foo bar',
				NS_MAIN,
				'foo ',
			]
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( $query, $filterValue, $namespace, $expectedRemaining ) {
		$assertions = null;

		$assertFilter = function ( AbstractQuery $filter ) use ( $filterValue ) {
			$this->assertInstanceOf( MatchQuery::class, $filter );
			$this->assertEquals( [ 'query' => $filterValue ], $filter->getParam( 'title.prefix' ) );
			return true;
		};

		$assertNsFilter = function ( AbstractQuery $filter ) use ( $namespace ) {
			$this->assertInstanceOf( Term::class, $filter );
			$this->assertEquals( $namespace, $filter->getParam( 'namespace' ) );
			return true;
		};

		if ( $filterValue !== null && $namespace !== null ) {
			$assertions = function ( AbstractQuery $filter ) use (
				$filterValue,
				$namespace,
				$assertFilter,
				$assertNsFilter
			) {
				$this->assertInstanceOf( BoolQuery::class, $filter );
				$boolQuery = $filter;
				$queries = $boolQuery->getParam( 'must' );
				$this->assertCount( 2, $queries );
				$valueFilter = $queries[0];
				$nsFilter = $queries[1];
				$assertFilter( $valueFilter );
				$assertNsFilter( $nsFilter );
				return true;
			};
		} elseif ( $filterValue !== null ) {
			$assertions = $assertFilter;
		} elseif ( $namespace !== null ) {
			$assertions = $assertNsFilter;
		}

		$feature = new PrefixFeature( $this->namespacePrefixParser() );
		if ( $assertions !== null ) {
			if ( $namespace === null || $namespace <= NS_CATEGORY_TALK ) {
				$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::allWikisStrategy() );
			} else {
				$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::hostWikiOnlyStrategy() );
			}
		}
		$parsedValue = [ 'value' => $filterValue ];
		if ( $namespace !== null ) {
			$parsedValue['namespace'] = $namespace;
			$parsedValue[PrefixFeature::PARSED_NAMESPACES] = [ $namespace ];
		} else {
			$parsedValue[PrefixFeature::PARSED_NAMESPACES] = 'all';
		}
		$this->assertParsedValue( $feature, $query, $parsedValue, [] );
		$this->assertExpandedData( $feature, $query, [], [] );
		$this->assertFilter( $feature, $query, $assertions, [] );
		$this->assertRemaining( $feature, $query, $expectedRemaining );

		$context = new SearchContext( new HashSearchConfig( [] ),
			[ -1 ] );
		$feature->apply( $context, $query );
		if ( $namespace === null ) {
			$this->assertNull( $context->getNamespaces() );
		} else {
			$this->assertContains( $namespace, $context->getNamespaces() );
		}
		$this->assertEmpty( $context->getWarnings() );
	}

	public function testEmpty() {
		$this->assertNotConsumed( new PrefixFeature( $this->namespacePrefixParser() ), 'foo bar' );
	}

	public function provideBadPrefixQueries() {
		return [
			'prefix wants all but context is NS_MAIN' => [
				'prefix:all:',
				[ NS_MAIN ],
				null,
				'all'
			],
			'prefix wants Help but context is NS_MAIN' => [
				'prefix:Help:Test',
				[ NS_MAIN, NS_TALK ],
				[ NS_MAIN, NS_TALK, NS_HELP ],
				[ NS_HELP ],
			],
			'prefix wants main but context is Help' => [
				'prefix:Test',
				[ NS_HELP ],
				[ NS_HELP, NS_MAIN ],
				[ NS_MAIN ]
			],
			'prefix wants NS_MAIN and context has it' => [
				'prefix:Test',
				[ NS_MAIN, NS_HELP ],
				[ NS_MAIN, NS_HELP ],
				[ NS_MAIN ]
			],
			'prefix wants all and context is all' => [
				'prefix:all:',
				[],
				[],
				'all'
			],
			'prefix wants all and context is null' => [
				'prefix:all:',
				null, // means all
				null,
				'all'
			],
			'combined prefix wants main but context is Help' => [
				'foo prefix:Test',
				[ NS_HELP ],
				[ NS_HELP, NS_MAIN ],
				[ NS_MAIN ]
			],
			'combined negated prefix wants main but context is Help' => [
				'foo -prefix:Test',
				[ NS_HELP ],
				[ NS_HELP, NS_MAIN ],
				[ NS_MAIN ]
			],
		];
	}

	/**
	 * @dataProvider provideBadPrefixQueries()
	 * @covers \CirrusSearch\Parser\AST\KeywordFeatureNode
	 * @covers \CirrusSearch\Parser\QueryStringRegex\QueryStringRegexParser
	 */
	public function testRequiredNamespaces( $query, $namespace, $expectedNamespaces, $additionalNs ) {
		$config = new HashSearchConfig( [] );
		$context = new SearchContext( $config, $namespace );
		$feature = new PrefixFeature( $this->namespacePrefixParser() );
		$feature->apply( $context, $query );
		$this->assertEquals( $expectedNamespaces, $context->getNamespaces() );
		$parser = QueryParserFactory::newFullTextQueryParser( $config, $this->namespacePrefixParser() );
		$parsedQuery = $parser->parse( $query );
		$this->assertEquals( $additionalNs, $parsedQuery->getRequiredNamespaces() );
	}

	public function provideTestPrepareSearchContext() {
		return [
			'main' => [
				[ NS_MAIN ],
				'test',
				[ NS_MAIN ]
			],
			'main add ns' => [
				[ NS_MAIN ],
				'help:test',
				[ NS_MAIN, NS_HELP ]
			],
			'ns untouched' => [
				null,
				'help:test',
				null
			],
			'ns to all' => [
				[ NS_MAIN ],
				'all:test',
				null
			],
		];
	}

	/**
	 * @covers \CirrusSearch\Search\SearchContext
	 * @dataProvider provideTestPrepareSearchContext
	 * @param int[]|null $initialNs
	 * @param string $prefix
	 * @param int[]|null $expectedNs
	 */
	public function testPrepareSearchContext( $initialNs, $prefix, $expectedNs ) {
		$config = new HashSearchConfig( [] );
		$context = new SearchContext( $config, $initialNs );
		PrefixFeature::prepareSearchContext( $context, $prefix, $this->namespacePrefixParser() );
		$this->assertEquals( $expectedNs, $context->getNamespaces() );
		$this->assertCount( 1, $context->getFilters() );
		$this->assertFilter(
			new PrefixFeature( $this->namespacePrefixParser() ),
			'prefix:' . $prefix, $context->getFilters()[0],
			[],
			$config
		);
	}

	public function provideTestContextualFilter() {
		return [
			'main' => [
				'test',
				[ NS_MAIN ]
			],
			'specific' => [
				'help:test',
				[ NS_HELP ]
			],
			'all' => [
				'all:test',
				[]
			],
		];
	}

	/**
	 * @dataProvider provideTestContextualFilter
	 */
	public function testContextualFilter( $prefix, $expectedNs ) {
		$contextualFilter = PrefixFeature::asContextualFilter( $prefix, $this->namespacePrefixParser() );
		$this->assertEquals( $expectedNs, $contextualFilter->requiredNamespaces() );
		$filterBuilderMock = $this->createMock( FilterBuilder::class );
		$filters = [];
		$filterBuilderMock->expects( $this->once() )
			->method( 'must' )
			->with( $this->captureArgs( $filters ) );
		$filterBuilderMock->expects( $this->never() )->method( 'mustNot' );
		$contextualFilter->populate( $filterBuilderMock );
		$this->assertCount( 1, $filters );
		$this->assertFilter( new PrefixFeature( $this->namespacePrefixParser() ), 'prefix:' . $prefix,  $filters[0], [] );
	}
}
