<?php

namespace Cite\Tests\Unit;

use Cite\ErrorReporter;
use Cite\ReferenceMessageLocalizer;
use Language;
use Message;
use Parser;
use ParserOptions;

/**
 * @coversDefaultClass \Cite\ErrorReporter
 *
 * @license GPL-2.0-or-later
 */
class ErrorReporterTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::getInterfaceLanguageAndSplitCache
	 * @covers ::plain
	 * @dataProvider provideErrors
	 */
	public function testPlain(
		string $key,
		string $expectedHtml,
		array $expectedCategories
	) {
		$language = $this->createLanguage();
		$reporter = $this->createReporter( $language );
		$mockParser = $this->createParser( $language, $expectedCategories );
		$this->assertSame(
			$expectedHtml,
			$reporter->plain( $mockParser, $key, 'first param' ) );
	}

	/**
	 * @covers ::halfParsed
	 */
	public function testHalfParsed() {
		$language = $this->createLanguage();
		$reporter = $this->createReporter( $language );
		$mockParser = $this->createParser( $language, [] );
		$this->assertSame(
			'<span class="warning mw-ext-cite-warning mw-ext-cite-warning-example" lang="qqx" ' .
				'dir="rtl">[(cite_warning|(cite_warning_example|first param))]</span>',
			$reporter->halfParsed( $mockParser, 'cite_warning_example', 'first param' ) );
	}

	public function provideErrors() {
		return [
			'Example error' => [
				'cite_error_example',
				'<span class="error mw-ext-cite-error" lang="qqx" dir="rtl">' .
					'(cite_error|(cite_error_example|first param))</span>',
				[ 'cite-tracking-category-cite-error' ]
			],
			'Warning error' => [
				'cite_warning_example',
				'<span class="warning mw-ext-cite-warning mw-ext-cite-warning-example" lang="qqx" ' .
					'dir="rtl">(cite_warning|(cite_warning_example|first param))</span>',
				[]
			],
		];
	}

	private function createLanguage() : Language {
		$language = $this->createMock( Language::class );
		$language->method( 'getDir' )->willReturn( 'rtl' );
		$language->method( 'getHtmlCode' )->willReturn( 'qqx' );
		return $language;
	}

	private function createReporter( Language $language ) : ErrorReporter {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $language ) {
				$message = $this->createMock( Message::class );
				$message->method( 'getKey' )->willReturn( $args[0] );
				$message->method( 'plain' )->willReturn( '(' . implode( '|', $args ) . ')' );
				$message->method( 'inLanguage' )->with( $language )->willReturnSelf();
				$message->method( 'getLanguage' )->willReturn( $language );
				return $message;
			}
		);

		/** @var ReferenceMessageLocalizer $mockMessageLocalizer */
		return new ErrorReporter( $mockMessageLocalizer );
	}

	public function createParser( Language $language, array $expectedCategories ) {
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'getUserLangObj' )->willReturn( $language );

		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->exactly( count( $expectedCategories ) ) )
			->method( 'addTrackingCategory' )
			->withConsecutive( $expectedCategories );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parser->method( 'recursiveTagParse' )->willReturnCallback(
			function ( $content ) {
				return '[' . $content . ']';
			}
		);
		return $parser;
	}

}
