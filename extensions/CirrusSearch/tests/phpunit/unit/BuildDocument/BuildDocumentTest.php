<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\Connection;
use CirrusSearch\SearchConfig;
use Elastica\Document;
use IDatabase;
use MediaWiki\Revision\RevisionStore;
use ParserCache;
use Title;
use WikiPage;

/**
 * @covers \CirrusSearch\BuildDocument\BuildDocument
 */
class BuildDocumentTest extends \MediaWikiUnitTestCase {
	private function mockBuilder( Title $title ) {
		// Would be nice if we could pass the makeId function instead of a whole SearchConfig
		$config = new SearchConfig;
		$connection = $this->mock( Connection::class );
		$connection->method( 'getConfig' )
			->will( $this->returnValue( $config ) );
		$db = $this->mock( IDatabase::class );
		$parserCache = $this->mock( ParserCache::class );
		$revStore = $this->mock( RevisionStore::class );
		$revStore->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		return new class( $connection, $db, $parserCache, $revStore ) extends BuildDocument {
			// Override create builders to avoid testing those implementations
			protected function createBuilders( int $flags ): array {
				return [ new class() implements PagePropertyBuilder {
					private $doc;

					public function initialize( Document $doc, WikiPage $page ): void {
						$this->doc = $doc;
						$doc->set( 'phpunit_page_id', $page->getId() );
					}

					public function finishInitializeBatch(): void {
						$this->doc->set( 'phpunit_finish_batch', true );
					}

					public function finalize( Document $doc, Title $title ): void {
						$doc->set( 'phpunit_finalize', true );
					}
				} ];
			}
		};
	}

	public function testHappyPath() {
		$title = $this->mock( Title::class );
		$title->method( 'getLatestRevID' )->will( $this->returnValue( 42 ) );
		$pages = [];
		foreach ( range( 0, 1 ) as $pageId ) {
			$page = $this->mock( WikiPage::class );
			$page->method( 'getId' )->will( $this->returnValue( $pageId ) );
			$page->method( 'getTitle' )->will( $this->returnValue( $title ) );
			$page->method( 'getLatest' )->will( $this->returnValue( 42 ) );
			// $pageId == 0 does not exist
			$page->method( 'exists' )->will( $this->returnValue( (bool)$pageId ) );
			$pages[] = $page;
		}

		$builder = $this->mockBuilder( $title );
		$docs = $builder->initialize(
			$pages, BuildDocument::INDEX_EVERYTHING
		);
		// non existent doc was thrown away
		$this->assertCount( 1, $docs );
		// doc has expected index of $pageId
		$this->assertArrayHasKey( 1, $docs );
		$doc = $docs[$pageId];
		$this->assertSame( 1, $doc->get( 'phpunit_page_id' ) );
		$this->assertTrue( $doc->get( 'phpunit_finish_batch' ) );

		$builder->finalize( $doc );
		$this->assertTrue( $doc->get( 'phpunit_finalize' ) );
	}

	private function mock( $class ) {
		return $this->getMockBuilder( $class )
			->disableOriginalConstructor()
			->getMock();
	}
}
