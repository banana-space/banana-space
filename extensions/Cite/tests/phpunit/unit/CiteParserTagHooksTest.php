<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\Hooks\CiteParserTagHooks;
use Parser;
use ParserOutput;
use PPFrame;

/**
 * @coversDefaultClass \Cite\Hooks\CiteParserTagHooks
 *
 * @license GPL-2.0-or-later
 */
class CiteParserTagHooksTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::register
	 */
	public function testRegister() {
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->exactly( 2 ) )
			->method( 'setHook' )
			->withConsecutive(
				[ 'ref', $this->isType( 'callable' ) ],
				[ 'references', $this->isType( 'callable' ) ]
			);

		CiteParserTagHooks::register( $parser );
	}

	/**
	 * @covers ::ref
	 */
	public function testRef_fails() {
		$cite = $this->createMock( Cite::class );
		$cite->method( 'ref' )
			->willReturn( false );

		$parser = $this->createMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$html = CiteParserTagHooks::ref( null, [], $parser, $frame );
		$this->assertSame( '&lt;ref&gt;&lt;/ref&gt;', $html );
	}

	/**
	 * @covers ::citeForParser
	 * @covers ::ref
	 */
	public function testRef() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'ref' )
			->willReturn( '<HTML>' );

		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->expects( $this->once() )
			->method( 'addModules' );
		$parserOutput->expects( $this->once() )
			->method( 'addModuleStyles' );

		$parser = $this->createMock( Parser::class );
		$parser->method( 'getOutput' )
			->willReturn( $parserOutput );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$html = CiteParserTagHooks::ref( null, [], $parser, $frame );
		$this->assertSame( '<HTML>', $html );
	}

	/**
	 * @covers ::references
	 */
	public function testReferences_fails() {
		$cite = $this->createMock( Cite::class );
		$cite->method( 'references' )
			->willReturn( false );

		$parser = $this->createMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$html = CiteParserTagHooks::references( null, [], $parser, $frame );
		$this->assertSame( '&lt;references/&gt;', $html );
	}

	/**
	 * @covers ::citeForParser
	 * @covers ::references
	 */
	public function testReferences() {
		$cite = $this->createMock( Cite::class );
		$cite->expects( $this->once() )
			->method( 'references' )
			->willReturn( '<HTML>' );

		$parser = $this->createMock( Parser::class );
		$parser->extCite = $cite;

		$frame = $this->createMock( PPFrame::class );

		$html = CiteParserTagHooks::references( null, [], $parser, $frame );
		$this->assertSame( '<HTML>', $html );
	}

}
