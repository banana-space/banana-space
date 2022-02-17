<?php

namespace CirrusSearch;

use CirrusSearch\Query\FullTextQueryStringQueryBuilder;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Test\DummyConnection;
use CirrusSearch\Test\SearchConfigUsageDecorator;
use Elastica\Query;
use Elastica\Response;
use HtmlArmor;
use MediaWiki\MediaWikiServices;
use Title;

/**
 * @covers \CirrusSearch\Searcher
 * @group CirrusSearch
 * @group Standalone
 */
class SearcherTest extends CirrusIntegrationTestCase {

	public function setUp() : void {
		parent::setUp();
		MediaWikiServices::getInstance()->getConfigFactory()->register( 'CirrusSearch',
			function () {
				return new SearchConfigUsageDecorator();
			}
		);
	}

	public function tearDown() : void {
		MediaWikiServices::getInstance()
			->resetServiceForTesting( 'ConfigFactory' );
		parent::tearDown();
	}

	public function searchTextProvider() {
		$configs = [];
		// globals overrides. All tests will be run for each defined configuration
		foreach ( CirrusIntegrationTestCase::findFixtures( 'searchText/*.config' ) as $configFile ) {
			$configName = substr( basename( $configFile ), 0, -7 );
			$configs[$configName] = CirrusIntegrationTestCase::loadFixture( $configFile );
		}
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'searchText/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, -6 );
			$querySettings = CirrusIntegrationTestCase::loadFixture( $queryFile );
			foreach ( $configs as $configName => $config ) {
				$expectedFile = substr( $queryFile, 0, -5 ) . $configName . '.expected';
				$expected = CirrusIntegrationTestCase::hasFixture( $expectedFile )
					? CirrusIntegrationTestCase::loadFixture( $expectedFile )
					// Flags test to generate a new fixture
					: $expectedFile;
				if ( isset( $querySettings['config'] ) ) {
					$config = $querySettings['config'] + $config;
				}
				$tests["{$testName}-{$configName}"] = [
					$config,
					$expected,
					$querySettings['query'],
					$querySettings['sort'] ?? 'relevance'
				];
			}
		}

