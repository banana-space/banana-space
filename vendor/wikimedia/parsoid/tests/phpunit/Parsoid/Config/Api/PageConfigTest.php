<?php

namespace Test\Parsoid\Config\Api;

use Wikimedia\Parsoid\Config\Api\PageConfig;
use Wikimedia\Parsoid\Config\PageContent;

/**
 * @covers \Wikimedia\Parsoid\Config\Api\PageConfig
 */
class PageConfigTest extends \PHPUnit\Framework\TestCase {

	private static $pageConfigs = [];

	protected function getPageConfig( string $id ) {
		if ( !self::$pageConfigs ) {
			foreach (
				[
					'missing' => 'ThisPageDoesNotExist',
					'existing' => 'Help:Sample_page',
				] as $name => $title
			) {
				$helper = new TestApiHelper( $this, $name . 'page' );
				self::$pageConfigs[$name] = new PageConfig( $helper, [ 'title' => $title ] );
			}
		}

		return self::$pageConfigs[$id];
	}

	public function testHasLintableContentModel() {
		// Assumes wikitext:
		$this->assertTrue( $this->getPageConfig( 'missing' )->hasLintableContentModel() );
		$this->assertTrue( $this->getPageConfig( 'existing' )->hasLintableContentModel() );
	}

	public function testGetTitle() {
		$this->assertSame( 'ThisPageDoesNotExist', $this->getPageConfig( 'missing' )->getTitle() );
		$this->assertSame( 'Help:Sample page', $this->getPageConfig( 'existing' )->getTitle() );
	}

	public function testGetNs() {
		$this->assertSame( 0, $this->getPageConfig( 'missing' )->getNs() );
		$this->assertSame( 12, $this->getPageConfig( 'existing' )->getNs() );
	}

	public function testGetPageId() {
		$this->assertSame( -1, $this->getPageConfig( 'missing' )->getPageId() );
		$this->assertSame( 53796160, $this->getPageConfig( 'existing' )->getPageId() );
	}

	public function testGetPageLanguage() {
		$this->assertSame( 'en', $this->getPageConfig( 'missing' )->getPageLanguage() );
		$this->assertSame( 'en', $this->getPageConfig( 'existing' )->getPageLanguage() );
	}

	public function testGetPageLanguageDir() {
		$this->assertSame( 'ltr', $this->getPageConfig( 'missing' )->getPageLanguageDir() );
		$this->assertSame( 'ltr', $this->getPageConfig( 'existing' )->getPageLanguageDir() );
	}

	public function testGetRevisionId() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionId() );
		$this->assertSame( 810158619, $this->getPageConfig( 'existing' )->getRevisionId() );
	}

	public function testGetParentRevisionId() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getParentRevisionId() );
		$this->assertSame( 776171508, $this->getPageConfig( 'existing' )->getParentRevisionId() );
	}

	public function testGetRevisionTimestamp() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionTimestamp() );
		$this->assertSame( '20171113173753', $this->getPageConfig( 'existing' )->getRevisionTimestamp() );
	}

	public function testGetRevisionUser() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionUser() );
		$this->assertSame( 'ESanders (WMF)', $this->getPageConfig( 'existing' )->getRevisionUser() );
	}

	public function testGetRevisionUserId() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionUserId() );
		$this->assertSame( 18442520, $this->getPageConfig( 'existing' )->getRevisionUserId() );
	}

	public function testGetRevisionSha1() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionSha1() );
		$this->assertSame(
			'ae1ed2d432a4267b0cc5612d22cbf0d3d86a6e5c',
			$this->getPageConfig( 'existing' )->getRevisionSha1()
		);
	}

	public function testGetRevisionSize() {
		$this->assertNull( $this->getPageConfig( 'missing' )->getRevisionSize() );
		$this->assertSame( 1748, $this->getPageConfig( 'existing' )->getRevisionSize() );
	}

	public function testGetRevisionContent() {
		$this->assertSame(
			'',
			$this->getPageConfig( 'missing' )->getRevisionContent()->getContent( 'main' )
		);

		$c = $this->getPageConfig( 'existing' )->getRevisionContent();
		$this->assertInstanceOf( PageContent::class, $c );
		$this->assertSame( [ 'main' ], $c->getRoles() );
		$this->assertSame( 'wikitext', $c->getModel( 'main' ) );
		$this->assertSame( 'text/x-wiki', $c->getFormat( 'main' ) );
		$this->assertSame(
			"Our '''world''' is a planet where human beings have formed many societies.\n\n",
			substr( $c->getContent( 'main' ), 0, 76 )
		);
	}

}
