<?php

/**
 * @group TemplateStyles
 * @covers TemplateStylesContent
 */
class TemplateStylesContentTest extends TextContentTest {

	protected function setUp() : void {
		parent::setUp();

		$this->setMwGlobals( [
			'wgTextModelsToParse' => [
				'sanitized-css',
			],
			'wgTemplateStylesMaxStylesheetSize' => 1024000,
		] );
	}

	public function newContent( $text ) {
		return new TemplateStylesContent( $text );
	}

	/**
	 * @dataProvider provideSanitize
	 * @param string $text Input text
	 * @param array $options
	 * @param Status $expect
	 */
	public function testSanitize( $text, $options, $expect ) {
		$this->assertEquals( $expect, $this->newContent( $text )->sanitize( $options ) );
	}

	public static function provideSanitize() {
		$status1 = Status::newGood( '.mw-parser-output .foo{}' );
		$status1->warning( 'templatestyles-error-bad-value-for-property', 1, 15, 'color' );

		return [
			'flip' => [
				'.foo { margin-left: 10px; /*@noflip*/ padding-left: 1em; }',
				[ 'flip' => true ],
				Status::newGood( '.mw-parser-output .foo{margin-right:10px;padding-left:1em}' )
			],
			'no minify' => [
				'.foo { margin-left: 10px }',
				[ 'minify' => false ],
				Status::newGood( '.mw-parser-output .foo { margin-left: 10px ; }' )
			],
			'With warnings' => [
				'.foo { color: bogus; }',
				[],
				$status1
			],
			'With warnings, fatal and no value' => [
				'.foo { bogus: bogus; }',
				[ 'severity' => 'fatal', 'novalue' => true ],
				Status::newFatal( 'templatestyles-error-unrecognized-property', 1, 8 ),
			],
			'With overridden class prefix' => [
				'.foo { margin-left: 10px }',
				[ 'class' => 'foo bar', 'minify' => false ],
				Status::newGood( '.foo\ bar .foo { margin-left: 10px ; }' )
			],
			'With boolean false as a class prefix' => [
				'.foo { margin-left: 10px }',
				[ 'class' => false, 'minify' => false ],
				Status::newGood( '.mw-parser-output .foo { margin-left: 10px ; }' )
			],
			'With an extra wrapper' => [
				'.foo { margin-left: 10px }',
				[ 'extraWrapper' => 'div.class' ],
				Status::newGood( '.mw-parser-output div.class .foo{margin-left:10px}' )
			],
			'Escaping U+007F' => [
				".foo\\\x7f { content: '\x7f'; }",
				[],
				Status::newGood(
					'.mw-parser-output .foo\\7f {content:"\\7f "}'
				)
			],
			'@font-face prefixing' => [
				'@font-face { font-family: nope; }',
				[ 'severity' => 'fatal', 'novalue' => true ],
				Status::newFatal( 'templatestyles-error-bad-value-for-property', 1, 27, 'font-family' ),
			],
			'</style> in string' => [
				'.foo { content: "</style>"; }',
				[],
				Status::newGood( '.mw-parser-output .foo{content:"\3c /style\3e "}' )
			],
			'</style> via identifiers' => [
				'.foo { grid-area: \< / style 0 / \>; }',
				[],
				Status::newGood( '.mw-parser-output .foo{grid-area:\3c /style 0/\3e }' ),
			],
		];
	}

