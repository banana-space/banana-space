<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Fallbacks\FallbackRunner;
use CirrusSearch\Parser\AST\ParsedQuery;
use CirrusSearch\Parser\BasicQueryClassifier;
use CirrusSearch\Parser\QueryParserFactory;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Query\Builder\ContextualFilter;
use CirrusSearch\Query\Builder\FilterBuilder;
use CirrusSearch\Query\PrefixFeature;
use CirrusSearch\SearchConfig;
use PHPUnit\Framework\Assert;

/**
 * @covers \CirrusSearch\Search\SearchQuery
 * @covers \CirrusSearch\Search\SearchQueryBuilder
 * @covers \CirrusSearch\Search\SearchContext
 * @covers \CirrusSearch\Parser\AST\ParsedQuery
 */
class SearchQueryTest extends CirrusTestCase {

	public function provildeGetNamespaces() {
		return [
			'all' => [
				[],
				[],
				[]
			],
			'simple' => [
				[ NS_MAIN ],
				[],
				[ NS_MAIN ]
			],
			'all + specific' => [
				[],
				[ 'simple' => [ NS_MAIN ] ],
				[]
			],
			'specific + all' => [
				[ NS_MAIN ],
				[ 'simple' => [] ],
				[]
			],
			'specific + specific' => [
				[ NS_MAIN ],
				[ 'simple' => [ NS_HELP ] ],
				[ NS_MAIN, NS_HELP ]
			],
			'specific + specifics + specific' => [
				[ NS_MAIN ],
				[
					'specifics' => [ NS_HELP, NS_HELP_TALK ],
					'specific' => [ NS_CATEGORY ],
				],
				[ NS_MAIN, NS_HELP, NS_HELP_TALK, NS_CATEGORY ]
			],
			'specific + specifics + all' => [
				[ NS_MAIN ],
				[
					'specifics' => [ NS_HELP, NS_HELP_TALK ],
					'all' => [],
				],
				[]
			]
		];
	}

