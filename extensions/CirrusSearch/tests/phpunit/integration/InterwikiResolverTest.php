<?php

namespace CirrusSearch\Test;

use CirrusSearch\CirrusConfigInterwikiResolver;
use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\EmptyInterwikiResolver;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\InterwikiResolver;
use CirrusSearch\InterwikiResolverFactory;
use CirrusSearch\SiteMatrixInterwikiResolver;
use ExtensionRegistry;
use MediaWiki\MediaWikiServices;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\CirrusConfigInterwikiResolver
 * @covers \CirrusSearch\SiteMatrixInterwikiResolver
 * @covers \CirrusSearch\BaseInterwikiResolver
 * @covers \CirrusSearch\InterwikiResolverFactory
 * @covers \CirrusSearch\EmptyInterwikiResolver
 */
class InterwikiResolverTest extends CirrusIntegrationTestCase {
	/**
	 * @return bool
	 */
	public function testCirrusConfigInterwikiResolver() {
		$resolver = $this->getCirrusConfigInterwikiResolver();

		// Test wikiId => prefix map
		$this->assertEquals( 'fr', $resolver->getInterwikiPrefix( 'frwiki' ) );
		$this->assertEquals( 'no', $resolver->getInterwikiPrefix( 'nowiki' ) );
		$this->assertEquals( 'b', $resolver->getInterwikiPrefix( 'enwikibooks' ) );
		$this->assertNull( $resolver->getInterwikiPrefix( 'simplewiki' ) );
		$this->assertNull( $resolver->getInterwikiPrefix( 'enwiki' ) );

		// Test sister projects
		$this->assertArrayHasKey( 'voy', $resolver->getSisterProjectPrefixes() );
		$this->assertArrayHasKey( 'b', $resolver->getSisterProjectPrefixes() );
		$this->assertEquals( 'enwikivoyage', $resolver->getSisterProjectPrefixes()['voy'] );
		$this->assertArrayNotHasKey( 'commons', $resolver->getSisterProjectPrefixes() );

		// Test by-language lookup
		$this->assertEquals(
			[ 'frwiki', 'fr' ],
			$resolver->getSameProjectWikiByLang( 'fr' )
		);
		$this->assertEquals(
			[ 'nowiki', 'no' ],
			$resolver->getSameProjectWikiByLang( 'no' )
		);
		$this->assertEquals(
			[ 'nowiki', 'no' ],
			$resolver->getSameProjectWikiByLang( 'nb' )
		);
		$this->assertEquals(
			[],
			$resolver->getSameProjectWikiByLang( 'ccc' )
		);
		$this->assertEquals(
			[],
			$resolver->getSameProjectWikiByLang( 'en' ),
			'enwiki should not find itself.'
		);
	}

	/**
	 * @dataProvider provideSiteMatrixTestCases
	 * @param string $wiki
	 * @param string $what method to test
	 * @param mixed $arg arg to $what
	 * @param mixed $expected expected result of $what($arg)
	 * @param string[]|null $blacklist
	 * @param string[]|null $overrides
	 */
	public function testSiteMatrixResolver( $wiki, $what, $arg, $expected,
			$blacklist = [], $overrides = [] ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SiteMatrix' ) ) {
			$this->markTestSkipped( 'SiteMatrix not available.' );
		}

