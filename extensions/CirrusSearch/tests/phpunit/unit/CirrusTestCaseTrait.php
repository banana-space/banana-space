<?php

namespace CirrusSearch;

use CirrusSearch\Parser\NamespacePrefixParser;
use CirrusSearch\Profile\PhraseSuggesterProfileRepoWrapper;
use CirrusSearch\Profile\SearchProfileServiceFactory;
use CirrusSearch\Profile\SearchProfileServiceFactoryFactory;
use CirrusSearch\Search\TitleHelper;
use Config;
use MediaWiki\Interwiki\InterwikiLookup;
use Title;

trait CirrusTestCaseTrait {
	public static $FIXTURE_DIR = __DIR__ . '/../fixtures/';
	public static $CIRRUS_REBUILD_FIXTURES = 'CIRRUS_REBUILD_FIXTURES';

	/**
	 * @var int|null (lazy loaded)
	 */
	private static $SEED;

	/**
	 * @var int
	 */
	private static $MAX_TESTED_FIXTURES_PER_TEST;

	/**
	 * @return bool
	 */
	public static function canRebuildFixture() {
		return getenv( self::$CIRRUS_REBUILD_FIXTURES ) === 'yes';
	}

	/**
	 * @return int
	 */
	public static function getSeed() {
		if ( self::$SEED === null ) {
			if ( is_numeric( getenv( 'CIRRUS_SEARCH_UNIT_TESTS_SEED' ) ) ) {
				self::$SEED = intval( getenv( 'CIRRUS_SEARCH_UNIT_TESTS_SEED' ) );
			} else {
				self::$SEED = time();
			}
		}
		return self::$SEED;
	}

	/**
	 * @return int
	 */
	public static function getMaxTestedFixturesPerTest() {
		if ( self::$MAX_TESTED_FIXTURES_PER_TEST === null ) {
			if ( is_numeric( getenv( 'CIRRUS_SEARCH_UNIT_TESTS_MAX_FIXTURES_PER_TEST' ) ) ) {
				self::$MAX_TESTED_FIXTURES_PER_TEST = intval( getenv( 'CIRRUS_SEARCH_UNIT_TESTS_MAX_FIXTURES_PER_TEST' ) );
			} else {
				self::$MAX_TESTED_FIXTURES_PER_TEST = 200;
			}
		}
		return self::$MAX_TESTED_FIXTURES_PER_TEST;
	}

	public static function findFixtures( $path ) {
		$prefixLen = strlen( self::$FIXTURE_DIR );
		$results = [];
		foreach ( glob( self::$FIXTURE_DIR . $path ) as $file ) {
			$results[] = substr( $file, $prefixLen );
		}
		return $results;
	}

	public static function saveFixture( $testFile, $fixture ) {
		file_put_contents(
			self::$FIXTURE_DIR . $testFile,
			self::encodeFixture( $fixture )
		);
	}

	public static function encodeFixture( $fixture ) {
		$old = ini_set( 'serialize_precision', 14 );
		try {
			return json_encode( $fixture, JSON_PRETTY_PRINT );
		} finally {
			ini_set( 'serialize_precision', $old );
		}
	}

	/**
	 * @param array $cases
	 * @return array
	 */
	public static function randomizeFixtures( array $cases ): array {
		if ( self::canRebuildFixture() ) {
			return $cases;
		}
		ksort( $cases );
		srand( self::getSeed() );
		$randomizedKeys = array_rand( $cases, min( count( $cases ), self::getMaxTestedFixturesPerTest() ) );
		$randomizedCases = array_intersect_key( $cases, array_flip( $randomizedKeys ) );
		return $randomizedCases;
	}

	public static function hasFixture( $testFile ) {
		return is_file( self::$FIXTURE_DIR . $testFile );
	}

