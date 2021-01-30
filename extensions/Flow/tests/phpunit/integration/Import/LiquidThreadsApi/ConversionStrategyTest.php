<?php

namespace Flow\Tests\Import\LiquidThreadsApi;

use DateTime;
use DateTimeZone;
use ExtensionRegistry;
use Flow\Import\IImportSource;
use Flow\Import\LiquidThreadsApi\ApiBackend;
use Flow\Import\LiquidThreadsApi\ConversionStrategy;
use Flow\Import\Postprocessor\Postprocessor;
use Flow\Import\SourceStore\NullImportSourceStore;
use Flow\Import\SourceStore\SourceStoreInterface;
use Title;
use Wikimedia\Rdbms\IDatabase;
use WikitextContent;

/**
 * @covers \Flow\Import\LiquidThreadsApi\ConversionStrategy
 *
 * @group Flow
 */
class ConversionStrategyTest extends \MediaWikiTestCase {
	protected function setUp() : void {
		parent::setUp();

		// Stash existing $wgEchoNotifications and provide a dummy for these
		// tests:  LqtNotifications::overrideUsersToNotify will override it
		global $wgEchoNotifications;
		$this->setMwGlobals( 'wgEchoNotifications', $wgEchoNotifications );
	}

	public function testCanConstruct() {
		$this->assertInstanceOf(
			ConversionStrategy::class,
			$this->createStrategy()
		);
	}

	public function testGeneratesMoveComment() {
		$from = Title::newFromText( 'Talk:Blue_birds' );
		$to = Title::newFromText( 'Talk:Blue_birds/Archive 4' );
		$this->assertGreaterThan(
			1,
			strlen( $this->createStrategy()->getMoveComment( $from, $to ) )
		);
	}

	public function testGeneratesCleanupComment() {
		$from = Title::newFromText( 'Talk:Blue_birds' );
		$to = Title::newFromText( 'Talk:Blue_birds/Archive 4' );
		$this->assertGreaterThan(
			1,
			strlen( $this->createStrategy()->getCleanupComment( $from, $to ) )
		);
	}

	public function testCreatesValidImportSource() {
		$this->assertInstanceOf(
			IImportSource::class,
			$this->createStrategy()->createImportSource( Title::newFromText( 'Talk:Blue_birds' ) )
		);
	}

	public function testReturnsValidSourceStore() {
		$this->assertInstanceOf(
			SourceStoreInterface::class,
			$this->createStrategy()->getSourceStore()
		);
	}

	public function testDecidesArchiveTitle() {
		// we don't have control of the Title::exists() calls that are made here,
		// so just assume the page doesn't exist and we get format = 0 n = 1
		$this->assertEquals(
			'Talk:Blue birds/LQT Archive 1',
			$this->createStrategy()
				->decideArchiveTitle( Title::newFromText( 'Talk:Blue_birds' ) )
				->getPrefixedText()
		);
	}

	public function provideArchiveCleanupRevisionContent() {
		// @todo superm401 suggested finding library that lets us control time during tests,
		// would probably be better
		$now = new DateTime( "now", new DateTimeZone( "GMT" ) );
		$date = $now->format( 'Y-m-d' );

		return [
			[
				'Blank input page',
				// expect
				"{{Archive for converted LQT page|from=Talk:Blue birds|date=$date}}\n\n{{#useliquidthreads:0}}\n\n",
				// input content
				'',
			],
			[
				'Page containing lqt magic word',
				// expect
				"{{Archive for converted LQT page|from=Talk:Blue birds|date=$date}}\n\n{{#useliquidthreads:0}}\n\n",
				// input content
				'{{#useliquidthreads:1}}',
			],

			[
				'Page containing some stuff and the lqt magic word',
				// expect
				<<<EOD
{{Archive for converted LQT page|from=Talk:Blue birds|date=$date}}

{{#useliquidthreads:0}}

Four score and seven years ago our fathers brought forth
on this continent, a new nation, conceived in Liberty, and
dedicated to the proposition that all men are created equal.

EOD
				,
				// input content
				<<<EOD
Four score and seven years ago our fathers brought forth
on this continent, a new nation, conceived in Liberty, and
dedicated to the proposition that all men are created equal.
{{#useliquidthreads:
	1
}}
EOD
			],
		];
	}

	/**
	 * @group Broken
	 * @dataProvider provideArchiveCleanupRevisionContent
	 * @param string $content
	 */
	public function testCreateArchiveCleanupRevisionContent( $message, $expect, $content ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Liquid Threads' ) ) {
			$this->markTestSkipped( 'LiquidThreads not enabled' );
		}

		$result = $this->createStrategy()->createArchiveCleanupRevisionContent(
			new WikitextContent( $content ),
			Title::newFromText( 'Talk:Blue_birds' )
		);
		if ( $result !== null ) {
			$this->assertInstanceOf( WikitextContent::class, $result );
		}
		$this->assertEquals( $expect, $result->getText(), $message );
	}

	public function testGetPostprocessor() {
		$this->assertInstanceOf(
			Postprocessor::class,
			$this->createStrategy()->getPostprocessor()
		);
	}

	protected function createStrategy(
		IDatabase $dbr = null,
		SourceStoreInterface $sourceStore = null,
		ApiBackend $api = null
	) {
		return new ConversionStrategy(
			$dbr ?: wfGetDB( DB_REPLICA ),
			$sourceStore ?: new NullImportSourceStore,
			$api ?: $this->getMockBuilder( ApiBackend::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( \Flow\UrlGenerator::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( \User::class )
				->disableOriginalConstructor()
				->getMock(),
			$this->getMockBuilder( \Flow\Notifications\Controller::class )
				->disableOriginalConstructor()
				->getMock()
		);
	}
}
