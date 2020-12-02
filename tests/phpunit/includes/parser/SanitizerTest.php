<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Sanitizer
 */
class SanitizerTest extends MediaWikiIntegrationTestCase {

	protected function tearDown() : void {
		MWTidy::destroySingleton();
		parent::tearDown();
	}

	/**
	 * @covers Sanitizer::removeHTMLtags
	 * @dataProvider provideHtml5Tags
	 *
	 * @param string $tag Name of an HTML5 element (ie: 'video')
	 * @param bool $escaped Whether sanitizer let the tag in or escape it (ie: '&lt;video&gt;')
	 */
	public function testRemovehtmltagsOnHtml5Tags( $tag, $escaped ) {
		if ( $escaped ) {
			$this->assertEquals( "&lt;$tag&gt;",
				Sanitizer::removeHTMLtags( "<$tag>" )
			);
		} else {
			$this->assertEquals( "<$tag></$tag>\n",
				Sanitizer::removeHTMLtags( "<$tag></$tag>\n" )
			);
		}
	}

	/**
	 * Provide HTML5 tags
	 */
	public static function provideHtml5Tags() {
		$ESCAPED = true; # We want tag to be escaped
		$VERBATIM = false; # We want to keep the tag
		return [
			[ 'data', $VERBATIM ],
			[ 'mark', $VERBATIM ],
			[ 'time', $VERBATIM ],
			[ 'video', $ESCAPED ],
		];
	}

	public function dataRemoveHTMLtags() {
		return [
			// former testSelfClosingTag
			[
				'<div>Hello world</div />',
				'<div>Hello world</div>',
				'Self-closing closing div'
			],
			// Make sure special nested HTML5 semantics are not broken
			// https://html.spec.whatwg.org/multipage/semantics.html#the-kbd-element
			[
				'<kbd><kbd>Shift</kbd>+<kbd>F3</kbd></kbd>',
				'<kbd><kbd>Shift</kbd>+<kbd>F3</kbd></kbd>',
				'Nested <kbd>.'
			],
			// https://html.spec.whatwg.org/multipage/semantics.html#the-sub-and-sup-elements
			[
				'<var>x<sub><var>i</var></sub></var>, <var>y<sub><var>i</var></sub></var>',
				'<var>x<sub><var>i</var></sub></var>, <var>y<sub><var>i</var></sub></var>',
				'Nested <var>.'
			],
			// https://html.spec.whatwg.org/multipage/semantics.html#the-dfn-element
			[
				'<dfn><abbr title="Garage Door Opener">GDO</abbr></dfn>',
				'<dfn><abbr title="Garage Door Opener">GDO</abbr></dfn>',
				'<abbr> inside <dfn>',
			],
		];
	}

	/**
	 * @dataProvider dataRemoveHTMLtags
	 * @covers Sanitizer::removeHTMLtags
	 */
	public function testRemoveHTMLtags( $input, $output, $msg = null ) {
		$this->assertEquals( $output, Sanitizer::removeHTMLtags( $input ), $msg );
	}

	/**
	 * @dataProvider provideDeprecatedAttributes
	 * @covers Sanitizer::fixTagAttributes
	 * @covers Sanitizer::validateTagAttributes
	 * @covers Sanitizer::validateAttributes
	 */
	public function testDeprecatedAttributesUnaltered( $inputAttr, $inputEl, $message = '' ) {
		$this->assertEquals( " $inputAttr",
			Sanitizer::fixTagAttributes( $inputAttr, $inputEl ),
			$message
		);
	}

	public static function provideDeprecatedAttributes() {
		/** [ <attribute>, <element>, [message] ] */
		return [
			[ 'clear="left"', 'br' ],
			[ 'clear="all"', 'br' ],
			[ 'width="100"', 'td' ],
			[ 'nowrap="true"', 'td' ],
			[ 'nowrap=""', 'td' ],
			[ 'align="right"', 'td' ],
			[ 'align="center"', 'table' ],
			[ 'align="left"', 'tr' ],
			[ 'align="center"', 'div' ],
			[ 'align="left"', 'h1' ],
			[ 'align="left"', 'p' ],
		];
	}

	/**
	 * @dataProvider provideValidateTagAttributes
	 * @covers Sanitizer::validateTagAttributes
	 * @covers Sanitizer::validateAttributes
	 */
	public function testValidateTagAttributes( $element, $attribs, $expected ) {
		$actual = Sanitizer::validateTagAttributes( $attribs, $element );
		$this->assertArrayEquals( $expected, $actual, false, true );
	}