	/**
	 * @dataProvider provildeGetNamespaces
	 * @param int[] $initialNs
	 * @param int[] $namespacesInContextualFilters
	 * @param int[] $expected
	 * @throws \Exception
	 */
	public function testGetNamespaces( $initialNs, array $namespacesInContextualFilters, $expected ) {
		$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $this->newHashSearchConfig( [] ), "foo", $this->namespacePrefixParser() )
			->setInitialNamespaces( $initialNs );
		foreach ( $namespacesInContextualFilters as $name => $namespaces ) {
			$builder->addContextualFilter( $name,
				$this->getContextualFilter( $namespaces )
			);
		}
		$this->assertEquals( $expected, $builder->build()->getNamespaces() );
	}

	public function provideCrossSearchStrategy() {
		return [
			'simple' => [
				'test',
				[
					'CirrusSearchEnableCrossProjectSearch' => true,
					'CirrusSearchEnableAltLanguage' => true,
				],
				CrossSearchStrategy::allWikisStrategy(),
				CrossSearchStrategy::allWikisStrategy(),
				CrossSearchStrategy::allWikisStrategy(),
			],
			'simple but crossproject disabled by config' => [
				'test',
				[
					'CirrusSearchEnableCrossProjectSearch' => false,
					'CirrusSearchEnableAltLanguage' => true,
				],
				CrossSearchStrategy::allWikisStrategy(),
				new CrossSearchStrategy( false, true, true ),
				new CrossSearchStrategy( false, true, true ),
			],
			'simple but crosslanguage disabled by config' => [
				'test',
				[
					'CirrusSearchEnableCrossProjectSearch' => true,
					'CirrusSearchEnableAltLanguage' => false,
				],
				CrossSearchStrategy::allWikisStrategy(),
				new CrossSearchStrategy( true, false, true ),
				new CrossSearchStrategy( true, false, true ),
			],
			'simple but crossproject & crosslanguage disabled by config' => [
				'test',
				[
					'CirrusSearchEnableAltLanguage' => false,
					'CirrusSearchEnableCrossProjectSearch' => false,
				],
				CrossSearchStrategy::allWikisStrategy(),
				new CrossSearchStrategy( false, false, true ),
				new CrossSearchStrategy( false, false, true ),
			],
			'reduce to hostwiki' => [
				'test',
				[
					'CirrusSearchEnableCrossProjectSearch' => true,
					'CirrusSearchEnableAltLanguage' => true,
				],
				CrossSearchStrategy::hostWikiOnlyStrategy(),
				CrossSearchStrategy::hostWikiOnlyStrategy(),
				CrossSearchStrategy::hostWikiOnlyStrategy(),
			],
			'reduced by query' => [
				'local:test',
				[
					'CirrusSearchEnableCrossProjectSearch' => true,
					'CirrusSearchEnableAltLanguage' => true,
				],
				CrossSearchStrategy::allWikisStrategy(),
				CrossSearchStrategy::allWikisStrategy(),
				CrossSearchStrategy::hostWikiOnlyStrategy(),
			],
			'fine tuned' => [
				'test',
				[
					'CirrusSearchEnableCrossProjectSearch' => true,
					'CirrusSearchEnableAltLanguage' => true,
				],
				new CrossSearchStrategy( false, true, true ),
				new CrossSearchStrategy( false, true, true ),
				new CrossSearchStrategy( false, true, true ),
			],
		];
	}

	/**
	 * Test how crosswiki strategy is merged between:
	 * - what is requested from SearchQueryBuilder::setCrossProjectSearch/setCrossLanguageSearch/setExtraIndicesSearch
	 * - what is allowed in the config (SearchRequestBuilder::build())
	 * - what is allowed by the query syntax (ParsedQuery/SearchQuery::getCrossSearchStrategy())
	 * @dataProvider provideCrossSearchStrategy
	 * @param string $query
	 * @param array $config
	 * @param CrossSearchStrategy $callerStrategy
	 * @param CrossSearchStrategy $initialCrossSearchStrategy
	 * @param CrossSearchStrategy $expected
	 */
	public function testCrossSearchStrategy(
		$query,
		array $config,
		CrossSearchStrategy $callerStrategy,
		CrossSearchStrategy $initialCrossSearchStrategy,
		CrossSearchStrategy $expected
	) {
		$searchQuery = SearchQueryBuilder::newFTSearchQueryBuilder( $this->newHashSearchConfig( $config ), $query,
				$this->namespacePrefixParser() )
			->setCrossProjectSearch( $callerStrategy->isCrossProjectSearchSupported() )
			->setCrossLanguageSearch( $callerStrategy->isCrossLanguageSearchSupported() )
			->setExtraIndicesSearch( $callerStrategy->isExtraIndicesSearchSupported() )
			->build();
		$this->assertEquals( $expected, $searchQuery->getCrossSearchStrategy() );
		$this->assertEquals( $initialCrossSearchStrategy, $searchQuery->getInitialCrossSearchStrategy() );
	}

	/**
	 * @param int[]|null $namespaces
	 * @return ContextualFilter
	 */
	private function getContextualFilter( array $namespaces = null ) {
		return new class( $namespaces ) implements ContextualFilter {
			private $namespaces;

			public function __construct( $namespaces ) {
				$this->namespaces = $namespaces;
			}

			public function populate( FilterBuilder $builder ) {
				Assert::fail();
			}

			public function requiredNamespaces() {
				return $this->namespaces;
			}
		};
	}

	public function testBuilderWithDefaults() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchEnableCrossProjectSearch' => true,
			'CirrusSearchEnableAltLanguage' => true,
		] );
		$defaults = SearchQueryBuilder::newFTSearchQueryBuilder( $config, 'test', $this->namespacePrefixParser() )->build();
		$expectedParsedQuery = QueryParserFactory::newFullTextQueryParser( $config, $this->namespacePrefixParser() )
			->parse( 'test' );
		$this->assertEquals( $expectedParsedQuery, $defaults->getParsedQuery() );
		$this->assertFalse( $defaults->hasForcedProfile() );
		$this->assertEquals( CrossSearchStrategy::allWikisStrategy(), $defaults->getInitialCrossSearchStrategy() );
		$this->assertEquals( CrossSearchStrategy::allWikisStrategy(), $defaults->getCrossSearchStrategy() );
		$this->assertEquals( SearchQuery::SEARCH_TEXT, $defaults->getSearchEngineEntryPoint() );
		$this->assertEquals( [ NS_MAIN ], $defaults->getNamespaces() );
		$this->assertEquals( [ NS_MAIN ], $defaults->getInitialNamespaces() );
		$this->assertEquals( 'relevance', $defaults->getSort() );
		$this->assertSame( 0, $defaults->getOffset() );
		$this->assertEquals( 10, $defaults->getLimit() );
		$this->assertEquals( CirrusDebugOptions::defaultOptions(), $defaults->getDebugOptions() );
		$this->assertEquals( $config, $defaults->getSearchConfig() );
		$this->assertEmpty( $defaults->getContextualFilters() );
		$this->assertTrue( $defaults->isWithDYMSuggestion() );
		$this->assertFalse( $defaults->isAllowRewrite() );
		$this->assertEmpty( $defaults->getProfileContextParameters() );
	}

	public function testBuilder() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchEnableCrossProjectSearch' => true,
			'CirrusSearchEnableAltLanguage' => true,
		] );
		$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $config, 'test', $this->namespacePrefixParser() )
			->setExtraIndicesSearch( false )
			->setCrossLanguageSearch( false )
			->setCrossProjectSearch( false )
			->setInitialNamespaces( [ NS_MAIN, NS_HELP ] )
			->addForcedProfile( SearchProfileService::RESCORE, 'test' )
			->setOffset( 10 )
			->setLimit( 100 )
			->setDebugOptions( CirrusDebugOptions::forDumpingQueriesInUnitTests() )
			->setSort( 'size' )
			->setWithDYMSuggestion( false )
			->setAllowRewrite( true )
			->addProfileContextParameter( "foo", "bar" );
		$custom = $builder->build();
		$expectedParsedQuery = QueryParserFactory::newFullTextQueryParser( $config, $this->namespacePrefixParser() )
			->parse( 'test' );
		$this->assertEquals( $expectedParsedQuery, $custom->getParsedQuery() );
		$this->assertTrue( $custom->hasForcedProfile() );
		$this->assertEquals( 'test', $custom->getForcedProfile( SearchProfileService::RESCORE ) );
		$this->assertEquals( CrossSearchStrategy::hostWikiOnlyStrategy(), $custom->getInitialCrossSearchStrategy() );
		$this->assertEquals( CrossSearchStrategy::hostWikiOnlyStrategy(), $custom->getCrossSearchStrategy() );
		$this->assertEquals( SearchQuery::SEARCH_TEXT, $custom->getSearchEngineEntryPoint() );
		$this->assertEquals( [ NS_MAIN, NS_HELP ], $custom->getNamespaces() );
		$this->assertEquals( [ NS_MAIN, NS_HELP ], $custom->getInitialNamespaces() );
		$this->assertEquals( 'size', $custom->getSort() );
		$this->assertEquals( 10, $custom->getOffset() );
		$this->assertEquals( 100, $custom->getLimit() );
		$this->assertEquals( CirrusDebugOptions::forDumpingQueriesInUnitTests(), $custom->getDebugOptions() );
		$this->assertEquals( $config, $custom->getSearchConfig() );
		$this->assertEmpty( $custom->getContextualFilters() );
		$this->assertFalse( $custom->isWithDYMSuggestion() );
		$this->assertTrue( $custom->isAllowRewrite() );
		$this->assertEquals( [ 'foo' => 'bar' ], $custom->getProfileContextParameters() );
		// test that contextual filters force a hostwiki only crosswiki search
		$builder->setExtraIndicesSearch( true )
			->setCrossLanguageSearch( true )
			->setCrossProjectSearch( true )
			->addContextualFilter( 'prefix', PrefixFeature::asContextualFilter( 'test' ) );
		$custom = $builder->build();
		$this->assertEquals( CrossSearchStrategy::allWikisStrategy(), $custom->getInitialCrossSearchStrategy() );
		$this->assertEquals( CrossSearchStrategy::hostWikiOnlyStrategy(), $custom->getCrossSearchStrategy() );
		$this->assertNotEmpty( $custom->getContextualFilters() );
		$this->assertInstanceOf( ContextualFilter::class, $custom->getContextualFilters()['prefix'] );
	}

	public function testSearchContextFromDefaults() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchEnableCrossProjectSearch' => true,
			'CirrusSearchEnableAltLanguage' => true,
		] );
		$context = SearchContext::fromSearchQuery(
			SearchQueryBuilder::newFTSearchQueryBuilder( $config, 'test', $this->namespacePrefixParser() )->build() );
		$this->assertEquals( $config, $context->getConfig() );
		$this->assertEquals( [ NS_MAIN ], $context->getNamespaces() );
		$this->assertFalse( $context->getLimitSearchToLocalWiki() );
		$this->assertEmpty( $context->getFilters() );
		$this->assertEquals( $config->getProfileService()->getProfileName( SearchProfileService::RESCORE ),
			$context->getRescoreProfile() );
		$this->assertEquals( $config->getProfileService()->getProfileName( SearchProfileService::FT_QUERY_BUILDER ),
			$context->getFulltextQueryBuilderProfile() );
		$this->assertEquals( 'test', $context->getOriginalSearchTerm() );
		$this->assertEmpty( $context->getProfileContextParams() );
		$this->assertSame( FallbackRunner::noopRunner(), $context->getFallbackRunner() );
		$this->assertFalse( $context->isSpecialKeywordUsed() );
		$this->assertTrue( $context->isSyntaxUsed( BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ) );
	}

	public function testSearchContextFromBuilder() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchEnableCrossProjectSearch' => true,
			'CirrusSearchEnableAltLanguage' => true,
		] );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( $config, '~help:test prefix:help_talk:test',
				$this->namespacePrefixParser() )
			->setInitialNamespaces( [ NS_MAIN ] )
			->setWithDYMSuggestion( false )
			->setExtraIndicesSearch( false )
			->addContextualFilter( 'prefix', PrefixFeature::asContextualFilter( 'category:test', $this->namespacePrefixParser() ) )
			->addForcedProfile( SearchProfileService::RESCORE, 'foo' )
			->addForcedProfile( SearchProfileService::FT_QUERY_BUILDER, 'bar' )
			->addProfileContextParameter( 'foo', 'bar' )
			->build();
		$myFallbackRunner = new FallbackRunner( [] );
		$context = SearchContext::fromSearchQuery(
			$query,
			$myFallbackRunner
		);
		$this->assertEquals( $config, $context->getConfig() );
		// the help prefix overrides NS_MAIN
		// the prefix keyword will add NS_HELP_TALK
		// the contextual filter will then add NS_CATEGORY
		$this->assertEquals( [ NS_HELP, NS_HELP_TALK, NS_CATEGORY ], $context->getNamespaces() );
		$this->assertTrue( $context->getLimitSearchToLocalWiki() );
		$this->assertNotEmpty( $context->getFilters() );
		$this->assertEquals( 'foo', $context->getRescoreProfile() );
		$this->assertEquals( 'bar', $context->getFulltextQueryBuilderProfile() );
		$this->assertEquals( '~help:test prefix:help_talk:test', $context->getOriginalSearchTerm() );
		$this->assertEquals( [ 'foo' => 'bar' ], $context->getProfileContextParams() );
		$this->assertEquals( SearchProfileService::CONTEXT_DEFAULT, $context->getProfileContext() );
		$this->assertSame( $myFallbackRunner, $context->getFallbackRunner() );
		$this->assertTrue( $context->isSpecialKeywordUsed() );
		$this->assertFalse( $context->isSyntaxUsed( BasicQueryClassifier::SIMPLE_BAG_OF_WORDS ) );
		$this->assertTrue( $context->isSyntaxUsed( BasicQueryClassifier::COMPLEX_QUERY ) );
	}

	public function testForCrossProjectSearch() {
		$nbRes = rand( 1, 10 );
		$hostWikiConfig = $this->newHashSearchConfig( [
			'CirrusSearchNumCrossProjectSearchResults' => $nbRes,
			'CirrusSearchEnableCrossProjectSearch' => true,
			'CirrusSearchRescoreProfiles' => [
				'foo' => [],
				'common' => []
			]
		] );
		$targetWikiConfig = $this->newHashSearchConfig( [
			'_wikiID' => 'target',
			'CirrusSearchRescoreProfiles' => [
				'common' => []
			]
		] );

		// Keep the $builder around so that we can reuse it for multiple queries & assertions.
		$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $hostWikiConfig, 'myquery', $this->namespacePrefixParser() )
			->addForcedProfile( SearchProfileService::RESCORE, 'foo' )
			->addProfileContextParameter( 'foo', 'bar' );

		$hostWikiQuery = $builder->build();
		$crossSearchQuery = SearchQueryBuilder::forCrossProjectSearch( $targetWikiConfig,
			$hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig,
			[ NS_MAIN ], [ NS_MAIN ], 'relevance', 0, $nbRes );

		$hostWikiQuery = $builder->setOffset( 10 )
			->setLimit( 100 )
			->addForcedProfile( SearchProfileService::RESCORE, 'foo' )
			->setInitialNamespaces( [ NS_MAIN, NS_HELP, 100 ] )
			->setSort( 'size' )
			->setDebugOptions( CirrusDebugOptions::forDumpingQueriesInUnitTests() )
			->build();
		$crossSearchQuery = SearchQueryBuilder::forCrossProjectSearch( $targetWikiConfig,
			$hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig,
			[ NS_MAIN, NS_HELP ], [ NS_MAIN, NS_HELP ], 'size', 0, $nbRes,
			CirrusDebugOptions::forDumpingQueriesInUnitTests() );

		// Test that forced profiles do not propagate to the cross search query if they do not exist
		// on the target wiki. Forced profiles are only set using official API params, cirrus debug
		// params may still allow to force a specific profile using the overrides chain.
		$hostWikiQuery = $builder->addForcedProfile( SearchProfileService::RESCORE, 'common' )
			->build();
		$crossSearchQuery = SearchQueryBuilder::forCrossProjectSearch( $targetWikiConfig, $hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig,
			[ NS_MAIN, NS_HELP ], [ NS_MAIN, NS_HELP ], 'size', 0, $nbRes,
			CirrusDebugOptions::forDumpingQueriesInUnitTests(), 'common' );
	}

	public function testForCrossLanguageSearch() {
		$hostWikiConfig = $this->newHashSearchConfig( [
			'CirrusSearchEnableAltLanguage' => true,
			'CirrusSearchRescoreProfiles' => [
				'foo' => [],
				'common' => []
			]
		] );
		$targetWikiConfig = $this->newHashSearchConfig( [
			'_wikiID' => 'target',
			'CirrusSearchRescoreProfiles' => [
				'common' => []
			]
		] );

		// Keep the $builder around so that we can reuse it for multiple queries & assertions.
		$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $hostWikiConfig, 'myquery', $this->namespacePrefixParser() )
			->addForcedProfile( SearchProfileService::RESCORE, 'foo' )
			->addProfileContextParameter( 'foo', 'bar' );
		$hostWikiQuery = $builder->build();
		$crossSearchQuery = SearchQueryBuilder::forCrossLanguageSearch( $targetWikiConfig,
			$hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig );

		$hostWikiQuery = $builder->setOffset( 10 )
			->setLimit( 100 )
			->addForcedProfile( SearchProfileService::RESCORE, 'foo' )
			->setInitialNamespaces( [ NS_MAIN, NS_HELP, 100 ] )
			->setSort( 'size' )
			->setDebugOptions( CirrusDebugOptions::forDumpingQueriesInUnitTests() )
			->build();

		$crossSearchQuery = SearchQueryBuilder::forCrossLanguageSearch( $targetWikiConfig,
			$hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig,
			[ NS_MAIN, NS_HELP ], [ NS_MAIN, NS_HELP ], 'size', 10, 100,
			CirrusDebugOptions::forDumpingQueriesInUnitTests() );

		$hostWikiQuery = $builder->addForcedProfile( SearchProfileService::RESCORE, 'common' )
			->build();
		$crossSearchQuery = SearchQueryBuilder::forCrossLanguageSearch( $targetWikiConfig, $hostWikiQuery )->build();
		$this->copyForCrossSearchAssertions( $hostWikiQuery, $crossSearchQuery, $targetWikiConfig,
			[ NS_MAIN, NS_HELP ], [ NS_MAIN, NS_HELP ], 'size', 10, 100,
			CirrusDebugOptions::forDumpingQueriesInUnitTests(), 'common' );
	}

	private function copyForCrossSearchAssertions(
		SearchQuery $hostWikiQuery,
		SearchQuery $crossSearchQuery,
		SearchConfig $targetWikiConfig,
		$initialNs = [ NS_MAIN ],
		$namespaces = [ NS_MAIN ],
		$sortOptions = 'relevance',
		$offset = 0,
		$limit = 10,
		CirrusDebugOptions $options = null,
		$forcedRescoreProfile = null
	) {
		$this->assertEquals( $hostWikiQuery->getParsedQuery(), $crossSearchQuery->getParsedQuery() );
		$this->assertEquals( CrossSearchStrategy::hostWikiOnlyStrategy(),
			$crossSearchQuery->getInitialCrossSearchStrategy() );
		$this->assertEquals( CrossSearchStrategy::hostWikiOnlyStrategy(),
			$crossSearchQuery->getCrossSearchStrategy() );
		$this->assertEquals( SearchQuery::SEARCH_TEXT, $crossSearchQuery->getSearchEngineEntryPoint() );
		$this->assertEquals( $initialNs, $crossSearchQuery->getInitialNamespaces() );
		$this->assertEquals( $namespaces, $crossSearchQuery->getNamespaces() );
		$this->assertEquals( $sortOptions, $crossSearchQuery->getSort() );
		$this->assertEquals( $options ?? CirrusDebugOptions::defaultOptions(),
			$crossSearchQuery->getDebugOptions() );
		$this->assertEquals( $targetWikiConfig, $crossSearchQuery->getSearchConfig() );
		$this->assertEmpty( $crossSearchQuery->getContextualFilters() );
		$this->assertFalse( $crossSearchQuery->isWithDYMSuggestion() );
		$this->assertFalse( $crossSearchQuery->isAllowRewrite() );
		$this->assertEquals( $offset, $crossSearchQuery->getOffset() );
		$this->assertEquals( $limit, $crossSearchQuery->getLimit() );
		$this->assertEquals( [ 'foo' => 'bar' ], $crossSearchQuery->getProfileContextParameters() );
		if ( $forcedRescoreProfile !== null ) {
			$this->assertEquals( $forcedRescoreProfile, $crossSearchQuery->getForcedProfile( SearchProfileService::RESCORE ) );
		} else {
			$this->assertFalse( $crossSearchQuery->hasForcedProfile() );
		}
	}

	public function testforRewrittenQuery() {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchEnableAltLanguage' => true,
			'CirrusSearchEnableCrossProjectSearch' => true,
		] );
		$builder = SearchQueryBuilder::newFTSearchQueryBuilder( $config, 'fooba\\?', $this->namespacePrefixParser() )
			->addForcedProfile( SearchProfileService::RESCORE, 'foobar' )
			->addContextualFilter( 'hop', $this->getContextualFilter() )
			->setLimit( 100 )
			->setOffset( 10 )
			->setSort( 'size' )
			->setAllowRewrite( true )
			->setInitialNamespaces( [ NS_HELP, NS_FILE ] )
			->setDebugOptions( CirrusDebugOptions::forDumpingQueriesInUnitTests() )
			->addProfileContextParameter( 'foo', 'bar' );
		$query = $builder->build();

		$rewritten = SearchQueryBuilder::forRewrittenQuery( $query, 'foobar?', $this->namespacePrefixParser() )->build();
		$this->assertFalse( $rewritten->getParsedQuery()->hasCleanup( ParsedQuery::CLEANUP_QMARK_STRIPPING ) );
		$this->assertFalse( $rewritten->getInitialCrossSearchStrategy()->isCrossLanguageSearchSupported() );
		$this->assertFalse( $rewritten->getInitialCrossSearchStrategy()->isCrossProjectSearchSupported() );
		$this->assertTrue( $rewritten->getInitialCrossSearchStrategy()->isExtraIndicesSearchSupported() );
		$this->assertFalse( $rewritten->isWithDYMSuggestion() );
		$this->assertFalse( $rewritten->isAllowRewrite() );

		$this->assertEquals( $query->getDebugOptions(), $rewritten->getDebugOptions() );
		$this->assertEquals( [ 'foo' => 'bar' ], $query->getProfileContextParameters() );
		// FIXME: config is a bit differrent to disable quotation mark stripping
		// $this->assertEquals( $query->getSearchConfig(), $rewritten->getSearchConfig() );
		$this->assertEquals( $query->getInitialNamespaces(), $rewritten->getInitialNamespaces() );
		$this->assertEquals( $query->getSort(), $rewritten->getSort() );
		$this->assertEquals( 100, $rewritten->getLimit() );
		$this->assertEquals( 10, $rewritten->getOffset() );
		$this->assertEquals( $query->getForcedProfiles(), $rewritten->getForcedProfiles() );
		$this->assertEquals( $query->getContextualFilters(), $rewritten->getContextualFilters() );

		$query = $builder->setExtraIndicesSearch( false )->build();
		$rewritten = SearchQueryBuilder::forRewrittenQuery( $query, 'foobar?', $this->namespacePrefixParser() )->build();
		$this->assertFalse( $rewritten->getInitialCrossSearchStrategy()->isExtraIndicesSearchSupported() );
	}
}
