<?php

namespace TextExtracts\Test;

use MediaWikiCoversValidator;
use TextExtracts\ApiQueryExtracts;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \TextExtracts\ApiQueryExtracts
 * @group TextExtracts
 *
 * @license GPL-2.0-or-later
 */
class ApiQueryExtractsTest extends \MediaWikiTestCase {
	use MediaWikiCoversValidator;

	private function newInstance() {
		$config = new \HashConfig( [
			'ParserCacheExpireTime' => \IExpiringStore::TTL_INDEFINITE,
		] );

		$cache = new \WANObjectCache( [ 'cache' => new \HashBagOStuff() ] );

		$context = $this->createMock( \IContextSource::class );
		$context->method( 'getConfig' )
			->willReturn( $config );

		$main = $this->createMock( \ApiMain::class );
		$main->expects( $this->once() )
			->method( 'getContext' )
			->willReturn( $context );

		$query = $this->createMock( \ApiQuery::class );
		$query->expects( $this->once() )
			->method( 'getMain' )
			->willReturn( $main );

		return new ApiQueryExtracts( $query, '', $config, $cache );
	}

	public function testMemCacheHelpers() {
		$title = $this->createMock( \Title::class );
		$title->method( 'getPageLanguage' )
			->willReturn( $this->createMock( \Language::class ) );

		$page = $this->createMock( \WikiPage::class );
		$page->method( 'getTitle' )
			->willReturn( $title );

		$text = 'Text to cache';

		/** @var ApiQueryExtracts $instance */
		$instance = TestingAccessWrapper::newFromObject( $this->newInstance() );
		// Default param values for this API module
		$instance->params = [ 'intro' => false, 'plaintext' => false ];

		$this->assertFalse( $instance->getFromCache( $page, false ), 'is not cached yet' );

		$instance->setCache( $page, $text );
		$instance->cache->clearProcessCache();
		$this->assertSame( $text, $instance->getFromCache( $page, false ) );
	}

	public function testSelfDocumentation() {
		/** @var ApiQueryExtracts $instance */
		$instance = TestingAccessWrapper::newFromObject( $this->newInstance() );

		$this->assertIsString( $instance->getCacheMode( [] ) );
		$this->assertNotEmpty( $instance->getExamplesMessages() );
		$this->assertIsString( $instance->getHelpUrls() );

		$params = $instance->getAllowedParams();
		$this->assertIsArray( $params );

		$this->assertSame( $params['chars'][\ApiBase::PARAM_MIN], 1 );
		$this->assertSame( $params['chars'][\ApiBase::PARAM_MAX], 1200 );

		$this->assertSame( $params['limit'][\ApiBase::PARAM_DFLT], 20 );
		$this->assertSame( $params['limit'][\ApiBase::PARAM_TYPE], 'limit' );
		$this->assertSame( $params['limit'][\ApiBase::PARAM_MIN], 1 );
		$this->assertSame( $params['limit'][\ApiBase::PARAM_MAX], 20 );
		$this->assertSame( $params['limit'][\ApiBase::PARAM_MAX2], 20 );
	}

	/**
	 * @dataProvider provideFirstSectionsToExtract
	 */
	public function testGetFirstSection( $text, $isPlainText, $expected ) {
		/** @var ApiQueryExtracts $instance */
		$instance = TestingAccessWrapper::newFromObject( $this->newInstance() );

		$this->assertSame( $expected, $instance->getFirstSection( $text, $isPlainText ) );
	}

	public function provideFirstSectionsToExtract() {
		return [
			'Plain text match' => [
				"First\nsection \1\2... \1\2...",
				true,
				"First\nsection ",
			],
			'Plain text without a match' => [
				'Example\1\2...',
				true,
				'Example\1\2...',
			],

			'HTML match' => [
				"First\nsection <h1>...<h2>...",
				false,
				"First\nsection ",
			],
			'HTML without a match' => [
				'Example <h11>...',
				false,
				'Example <h11>...',
			],
		];
	}

	/**
	 * @dataProvider provideSectionsToFormat
	 */
	public function testDoSections( $text, $format, $expected ) {
		/** @var ApiQueryExtracts $instance */
		$instance = TestingAccessWrapper::newFromObject( $this->newInstance() );
		$instance->params = [ 'sectionformat' => $format ];

		$this->assertSame( $expected, $instance->doSections( $text ) );
	}

	public function provideSectionsToFormat() {
		$level = 3;
		$marker = "\1\2$level\2\1";

		return [
			'Raw' => [
				"$marker Headline\t\nNext line",
				'raw',
				"$marker Headline\t\nNext line",
			],
			'Wiki text' => [
				"$marker Headline\t\nNext line",
				'wiki',
				"\n=== Headline ===\nNext line",
			],
			'Plain text' => [
				"$marker Headline\t\nNext line",
				'plain',
				"\nHeadline\nNext line",
			],

			'Multiple matches' => [
				"${marker}First\n${marker}Second",
				'wiki',
				"\n=== First ===\n\n=== Second ===",
			],
		];
	}
}