		$resolver = $this->getSiteMatrixInterwikiResolver( $wiki, $blacklist, $overrides );
		switch ( $what ) {
		case 'sisters':
			asort( $expected );
			$actual = $resolver->getSisterProjectPrefixes();
			asort( $actual );

			$this->assertEquals(
				$expected,
				$actual
			);
			break;
		case 'interwiki':
			$this->assertEquals(
				$expected,
				$resolver->getInterwikiPrefix( $arg )
			);
			break;
		case 'crosslang':
			$this->assertEquals(
				$expected,
				$resolver->getSameProjectWikiByLang( $arg )
			);
			break;
		default:
			throw new \Exception( "Invalid op $what" );
		}
	}

	public static function provideSiteMatrixTestCases() {
		return [
			'enwiki sisters' => [
				'enwiki',
				'sisters', null,
				[
					'wikt' => 'enwiktionary',
					'b' => 'enwikibooks',
					'n' => 'enwikinews',
					'q' => 'enwikiquote',
					's' => 'enwikisource',
					'v' => 'enwikiversity',
					'voy' => 'enwikivoyage'
				]
			],
			'enwiki sisters with overrides' => [
				'enwiki',
				'sisters', null,
				[
					'wikt' => 'enwiktionary',
					'b' => 'enwikibooks',
					'n' => 'enwikinews',
					'q' => 'enwikiquote',
					'src' => 'enwikisource',
					'v' => 'enwikiversity',
					'voy' => 'enwikivoyage'
				],
				[],
				[ 's' => 'src' ]
			],
			'enwiki sisters with blacklist and overrides' => [
				'enwiki',
				'sisters', null,
				[
					'wikt' => 'enwiktionary',
					'src' => 'enwikisource',
					'voy' => 'enwikivoyage'
				],
				[ 'n', 'books', 'q', 'v' ],
				[ 's' => 'src', 'b' => 'books' ]

			],
			'enwikibook sisters' => [
				'enwikibooks',
				'sisters', null,
				[
					'wikt' => 'enwiktionary',
					'w' => 'enwiki',
					'n' => 'enwikinews',
					'q' => 'enwikiquote',
					's' => 'enwikisource',
					'v' => 'enwikiversity',
					'voy' => 'enwikivoyage'
				]
			],
			'mywiki sisters load only open projects' => [
				'mywiki',
				'sisters', null,
				[
					'wikt' => 'mywiktionary'
				],
			],
			'enwiki interwiki can find sister projects project enwikibooks' => [
				'enwiki',
				'interwiki', 'enwikibooks',
				'b'
			],
			'enwiki interwiki can find same project other lang: frwiki' => [
				'enwiki',
				'interwiki', 'frwiki',
				'fr'
			],
			'enwiki interwiki cannot find other project other lang: frwiktionary' => [
				'enwiki',
				'interwiki', 'frwiktionary',
				null
			],
			'enwiki interwiki cannot find itself' => [
				'enwiki',
				'interwiki', 'enwiki',
				null
			],
			'enwiki interwiki can find project with non default lang: nowiki' => [
				'enwiki',
				'interwiki', 'nowiki',
				'no'
			],
			'enwiki interwiki ignores closed projects: mowiki' => [
				'enwiki',
				'interwiki', 'mowiki',
				null
			],
			'enwiki interwiki ignores projects not directly with lang/project: officewiki' => [
				'enwiki',
				'interwiki', 'officewiki',
				null
			],
			'frwikinews interwiki ignore inexistent projects: mywikinews' => [
				'frwikinews',
				'interwiki', 'mywikinews',
				null
			],
			'enwiki cross lang lookup finds frwiki' => [
				'enwiki',
				'crosslang', 'fr',
				[ 'frwiki', 'fr' ],
			],
			'enwiki cross lang lookup finds nowiki' => [
				'enwiki',
				'crosslang', 'nb',
				[ 'nowiki', 'no' ],
			],
			'enwikinews cross lang lookup finds frwikinews' => [
				'enwikinews',
				'crosslang', 'fr',
				[ 'frwikinews', 'fr' ],
			],
			'enwikinews cross lang lookup cannot find inexistent hawwikinews' => [
				'enwikinews',
				'crosslang', 'haw',
				[],
			],
			'enwikinews cross lang lookup cannot find closed nlwikinews' => [
				'enwikinews',
				'crosslang', 'nl',
				[],
			],
			'enwikinews cross lang lookup should not find itself' => [
				'enwikinews',
				'crosslang', 'en',
				[],
			],
		];
	}

	public function testLoadConfigForCrossProject() {
		$this->setMwGlobals( [ 'wgCirrusSearchRescoreProfile' => 'test_inheritance' ] );
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SiteMatrix' ) ) {
			$this->markTestSkipped( 'SiteMatrix not available.' );
		}
		$fixtureFile = 'configDump/enwiki_sisterproject_configs.json';
		if ( !CirrusIntegrationTestCase::hasFixture( $fixtureFile ) ) {
			if ( self::canRebuildFixture() ) {
				CirrusIntegrationTestCase::saveFixture( $fixtureFile, $this->genSisterProjectFixtures() );
				$this->markTestSkipped();
				return;
			} else {
				$this->fail( 'Missing fixture file ' . $fixtureFile );
			}
		}
		$apiResponse = CirrusIntegrationTestCase::loadFixture( $fixtureFile );

		$client = $this->getMockBuilder( \MultiHttpClient::class )
			->disableOriginalConstructor()
			->getMock();
		$client->expects( $this->any() )
			->method( 'runMulti' )
			->will( $this->returnValue( $apiResponse ) );
		$resolver = $this->getSiteMatrixInterwikiResolver( 'enwiki', [], [], $client );
		$configs = $resolver->getSisterProjectConfigs();

		$this->assertEquals( array_keys( $configs ), array_keys( $resolver->getSisterProjectPrefixes() ) );

		$wikis = [
			'enwiktionary' => true,
			'enwikiversity' => true,
			'enwikivoyage' => true,
			'enwikinews' => false,
			'enwikibooks' => false,
			'enwikiquote' => false,
			'enwikisource' => false,
		];
		foreach ( $wikis as $wikiId => $validConfig ) {
			$prefix = $resolver->getInterwikiPrefix( $wikiId );
			$this->assertNotNull( $prefix, "$wikiId does not seem to exist" );
			$this->assertArrayHasKey( $prefix, $configs,
				"config for $wikiId is available" );
			$this->assertEquals( $configs[$prefix]->getWikiId(), $wikiId,
				"config $wikiId has valid wikiId" );
			$this->assertEquals( $wikiId, $configs[$prefix]->get( 'CirrusSearchIndexBaseName' ),
				"config for $wikiId has valid CirrusSearchIndexBaseName" );
			$this->assertEquals( !$validConfig,
				$configs[$prefix]->get( 'CirrusSearchRescoreProfile' ) === 'test_inheritance',
				"config for $wikiId is" . ( !$validConfig ? " not" : "" ) .
				" rescued using a fallback config" );
		}
	}

	public function provideTestLoadConfigForCrossLang() {
		return [
			'enwiki loads frwiki config properly' => [ true ],
			'enwiki loads frwiki config and fails' => [ false ],
		];
	}

	/**
	 * @dataProvider provideTestLoadConfigForCrossLang
	 * @param bool $valid
	 * @throws \MWException
	 */
	public function testLoadConfigForCrossLang( $valid ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SiteMatrix' ) ) {
			$this->markTestSkipped( 'SiteMatrix not available.' );
		}
		$this->setMwGlobals( [ 'wgCirrusSearchRescoreProfile' => 'test_inheritance' ] );
		$fixtureFile = 'configDump/enwiki_crosslang_frwiki' . ( !$valid ? '_invalid' : '' ) . '_config.json';
		if ( !CirrusIntegrationTestCase::hasFixture( $fixtureFile ) ) {
			if ( self::canRebuildFixture() ) {
				CirrusIntegrationTestCase::saveFixture( $fixtureFile, $this->genFrProjectConfig( $valid ) );
				$this->markTestSkipped();
				return;
			} else {
				$this->fail( 'Missing fixture file ' . $fixtureFile );
			}
		}

		$apiResponse = CirrusIntegrationTestCase::loadFixture( $fixtureFile );
		$client = $this->getMockBuilder( \MultiHttpClient::class )
			->disableOriginalConstructor()
			->getMock();
		$client->expects( $this->any() )
			->method( 'runMulti' )
			->will( $this->returnValue( $apiResponse ) );
		$resolver = $this->getSiteMatrixInterwikiResolver( 'enwiki', [], [], $client );
		$configs = $resolver->getSameProjectConfigByLang( 'fr' );

		$wikiId = "frwiki";
		$prefix = $resolver->getInterwikiPrefix( $wikiId );
		$this->assertNotNull( $prefix, "$wikiId does not seem to exist" );
		$this->assertArrayHasKey( $prefix, $configs,
			"config for $wikiId is available" );
		$this->assertEquals( $configs[$prefix]->getWikiId(), $wikiId,
			"config $wikiId has valid wikiId" );
		$this->assertEquals( $wikiId, $configs[$prefix]->get( 'CirrusSearchIndexBaseName' ),
			"config for $wikiId has valid CirrusSearchIndexBaseName" );
		if ( $valid ) {
			$this->assertNotEquals( 'test_inheritance',
				$configs[$prefix]->get( 'CirrusSearchRescoreProfile' ),
				"config for $wikiId is not rescued using a fallback config" );
		} else {
			$this->assertEquals( 'test_inheritance',
				$configs[$prefix]->get( 'CirrusSearchRescoreProfile' ),
				"config for $wikiId is rescued using a fallback config" );
		}
	}

	/**
	 * @return InterwikiResolver
	 */
	private function getCirrusConfigInterwikiResolver() {
		$wikiId = 'enwiki';
		$myGlobals = [
			'wgDBprefix' => null,
			'wgDBname' => $wikiId,
			'wgLanguageCode' => 'en',
			'wgCirrusSearchInterwikiSources' => [
				'voy' => 'enwikivoyage',
				'wikt' => 'enwiktionary',
				'b' => 'enwikibooks',
			],
			'wgCirrusSearchLanguageToWikiMap' => [
				'fr' => 'fr',
				'nb' => 'no',
				'en' => 'en',
			],
			'wgCirrusSearchWikiToNameMap' => [
				'fr' => 'frwiki',
				'no' => 'nowiki',
				'en' => 'enwiki',
			]
		];
		$this->setMwGlobals( $myGlobals );
		$myGlobals['_wikiID'] = $wikiId;
		// We need to reset this service so it can load wgInterwikiCache
		$config = new HashSearchConfig( $myGlobals, [ HashSearchConfig::FLAG_INHERIT ] );
		$resolver = MediaWikiServices::getInstance()
			->getService( InterwikiResolverFactory::SERVICE )
			->getResolver( $config );
		$this->assertEquals( CirrusConfigInterwikiResolver::class, get_class( $resolver ) );
		return $resolver;
	}

	/**
	 * @return InterwikiResolver
	 */
	private function getSiteMatrixInterwikiResolver( $wikiId, array $blacklist,
		array $overrides, \MultiHttpClient $client = null ) {
		$conf = new \SiteConfiguration;
		$conf->settings = include __DIR__ . '/../resources/wmf/SiteMatrix_SiteConf_IS.php';
		$conf->suffixes = include __DIR__ . '/../resources/wmf/suffixes.php';
		$conf->wikis = self::readDbListFile( __DIR__ . '/../resources/wmf/all.dblist' );

		$myGlobals = [
			'wgConf' => $conf,
			// Used directly by SiteMatrix
			'wgLocalDatabases' => $conf->wikis,
			// Used directly by SiteMatrix & SiteMatrixInterwikiResolver
			'wgSiteMatrixSites' => include __DIR__ . '/../resources/wmf/SiteMatrixProjects.php',
			// Used by SiteMatrix
			'wgSiteMatrixFile' => __DIR__ . '/resources/wmf/langlist',
			// Used by SiteMatrix
			'wgSiteMatrixClosedSites' => self::readDbListFile( __DIR__ . '/../resources/wmf/closed.dblist' ),
			// Used by SiteMatrix
			'wgSiteMatrixPrivateSites' => self::readDbListFile( __DIR__ . '/../resources/wmf/private.dblist' ),
			// Used by SiteMatrix
			'wgSiteMatrixFishbowlSites' => self::readDbListFile( __DIR__ . '/../resources/wmf/fishbowl.dblist' ),
			'wgCirrusSearchFetchConfigFromApi' => $client !== null,

			// XXX: for the purpose of the test we need
			// to have wfWikiID() without DBPrefix so we can reuse
			// the wmf InterwikiCache which is built against WMF config
			// where no wgDBprefix is set.
			'wgDBprefix' => null,
			'wgDBname' => $wikiId,
			// Used by ClassicInterwikiLookup & SiteMatrixInterwikiResolver
			'wgInterwikiCache' => include __DIR__ . '/../resources/wmf/interwiki.php',
			// Reset values so that SiteMatrixInterwikiResolver is used
			'wgCirrusSearchInterwikiSources' => [],
			'wgCirrusSearchLanguageToWikiMap' => [],
			'wgCirrusSearchWikiToNameMap' => [],
			'wgCirrusSearchCrossProjectSearchBlackList' => $blacklist,
			'wgCirrusSearchInterwikiPrefixOverrides' => $overrides,
		];
		$this->setMwGlobals( $myGlobals );
		// We need to reset this service so it can load wgInterwikiCache
		MediaWikiServices::getInstance()
			->resetServiceForTesting( 'InterwikiLookup' );
		$config = new HashSearchConfig( [ '_wikiID' => $wikiId ], [ HashSearchConfig::FLAG_INHERIT ] );
		$wanCache = \WANObjectCache::newEmpty();
		$srvCache = new \EmptyBagOStuff();
		$resolver = MediaWikiServices::getInstance()
			->getService( InterwikiResolverFactory::SERVICE )
			->getResolver( $config, $client, $wanCache, $srvCache );
		$this->assertEquals( SiteMatrixInterwikiResolver::class, get_class( $resolver ) );
		return $resolver;
	}

	protected function tearDown() : void {
		MediaWikiServices::getInstance()
			->resetServiceForTesting( 'InterwikiLookup' );
		parent::tearDown();
	}

	private static function readDbListFile( $fileName ) {
		\Wikimedia\suppressWarnings();
		$fileContent = file( $fileName, FILE_IGNORE_NEW_LINES );
		\Wikimedia\restoreWarnings();
		return $fileContent;
	}

	public function testEmptyResolver() {
		$config = new HashSearchConfig( [ '_wikiID' => 'dummy' ] );
		$resolver = MediaWikiServices::getInstance()
			->getService( InterwikiResolverFactory::SERVICE )
			->getResolver( $config );
		$this->assertInstanceOf( EmptyInterwikiResolver::class, $resolver );
	}

	private function genFrProjectConfig( $valid = true ) {
		$reqs = [
			'fr' => [
				'method' => 'GET',
				'url' => 'https://fr.wikipedia.org' . ( !$valid ? '.invalid' : '' ) . '/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			]
		];
		// to not expose you personal IP
		// use a proxy from vagrant e.g. webproxy on WMF infra by opening
		// a ssh tunnel e.g.:
		// ssh -L10.11.12.1:8765:webproxy.eqiad.wmnet:8080 XYZ.eqiad.wmnet
		$client = new \MultiHttpClient( [ 'proxy' => '10.11.12.1:8765' ] );
		return $client->runMulti( $reqs );
	}

	private function genSisterProjectFixtures() {
		$reqs = [
			'wikt' => [
				'method' => 'GET',
				'url' => 'https://en.wiktionary.org/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			],
			'n' => [
				'method' => 'GET',
				'url' => 'https://en.wikinews.org.invalid/w/api.php',
				'query' => [
					'action' => 'cirrus-confi-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			],
			'b' => [
				'method' => 'GET',
				'url' => 'https://en.wikibooks.org:1/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			],
			'q' => [
				'method' => 'GET',
				'url' => 'https://en.wikiquote.org/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump-wont-work',
					'format' => 'json',
					'formatversion' => '2',
				]
			],
			's' => [
				'method' => 'GET',
				'url' => 'https://en.wikisource.org/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'xml',
				]
			],
			'v' => [
				'method' => 'GET',
				'url' => 'https://en.wikiversity.org/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			],
			'voy' => [
				'method' => 'GET',
				'url' => 'https://en.wikivoyage.org/w/api.php',
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			]
		];
		$client = new \MultiHttpClient( [ 'proxy' => '10.11.12.1:8765' ] );
		return $client->runMulti( $reqs );
	}

}
