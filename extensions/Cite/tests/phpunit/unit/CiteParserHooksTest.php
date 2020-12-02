<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\Hooks\CiteParserHooks;
use Parser;
use ParserOptions;
use ParserOutput;
use StripState;

/**
 * @coversDefaultClass \Cite\Hooks\CiteParserHooks
 *
 * @license GPL-2.0-or-later
 */
class CiteParserHooksTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::onParserFirstCallInit
	 */
	public function testOnParserFirstCallInit() {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->exactly( 2 ) )
			->method( 'setHook' )
			->withConsecutive(
				[ 'ref', $this->isType( 'callable' ) ],
				[ 'references', $this->isType( 'callable' ) ]
			);

		CiteParserHooks::onParserFirstCallInit( $parser );
	}

	/**
	 * @covers ::onParserClearStateOrCloned
	 */
	public function testOnParserCloned() {
		$parser = $this->createMock( Parser::class );
		$parser->extCite = $this->createMock( Cite::class );

		/** @var Parser $parser */
		CiteParserHooks::onParserClearStateOrCloned( $parser );

		$this->assertFalse( isset( $parser->extCite ) );
	}

	/**
	 * @covers ::onParserAfterParse
	 */
	public function testAfterParseHooks() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'checkRefsNoReferences' );

		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'getIsSectionPreview' )
			->willReturn( false );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'getOptions' )
			->willReturn( $parserOptions );
		$parser->method( 'getOutput' )
			->willReturn( $this->createMock( ParserOutput::class ) );
		$parser->extCite = $cite;

		$text = '';
		CiteParserHooks::onParserAfterParse( $parser, $text, $this->createMock( StripState::class ) );
	}

}
