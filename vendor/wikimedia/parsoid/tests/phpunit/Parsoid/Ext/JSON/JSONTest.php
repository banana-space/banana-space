<?php
// phpcs:disable Generic.Files.LineLength.TooLong

namespace Test\Parsoid\Ext\JSON;

use DOMDocument;
use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Ext\JSON\JSON;
use Wikimedia\Parsoid\Ext\ParsoidExtensionAPI;
use Wikimedia\Parsoid\Mocks\MockEnv;

class JSONTest extends TestCase {
	/**
	 * Create a DOM document using JSON to create the document body and verify correctness
	 * @covers \Wikimedia\Parsoid\Ext\JSON\JSON::toDOM
	 */
	public function testToDOM() {
		// Test malformed JSON handler
		$opts = [];
		$env = new MockEnv( $opts );
		$API = new ParsoidExtensionAPI( $env );
		$json = new JSON();

		$pageContent = '{"1":2';    // malformed JSON example
		$expected = '<!DOCTYPE html>' . "\n" .
			'<html prefix="dc: http://purl.org/dc/terms/ mw: http://mediawiki.org/rdf/" about="https://my.wiki.example/wikix/Special:Redirect/revision/1"><head prefix="mwr: https://my.wiki.example/wikix/Special:Redirect/"><meta charset="utf-8"><meta property="mw:pageId" content="-1"><meta property="mw:pageNamespace" content="0"><meta property="mw:html:version" content="2.1.0"><link rel="dc:isVersionOf" href="//my.wiki.example/wikix/TestPage"><title>TestPage</title><base href="//my.wiki.example/wikix/"><link rel="stylesheet" href="//my.wiki.example/wx/load.php?modules=mediawiki.skinning.content.parsoid%7Cmediawiki.skinning.interface%7Csite.styles%7Cmediawiki.page.gallery.styles%7Cext.cite.style%7Cext.cite.styles&amp;only=styles&amp;skin=vector"><!--[if lt IE 9]><script src="//my.wiki.example/wx/load.php?modules=html5shiv&amp;only=scripts&amp;skin=vector&amp;sync=1"></script><script>html5.addElements(\'figure-inline\');</script><![endif]--><meta http-equiv="content-language" content="en"><meta http-equiv="vary" content="Accept"></head><body data-parsoid="{}" lang="en" class="mw-content-rtl sitedir-rtl rtl mw-body-content parsoid-body mediawiki mw-parser-output" dir="rtl"><table typeof="mw:Error" data-parsoid="{}" data-mw=\'{"errors":[{"key":"bad-json"}]}\'></table></body></html>' .
			"\n";

		$doc = $json->toDOM( $API, $pageContent );
		$response = $doc->saveHTML();
		$this->assertSame( $expected, $response );

		// Test complex nested JSON object using string matching
		$pageContent =
			'{"array":[{"foo":"bar","key":["string1",null,false,true,0,1,123,456.789]}]}';
		$expected = '<!DOCTYPE html>' . "\n" .
			'<html prefix="dc: http://purl.org/dc/terms/ mw: http://mediawiki.org/rdf/" about="https://my.wiki.example/wikix/Special:Redirect/revision/1"><head prefix="mwr: https://my.wiki.example/wikix/Special:Redirect/"><meta charset="utf-8"><meta property="mw:pageId" content="-1"><meta property="mw:pageNamespace" content="0"><meta property="mw:html:version" content="2.1.0"><link rel="dc:isVersionOf" href="//my.wiki.example/wikix/TestPage"><title>TestPage</title><base href="//my.wiki.example/wikix/"><link rel="stylesheet" href="//my.wiki.example/wx/load.php?modules=mediawiki.skinning.content.parsoid%7Cmediawiki.skinning.interface%7Csite.styles%7Cmediawiki.page.gallery.styles%7Cext.cite.style%7Cext.cite.styles&amp;only=styles&amp;skin=vector"><!--[if lt IE 9]><script src="//my.wiki.example/wx/load.php?modules=html5shiv&amp;only=scripts&amp;skin=vector&amp;sync=1"></script><script>html5.addElements(\'figure-inline\');</script><![endif]--><meta http-equiv="content-language" content="en"><meta http-equiv="vary" content="Accept"></head><body data-parsoid="{}" lang="en" class="mw-content-rtl sitedir-rtl rtl mw-body-content parsoid-body mediawiki mw-parser-output" dir="rtl"><table class="mw-json mw-json-object" data-parsoid="{}"><tbody data-parsoid="{}"><tr data-parsoid="{}"><th data-parsoid="{}">array</th><td data-parsoid="{}"><table class="mw-json mw-json-array" data-parsoid="{}"><tbody data-parsoid="{}"><tr data-parsoid="{}"><td data-parsoid="{}"><table class="mw-json mw-json-object" data-parsoid="{}"><tbody data-parsoid="{}"><tr data-parsoid="{}"><th data-parsoid="{}">foo</th><td class="value mw-json-string" data-parsoid="{}">bar</td></tr><tr data-parsoid="{}"><th data-parsoid="{}">key</th><td data-parsoid="{}"><table class="mw-json mw-json-array" data-parsoid="{}"><tbody data-parsoid="{}"><tr data-parsoid="{}"><td class="value mw-json-string" data-parsoid="{}">string1</td></tr><tr data-parsoid="{}"><td class="value mw-json-null" data-parsoid="{}">null</td></tr><tr data-parsoid="{}"><td class="value mw-json-boolean" data-parsoid="{}">false</td></tr><tr data-parsoid="{}"><td class="value mw-json-boolean" data-parsoid="{}">true</td></tr><tr data-parsoid="{}"><td class="value mw-json-number" data-parsoid="{}">0</td></tr><tr data-parsoid="{}"><td class="value mw-json-number" data-parsoid="{}">1</td></tr><tr data-parsoid="{}"><td class="value mw-json-number" data-parsoid="{}">123</td></tr><tr data-parsoid="{}"><td class="value mw-json-number" data-parsoid="{}">456.789</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></body></html>' .
			"\n";

		$doc = $json->toDOM( $API, $pageContent );
		$response = $doc->saveHTML();
		$this->assertSame( $expected, $response );
	}

