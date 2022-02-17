<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Connection;
use ContentHandler;
use Elastica\Document;
use ParserCache;
use ParserOutput;
use Title;
use WikiPage;

/**
 * @group Database
 * @covers \CirrusSearch\BuildDocument\ParserOutputPageProperties
 */
class ParserOutputPagePropertiesTest extends \MediaWikiIntegrationTestCase {
	public function testFixAndFlagInvalidUTF8InSource() {
		$this->assertNotContains( 'CirrusSearchInvalidUTF8',
			ParserOutputPageProperties::fixAndFlagInvalidUTF8InSource(
				[ 'source_text' => 'valid' ], 1 )['template'] ?? [], 1 );
		$this->assertContains( 'Template:CirrusSearchInvalidUTF8',
			ParserOutputPageProperties::fixAndFlagInvalidUTF8InSource(
				[ 'source_text' => chr( 130 ) ], 1 )['template'] ?? [] );
	}

	public function displayTitleProvider() {
		$mainTitle = Title::makeTitle( NS_MAIN, 'Phpunit' );
		$this->forceTitleLang( $mainTitle, 'fr' );
		$talkTitle = Title::makeTitle( NS_TALK, 'Phpunit' );
		$this->forceTitleLang( $talkTitle, 'fr' );
		return [
			'null when no display title is set' => [
				null, $mainTitle, false,
			],
			'null when display title matches normal title (ns_main)' => [
				null, $mainTitle, 'Phpunit',
			],
			'null when display title matches normal title without namespace prefix' => [
				null, $talkTitle, 'Phpunit',
			],
			'null when display title matches normal title in different case' => [
				null, $mainTitle, 'phpunit',
			],
			'null when display title matches normal ns:title' => [
				null, $talkTitle, 'talk:phpunit',
			],
			'null when display title has only extra html tags (ns_main)' => [
				null, $mainTitle, 'php<i>unit</i>',
			],
			'null when display title has only extra html tags (ns_talk)' => [
				null, $talkTitle, 'php<i>unit</i>',
			],
			'values different from title text are returned' => [
				'foo', $mainTitle, 'foo',
			],
			'strips html' => [
				'foo', $mainTitle, '<b>foo</b>',
			],
			'strips broken html' => [
				'foo', $mainTitle, 'fo<b>o',
			],
			'strips namespace if it matches doc namespace' => [
				'foo', $talkTitle, 'talk:foo',
			],
			'strips namespaces in the language of the document' => [
				'bar', $talkTitle, 'Discussion:bar',
			],
			'strips namespaces aliases as well' => [
				'bar', $talkTitle, 'Discuter:bar',
			],
			'ignores namespace case' => [
				'bar', $talkTitle, 'discuter:bar',
			],
			'null when only difference is translated namespace' => [
				null, $talkTitle, 'Discuter:<i>phpunit</i>',
			],
			'leaves non-namespaces in display title (ns_main)' => [
				'foo:bar', $mainTitle, 'foo:bar',
			],
			'leaves non-namespaces in display title (ns_talk)' => [
				'foo:bar', $talkTitle, 'foo:bar',
			],
			'leaves existing but unrelated namespaces in display title' => [
				'user:bar', $talkTitle, 'user:bar',
			],
			'invalid title is kept on NS_MAIN' => [
				':', $mainTitle, ':',
			],
			'invalid title is kept on non NS_MAIN' => [
				':', $talkTitle, ':',
			],
		];
	}

	private function buildDoc( WikiPage $page ) {
		$doc = new Document( null, [] );
		$cache = $this->mock( ParserCache::class );
		$builder = new ParserOutputPageProperties( $cache, false );
		$builder->finalizeReal( $doc, $page, null, new CirrusSearch );
		return $doc;
	}

	/**
	 * @dataProvider displayTitleProvider
	 */
	public function testDisplayTitle( $expected, Title $title, $displayTitle ) {
		$parserOutput = $this->mock( ParserOutput::class );
		$parserOutput->expects( $this->any() )
			->method( 'getDisplayTitle' )
			->will( $this->returnValue( $displayTitle ) );

		$engine = new CirrusSearch();
		$page = $this->pageWithMockParserOutput( $title, $parserOutput );
		$conn = $this->mock( Connection::class );
		$doc = $this->buildDoc( $page );
		$this->assertTrue( $doc->has( 'display_title' ), 'field must exist' );
		$this->assertSame( $expected, $doc->get( 'display_title' ) );
	}

	private function mock( $className ) {
		return $this->getMockBuilder( $className )
			->disableOriginalConstructor()
			->getMock();
	}

	private function pageWithMockParserOutput( Title $title, ParserOutput $parserOutput ) {
		$contentHandler = $this->mock( ContentHandler::class );
		$contentHandler->expects( $this->any() )
			->method( 'getParserOutputForIndexing' )
			->will( $this->returnValue( $parserOutput ) );
		$contentHandler->expects( $this->any() )
			->method( 'getDataForSearchIndex' )
			->will( $this->returnValue( [] ) );

		$page = $this->mock( WikiPage::class );
		$page->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );
		$page->expects( $this->any() )
			->method( 'getContentHandler' )
			->will( $this->returnValue( $contentHandler ) );
		$page->expects( $this->any() )
			->method( 'getContent' )
			->will( $this->returnValue( new \WikitextContent( 'TEST_CONTENT' ) ) );
		$page->expects( $this->any() )
			->method( 'getId' )
			->will( $this->returnValue( 2 ) );

		return $page;
	}

	private function forceTitleLang( \Title $title, $langCode ) {
		global $wgLanguageCode;
		$refl = new \ReflectionProperty( \Title::class, 'mPageLanguage' );
		$refl->setAccessible( true );
		$refl->setValue( $title, [ $langCode, $wgLanguageCode ] );
	}
}