		return self::randomizeFixtures( $tests );
	}

	/**
	 * @dataProvider searchTextProvider
	 */
	public function testSearchText( array $config, $expected, $queryString, $sort ) {
		// Override some config for parsing purposes
		$this->setMwGlobals( $config + [
			// We want to override the wikiid for consistent output, but this might break everything else...
			'wgCirrusSearchExtraIndexes' => [],
			'wgCirrusSearchExtraIndexBoostTemplates' => [],
			'wgCirrusSearchIndexBaseName' => 'wiki',
			'wgCirrusSearchUseExperimentalHighlighter' => true,
			'wgCirrusSearchWikimediaExtraPlugin' => [
				'regex' => [ 'build', 'use' ],
			],
			'wgCirrusSearchQueryStringMaxDeterminizedStates' => 500,
			'wgContentNamespaces' => [ NS_MAIN ],
			// Override the list of namespaces to give more deterministic results
			'wgHooks' => [
				'CanonicalNamespaces' => [
					function ( &$namespaces ) {
						$namespaces = [
							0 => '',
							-2 => 'Media',
							-1 => 'Special',
							1 => 'Talk',
							2 => 'User',
							3 => 'User_talk',
							4 => 'Project',
							5 => 'Project_talk',
							6 => 'File',
							7 => 'File_talk',
							8 => 'MediaWiki',
							9 => 'MediaWiki_talk',
							10 => 'Template',
							11 => 'Template_talk',
							12 => 'Help',
							13 => 'Help_talk',
							14 => 'Category',
							15 => 'Category_talk',
						];
					}
				],
			] + $GLOBALS['wgHooks']
		] );

		// Set a couple pre-defined pages for anything (morelike) that needs valid pages
		$linkCache = MediaWikiServices::getInstance()->getLinkCache();
		$linkCache->addGoodLinkObj( 12345, Title::newFromText( 'Some page' ) );
		$linkCache->addGoodLinkObj( 23456, Title::newFromText( 'Other page' ) );

		$engine = new CirrusSearch( null, CirrusDebugOptions::forDumpingQueriesInUnitTests() );
		// Set some default namespaces, otherwise installed extensions will change
		// the generated query
		$engine->setNamespaces( [
			NS_MAIN, NS_TALK, NS_USER, NS_USER_TALK,
		] );
		$engine->setShowSuggestion( true );
		$engine->setLimitOffset( 20, 0 );
		$engine->setSort( $sort );

		$encodedQuery = $engine->searchText( $queryString )->getValue();
		$elasticQuery = json_decode( $encodedQuery, true );
		// Drop the keys to keep fixture clean
		// For extra fun, prefer-recent queries include a 'now' timestamp. We need to normalize that so
		// the output is actually the same.
		$elasticQuery = $this->normalizeNow( $elasticQuery );
		// random seeds also need to be made constant
		$elasticQuery = $this->normalizeSeed( $elasticQuery );
		// The helps with ensuring if there are minor code changes that change the ordering,
		// regenerating the fixture wont cause changes. Do it always, instead of only when
		// writing, so that the diff's from phpunit are also as minimal as possible.
		$elasticQuery = $this->normalizeOrdering( $elasticQuery );

		if ( is_string( $expected ) ) {
			// Flag to generate a new fixture.
			CirrusIntegrationTestCase::saveFixture( $expected, $elasticQuery );
		} else {
			// Repeat normalizations applied to $elasticQuery
			$expected = $this->normalizeNow( $expected );

			// Finally compare some things
			$this->assertEquals( $expected, $elasticQuery, $encodedQuery );
		}
		$this->assertConfigIsExported();
	}

	/**
	 * @var string[] List of false positives detected by the assertions below
	 * Add config vars when you don't want to explicit export it and are sure
	 * that it won't be needed to build query on a target wiki.
	 */
	private static $CONFIG_VARS_FALSE_POSITIVES = [
		'CirrusSearchFetchConfigFromApi', // Should not be needed to build a crosswiki search
		'DBname',
		'SiteMatrixSites',
		'CirrusSearchInterwikiPrefixOverrides',
		'CirrusSearchCrossClusterSearch', // We explicitly want this to fall through to local wiki conf
		'CirrusSearchInterwikiHTTPConnectTimeout', // Needed to fetch crosswiki config
		'CirrusSearchInterwikiHTTPTimeout' // Needed to fetch crosswiki config
	];

	private function assertConfigIsExported() {
		try {
			$notInApi = [];
			$notInSearchConfig = [];
			foreach ( array_keys( SearchConfigUsageDecorator::getUsedConfigKeys() ) as $k ) {
				if ( in_array( $k, self::$CONFIG_VARS_FALSE_POSITIVES ) ) {
					continue;
				}
				if ( !in_array( $k, \CirrusSearch\Api\ConfigDump::$WHITE_LIST ) ) {
					$notInApi[] = $k;
				}
				if ( preg_match( '/^CirrusSearch/', $k ) == 0 ) {
					if ( !in_array( 'wg' . $k, SearchConfig::getNonCirrusConfigVarNames() ) ) {
						$notInSearchConfig[] = $k;
					}
				}
			}
			$this->assertEmpty( $notInApi, implode( ',', $notInApi ) .
				" are exported from \CirrusSearch\Api\ConfigDump" );
			$this->assertEmpty( $notInSearchConfig, implode( ',', $notInSearchConfig ) .
				" are allowed in SearchConfig::getNonCirrusConfigVarNames()" );
		} finally {
			SearchConfigUsageDecorator::resetUsedConfigKeys();
		}
	}

	private function normalizeNow( array $query ) {
		array_walk_recursive( $query, function ( &$value, $key ) {
			if ( $key === 'now' && is_int( $value ) ) {
				$value = 1468084245000;
			}
		} );

		return $query;
	}

	private function normalizeSeed( array $query ) {
		array_walk_recursive( $query, function ( &$value, $key ) {
			if ( $key === 'seed' && is_string( $value ) ) {
				$value = 'phpunit searchText random seed';
			}
		} );

		return $query;
	}

	private function normalizeOrdering( array $query, $topLevel = true ) {
		foreach ( $query as $key => $value ) {
			if ( is_array( $value ) ) {
				$query[$key] = $this->normalizeOrdering( $value, false );
			}
		}
		if ( $topLevel ) {
			return $query;
		}
		if ( isset( $query[0] ) ) {
			// list like. Expensive, but sorta-works?
			// TODO: This breaks things that require a specific ordering, such as the token count router
			usort( $query, function ( $a, $b ) {
				return strcmp( json_encode( $a ), json_encode( $b ) );
			} );
		} else {
			// dict like
			ksort( $query );
		}

		return $query;
	}

	public function archiveFixtureProvider() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'archiveSearch/*.query' ) as $queryFile ) {
			$testName = substr( basename( $queryFile ), 0, - 6 );
			$query = file_get_contents( CirrusTestCaseTrait::$FIXTURE_DIR . $queryFile );
			// Remove trailing newline
			$query = preg_replace( '/\n$/', '', $query );
			$expectedFile = substr( $queryFile, 0, -5 ) . 'expected';
			$expected = CirrusIntegrationTestCase::hasFixture( $expectedFile )
					? CirrusIntegrationTestCase::loadFixture( $expectedFile )
					// Flags test to generate a new fixture
					: $expectedFile;
			$tests[$testName] = [
				$expected,
				$query,
			];

		}
		return $tests;
	}

	/**
	 * @dataProvider archiveFixtureProvider
	 * @param mixed $expected
	 * @param array $query
	 */
	public function testArchiveQuery( $expected, $query ) {
		$this->setMwGlobals( [
				'wgCirrusSearchIndexBaseName' => 'wiki',
				'wgCirrusSearchQueryStringMaxDeterminizedStates' => 500,
				'wgContentNamespaces' => [ NS_MAIN ],
				'wgCirrusSearchEnableArchive' => true,
		] );

		$title = Title::newFromText( $query );
		if ( $title ) {
			$ns = $title->getNamespace();
			$termMain = $title->getText();
		} else {
			$ns = 0;
			$termMain = $query;
		}

		$engine = new CirrusSearch( null, CirrusDebugOptions::forDumpingQueriesInUnitTests() );
		$engine->setLimitOffset( 20, 0 );
		$engine->setNamespaces( [ $ns ] );
		$elasticQuery = $engine->searchArchiveTitle( $termMain )->getValue();
		$decodedQuery = json_decode( $elasticQuery, true );
		if ( is_string( $expected ) ) {
			// Flag to generate a new fixture.
			CirrusIntegrationTestCase::saveFixture( $expected, $decodedQuery );
		} else {

			// Finally compare some things
			$this->assertEquals( $expected, $decodedQuery, $elasticQuery );
		}
	}

	public function testImpossibleQueryResults() {
		$engine = new CirrusSearch();
		// query is invalid, filesize:> needs an integer
		$status = $engine->searchText( 'filesize:>q' );
		$this->assertTrue( $status->isOK(), 'search didnt fail' );
		$this->assertFalse( $status->isGood(), 'but it has warnings' );
		$this->assertTrue( $status->getValue()->searchContainedSyntax(), 'it used special syntax' );
		$this->assertSame( 0, $status->getValue()->numRows(), 'and returned no results' );
	}

	public function testApplyDebugOptions() {
		$config = new HashSearchConfig( [] );
		$searcher = new Searcher( new DummyConnection(), 0, 20, $config,
			[], null, false,
			CirrusDebugOptions::fromRequest( new \FauxRequest( [ 'cirrusExplain' => 'pretty' ] ) ) );
		$query = new Query();
		$searcher->applyDebugOptionsToQuery( $query );
		$this->assertTrue( $query->getParam( 'explain' ) );

		$searcher = new Searcher( new DummyConnection(), 0, 20, $config,
			[], null, null,
			CirrusDebugOptions::fromRequest( new \FauxRequest() ) );
		$query = new Query();
		$searcher->applyDebugOptionsToQuery( $query );
		$this->assertFalse( $query->hasParam( 'explain' ) );
	}

	public function provideTestOffsetLimitBounds() {
		return [
			'ok' => [
				5000, 5000,
				[ 5000, 5000 ]
			],
			'out of bounds but repairable' => [
				5000, 5001,
				[ 5000, 5000 ]
			],
			'out of bounds non repairable' => [
				10000, 10,
				[ 10000, 0 ]
			],
			'out of bounds non repairable (2)' => [
				10010, 10,
				[ 10010, -10 ]
			],
		];
	}

	/**
	 * @dataProvider provideTestOffsetLimitBounds
	 */
	public function testOffsetLimitBounds( $offset, $limit, $expected ) {
		$conf = new HashSearchConfig( [], [ HashSearchConfig::FLAG_INHERIT ] );
		$searcher = new Searcher( new DummyConnection( $conf ), $offset, $limit, $conf );
		$this->assertEquals( $expected, $searcher->getOffsetLimit() );
		$searcher = new Searcher( new DummyConnection( $conf ), 0, 20, $conf );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( $conf, 'test', $this->namespacePrefixParser() )
			->setDebugOptions( CirrusDebugOptions::forDumpingQueriesInUnitTests() )
			->setOffset( $offset )
			->setLimit( $limit );
		$searcher->search( $query->build() );
		$this->assertEquals( $expected, $searcher->getOffsetLimit() );
	}

	public function provideTestSuggestQueries() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'phraseSuggest/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$expectedFile,
				$fixture['query'],
				$fixture['namespaces'],
				$fixture['offset'],
				$fixture['with_dym'] ?? true,
				$fixture['config']
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestSuggestQueries()
	 */
	public function testPhraseSuggest( $expectedFile, $query, $namespaces, $offset, $withDym, $config ) {
		$engine = new CirrusSearch( new HashSearchConfig( $config + [
					'CirrusSearchPhraseSuggestReverseField' => [ 'use' => false ],
				], [ HashSearchConfig::FLAG_INHERIT ] ),
				CirrusDebugOptions::forDumpingQueriesInUnitTests() );
		$engine->setShowSuggestion( $withDym );
		$engine->setNamespaces( $namespaces );
		$engine->setLimitOffset( 20, $offset );
		$status = $engine->searchText( $query );
		$res = json_decode( $status->getValue(), JSON_OBJECT_AS_ARRAY );
		$q = null;
		if ( isset( $res[Searcher::MAINSEARCH_MSEARCH_KEY]['query']['suggest'] ) ) {
			$q = [ 'suggest' => $res[Searcher::MAINSEARCH_MSEARCH_KEY]['query']['suggest'] ];
		}
		$this->assertFileContains(
			CirrusIntegrationTestCase::fixturePath( $expectedFile ),
			CirrusIntegrationTestCase::encodeFixture( $q ),
			self::canRebuildFixture()
		);
	}

	public function providePhraseSuggestResponse() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'phraseSuggestResponses/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, - 7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$tests[$testName] = [
				$fixture['query'],
				$fixture['response'],
				$fixture['approxScore'],
				$fixture['suggestion'],
				$fixture['suggestionSnippet'],
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider providePhraseSuggestResponse
	 */
	public function testPhraseSuggestResponse( $query, $response, $approxScore, $suggestion, $suggestionSnippet ) {
		$this->setMwGlobals( [ 'wgCirrusSearchLogElasticRequests' => false ] );
		$rewrittenResponse = new \Elastica\Response( json_encode(
			[
				'status' => 200,
				'responses' => [
					[
						'hits' => [ 'total' => 123456, 'max_score' => 0.0, 'hits' => [] ]
					]
			] ] )
		);
		$transport = $this->mockTransportWithResponse(
			new Response( [
				'status' => 200,
				'responses' => [ $response ]
			] ),
			$rewrittenResponse
		);
		$config = new HashSearchConfig( [
			'CirrusSearchLogElasticRequests' => false,
			'CirrusSearchDefaultCluster' => 'default',
			'CirrusSearchClusters' => [
				'default' => [
					[ 'transport' => $transport ]
				]
			],
			'CirrusSearchEnablePhraseSuggest' => true,
			'CirrusSearchPhraseSuggestReverseField' => [ 'use' => false ],
		], [ HashSearchConfig::FLAG_INHERIT ] );
		$engine = new CirrusSearch( $config );
		$engine->setFeatureData( 'rewrite', true );
		$engine->setShowSuggestion( true );
		/**
		 * @var CirrusSearchResultSet $resultSet
		 */
		$resultSet = $engine->searchText( $query )->getValue();

		$engine = new CirrusSearch( $config, CirrusDebugOptions::forDumpingQueriesInUnitTests() );
		$engine->setFeatureData( 'rewrite', true );
		$engine->setShowSuggestion( true );
		$query = json_decode( $engine->searchText( $query )->getValue(), JSON_OBJECT_AS_ARRAY );

		if ( $approxScore > 0 ) {
			$this->assertArrayHasKey( 'suggest', $query[Searcher::MAINSEARCH_MSEARCH_KEY]['query'] );
			$this->assertEquals( $response['suggest']['suggest'][0]['text'],
				$query[Searcher::MAINSEARCH_MSEARCH_KEY]['query']['suggest']['text'] );

			if ( $resultSet->getTotalHits() === 123456 ) {
				$this->assertEquals( $suggestion, $resultSet->getQueryAfterRewrite() );

				$this->assertEquals( $suggestionSnippet,
					HtmlArmor::getHtml( $resultSet->getQueryAfterRewriteSnippet() ) );
			} else {
				$this->assertNull( $resultSet->getQueryAfterRewrite() );
				$this->assertNull( $resultSet->getQueryAfterRewriteSnippet() );
				$this->assertEquals( $suggestion, $resultSet->getSuggestionQuery() );
				$this->assertEquals( $suggestionSnippet,
					HtmlArmor::getHtml( $resultSet->getSuggestionSnippet() ) );
			}
		} else {
			$this->assertArrayNotHasKey( 'suggest', $query[Searcher::MAINSEARCH_MSEARCH_KEY] );
		}
	}

	/**
	 * @covers \CirrusSearch\Searcher::buildFullTextBuilder()
	 * @throws \ReflectionException
	 */
	public function testBuildFullTextBuilder() {
		$config = new HashSearchConfig( [] );
		$mysettings = [ 'random' => 'data' ];

		$profile = [ 'builder_class' => FullTextQueryStringQueryBuilder::class, 'settings' => $mysettings ];
		$this->assertEquals( new FullTextQueryStringQueryBuilder( $config, [], $mysettings ),
			Searcher::buildFullTextBuilder( $profile, $config, [] ) );

		$profile = [
			'builder_factory' => function ( $settings ) use ( $config, $mysettings ) {
				$this->assertSame( $mysettings, $settings );
				return new FullTextQueryStringQueryBuilder( $config, [], $settings );
			},
			'settings' => $mysettings
		];
		$this->assertInstanceOf( FullTextQueryStringQueryBuilder::class,
			Searcher::buildFullTextBuilder( $profile, $config, [] ) );
	}
}
