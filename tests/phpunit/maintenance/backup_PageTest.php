<?php

namespace MediaWiki\Tests\Maintenance;

use DumpBackup;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use WikiExporter;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use XmlDumpWriter;

/**
 * Tests for page dumps of BackupDumper
 *
 * @group Database
 * @group Dump
 * @covers BackupDumper
 */
class BackupDumperPageTest extends DumpTestCase {

	use PageDumpTestDataTrait;

	/**
	 * @var ILoadBalancer
	 */
	private $streamingLoadBalancer;

	public function addDBData() {
		parent::addDBData();

		$this->addTestPages();
	}

	protected function tearDown() : void {
		parent::tearDown();

		if ( isset( $this->streamingLoadBalancer ) ) {
			$this->streamingLoadBalancer->closeAll();
		}
	}

	/**
	 * Returns a new database connection which is separate from the conenctions returned
	 * by the default LoadBalancer instance.
	 *
	 * @return IDatabase
	 */
	private function newStreamingDBConnection() {
		// Create a *new* LoadBalancer, so no connections are shared
		if ( !$this->streamingLoadBalancer ) {
			$lbFactory = MediaWikiServices::getInstance()->getDBLoadBalancerFactory();

			$this->streamingLoadBalancer = $lbFactory->newMainLB();
		}

		$db = $this->streamingLoadBalancer->getConnection( DB_REPLICA );

		// Make sure the DB connection has the fake table clones and the fake table prefix
		MediaWikiIntegrationTestCase::setupDatabaseWithTestPrefix( $db );

		// Make sure the DB connection has all the test data
		$this->copyTestData( $this->db, $db );

		return $db;
	}

	/**
	 * @param array $argv
	 * @param int $startId
	 * @param int $endId
	 *
	 * @return DumpBackup
	 */
	private function newDumpBackup( $argv, $startId, $endId ) {
		$dumper = new DumpBackup( $argv );
		$dumper->startId = $startId;
		$dumper->endId = $endId;
		$dumper->reporting = false;

		// NOTE: The copyTestData() method used by newStreamingDBConnection()
		// doesn't work with SQLite (T217607).
		// But DatabaseSqlite doesn't support streaming anyway, so just skip that part.
		if ( $this->db->getType() === 'sqlite' ) {
			$dumper->setDB( $this->db );
		} else {
			$dumper->setDB( $this->newStreamingDBConnection() );
		}

		return $dumper;
	}

	public function schemaVersionProvider() {
		foreach ( XmlDumpWriter::$supportedSchemas as $schemaVersion ) {
			yield [ $schemaVersion ];
		}
	}

