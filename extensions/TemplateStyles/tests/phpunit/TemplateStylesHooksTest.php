<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * @group TemplateStyles
 * @group Database
 * @covers TemplateStylesHooks
 */
class TemplateStylesHooksTest extends MediaWikiLangTestCase {

	protected function addPage( $page, $text, $model ) {
		$title = Title::newFromText( 'Template:TemplateStyles test/' . $page );
		$content = ContentHandler::makeContent( $text, $title, $model );

		$page = WikiPage::factory( $title );
		$user = static::getTestSysop()->getUser();
		$status = $page->doEditContent( $content, 'Test for TemplateStyles', 0, false, $user );
		if ( !$status->isOk() ) {
			$this->fail( "Failed to create $title: " . $status->getWikiText( false, false, 'en' ) );
		}
	}

	public function addDBDataOnce() {
		$this->addPage( 'wikitext', '.foo { color: red; }', CONTENT_MODEL_WIKITEXT );
		$this->addPage( 'nonsanitized.css', '.foo { color: red; }', CONTENT_MODEL_CSS );
		$this->addPage( 'styles1.css', '.foo { color: blue; }', 'sanitized-css' );
		$this->addPage( 'styles2.css', '.bar { color: green; }', 'sanitized-css' );
		$this->addPage(
			'styles3.css', 'html.no-js body.skin-minerva .bar { color: yellow; }', 'sanitized-css'
		);
	}

	public function testGetSanitizerInvalidWrapper() {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid value for $extraWrapper: .foo>.bar' );
		TemplateStylesHooks::getSanitizer( 'foo', '.foo>.bar' );
	}

	/**
	 * @dataProvider provideOnRegistration
	 * @param array $textModelsToParse
	 * @param bool $autoParseContent
	 * @param array $expect
	 */
	public function testOnRegistration( $textModelsToParse, $autoParseContent, $expect ) {
		$this->setMwGlobals( [
			'wgTextModelsToParse' => $textModelsToParse,
			'wgTemplateStylesAutoParseContent' => $autoParseContent,
		] );

		global $wgTextModelsToParse;
		TemplateStylesHooks::onRegistration();
		$this->assertSame( $expect, $wgTextModelsToParse );
	}

	public static function provideOnRegistration() {
		return [
			[
				[ CONTENT_MODEL_WIKITEXT ],
				true,
				[ CONTENT_MODEL_WIKITEXT ]
			],
			[
				[ CONTENT_MODEL_WIKITEXT, CONTENT_MODEL_CSS ],
				true,
				[ CONTENT_MODEL_WIKITEXT, CONTENT_MODEL_CSS, 'sanitized-css' ],
			],
			[
				[ CONTENT_MODEL_WIKITEXT, CONTENT_MODEL_CSS ],
				false,
				[ CONTENT_MODEL_WIKITEXT, CONTENT_MODEL_CSS ],
			],
		];
	}

	/**
	 * @dataProvider provideOnContentHandlerDefaultModelFor
	 */
	public function testOnContentHandlerDefaultModelFor( $ns, $title, $expect ) {
		$this->setMwGlobals( [
			'wgTemplateStylesNamespaces' => [
				10 => true,
				2 => false,
				3000 => true,
				3002 => true,
				3006 => false,
			],
			'wgNamespacesWithSubpages' => [
				10 => true,
				2 => true,
				3000 => true,
				3002 => false,
				3004 => true,
				3006 => true
			],
		] );

		$reset = ExtensionRegistry::getInstance()->setAttributeForTest(
			'TemplateStylesNamespaces', [ 3004, 3006 ]
		);

		$model = 'unchanged';
		$ret = TemplateStylesHooks::onContentHandlerDefaultModelFor(
			Title::makeTitle( $ns, $title ), $model
		);
		$this->assertSame( !$expect, $ret );
		$this->assertSame( $expect ? 'sanitized-css' : 'unchanged', $model );
	}

	public static function provideOnContentHandlerDefaultModelFor() {
		return [
			[ 10, 'Test/test.css', true ],
			[ 10, 'Test.css', false ],
			[ 10, 'Test/test.xss', false ],
			[ 10, 'Test/test.CSS', false ],
			[ 3000, 'Test/test.css', true ],
			[ 3002, 'Test/test.css', false ],
			[ 2, 'Test/test.css', false ],
			[ 3004, 'Test/test.css', true ],
			[ 3006, 'Test/test.css', false ],
		];
	}

	/**
	 * @dataProvider provideOnCodeEditorGetPageLanguage
	 */
	public function testOnCodeEditorGetPageLanguage( $useCodeEditor, $model, $expect ) {
		$this->setMwGlobals( [
			'wgTemplateStylesUseCodeEditor' => $useCodeEditor,
		] );

		$title = Title::makeTitle( NS_TEMPLATE, 'Test.css' );
		$lang = 'unchanged';
		$ret = TemplateStylesHooks::onCodeEditorGetPageLanguage(
			$title, $lang, $model, 'text/x-whatever'
		);
		$this->assertSame( !$expect, $ret );
		$this->assertSame( $expect ? 'css' : 'unchanged', $lang );
	}

