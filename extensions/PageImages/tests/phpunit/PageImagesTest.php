<?php

namespace PageImages\Tests;

use IContextSource;
use MediaWikiTestCase;
use OutputPage;
use PageImages\PageImages;
use SkinTemplate;
use Title;

/**
 * @covers \PageImages\PageImages
 *
 * @group PageImages
 * @group Database
 *
 * @license WTFPL
 * @author Thiemo Kreuz
 */
class PageImagesTest extends MediaWikiTestCase {

	public function testPagePropertyNames() {
		$this->assertSame( 'page_image', PageImages::PROP_NAME );
		$this->assertSame( 'page_image_free', PageImages::PROP_NAME_FREE );
	}

	public function testConstructor() {
		$pageImages = new PageImages();
		$this->assertInstanceOf( PageImages::class, $pageImages );
	}

	public function testGivenNonExistingPageGetPageImageReturnsFalse() {
		$title = $this->newTitle();
		$this->assertFalse( PageImages::getPageImage( $title ) );
	}

	public function testGetPropName() {
		$this->assertSame( 'page_image', PageImages::getPropName( false ) );
		$this->assertSame( 'page_image_free', PageImages::getPropName( true ) );
	}

	public function testGetPropNames() {
		$this->assertSame(
			[ PageImages::PROP_NAME_FREE, PageImages::PROP_NAME ],
			PageImages::getPropNames( PageImages::LICENSE_ANY )
		);
		$this->assertSame(
			PageImages::PROP_NAME_FREE,
			PageImages::getPropNames( PageImages::LICENSE_FREE )
		);
	}

	public function testGivenNonExistingPageOnBeforePageDisplayDoesNotAddMeta() {
		$context = $this->createMock( IContextSource::class );
		$context->method( 'getTitle' )
			->will( $this->returnValue( $this->newTitle() ) );
		$fauxRequest = new \FauxRequest();
		$config = new \HashConfig();
		$context->method( 'getRequest' )
			->willReturn( $fauxRequest );
		$context->method( 'getConfig' )
			->willReturn( $config );

		$outputPage = $this->getMockBuilder( OutputPage::class )
			->setMethods( [ 'addMeta' ] )
			->setConstructorArgs( [ $context ] )
			->getMock();
		$outputPage->expects( $this->never() )
			->method( 'addMeta' );

		$skinTemplate = new SkinTemplate();
		PageImages::onBeforePageDisplay( $outputPage, $skinTemplate );
	}

	/**
	 * @return Title
	 */
	private function newTitle() {
		$title = Title::newFromText( 'New' );
		$title->resetArticleID( 0 );
		return $title;
	}

}