	public static function loadFixture( $testFile, $errorMessage = "fixture config" ) {
		$decoded = json_decode( file_get_contents( self::$FIXTURE_DIR . $testFile ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			throw new \RuntimeException( "Failed decoding {$errorMessage}: $testFile" );
		}
		return $decoded;
	}

	public static function fixturePath( $testFile ) {
		return self::$FIXTURE_DIR . $testFile;
	}

	/**
	 * Capture the args of a mocked method
	 *
	 * @param mixed &$args placeholder for args to capture
	 * @param callable|null $callback optional callback methods to run on captured args
	 * @return \PHPUnit\Framework\Constraint\Callback
	 * @see Assert::callback()
	 */
	public function captureArgs( &$args, callable $callback = null ) {
		return $this->callback( function ( ...$argToCapture ) use ( &$args, $callback ) {
			$args = $argToCapture;
			if ( $callback !== null ) {
				return $callback( $argToCapture );
			}
			return true;
		} );
	}

	/**
	 * @param \Elastica\Response ...$responses
	 * @return \Elastica\Transport\AbstractTransport
	 */
	public function mockTransportWithResponse( ...$responses ) {
		$transport = $this->getMockBuilder( \Elastica\Transport\AbstractTransport::class )
			->disableOriginalConstructor()
			->getMock();
		$transport->expects( $this->any() )
			->method( 'exec' )
			->willReturnOnConsecutiveCalls( ...$responses );
		return $transport;
	}

	/**
	 * @param array $config
	 * @param array $flags
	 * @param Config|null $inherited
	 * @param SearchProfileServiceFactoryFactory|null $factoryFactory
	 * @return SearchConfig
	 */
	public function newHashSearchConfig(
		array $config = [],
		$flags = [],
		Config $inherited = null,
		SearchProfileServiceFactoryFactory $factoryFactory = null
	): SearchConfig {
		try {
			return new HashSearchConfig( $config, $flags, $inherited,
				$factoryFactory ?: $this->hostWikiSearchProfileServiceFactory() );
		} catch ( \MWException $e ) {
			$this->fail( $e->getMessage() );
		}
	}

	/**
	 * @return SearchProfileServiceFactoryFactory
	 */
	public function hostWikiSearchProfileServiceFactory(): SearchProfileServiceFactoryFactory {
		return new class( $this ) implements SearchProfileServiceFactoryFactory {
			/**
			 * @var CirrusTestCaseTrait
			 */
			private $testCase;

			public function __construct( $testCase ) {
				$this->testCase = $testCase;
			}

			public function getFactory( SearchConfig $config ): SearchProfileServiceFactory {
				return new SearchProfileServiceFactory( $this->testCase->getInterWikiResolver( $config ),
					$config, $this->testCase->localServerCacheForProfileService() );
			}
		};
	}

	public function getInterWikiResolver( SearchConfig $config ): InterwikiResolver {
		return new EmptyInterwikiResolver( $config );
	}

	public function namespacePrefixParser(): NamespacePrefixParser {
		return new class() implements NamespacePrefixParser {
			public function parse( $query ) {
				$pieces = explode( ':', $query, 2 );
				if ( count( $pieces ) === 2 ) {
					$ns = null;
					switch ( mb_strtolower( $pieces[0] ) ) {
						case 'all':
							return [ $pieces[1], null ];
						case 'category':
							return [ $pieces[1], [ NS_CATEGORY ] ];
						case 'help':
							return [ $pieces[1], [ NS_HELP ] ];
						case 'template';
							return [ $pieces[1], [ NS_TEMPLATE ] ];
						case 'category_talk':
							return [ $pieces[1], [ NS_CATEGORY_TALK ] ];
						case 'help_talk':
							return [ $pieces[1], [ NS_HELP_TALK ] ];
						case 'template_talk';
							return [ $pieces[1], [ NS_TEMPLATE_TALK ] ];
						case 'file':
							return [ $pieces[1], [ NS_FILE ] ];
						case 'file_talk':
							return [ $pieces[1], [ NS_FILE_TALK ] ];
					}
				}
				return false;
			}
		};
	}

	/**
	 * @return CirrusSearch
	 */
	public function newEngine(): CirrusSearch {
		return new CirrusSearch( $this->newHashSearchConfig( [ 'CirrusSearchServers' => [] ] ),
			CirrusDebugOptions::defaultOptions(), $this->namespacePrefixParser(), new EmptyInterwikiResolver() );
	}

	public function sanitizeLinkFragment( string $id ): string {
		return str_replace( ' ', '_', $id );
	}

	public function newTitleHelper( $hostWikiID = null, InterwikiResolver $iwResolver = null ): TitleHelper {
		return new class(
			$hostWikiID,
			$iwResolver ?: new EmptyInterwikiResolver(),
			function ( $v ) {
				return $this->sanitizeLinkFragment( $v );
			}
		) extends TitleHelper {
			public function __construct( $hostWikiId,
				InterwikiResolver $interwikiResolver = null, callable $linkSanitizer = null
			) {
				parent::__construct( $hostWikiId, $interwikiResolver, $linkSanitizer );
			}

			public function getNamespaceText( Title $title ) {
				// We only use common namespaces in tests, if this fails or you need
				// more please adjust.
				static $canonicalNames = [
					NS_MEDIA            => 'Media',
					NS_SPECIAL          => 'Special',
					NS_MAIN             => '',
					NS_TALK             => 'Talk',
					NS_USER             => 'User',
					NS_USER_TALK        => 'User_talk',
					NS_PROJECT          => 'Project',
					NS_PROJECT_TALK     => 'Project_talk',
					NS_FILE             => 'File',
					NS_FILE_TALK        => 'File_talk',
					NS_MEDIAWIKI        => 'MediaWiki',
					NS_MEDIAWIKI_TALK   => 'MediaWiki_talk',
					NS_TEMPLATE         => 'Template',
					NS_TEMPLATE_TALK    => 'Template_talk',
					NS_HELP             => 'Help',
					NS_HELP_TALK        => 'Help_talk',
					NS_CATEGORY         => 'Category',
					NS_CATEGORY_TALK    => 'Category_talk',
				];
				return $canonicalNames[$title->getNamespace()];
			}
		};
	}

	public function newManualInterwikiResolver( SearchConfig $config ): InterwikiResolver {
		return new CirrusConfigInterwikiResolver( $config, $this->createMock( \MultiHttpClient::class ), null, new \EmptyBagOStuff(),
			$this->createMock( InterwikiLookup::class ) );
	}

	public function localServerCacheForProfileService(): \BagOStuff {
		$bagOSTuff = new \HashBagOStuff();
		$bagOSTuff->set(
			$bagOSTuff->makeKey( PhraseSuggesterProfileRepoWrapper::CIRRUSSEARCH_DIDYOUMEAN_SETTINGS ),
			[]
		);
		return $bagOSTuff;
	}
}