	public static function provideOnCodeEditorGetPageLanguage() {
		return [
			[ true, 'wikitext', false ],
			[ true, 'css', false ],
			[ true, 'sanitized-css', true ],
			[ false, 'sanitized-css', false ],
		];
	}

	/**
	 * Unfortunately we can't just use a parserTests.txt file because our
	 * tag's output depends on the revision IDs of the input pages.
	 * @dataProvider provideTag
	 */
	public function testTag(
		ParserOptions $popt, $getTextOptions, $wikitext, $expect, $globals = []
	) {
		$this->setMwGlobals( $globals + [
			'wgScriptPath' => '',
			'wgScript' => '/index.php',
			'wgArticlePath' => '/wiki/$1',
		] );

		$oldCurrentRevisionRecordCallback = $popt->setCurrentRevisionRecordCallback(
			function ( Title $title, $parser = null ) use ( &$oldCurrentRevisionRecordCallback ) {
				if ( $title->getPrefixedText() === 'Template:Test replacement' ) {
					$user = RequestContext::getMain()->getUser();
					$revRecord = new MutableRevisionRecord( $title );
					$revRecord->setUser( $user );
					$revRecord->setContent(
						SlotRecord::MAIN,
						new TemplateStylesContent( '.baz { color:orange; bogus:bogus; }' )
					);
					$revRecord->setParentId( $title->getLatestRevID() );
					return $revRecord;
				}
				return call_user_func( $oldCurrentRevisionRecordCallback, $title, $parser );
			}
		);

		$services = MediaWikiServices::getInstance();
		$parser = $services->getParserFactory()->create();
		$parser->firstCallInit();
		if ( !isset( $parser->mTagHooks['templatestyles'] ) ) {
			throw new Exception( 'templatestyles tag hook is not in the parser' );
		}
		$out = $parser->parse( $wikitext, Title::newFromText( 'Test' ), $popt );
		$parser->mPreprocessor = null; # Break the Parser <-> Preprocessor cycle

		$expect = preg_replace_callback( '/\{\{REV:(.*?)\}\}/', function ( $m ) {
			return Title::newFromText( 'Template:TemplateStyles test/' . $m[1] )->getLatestRevID();
		}, $expect );
		$this->assertEquals( $expect, $out->getText( $getTextOptions ) );
	}