	/**
	 * @dataProvider schemaVersionProvider
	 */
	public function testFullTextPlain( $schemaVersion ) {
		// Preparing the dump
		$tmpFile = $this->getNewTempFile();

		$dumper = $this->newDumpBackup(
			[ '--full', '--quiet', '--output', 'file:' . $tmpFile, '--schema-version', $schemaVersion ],
			$this->pageId1,
			$this->pageId5 + 1
		);

		// Performing the dump. Suppress warnings, since we want to test
		// accessing broken revision data (page 5).
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );
		$dumper->execute();
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );

		// Checking syntax and schema
		$this->assertDumpSchema( $tmpFile, $this->getXmlSchemaPath( $schemaVersion ) );

		// Checking file contents
		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setAllRevisionsVarMappings( $asserter );

		$siteInfoTemplate = $this->getDumpTemplatePath( 'SiteInfo', $schemaVersion );
		$pagesTemplate = $this->getDumpTemplatePath( 'AllText', $schemaVersion );

		$asserter->open( $tmpFile );
		$asserter->assertDumpHead( $siteInfoTemplate );

		// Check pages and revisions
		$asserter->assertDOM( $pagesTemplate );
		$asserter->assertDumpEnd();
	}

	/**
	 * @dataProvider schemaVersionProvider
	 */
	public function testFullStubPlain( $schemaVersion ) {
		// Preparing the dump
		$tmpFile = $this->getNewTempFile();

		$dumper = $this->newDumpBackup(
			[
				'--full',
				'--quiet',
				'--output',
				'file:' . $tmpFile,
				'--stub',
				'--schema-version', $schemaVersion,
			],
			$this->pageId1,
			$this->pageId5 + 1
		);

		// Performing the dump. Suppress warnings, since we want to test
		// accessing broken revision data (page 5).
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );
		$dumper->execute();
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );

		// Checking the dumped data
		$this->assertDumpSchema( $tmpFile, $this->getXmlSchemaPath( $schemaVersion ) );

		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setAllRevisionsVarMappings( $asserter );

		$siteInfoTemplate = $this->getDumpTemplatePath( 'SiteInfo', $schemaVersion );
		$pagesTemplate = $this->getDumpTemplatePath( 'AllStubs', $schemaVersion );

		$asserter->open( $tmpFile );
		$asserter->assertDumpHead( $siteInfoTemplate );

		// Check pages and revisions
		$asserter->assertDOM( $pagesTemplate );
		$asserter->assertDumpEnd();
	}

	/**
	 * @dataProvider schemaVersionProvider
	 */
	public function testCurrentStubPlain( $schemaVersion ) {
		// Preparing the dump
		$tmpFile = $this->getNewTempFile();

		$dumper = $this->newDumpBackup(
			[ '--output', 'file:' . $tmpFile, '--schema-version', $schemaVersion ],
			$this->pageId1,
			$this->pageId5 + 1
		);

		// Performing the dump. Suppress warnings, since we want to test
		// accessing broken revision data (page 5).
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );
		$dumper->dump( WikiExporter::CURRENT, WikiExporter::STUB );
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );

		// Checking the dumped data
		$this->assertDumpSchema( $tmpFile, $this->getXmlSchemaPath( $schemaVersion ) );

		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setAllRevisionsVarMappings( $asserter );

		$siteInfoTemplate = $this->getDumpTemplatePath( 'SiteInfo', $schemaVersion );
		$pagesTemplate = $this->getDumpTemplatePath( 'CurrentStubs', $schemaVersion );

		$asserter->open( $tmpFile );
		$asserter->assertDumpHead( $siteInfoTemplate );

		// Check pages and revisions
		$asserter->assertDOM( $pagesTemplate );
		$asserter->assertDumpEnd();
	}

	public function testCurrentStubGzip() {
		global $wgXmlDumpSchemaVersion;

		$this->checkHasGzip();

		// Preparing the dump
		$tmpFile = $this->getNewTempFile();

		$dumper = $this->newDumpBackup(
			[ '--output', 'gzip:' . $tmpFile ],
			$this->pageId1,
			$this->pageId5 + 1
		);

		// Performing the dump. Suppress warnings, since we want to test
		// accessing broken revision data (page 5).
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );
		$dumper->dump( WikiExporter::CURRENT, WikiExporter::STUB );
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );

		// Checking the dumped data
		$this->gunzip( $tmpFile );

		$this->assertDumpSchema( $tmpFile, $this->getXmlSchemaPath( $wgXmlDumpSchemaVersion ) );

		$asserter = $this->getDumpAsserter( $wgXmlDumpSchemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setAllRevisionsVarMappings( $asserter );

		$siteInfoTemplate = $this->getDumpTemplatePath( 'SiteInfo', $wgXmlDumpSchemaVersion );
		$pagesTemplate = $this->getDumpTemplatePath( 'CurrentStubs', $wgXmlDumpSchemaVersion );

		$asserter->open( $tmpFile );
		$asserter->assertDumpHead( $siteInfoTemplate );

		// Check pages and revisions
		$asserter->assertDOM( $pagesTemplate );
		$asserter->assertDumpEnd();
	}

	/**
	 * xmldumps-backup typically performs a single dump that that writes
	 * out three files
	 * - gzipped stubs of everything (meta-history)
	 * - gzipped stubs of latest revisions of all pages (meta-current)
	 * - gzipped stubs of latest revisions of all pages of namespage 0
	 *   (articles)
	 *
	 * We reproduce such a setup with our mini fixture, although we omit
	 * chunks, and all the other gimmicks of xmldumps-backup.
	 *
	 * @dataProvider schemaVersionProvider
	 */
	public function testXmlDumpsBackupUseCase( $schemaVersion ) {
		$this->checkHasGzip();

		$fnameMetaHistory = $this->getNewTempFile();
		$fnameMetaCurrent = $this->getNewTempFile();
		$fnameArticles = $this->getNewTempFile();

		$expSiteInfo = $this->getDumpTemplatePath( 'SiteInfo', $schemaVersion );
		$expMetaHistory = $this->getDumpTemplatePath( 'AllStubs', $schemaVersion );
		$expMetaCurrent = $this->getDumpTemplatePath( 'CurrentStubs', $schemaVersion );
		$expArticles = $this->getDumpTemplatePath( 'CurrentArticleStubs', $schemaVersion );

		$dumper = $this->newDumpBackup(
			[ "--quiet", "--full", "--stub", "--output=gzip:" . $fnameMetaHistory,
				"--output=gzip:" . $fnameMetaCurrent, "--filter=latest",
				"--output=gzip:" . $fnameArticles, "--filter=latest",
				"--filter=notalk", "--filter=namespace:!NS_USER",
				"--reporting=1000", '--schema-version', $schemaVersion
			],
			$this->pageId1,
			$this->pageId5 + 1
		);
		$dumper->reporting = true;

		// xmldumps-backup uses reporting. We will not check the exact reported
		// message, as they are dependent on the processing power of the used
		// computer. We only check that reporting does not crash the dumping
		// and that something is reported
		$fnameReport = $this->getNewTempFile();
		$dumper->stderr = fopen( $fnameReport, 'a' );
		if ( $dumper->stderr === false ) {
			$this->fail( "Could not open stream for stderr" );
		}

		// Performing the dump. Suppress warnings, since we want to test
		// accessing broken revision data (page 5).
		$this->setMwGlobals( 'wgDevelopmentWarnings', false );
		$dumper->dump( WikiExporter::FULL, WikiExporter::STUB );
		$this->setMwGlobals( 'wgDevelopmentWarnings', true );

		$this->assertTrue( fclose( $dumper->stderr ), "Closing stderr handle" );
		$this->assertNotEmpty( file_get_contents( $fnameReport ) );

		// Checking meta-history -------------------------------------------------

		$this->gunzip( $fnameMetaHistory );

		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setAllRevisionsVarMappings( $asserter );

		$asserter->open( $fnameMetaHistory );
		$asserter->assertDumpHead( $expSiteInfo );
		$asserter->assertDOM( $expMetaHistory );
		$asserter->assertDumpEnd();

		// Checking meta-current -------------------------------------------------

		$this->gunzip( $fnameMetaCurrent );
		$this->assertDumpSchema( $fnameMetaCurrent, $this->getXmlSchemaPath( $schemaVersion ) );

		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setCurrentRevisionsVarMappings( $asserter );

		$asserter->open( $fnameMetaCurrent );
		$asserter->assertDumpHead( $expSiteInfo );
		$asserter->assertDOM( $expMetaCurrent );
		$asserter->assertDumpEnd();

		// Checking articles -------------------------------------------------

		$this->gunzip( $fnameArticles );
		$this->assertDumpSchema( $fnameArticles, $this->getXmlSchemaPath( $schemaVersion ) );

		$asserter = $this->getDumpAsserter( $schemaVersion );
		$this->setSiteVarMappings( $asserter );
		$this->setCurrentRevisionsVarMappings( $asserter );

		$asserter->open( $fnameArticles );
		$asserter->assertDumpHead( $expSiteInfo );
		$asserter->assertDOM( $expArticles );
		$asserter->assertDumpEnd();
	}
}