	public static function provideValidateTagAttributes() {
		return [
			[ 'math',
				[ 'id' => 'foo bar', 'bogus' => 'stripped', 'data-foo' => 'bar' ],
				[ 'id' => 'foo_bar', 'data-foo' => 'bar' ],
			],
			[ 'meta',
				[ 'id' => 'foo bar', 'itemprop' => 'foo', 'content' => 'bar' ],
				[ 'itemprop' => 'foo', 'content' => 'bar' ],
			],
			[ 'div',
				[ 'role' => 'presentation', 'aria-hidden' => 'true' ],
				[ 'role' => 'presentation', 'aria-hidden' => 'true' ],
			],
			[ 'div',
				[ 'role' => 'menuitem', 'aria-hidden' => 'false' ],
				[ 'role' => 'menuitem', 'aria-hidden' => 'false' ],
			],
		];
	}

	/**
	 * @dataProvider provideAttributesAllowed
	 * @covers Sanitizer::attributesAllowedInternal
	 */
	public function testAttributesAllowedInternal( $element, $attribs ) {
		$sanitizer = TestingAccessWrapper::newFromClass( Sanitizer::class );
		$actual = $sanitizer->attributesAllowedInternal( $element );
		$this->assertArrayEquals( $attribs, array_keys( $actual ) );
	}

	public function provideAttributesAllowed() {
		/** [ <element>, [ <good attribute 1>, <good attribute 2>, ...] ] */
		return [
			[ 'math', [ 'class', 'style', 'id', 'title' ] ],
			[ 'meta', [ 'itemprop', 'content' ] ],
			[ 'link', [ 'itemprop', 'href', 'title' ] ],
		];
	}

	/**
	 * @dataProvider provideEscapeIdForStuff
	 *
	 * @covers Sanitizer::escapeIdForAttribute()
	 * @covers Sanitizer::escapeIdForLink()
	 * @covers Sanitizer::escapeIdForExternalInterwiki()
	 * @covers Sanitizer::escapeIdInternal()
	 * @covers Sanitizer::escapeIdInternalUrl()
	 *
	 * @param string $stuff
	 * @param string[] $config
	 * @param string $id
	 * @param string|false $expected
	 * @param int|null $mode
	 */
	public function testEscapeIdForStuff( $stuff, array $config, $id, $expected, $mode = null ) {
		$func = "Sanitizer::escapeIdFor{$stuff}";
		$iwFlavor = array_pop( $config );
		$this->setMwGlobals( [
			'wgFragmentMode' => $config,
			'wgExternalInterwikiFragmentMode' => $iwFlavor,
		] );
		$escaped = $func( $id, $mode );
		self::assertEquals( $expected, $escaped );
	}

