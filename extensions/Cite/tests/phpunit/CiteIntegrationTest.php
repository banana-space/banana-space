<?php

namespace Cite\Tests;

use Cite\Cite;
use Cite\ErrorReporter;
use Cite\ReferencesFormatter;
use Cite\ReferenceStack;
use Language;
use Parser;
use ParserOptions;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\Cite
 *
 * @license GPL-2.0-or-later
 */
class CiteIntegrationTest extends \MediaWikiIntegrationTestCase {

	protected function setUp() : void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgLanguageCode' => 'qqx',
		] );
	}

	/**
	 * @covers ::checkRefsNoReferences
	 * @dataProvider provideCheckRefsNoReferences
	 */
	public function testCheckRefsNoReferences(
		array $initialRefs, bool $isSectionPreview, string $expectedOutput
	) {
		global $wgCiteResponsiveReferences;
		$wgCiteResponsiveReferences = true;

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'halfParsed' )->willReturnCallback(
			function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);

		/** @var ReferenceStack $referenceStack */
		$referenceStack = TestingAccessWrapper::newFromObject( new ReferenceStack( $mockErrorReporter ) );
		$referenceStack->refs = $initialRefs;

		$referencesFormatter = $this->createMock( ReferencesFormatter::class );
		$referencesFormatter->method( 'formatReferences' )->willReturn( '<references />' );

		$cite = $this->newCite();
		/** @var Cite $spy */
		$spy = TestingAccessWrapper::newFromObject( $cite );
		$spy->referenceStack = $referenceStack;
		$spy->errorReporter = $mockErrorReporter;
		$spy->referencesFormatter = $referencesFormatter;
		$spy->isSectionPreview = $isSectionPreview;

		$output = $cite->checkRefsNoReferences(
			$this->createMock( Parser::class ), $isSectionPreview );
		$this->assertSame( $expectedOutput, $output );
	}

	public function provideCheckRefsNoReferences() {
		return [
			'Default group' => [
				[
					'' => [
						[
							'name' => 'a',
						]
					]
				],
				false,
				"\n" . '<references />'
			],
			'Default group in preview' => [
				[
					'' => [
						[
							'name' => 'a',
						]
					]
				],
				true,
				"\n" . '<div class="mw-ext-cite-cite_section_preview_references">' .
				'<h2 id="mw-ext-cite-cite_section_preview_references_header">' .
				'(cite_section_preview_references)</h2>' . "\n" . '<references /></div>'
			],
			'Named group' => [
				[
					'foo' => [
						[
							'name' => 'a',
						]
					]
				],
				false,
				"\n" . '<br />(cite_error_group_refs_without_references|foo)'
			],
			'Named group in preview' => [
				[
					'foo' => [
						[
							'name' => 'a',
						]
					]
				],
				true,
				"\n" . '<div class="mw-ext-cite-cite_section_preview_references">' .
				'<h2 id="mw-ext-cite-cite_section_preview_references_header">' .
				'(cite_section_preview_references)</h2>' . "\n" . '<references /></div>'
			]
		];
	}

	private function newCite() : Cite {
		$mockOptions = $this->createMock( ParserOptions::class );
		$mockOptions->method( 'getIsPreview' )->willReturn( false );
		$mockOptions->method( 'getIsSectionPreview' )->willReturn( false );
		$mockOptions->method( 'getUserLangObj' )->willReturn(
			$this->createMock( Language::class ) );
		$mockParser = $this->createMock( Parser::class );
		$mockParser->method( 'getOptions' )->willReturn( $mockOptions );
		$mockParser->method( 'getContentLanguage' )->willReturn(
			$this->createMock( Language::class ) );
		/** @var Parser $mockParser */
		return new Cite( $mockParser );
	}

}