	public function testInvalidWrapper() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid value for $extraWrapper: .foo>.bar' );
		$this->newContent( '.foo { margin-left: 10px }' )->sanitize( [
			'extraWrapper' => '.foo>.bar',
		] );
	}

	public function testCrazyBrokenSanitizer() {
		// Big hack: Make a Token that returns a bad string, and a Sanitizer
		// that returns that bad Token, just so we can test a code path that
		// handles such bad output.
		$this->setTemporaryHook(
			'TemplateStylesStylesheetSanitizer',
			function ( &$sanitizer ) {
				$badToken = $this->getMockBuilder( Wikimedia\CSS\Objects\Token::class )
					->disableOriginalConstructor()
					->setMethods( [ '__toString' ] )
					->getMock();
				$badToken->method( '__toString' )->willReturn( '"</style>"' );

				$sanitizer = $this->getMockBuilder( Wikimedia\CSS\Sanitizer\StylesheetSanitizer::class )
					->disableOriginalConstructor()
					->setMethods( [ 'sanitize' ] )
					->getMock();
				$sanitizer->method( 'sanitize' )->willReturn( $badToken );
				return false;
			}
		);

		$this->assertEquals(
			Status::newFatal( 'templatestyles-end-tag-injection' ),
			$this->newContent( '.foo {}' )->sanitize( [ 'class' => 'testCrazyBrokenSanitizer' ] )
		);
	}

	public function testSizeLimit() {
		$this->setMwGlobals( [
			'wgTemplateStylesMaxStylesheetSize' => 10,
		] );

		$this->assertEquals(
			Status::newGood( '.mw-parser-output .foobar{}' ),
			$this->newContent( '.foobar {}' )->sanitize()
		);
		$this->assertEquals(
			Status::newFatal( wfMessage( 'templatestyles-size-exceeded', 10, Message::sizeParam( 10 ) ) ),
			$this->newContent( '.foobar2 {}' )->sanitize()
		);

		$this->setMwGlobals( [
			'wgTemplateStylesMaxStylesheetSize' => null,
		] );
		$long = str_repeat( 'X', 102400 );
		$this->assertEquals(
			Status::newGood( ".mw-parser-output .{$long}{}" ),
			$this->newContent( ".{$long} {}" )->sanitize()
		);
	}

	public function testPrepareSave() {
		$this->assertEquals(
			$this->newContent( '.foo { bogus: bogus; }' )->prepareSave(
				WikiPage::factory( Title::newFromText( 'Template:Test/styles.css' ) ),
				0,
				123,
				new User
			),
			Status::newFatal( 'templatestyles-error-unrecognized-property', 1, 8 )
		);
	}

	public static function dataGetParserOutput() {
		return [
			[
				'Template:Test/styles.css',
				'sanitized-css',
				".hello { content: 'world'; color: bogus; }\n\n<ok>\n",
				// @codingStandardsIgnoreStart Generic.Files.LineLength
				"<pre class=\"mw-code mw-css\" dir=\"ltr\">\n.hello { content: 'world'; color: bogus; }\n\n&lt;ok&gt;\n\n</pre>",
				// @codingStandardsIgnoreEnd
				[
					'Warnings' => [
						'Unexpected end of stylesheet in rule at line 4 character 1.',
						'Invalid or unsupported value for property <code>color</code> at line 1 character 35.',
					]
				]
			],
			[
				'Template:Test/styles.css',
				'sanitized-css',
				"/* hello [[world]] */\n",
				"<pre class=\"mw-code mw-css\" dir=\"ltr\">\n/* hello [[world]] */\n\n</pre>",
				[
					'Links' => [
						[ 'World' => 0 ]
					]
				]
			],
		];
	}

	public static function dataPreSaveTransform() {
		return [
			[
				'hello this is ~~~',
				'hello this is ~~~',
			],
			[
				'hello \'\'this\'\' is <nowiki>~~~</nowiki>',
				'hello \'\'this\'\' is <nowiki>~~~</nowiki>',
			],
			[
				" Foo \n ",
				" Foo",
			],
		];
	}

	public static function dataPreloadTransform() {
		return [
			[
				'hello this is ~~~',
				'hello this is ~~~',
			],
			[
				'hello \'\'this\'\' is <noinclude>foo</noinclude><includeonly>bar</includeonly>',
				'hello \'\'this\'\' is <noinclude>foo</noinclude><includeonly>bar</includeonly>',
			],
		];
	}

	public function testGetModel() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'sanitized-css', $content->getModel() );
	}

	public function testGetContentHandler() {
		$content = $this->newContent( 'hello world.' );

		$this->assertEquals( 'sanitized-css', $content->getContentHandler()->getModelID() );
	}

	/**
	 * Redirects aren't supported
	 */
	public static function provideUpdateRedirect() {
		// @codingStandardsIgnoreStart Generic.Files.LineLength
		return [
			[
				'#REDIRECT [[Someplace]]',
				'#REDIRECT [[Someplace]]',
			],

			// The style supported by CssContent
			[
				'/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);',
				'/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);',
			],
		];
		// @codingStandardsIgnoreEnd
	}

	/**
	 * @dataProvider provideGetRedirectTarget
	 */
	public function testGetRedirectTarget( $title, $text ) {
		$this->setMwGlobals( [
			'wgServer' => '//example.org',
			'wgScriptPath' => '/w',
			'wgScript' => '/w/index.php',
		] );
		$content = $this->newContent( $text );
		$target = $content->getRedirectTarget();
		$this->assertEquals( $title, $target ? $target->getPrefixedText() : null );
	}

	public static function provideGetRedirectTarget() {
		// @codingStandardsIgnoreStart Generic.Files.LineLength
		return [
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=MediaWiki:MonoBook.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=User:FooBar/common.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.org/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
			[ null, "@import url(//example.org/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
			[ null, "/* #REDIRECT */@import url(//example.com/w/index.php?title=Gadget:FooBaz.css&action=raw&ctype=text/css);" ],
		];
		// @codingStandardsIgnoreEnd
	}

	public static function dataEquals() {
		return [
			[ new TemplateStylesContent( 'hallo' ), null, false ],
			[ new TemplateStylesContent( 'hallo' ), new TemplateStylesContent( 'hallo' ), true ],
			[ new TemplateStylesContent( 'hallo' ), new CssContent( 'hallo' ), false ],
			[ new TemplateStylesContent( 'hallo' ), new WikitextContent( 'hallo' ), false ],
			[ new TemplateStylesContent( 'hallo' ), new TemplateStylesContent( 'HALLO' ), false ],
		];
	}

	/**
	 * @dataProvider dataEquals
	 */
	public function testEquals( Content $a, Content $b = null, $equal = false ) {
		$this->assertEquals( $equal, $a->equals( $b ) );
	}
}