	public function provideEscapeIdForStuff() {
		// Test inputs and outputs
		$text = 'foo тест_#%!\'()[]:<>&&amp;&amp;amp;%F0';
		$legacyEncoded = 'foo_.D1.82.D0.B5.D1.81.D1.82_.23.25.21.27.28.29.5B.5D:.3C.3E' .
			'.26.26amp.3B.26amp.3Bamp.3B.25F0';
		$html5EncodedId = 'foo_тест_#%!\'()[]:<>&&amp;&amp;amp;%F0';
		$html5EncodedHref = 'foo_тест_#%!\'()[]:<>&&amp;&amp;amp;%25F0';

		// Settings: last element is $wgExternalInterwikiFragmentMode, the rest is $wgFragmentMode
		$legacy = [ 'legacy', 'legacy' ];
		$legacyNew = [ 'legacy', 'html5', 'legacy' ];
		$newLegacy = [ 'html5', 'legacy', 'legacy' ];
		$new = [ 'html5', 'legacy' ];
		$allNew = [ 'html5', 'html5' ];

		return [
			// Pure legacy: how MW worked before 2017
			[ 'Attribute', $legacy, $text, $legacyEncoded, Sanitizer::ID_PRIMARY ],
			[ 'Attribute', $legacy, $text, false, Sanitizer::ID_FALLBACK ],
			[ 'Link', $legacy, $text, $legacyEncoded ],
			[ 'ExternalInterwiki', $legacy, $text, $legacyEncoded ],

			// Transition to a new world: legacy links with HTML5 fallback
			[ 'Attribute', $legacyNew, $text, $legacyEncoded, Sanitizer::ID_PRIMARY ],
			[ 'Attribute', $legacyNew, $text, $html5EncodedId, Sanitizer::ID_FALLBACK ],
			[ 'Link', $legacyNew, $text, $legacyEncoded ],
			[ 'ExternalInterwiki', $legacyNew, $text, $legacyEncoded ],

			// New world: HTML5 links, legacy fallbacks
			[ 'Attribute', $newLegacy, $text, $html5EncodedId, Sanitizer::ID_PRIMARY ],
			[ 'Attribute', $newLegacy, $text, $legacyEncoded, Sanitizer::ID_FALLBACK ],
			[ 'Link', $newLegacy, $text, $html5EncodedHref ],
			[ 'ExternalInterwiki', $newLegacy, $text, $legacyEncoded ],

			// Distant future: no legacy fallbacks, but still linking to leagacy wikis
			[ 'Attribute', $new, $text, $html5EncodedId, Sanitizer::ID_PRIMARY ],
			[ 'Attribute', $new, $text, false, Sanitizer::ID_FALLBACK ],
			[ 'Link', $new, $text, $html5EncodedHref ],
			[ 'ExternalInterwiki', $new, $text, $legacyEncoded ],

			// Just before the heat death of universe: external interwikis are also HTML5 \m/
			[ 'Attribute', $allNew, $text, $html5EncodedId, Sanitizer::ID_PRIMARY ],
			[ 'Attribute', $allNew, $text, false, Sanitizer::ID_FALLBACK ],
			[ 'Link', $allNew, $text, $html5EncodedHref ],
			[ 'ExternalInterwiki', $allNew, $text, $html5EncodedHref ],

			// Whitespace
			[ 'attribute', $allNew, "foo bar", 'foo_bar', Sanitizer::ID_PRIMARY ],
			[ 'attribute', $allNew, "foo\fbar", 'foo_bar', Sanitizer::ID_PRIMARY ],
			[ 'attribute', $allNew, "foo\nbar", 'foo_bar', Sanitizer::ID_PRIMARY ],
			[ 'attribute', $allNew, "foo\tbar", 'foo_bar', Sanitizer::ID_PRIMARY ],
			[ 'attribute', $allNew, "foo\rbar", 'foo_bar', Sanitizer::ID_PRIMARY ],
		];
	}

	/**
	 * @covers Sanitizer::escapeIdInternal()
	 */
	public function testInvalidFragmentThrows() {
		$this->setMwGlobals( 'wgFragmentMode', [ 'boom!' ] );
		$this->expectException( InvalidArgumentException::class );
		Sanitizer::escapeIdForAttribute( 'This should throw' );
	}

	/**
	 * @covers Sanitizer::escapeIdForAttribute()
	 */
	public function testNoPrimaryFragmentModeThrows() {
		$this->setMwGlobals( 'wgFragmentMode', [ 666 => 'html5' ] );
		$this->expectException( UnexpectedValueException::class );
		Sanitizer::escapeIdForAttribute( 'This should throw' );
	}

	/**
	 * @covers Sanitizer::escapeIdForLink()
	 */
	public function testNoPrimaryFragmentModeThrows2() {
		$this->setMwGlobals( 'wgFragmentMode', [ 666 => 'html5' ] );
		$this->expectException( UnexpectedValueException::class );
		Sanitizer::escapeIdForLink( 'This should throw' );
	}

	/**
	 * Test escapeIdReferenceList for consistency with escapeIdForAttribute
	 *
	 * @dataProvider provideEscapeIdReferenceList
	 * @covers Sanitizer::escapeIdReferenceList
	 */
	public function testEscapeIdReferenceList( $referenceList, $id1, $id2 ) {
		$this->assertEquals(
			Sanitizer::escapeIdReferenceList( $referenceList ),
			Sanitizer::escapeIdForAttribute( $id1 )
			. ' '
			. Sanitizer::escapeIdForAttribute( $id2 )
		);
	}

	public static function provideEscapeIdReferenceList() {
		/** [ <reference list>, <individual id 1>, <individual id 2> ] */
		return [
			[ 'foo bar', 'foo', 'bar' ],
			[ '#1 #2', '#1', '#2' ],
			[ '+1 +2', '+1', '+2' ],
		];
	}

}
