<?php

use Wikimedia\TestingAccessWrapper;

class ImagePageTest extends MediaWikiMediaTestCase {

	protected function setUp() : void {
		$this->setMwGlobals( 'wgImageLimits', [
			[ 320, 240 ],
			[ 640, 480 ],
			[ 800, 600 ],
			[ 1024, 768 ],
			[ 1280, 1024 ]
		] );
		parent::setUp();
	}

	public function getImagePage( $filename ) {
		$title = Title::makeTitleSafe( NS_FILE, $filename );
		$file = $this->dataFile( $filename );
		$iPage = new ImagePage( $title );
		$iPage->setFile( $file );
		return $iPage;
	}

	/**
	 * @covers ImagePage::getThumbSizes
	 * @dataProvider providerGetThumbSizes
	 * @param string $filename
	 * @param int $expectedNumberThumbs How many thumbnails to show
	 */
	public function testGetThumbSizes( $filename, $expectedNumberThumbs ) {
		$iPage = $this->getImagePage( $filename );
		$reflection = new ReflectionClass( $iPage );
		$reflMethod = $reflection->getMethod( 'getThumbSizes' );
		$reflMethod->setAccessible( true );

		$actual = $reflMethod->invoke( $iPage, 545, 700 );
		$this->assertEquals( count( $actual ), $expectedNumberThumbs );
	}

	public function providerGetThumbSizes() {
		return [
			[ 'animated.gif', 2 ],
			[ 'Toll_Texas_1.svg', 1 ],
			[ '80x60-Greyscale.xcf', 1 ],
			[ 'jpeg-comment-binary.jpg', 2 ],
		];
	}

	/**
	 * @covers ImagePage::getLanguageForRendering()
	 * @dataProvider provideGetLanguageForRendering
	 *
	 * @param string|null $expected Expected language code
	 * @param string $wikiLangCode Wiki language code
	 * @param string|null $lang lang=... URL parameter
	 */
	public function testGetLanguageForRendering( $expected, $wikiLangCode, $lang = null ) {
		$params = [];
		if ( $lang !== null ) {
			$params['lang'] = $lang;
		}
		$request = new FauxRequest( $params );
		$this->setMwGlobals( 'wgLanguageCode', $wikiLangCode );

		$page = $this->getImagePage( 'translated.svg' );
		$page = TestingAccessWrapper::newFromObject( $page );

		/** @var ImagePage $page */
		$result = $page->getLanguageForRendering( $request, $page->getDisplayedFile() );
		$this->assertEquals( $expected, $result );
	}

	public function provideGetLanguageForRendering() {
		return [
			[ 'ru', 'ru' ],
			[ 'ru', 'ru', 'ru' ],
			[ null, 'en' ],
			[ null, 'fr' ],
			[ null, 'en', 'en' ],
			[ null, 'fr', 'fr' ],
			[ null, 'ru', 'en' ],
			[ 'de', 'ru', 'de' ],
		];
	}
}
