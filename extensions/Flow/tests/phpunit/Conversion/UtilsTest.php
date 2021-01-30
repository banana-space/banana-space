<?php

// phpcs:disable Generic.Files.LineLength -- Long html test examples

namespace Flow\Tests\Conversion;

use Flow\Conversion\Utils;
use Flow\Exception\WikitextException;
use Flow\Tests\FlowTestCase;
use Title;

/**
 * @covers \Flow\Conversion\Utils
 *
 * @group Flow
 */
class ConversionUtilsTest extends FlowTestCase {

	public static function createDomProvider() {
		return [
			[
				'A document with multiple matching ids is valid parser output',
				'<body><a id="foo">foo</a><a id="foo">bar</a></body>'
			],
			[
				'HTML5 tags, such as figcaption, are valid html',
				'<body><figcaption /></body>'
			],
		];
	}

	/**
	 * @dataProvider createDomProvider
	 */
	public function testCreateDomErrorModes( $message, $content ) {
		$this->assertInstanceOf( \DOMDocument::class, Utils::createDOM( $content ), $message );
	}

	public static function createRelativeTitleProvider() {
		return [
			[
				'strips leading ./ and treats as non-relative',
				// expect
				Title::newFromText( 'File:Foo.jpg' ),
				// input text
				'./File:Foo.jpg',
				// relative to title
				Title::newMainPage()
			],

			[
				'two level upwards traversal',
				// expect
				Title::newFromText( 'File:Bar.jpg' ),
				// input text
				'../../File:Bar.jpg',
				// relative to title
				Title::newFromText( 'Main_Page/And/Subpage' ),
			],
		];
	}

	/**
	 * @dataProvider createRelativeTitleProvider
	 */
	public function testResolveSubpageTraversal( $message, $expect, $text, Title $title ) {
		$result = Utils::createRelativeTitle( $text, $title );

		if ( $expect === null ) {
			$this->assertNull( $expect, $message );
		} elseif ( $expect instanceof Title ) {
			$this->assertInstanceOf( Title::class, $result, $message );
			$this->assertEquals( $expect->getPrefixedText(), $result->getPrefixedText(), $message );
		} else {
			$this->assertEquals( $expect, $result, $message );
		}
	}

	public static function wikitextRoundtripProvider() {
		return [
			[
				'italic text',
				// text & expect
				"''italic text''",
				// title
				Title::newMainPage(),
			],
			[
				'bold text',
				// text & expect
				"'''bold text'''",
				// title
				Title::newMainPage(),
			],
		];
	}

	/**
	 * Test full roundtrip (wikitext -> html -> wikitext)
	 *
	 * It doesn't make sense to test only a specific path, since Parsoid's HTML
	 * may change beyond our control & it doesn't really matter to us what
	 * exactly the HTML looks like, as long as Parsoid is able to understand it.
	 *
	 * @dataProvider wikitextRoundtripProvider
	 */
	public function testwikitextRoundtrip( $message, $expect, Title $title ) {
		// Check for Parsoid
		try {
			$html = Utils::convert( 'wikitext', 'html', $expect, $title );
			$wikitext = Utils::convert( 'html', 'wikitext', $html, $title );
			$this->assertEquals( $expect, trim( $wikitext ), $message );
		} catch ( WikitextException $excep ) {
			$this->markTestSkipped( 'Parsoid not enabled' );
		}
	}

	/**
	 * Test topic-title-plaintext
	 *
	 * @dataProvider topicTitleProvider
	 */
	public function testTopicTitle( $message, $wikitext, $expectedHtml, $expectedPlaintext ) {
		$this->setMwGlobals( 'wgScript', '/w/index.php' );

		$html = Utils::convert( 'topic-title-wikitext', 'topic-title-html', $wikitext, Title::newMainPage() );
		$this->assertEquals( $expectedHtml, $html, "$message: html" );

		$plaintext = Utils::convert( 'topic-title-wikitext', 'topic-title-plaintext', $wikitext, Title::newMainPage() );
		$this->assertEquals( $expectedPlaintext, $plaintext, "$message: plaintext" );
	}

	public static function topicTitleProvider() {
		return [
			[
				'External links not processed',
				'[http://example.com Example]',
				'[http://example.com Example]',
				'[http://example.com Example]',
			],
			[
				'Bold and italics not processed',
				"'''Bold''' and ''italics''",
				"&#039;&#039;&#039;Bold&#039;&#039;&#039; and &#039;&#039;italics&#039;&#039;",
				"'''Bold''' and ''italics''",
			],
			[
				'Script tags are treated as text',
				'<script>alert(\'Test\');</script>',
				'&lt;script&gt;alert(&#039;Test&#039;);&lt;/script&gt;',
				'<script>alert(\'Test\');</script>',
			],
			[
				'Entities processed',
				'&amp;&#x27;',
				'&amp;&#039;',
				'&\'',
			],
			[
				'Internal links are converted to plaintext',
				'[[asdfasdferqwer389]] is a place',
				'<a href="/w/index.php?title=Asdfasdferqwer389&amp;action=edit&amp;redlink=1" class="new" title="Asdfasdferqwer389 (page does not exist)">asdfasdferqwer389</a> is a place',
				'asdfasdferqwer389 is a place',
			],
			[
				'Quotes are preserved',
				'\'Single quotes\' "Double quotes"',
				'&#039;Single quotes&#039; &quot;Double quotes&quot;',
				'\'Single quotes\' "Double quotes"',
			],
		];
	}