	public static function provideTag() {
		$popt = ParserOptions::newFromContext( RequestContext::getMain() );
		$popt->setWrapOutputClass( 'templatestyles-test' );

		$popt2 = ParserOptions::newFromContext( RequestContext::getMain() );

		$popt3 = ParserOptions::newFromContext( RequestContext::getMain() );
		Wikimedia\quietCall( [ $popt3, 'setWrapOutputClass' ], false );

		return [
			'Tag without src' => [
				$popt, [],
				'<templatestyles />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">TemplateStyles' <code>src</code> attribute must not be empty.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Tag with invalid src' => [
				$popt, [],
				'<templatestyles src="Test&lt;&gt;" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Invalid title for TemplateStyles' <code>src</code> attribute.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Tag with valid but nonexistent title' => [
				$popt, [],
				'<templatestyles src="ThisDoes\'\'\'Not\'\'\'Exist" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Page <a href=\"/index.php?title=Template:ThisDoes%27%27%27Not%27%27%27Exist&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"Template:ThisDoes&#39;&#39;&#39;Not&#39;&#39;&#39;Exist (page does not exist)\">Template:ThisDoes&#39;&#39;&#39;Not&#39;&#39;&#39;Exist</a> has no content.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Tag with valid but nonexistent title, main namespace' => [
				$popt, [],
				'<templatestyles src=":ThisDoes\'\'\'Not\'\'\'Exist" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Page <a href=\"/index.php?title=ThisDoes%27%27%27Not%27%27%27Exist&amp;action=edit&amp;redlink=1\" class=\"new\" title=\"ThisDoes&#39;&#39;&#39;Not&#39;&#39;&#39;Exist (page does not exist)\">ThisDoes&#39;&#39;&#39;Not&#39;&#39;&#39;Exist</a> has no content.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Tag with wikitext page' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/wikitext" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Page <a href=\"/wiki/Template:TemplateStyles_test/wikitext\" title=\"Template:TemplateStyles test/wikitext\">Template:TemplateStyles test/wikitext</a> must have content model \"Sanitized CSS\" for TemplateStyles (current model is \"wikitext\").</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Tag with CSS (not sanitized-css) page' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/nonsanitized.css" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Page <a href=\"/wiki/Template:TemplateStyles_test/nonsanitized.css\" title=\"Template:TemplateStyles test/nonsanitized.css\">Template:TemplateStyles test/nonsanitized.css</a> must have content model \"Sanitized CSS\" for TemplateStyles (current model is \"CSS\").</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Working tag' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/styles1.css" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><style data-mw-deduplicate=\"TemplateStyles:r{{REV:styles1.css}}/templatestyles-test\">.templatestyles-test .foo{color:blue}</style></div>",
				// @codingStandardsIgnoreEnd
			],
			'Disabled' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/styles1.css" />',
				"<div class=\"templatestyles-test\"></div>",
				[ 'wgTemplateStylesDisable' => true ],
			],
			'Replaced content (which includes sanitization errors)' => [
				$popt, [],
				'<templatestyles src="Test replacement" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><style data-mw-deduplicate=\"TemplateStyles:8fd14043c1cce91e8b9d1487a9d17d8d9ae43890/templatestyles-test\">/*\nErrors processing stylesheet [[:Template:Test replacement]] (rev ):\n• Unrecognized or unsupported property at line 1 character 22.\n*/\n.templatestyles-test .baz{color:orange}</style></div>",
				// @codingStandardsIgnoreEnd
			],
			'Hoistable selectors are hoisted' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/styles3.css" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><style data-mw-deduplicate=\"TemplateStyles:r{{REV:styles3.css}}/templatestyles-test\">html.no-js body.skin-minerva .templatestyles-test .bar{color:yellow}</style></div>",
				// @codingStandardsIgnoreEnd
			],
			'Still prefixed despite no wrapping class' => [
				$popt2, [ 'unwrap' => true ],
				'<templatestyles src="TemplateStyles test/styles1.css" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<style data-mw-deduplicate=\"TemplateStyles:r{{REV:styles1.css}}\">.mw-parser-output .foo{color:blue}</style>",
				// @codingStandardsIgnoreEnd
			],
			'Still prefixed despite deprecated no wrapping class' => [
				$popt3, [],
				'<templatestyles src="TemplateStyles test/styles1.css" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<style data-mw-deduplicate=\"TemplateStyles:r{{REV:styles1.css}}\">.mw-parser-output .foo{color:blue}</style>",
				// @codingStandardsIgnoreEnd
			],
			'Deduplicated tags' => [
				$popt, [],
				trim( '
<templatestyles src="TemplateStyles test/styles1.css" />
<templatestyles src="TemplateStyles test/styles1.css" />
<templatestyles src="TemplateStyles test/styles2.css" />
<templatestyles src="TemplateStyles test/styles1.css" />
<templatestyles src="TemplateStyles test/styles2.css" />
				' ),
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				trim( '
<div class="templatestyles-test"><style data-mw-deduplicate="TemplateStyles:r{{REV:styles1.css}}/templatestyles-test">.templatestyles-test .foo{color:blue}</style>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r{{REV:styles1.css}}/templatestyles-test"/>
<style data-mw-deduplicate="TemplateStyles:r{{REV:styles2.css}}/templatestyles-test">.templatestyles-test .bar{color:green}</style>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r{{REV:styles1.css}}/templatestyles-test"/>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r{{REV:styles2.css}}/templatestyles-test"/></div>
				' ),
				// @codingStandardsIgnoreEnd
			],
			'Wrapper parameter' => [
				$popt2, [],
				'<templatestyles src="TemplateStyles test/styles1.css" wrapper=".foobar" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"mw-parser-output\"><style data-mw-deduplicate=\"TemplateStyles:r{{REV:styles1.css}}/mw-parser-output/.foobar\">.mw-parser-output .foobar .foo{color:blue}</style></div>",
				// @codingStandardsIgnoreEnd
			],
			'Invalid wrapper parameter' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/styles1.css" wrapper=".foo .bar" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Invalid value for TemplateStyles' <code>wrapper</code> attribute.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Invalid wrapper parameter (2)' => [
				$popt, [],
				'<templatestyles src="TemplateStyles test/styles1.css" wrapper=".foo/*" />',
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				"<div class=\"templatestyles-test\"><p><strong class=\"error\">Invalid value for TemplateStyles' <code>wrapper</code> attribute.</strong>\n</p></div>",
				// @codingStandardsIgnoreEnd
			],
			'Wrapper parameter and proper deduplication' => [
				$popt2, [],
				trim( '
<templatestyles src="TemplateStyles test/styles1.css" />
<templatestyles src="TemplateStyles test/styles1.css" wrapper=" " />
<templatestyles src="TemplateStyles test/styles1.css" wrapper=".foobar" />
<templatestyles src="TemplateStyles test/styles1.css" wrapper=".foobaz" />
<templatestyles src="TemplateStyles test/styles1.css" wrapper=" .foobar " />
				' ),
				// @codingStandardsIgnoreStart Ignore Generic.Files.LineLength.TooLong
				trim( '
<div class="mw-parser-output"><style data-mw-deduplicate="TemplateStyles:r{{REV:styles1.css}}">.mw-parser-output .foo{color:blue}</style>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r{{REV:styles1.css}}"/>
<style data-mw-deduplicate="TemplateStyles:r{{REV:styles1.css}}/mw-parser-output/.foobar">.mw-parser-output .foobar .foo{color:blue}</style>
<style data-mw-deduplicate="TemplateStyles:r{{REV:styles1.css}}/mw-parser-output/.foobaz">.mw-parser-output .foobaz .foo{color:blue}</style>
<link rel="mw-deduplicated-inline-style" href="mw-data:TemplateStyles:r{{REV:styles1.css}}/mw-parser-output/.foobar"/></div>
				' ),
				// @codingStandardsIgnoreEnd
			],
		];
	}

}