	/**
	 * Create a DOM document using HTML and convert that to JSON and verify correctness
	 * @covers \Wikimedia\Parsoid\Ext\JSON\JSON::fromDOM
	 */
	public function testFromDOM() {
		$opts = [];
		$env = new MockEnv( $opts );
		$API = new ParsoidExtensionAPI( $env );
		$json = new JSON();

		$html = '<!DOCTYPE html>' . "\n" .
			'<html><head></head><body><table class="mw-json mw-json-object"><tbody><tr><th>' .
			'array</th><td><table class="mw-json mw-json-array"><tbody><tr><td>' .
			'<table class="mw-json mw-json-object"><tbody><tr><th>foo</th>' .
			'<td class="value mw-json-string">bar</td></tr><tr><th>key</th>' .
			'<td><table class="mw-json mw-json-array"><tbody><tr>' .
			'<td class="value mw-json-string">string1</td></tr><tr>' .
			'<td class="value mw-json-null">null</td></tr><tr>' .
			'<td class="value mw-json-boolean">false</td></tr><tr>' .
			'<td class="value mw-json-boolean">true</td></tr><tr>' .
			'<td class="value mw-json-number">0</td></tr><tr>' .
			'<td class="value mw-json-number">1</td></tr><tr>' .
			'<td class="value mw-json-number">123</td></tr><tr>' .
			'<td class="value mw-json-number">456.789</td></tr></tbody></table></td></tr>' .
			'</tbody></table></td></tr></tbody></table></td></tr></tbody></table></body></html>' .
			"\n";
		$expected = '{"array":[{"foo":"bar","key":["string1",null,false,true,0,1,123,456.789]}]}';

		$doc = new DOMDocument();
		$doc->loadHTML( $html );
		$response = $json->fromDOM( $API, $doc );
		$this->assertSame( $expected, $response );
	}
}