	/**
	 * @dataProvider provideEncodeHeadInfo
	 */
	public function testEncodeHeadInfo( $message, $input, $expectedOutput ) {
		$this->assertEquals( $expectedOutput, Utils::encodeHeadInfo( $input ), $message );
	}

	public static function provideEncodeHeadInfo() {
		$parsoidVersion = Utils::PARSOID_VERSION;
		return [
			[
				'Head with base tag',
				'<html><head><base href="foo"></head><body><p>Hello</p></body></html>',
				'<body parsoid-version="' . $parsoidVersion . '" base-url="foo"><p>Hello</p></body>'
			],
			[
				'Head with base tag with no href',
				'<html><head><base></head><body><p>Hello</p></body></html>',
				'<body parsoid-version="' . $parsoidVersion . '"><p>Hello</p></body>'
			],
			[
				'Head with base tag with no href',
				'<html><head><base></head><body><p>Hello</p></body></html>',
				'<body parsoid-version="' . $parsoidVersion . '"><p>Hello</p></body>'
			],
			[
				'Parsoid example',
				'<!DOCTYPE html><html prefix="dc: http://purl.org/dc/terms/ mw: https://mediawiki.org/rdf/"><head prefix="mwr: http://en.wikipedia.org/wiki/Special:Redirect/"><meta charset="utf-8"/><meta property="mw:pageNamespace" content="0"/><meta property="isMainPage" content="true"/><meta property="mw:html:version" content="2.1.0"/><link rel="dc:isVersionOf" href="//en.wikipedia.org/wiki/Main_Page"/><title></title><base href="//en.wikipedia.org/wiki/"/><link rel="stylesheet" href="//en.wikipedia.org/w/load.php?modules=mediawiki.legacy.commonPrint%2Cshared%7Cmediawiki.skinning.content.parsoid%7Cmediawiki.skinning.interface%7Cskins.vector.styles%7Csite.styles%7Cext.cite.style%7Cext.cite.styles%7Cmediawiki.page.gallery.styles&amp;only=styles&amp;skin=vector"/><!--[if lt IE 9]><script src="//en.wikipedia.org/w/load.php?modules=html5shiv&amp;only=scripts&amp;skin=vector&amp;sync=1"></script><script>html5.addElements(\'figure-inline\');</script><![endif]--><meta http-equiv="content-language" content="en"/><meta http-equiv="vary" content="Accept"/></head><body id="mwAA" lang="en" class="mw-content-ltr sitedir-ltr ltr mw-body-content parsoid-body mediawiki mw-parser-output" dir="ltr"><section data-mw-section-id="0" id="mwAQ"><p id="mwAg">Hello <a rel="mw:WikiLink" href="./World" title="World" id="mwAw">world</a></p></section></body></html>',
				'<body id="mwAA" lang="en" dir="ltr" parsoid-version="' . $parsoidVersion . '" base-url="//en.wikipedia.org/wiki/"><section data-mw-section-id="0" id="mwAQ"><p id="mwAg">Hello <a rel="mw:WikiLink" href="./World" title="World" id="mwAw">world</a></p></section></body>'
			],
		];
	}

	/**
	 * @dataProvider provideDecodeHeadInfo
	 */
	public function testDecodeHeadInfo( $message, $input, $expectedOutput ) {
		$this->assertEquals( $expectedOutput, Utils::decodeHeadInfo( $input ), $message );
	}

	public static function provideDecodeHeadInfo() {
		return [
			[
				'Body tag with base-url',
				'<body base-url="//en.wikipedia.org/wiki/" parsoid-version="0.1.2"><p>Hello</p></body>',
				'<html><head><base href="//en.wikipedia.org/wiki/"/></head><body base-url="//en.wikipedia.org/wiki/" parsoid-version="0.1.2"><p>Hello</p></body></html>'
			],
			[
				'Body tag without base-url',
				'<body><p>Hello</p></body>',
				'<html><head></head><body><p>Hello</p></body></html>'
			],
			[
				'Unwrapped body tag',
				'<p>Hello</p>',
				'<html><head></head><body><p>Hello</p></body></html>'
			],
			[
				'Plain text',
				'Hello',
				'<html><head></head><body>Hello</body></html>'
			],
		];
	}
}
