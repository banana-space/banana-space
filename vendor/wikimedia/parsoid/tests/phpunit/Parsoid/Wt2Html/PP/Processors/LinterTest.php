<?php
declare( strict_types = 1 );

namespace Test\Parsoid\Wt2Html\PP\Processors;

use PHPUnit\Framework\TestCase;
use Wikimedia\Parsoid\Mocks\MockDataAccess;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\Parsoid;

/** Test cases for the linter */

class LinterTest extends TestCase {
	/**
	 * @param $wt
	 * @param array $options
	 * @return array
	 */
	private function parseWT( $wt, $options = [] ): array {
		$opts = [
			'prefix' => $options['prefix'] ?? 'enwiki',
			'pageName' => $options['pageName'] ?? 'main',
			'wrapSections' => false
		];

		$siteOptions = array_merge( [ 'linting' => true ], $options );
		$siteConfig = new MockSiteConfig( $siteOptions );
		$dataAccess = new MockDataAccess( [] );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$content = new MockPageContent( [ $opts['pageName'] => $wt ] );
		$pageConfig = new MockPageConfig( [], $content );

		return $parsoid->wikitext2lint( $pageConfig, [] );
	}

	/**
	 * @param $description
	 * @param $wt
	 * @param array $opts
	 */
	private function expectEmptyResults( $description, $wt, $opts = [] ): void {
		$result = $this->parseWT( $wt, $opts );
		$this->assertTrue( empty( $result ), $description );
	}

	/**
	 * @param $description
	 * @param $wt
	 * @param $cat
	 * @param array $opts
	 */
	private function expectLinterCategoryToBeAbsent( $description, $wt, $cat, $opts = [] ): void {
		$result = $this->parseWT( $wt, $opts );
		foreach ( $result as $r ) {
			if ( isset( $r['type'] ) ) {
				$this->assertNotEquals( $cat, $r['type'], $description );
			}
		}
	}

	/**
	 * @param $description
	 * @param $wt
	 * @param $type
	 */
	private function noLintsOfThisType( $description, $wt, $type ): void {
		$this->expectLinterCategoryToBeAbsent( $description, $wt, $type );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testNoIssues(): void {
		$desc = 'should not have lint any issues';
		$this->expectEmptyResults( $desc, 'foo' );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testMissingEndTags(): void {
		$desc = 'should lint missing end tags correctly';
		$result = $this->parseWT( '<div>foo' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 8, 5, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'div', $result[0]['params']['name'], $desc );

		$desc = 'should lint missing end tags for quotes correctly';
		$result = $this->parseWT( "'''foo" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 6, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$desc = 'should lint missing end tags found in transclusions correctly';
		$result = $this->parseWT( '{{1x|<div>foo<p>bar</div>}}' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 27, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'p', $result[0]['params']['name'], $desc );

		$desc = 'should not flag tags where end tags are optional in the spec';
		$wt = '<ul><li>x<li>y</ul><table><tr><th>heading 1<tr><td>col 1<td>col 2</table>';
		$this->expectEmptyResults( $desc, $wt );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testStrippedTags(): void {
		$desc = 'should lint stripped tags correctly';
		$result = $this->parseWT( 'foo</div>' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'stripped-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'div', $result[0]['params']['name'], $desc );
		$this->assertEquals( [ 3, 9, null, null ], $result[0]['dsr'], $desc );

		$desc = 'should lint stripped tags found in transclusions correctly';
		$result = $this->parseWT( '{{1x|<div>foo</div></div>}}' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'stripped-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'div', $result[0]['params']['name'], $desc );
		$this->assertEquals( [ 0, 27, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations (</i> is stripped)';
		$result = $this->parseWT( '<b><i>X</b></i>' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 3, 7, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'i', $result[0]['params']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations ' .
			'from template (</i> is stripped)';
		$result = $this->parseWT( '{{1x|<b><i>X</b></i>}}' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 22, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'i', $result[0]['params']['name'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations (<i> is auto-inserted)';
		$result = $this->parseWT( '<b><i>X</b>Y</i>' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 3, 7, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'i', $result[0]['params']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations ' .
			'(skip over empty autoinserted <small></small>)';
		$result = $this->parseWT( "*a<small>b\n*c</small>d" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 2, 10, 7, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'small', $result[0]['params']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations ' .
			'(formatting tags around lists, but ok for div)';
		$result = $this->parseWT( "<small>a\n*b\n*c\nd</small>\n<div>a\n*b\n*c\nd</div>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 8, 7, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'small', $result[0]['params']['name'], $desc );

		$desc = 'should lint stripped tags correctly in misnested tag situations ' .
			'(T221989 regression test case)';
		$result = $this->parseWT( "<div>\n* <span>foo\n\n</div>\n</span>y" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 8, 17, 6, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testObsoleteTags(): void {
		$desc = 'should lint obsolete tags correctly';
		$result = $this->parseWT( '<tt>foo</tt>bar' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'obsolete-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 12, 4, 5 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'tt', $result[0]['params']['name'], $desc );

		$desc = 'should not lint big as an obsolete tag';
		$this->expectEmptyResults( $desc, '<big>foo</big>bar' );

		$desc = 'should lint obsolete tags found in transclusions correctly';
		$result = $this->parseWT( '{{1x|<div><tt>foo</tt></div>}}foo' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'obsolete-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 30, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'tt', $result[0]['params']['name'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );

		$desc = 'should not lint auto-inserted obsolete tags';
		$result = $this->parseWT( "<tt>foo\n\n\nbar" );
		// obsolete-tag and missing-end-tag
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'obsolete-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 0, 7, 4, 0 ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'tt', $result[1]['params']['name'], $desc );

		$desc = 'should not have template info for extension tags';
		$result = $this->parseWT( "<gallery>\nFile:Test.jpg|<tt>foo</tt>\n</gallery>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'obsolete-tag', $result[0]['type'], $desc );
		$this->assertFalse( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( [ 24, 36, 4, 5 ], $result[0]['dsr'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testFosteredContent(): void {
		$desc = 'should lint fostered content correctly';
		$result = $this->parseWT( "{|\nfoo\n|-\n| bar\n|}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'fostered', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 18, 2, 2 ], $result[0]['dsr'], $desc );

		$desc = 'should not lint fostered categories';
		$this->expectEmptyResults( $desc, "{|\n[[Category:Fostered]]\n|-\n| bar\n|}" );

		$desc = 'should not lint fostered behavior switches';
		$this->expectEmptyResults( $desc, "{|\n__NOTOC__\n|-\n| bar\n|}" );

		$desc = 'should not lint fostered include directives without fostered content';
		$this->expectEmptyResults( $desc, "{|\n<includeonly>boo</includeonly>\n|-\n| bar\n|}" );

		$desc = 'should lint fostered include directives that has fostered content';
		$result = $this->parseWT( "{|\n<noinclude>boo</noinclude>\n|-\n| bar\n|}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'fostered', $result[0]['type'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testBogusImageOptions(): void {
		$desc = 'should lint Bogus image options correctly';
		$result = $this->parseWT( '[[file:a.jpg|foo|bar]]' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'bogus-image-options', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 22, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertTrue( isset( $result[0]['params']['items'] ), $desc );
		$this->assertEquals( 'foo', $result[0]['params']['items'][0], $desc );

		$desc = 'should lint Bogus image options found in transclusions correctly';
		$result = $this->parseWT( '{{1x|[[file:a.jpg|foo|bar]]}}' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'bogus-image-options', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 29, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'foo', $result[0]['params']['items'][0], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );

		$desc = 'should batch lint Bogus image options correctly';
		$result = $this->parseWT( '[[file:a.jpg|foo|bar|baz]]' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'bogus-image-options', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 26, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'foo', $result[0]['params']['items'][0], $desc );
		$this->assertEquals( 'bar', $result[0]['params']['items'][1], $desc );

		$desc = 'should not send any Bogus image options if there are none';
		$this->expectEmptyResults( $desc, '[[file:a.jpg|foo]]' );

		$desc = 'should flag noplayer, noicon, and disablecontrols as bogus options';
		$result = $this->parseWT(
		'[[File:Video.ogv|noplayer|noicon|disablecontrols=ok|These are bogus.]]' );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'bogus-image-options', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 70, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'noplayer', $result[0]['params']['items'][0], $desc );
		$this->assertEquals( 'noicon', $result[0]['params']['items'][1], $desc );
		$this->assertEquals( 'disablecontrols=ok', $result[0]['params']['items'][2], $desc );

		$desc = 'should not crash on gallery images';
		$this->expectEmptyResults( $desc, "<gallery>\nfile:a.jpg\n</gallery>" );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testSelfClosingTags(): void {
		$desc = 'should lint self-closing tags corrrectly';
		$result = $this->parseWT( "foo<b />bar<span />baz<hr />boo<br /> <ref name='boo' />" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'self-closed-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 3, 8, 5, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'self-closed-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 11, 19, 8, 0 ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'span', $result[1]['params']['name'], $desc );

		$desc = 'should lint self-closing tags in a template correctly';
		$result = $this->parseWT( "{{1x|<b /> <ref name='boo' />}}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'self-closed-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 31, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'][0], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testDeletableTableTag(): void {
		$desc = 'should identify deletable table tag for T161341 (1)';
		$wt = implode( "\n", [
			"{| style='border:1px solid red;'",
			"|a",
			"|-",
			"{| style='border:1px solid blue;'",
			"|b",
			"|c",
			"|}",
			"|}"
		] );
		$result = $this->parseWT( $wt );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'deletable-table-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( [ 39, 72, 0, 0 ], $result[0]['dsr'], $desc );

		$desc = 'should identify deletable table tag for T161341 (2)';
		$wt = implode( "\n", [
			"{| style='border:1px solid red;'",
			"|a",
			"|-  ",
			"   <!--boo-->   ",
			"{| style='border:1px solid blue;'",
			"|b",
			"|c",
			"|}"
		] );
		$result = $this->parseWT( $wt );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'deletable-table-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( [ 58, 91, 0, 0 ], $result[0]['dsr'], $desc );

		$desc = 'should identify deletable table tag for T161341 (3)';
		$wt = implode( "\n", [
			"{{1x|{{{!}}",
			"{{!}}a",
			"{{!}}-",
			"{{{!}}",
			"{{!}}b",
			"{{!}}c",
			"{{!}}}",
			"}}"
		] );
		$result = $this->parseWT( $wt );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'deletable-table-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );
		$this->assertEquals( [ 0, 56, null, null ], $result[0]['dsr'], $desc );

		$desc = 'should identify deletable table tag for T161341 (4)';
		$wt = implode( "\n", [
			"{{1x|{{{!}}",
			"{{!}}a",
			"{{!}}-",
			"}}",
			"{|",
			"|b",
			"|c",
			"|}"
		] );
		$result = $this->parseWT( $wt );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'deletable-table-tag', $result[0]['type'], $desc );
		$this->assertFalse( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( [ 29, 31, 0, 0 ], $result[0]['dsr'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testPwrapBugWorkaround(): void {
		$desc = 'should identify rendering workarounds needed for doBlockLevels bug';
		$wt = implode( "\n", [
			"<div><span style='white-space:nowrap'>",
			"a",
			"</span>",
			"</div>"
		] );
		$result = $this->parseWT( $wt );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'pwrap-bug-workaround', $result[1]['type'], $desc );
		$this->assertFalse( isset( $result[1]['templateInfo'] ), $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'div', $result[1]['params']['root'], $desc );
		$this->assertEquals( 'span', $result[1]['params']['child'], $desc );
		$this->assertEquals( [ 5, 48, 33, 0 ], $result[1]['dsr'], $desc );

		$desc = 'should not lint doBlockLevels bug rendering workarounds if newline break is present';
		$wt = implode( "\n", [
			"<div>",
			"<span style='white-space:nowrap'>",
			"a",
			"</span>",
			"</div>"
		] );
		$this->expectLinterCategoryToBeAbsent( $desc, $wt, 'pwrap-bug-workaround' );

		$desc = 'should not lint doBlockLevels bug rendering workarounds if nowrap CSS is not present';
		$wt = implode( "\n", [
			"<div><span>",
			"a",
			"</span>",
			"</div>"
		] );
		 $this->expectLinterCategoryToBeAbsent( $desc, $wt, 'pwrap-bug-workaround' );

		$desc = 'should not lint doBlockLevels bug rendering workarounds where not required';
		$wt = implode( "\n", [
			"<div><small style='white-space:nowrap'>",
			"a",
			"</small>",
			"</div>"
		] );
		$this->expectLinterCategoryToBeAbsent( $desc, $wt, 'pwrap-bug-workaround' );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testTidyWhitespaceBug(): void {
		$wt1 = implode( "", [
			// Basic with inline CSS + text sibling
			"<span style='white-space:nowrap'>a </span>",
			"x",
			// Basic with inline CSS + span sibling
			"<span style='white-space:nowrap'>a </span>",
			"<span>x</span>",
			// Basic with class CSS + text sibling
			"<span class='nowrap'>a </span>",
			"x",
			// Basic with class CSS + span sibling
			"<span class='nowrap'>a </span>",
			"<span>x</span>",
			// Comments shouldn't trip it up
			"<span style='white-space:nowrap'>a<!--boo--> <!--boo--></span>",
			"<!--boo-->",
			"<span>x</span>"
		] );

		$desc = 'should detect problematic whitespace hoisting';
		$result = $this->parseWT( $wt1, [ 'tidyWhitespaceBugMaxLength' => 0 ] );
		$this->assertEquals( 5, count( $result ), $desc );
		foreach ( $result as $r ) {
			$this->assertEquals( 'tidy-whitespace-bug', $r['type'], $desc );
			$this->assertEquals( 'span', $r['params']['node'], $desc );
		}
		$this->assertEquals( '#text', $result[0]['params']['sibling'], $desc );
		$this->assertEquals( 'span', $result[1]['params']['sibling'], $desc );
		$this->assertEquals( '#text', $result[2]['params']['sibling'], $desc );
		$this->assertEquals( 'span', $result[3]['params']['sibling'], $desc );
		$this->assertEquals( '#comment', $result[4]['params']['sibling'], $desc );
		// skipping dsr tests

		$desc = 'should not detect problematic whitespace hoisting for short text runs';
		// Nothing to trigger here
		$this->expectEmptyResults( $desc, $wt1, [ 'tidyWhitespaceBugMaxLength' => 100 ] );

		$wt2 = implode( "", [
			"some unaffected text here ",
			"<span style='white-space:nowrap'>a </span>",
			"<span style='white-space:nowrap'>bb</span>",
			"<span class='nowrap'>cc</span>",
			"<span class='nowrap'>d </span>",
			"<span style='white-space:nowrap'>e </span>",
			"<span class='nowrap'>x</span>"
		] );

		$desc = 'should flag tidy whitespace bug on a run of affected content';
		// The run length is 11 chars in the example above
		$result = $this->parseWT( $wt2, [ 'tidyWhitespaceBugMaxLength' => 5 ] );
		$this->assertEquals( 3, count( $result ), $desc );
		foreach ( $result as $r ) {
			$this->assertEquals( 'tidy-whitespace-bug', $r['type'], $desc );
			$this->assertEquals( 'span', $r['params']['node'], $desc );
		}
		$this->assertEquals( 'span', $result[0]['params']['sibling'], $desc );
		$this->assertEquals( [ 26, 68, 33, 7 ], $result[0]['dsr'], $desc );
		$this->assertEquals( 'span', $result[1]['params']['sibling'], $desc );
		$this->assertEquals( [ 140, 170, 21, 7 ], $result[1]['dsr'], $desc );
		$this->assertEquals( 'span', $result[2]['params']['sibling'], $desc );
		$this->assertEquals( [ 170, 212, 33, 7 ], $result[2]['dsr'], $desc );

		$desc = 'should not flag tidy whitespace bug on a run of short affected content';
		$this->expectEmptyResults( $desc, $wt2, [ 'tidyWhitespaceBugMaxLength' => 12 ] );

		$desc = 'should account for preceding text content';
		// Run length changes to 16 chars because of preceding text
		$wt2 = str_replace( 'some unaffected text here ', 'some unaffected text HERE-', $wt2 );
		$result = $this->parseWT( $wt2, [ 'tidyWhitespaceBugMaxLength' => 12 ] );
		$this->assertEquals( 3, count( $result ), $desc );
		foreach ( $result as $r ) {
			$this->assertEquals( 'tidy-whitespace-bug', $r['type'], $desc );
			$this->assertEquals( 'span', $r['params']['node'], $desc );
		}
		$this->assertEquals( 'span', $result[0]['params']['sibling'], $desc );
		$this->assertEquals( [ 26, 68, 33, 7 ], $result[0]['dsr'], $desc );
		$this->assertEquals( 'span', $result[1]['params']['sibling'], $desc );
		$this->assertEquals( [ 140, 170, 21, 7 ], $result[1]['dsr'], $desc );
		$this->assertEquals( 'span', $result[2]['params']['sibling'], $desc );
		$this->assertEquals( [ 170, 212, 33, 7 ], $result[2]['dsr'], $desc );

		$desc = 'should not flag tidy whitespace bug where it does not matter';
		$wt = implode( "", [
			// No CSS
			"<span>a </span>",
			"<span>x</span>",
			// No trailing white-space
			"<span class='nowrap'>a</span>",
			"x",
			// White-space follows
			"<span class='nowrap'>a </span>",
			" ",
			"<span>x</span>",
			// White-space follows
			"<span style='white-space:nowrap'>a </span>",
			"<!--boo--> boo",
			"<span>x</span>",
			// Block tag
			"<div class='nowrap'>a </div>",
			"<span>x</span>",
			// Block tag sibling
			"<span class='nowrap'>a </span>",
			"<div>x</div>",
			// br sibling
			"<span class='nowrap'>a </span>",
			"<br/>",
			// No next sibling
			"<span class='nowrap'>a </span>"
		] );
		$this->expectEmptyResults( $desc, $wt, [ 'tidyWhitespaceBugMaxLength' => 0 ] );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testMultipleColonEscape(): void {
		$desc = 'should lint links prefixed with multiple colons';
		$result = $this->parseWT( "[[None]]\n[[:One]]\n[[::Two]]\n[[:::Three]]" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( [ 18, 27, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( '::Two', $result[0]['params']['href'], $desc );
		$this->assertEquals( [ 28, 40, null, null ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( ':::Three', $result[1]['params']['href'], $desc );

		$desc = 'should lint links prefixed with multiple colons from templates';
		$result = $this->parseWT( "{{1x|[[:One]]}}\n{{1x|[[::Two]]}}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );
		// TODO(arlolra): Frame doesn't have tsr info yet
		$this->assertEquals( [ 0, 0, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( '::Two', $result[0]['params']['href'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testHtml5MisnestedTags(): void {
		$desc = "should not trigger html5 misnesting if there is no following content";
		$result = $this->parseWT( "<del>foo\nbar" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'del', $result[0]['params']['name'], $desc );

		$desc = "should trigger html5 misnesting correctly";
		$result = $this->parseWT( "<del>foo\n\nbar" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 8, 5, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'del', $result[0]['params']['name'], $desc );

		$desc = "should trigger html5 misnesting for span (1)";
		$result = $this->parseWT( "<span>foo\n\nbar" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 9, 6, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );

		$desc = "should trigger html5 misnesting for span (2)";
		$result = $this->parseWT( "<span>foo\n\n<div>bar</div>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 9, 6, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );

		$desc = "should trigger html5 misnesting for span (3)";
		$result = $this->parseWT( "<span>foo\n\n{|\n|x\n|}\nboo" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 9, 6, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );

		$desc = "should not trigger html5 misnesting when there is no misnested content";
		$result = $this->parseWT( "<span>foo\n\n</span>y" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misnested-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );

		$desc = "should not trigger html5 misnesting when unclosed tag is inside a td/th/heading tags";
		$result = $this->parseWT( "=<span id=\"1\">x=\n{|\n!<span id=\"2\">z\n|-\n|<span>id=\"3\"\n|}" );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
		$this->assertEquals( 'missing-end-tag', $result[2]['type'], $desc );

		$desc = "should not trigger html5 misnesting when misnested content is " .
			"outside an a-tag (without link-trails)";
		$result = $this->parseWT( "[[Foo|<span>foo]]Bar</span>" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'stripped-tag', $result[1]['type'], $desc );

		// Note that this is a false positive because of T177086 and fixing that will fix this.
		// We expect this to be an edge case.
		$desc = "should trigger html5 misnesting when linktrails brings content inside an a-tag";
		$result = $this->parseWT( "[[Foo|<span>foo]]bar</span>" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'stripped-tag', $result[1]['type'], $desc );

		$desc = "should not trigger html5 misnesting for formatting tags";
		$result = $this->parseWT( "<small>foo\n\nbar" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'small', $result[0]['params']['name'], $desc );

		$desc = "should not trigger html5 misnesting for span if there is a nested span tag";
		$result = $this->parseWT( "<span>foo<span>boo</span>\n\nbar</span>" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'stripped-tag', $result[1]['type'], $desc );

		$desc = "should trigger html5 misnesting for span if there is a nested non-span tag";
		$result = $this->parseWT( "<span>foo<del>boo</del>\n\nbar</span>" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'html5-misnesting', $result[0]['type'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'span', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'stripped-tag', $result[1]['type'], $desc );

		$desc = "should trigger html5 misnesting for span if there is a nested unclosed span tag";
		$result = $this->parseWT( "<span>foo<span>boo\n\nbar</span>" );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'html5-misnesting', $result[1]['type'], $desc );
		$this->assertEquals( 'stripped-tag', $result[2]['type'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testTidyFontBug(): void {
		$desc = "should flag Tidy font fixups accurately when color attribute is present";
		$wtLines = [
			"<font color='green'>[[Foo]]</font>",
			"<font color='green'>[[Category:Boo]][[Foo]]</font>",
			"<font color='green'>__NOTOC__[[Foo]]</font>",
			"<font color='green'><!--boo-->[[Foo]]</font>",
			"<font color='green'>[[Foo|bar]]</font>",
			"<font color='green'>[[Foo|''bar'']]</font>",
			"<font color='green'>[[Foo|''bar'' and boo]]</font>",
			"<font color='green'>[[Foo]]l</font>",
			"<font color='green'>{{1x|[[Foo]]}}</font>"
		];
		$n = count( $wtLines );
		$result = $this->parseWT( implode( "\n", $wtLines ) );
		$this->assertEquals( 2 * $n, count( $result ), $desc );
		for ( $i = 0; $i < 2 * $n; $i += 2 ) {
			$this->assertEquals( 'obsolete-tag', $result[$i]['type'], $desc );
			$this->assertEquals( 'tidy-font-bug', $result[$i + 1]['type'], $desc );
		}

		$desc = "should not flag Tidy font fixups when color attribute is absent";
		$wtLinesReplaced = str_replace( " color='green'", '', $wtLines );
		$n = count( $wtLinesReplaced );
		$result = $this->parseWT( implode( "\n", $wtLinesReplaced ) );
		$this->assertEquals( $n, count( $wtLinesReplaced ), $desc );
		foreach ( $result as $r ) {
			$this->assertEquals( 'obsolete-tag', $r['type'], $desc );
		}

		$desc = "should not flag Tidy font fixups when Tidy does not do the fixups";
		$wtLines2 = [
			"<font color='green'></font>", // Regression test for T179757
			"<font color='green'>[[Foo]][[Bar]]</font>",
			"<font color='green'> [[Foo]]</font>",
			"<font color='green'>[[Foo]] </font>",
			"<font color='green'>[[Foo]]D</font>",
			"<font color='green'>''[[Foo|bar]]''</font>",
			"<font color='green'><span>[[Foo|bar]]</span></font>",
			"<font color='green'><div>[[Foo|bar]]</div></font>"
		];
		$n = count( $wtLines2 );
		$result = $this->parseWT( implode( "\n", $wtLines2 ) );
		$this->assertEquals( $n, count( $result ), $desc );
		foreach ( $result as $r ) {
			$this->assertEquals( 'obsolete-tag', $r['type'], $desc );
		}
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testMultipleUnclosedFormatTags(): void {
		$desc = 'should detect multiple unclosed small tags';
		$result = $this->parseWT( '<div><small>x</div><div><small>y</div>' );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[2]['type'], $desc );
		$this->assertEquals( 'small', $result[2]['params']['name'], $desc );

		$desc = 'should detect multiple unclosed big tags';
		$result = $this->parseWT( '<div><big>x</div><div><big>y</div>' );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[2]['type'], $desc );
		$this->assertEquals( 'big', $result[2]['params']['name'], $desc );

		$desc = 'should detect multiple unclosed big tags';
		$result = $this->parseWT( '<div><small><big><small><big>y</div>' );
		$this->assertEquals( 5, count( $result ), $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[4]['type'], $desc );
		$this->assertEquals( 'small', $result[4]['params']['name'], $desc );

		$desc = 'should ignore unclosed small tags in tables';
		$this->noLintsOfThisType( $desc, "{|\n|<small>a\n|<small>b\n|}",
			'multiple-unclosed-formatting-tags' );

		$desc = 'should ignore unclosed small tags in tables but detect those outside it';
		$result = $this->parseWT( "<small>x\n{|\n|<small>a\n|<small>b\n|}\n<small>y" );
		$this->assertEquals( 5, count( $result ), $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[4]['type'], $desc );
		$this->assertEquals( 'small', $result[4]['params']['name'], $desc );

		$desc = 'should not flag undetected misnesting of formatting tags as " .
			"multiple unclosed formatting tags';
		$this->noLintsOfThisType( $desc, "<br><small>{{1x|<div>\n*item 1\n</div>}}</small>",
			'multiple-unclosed-formatting-tags' );

		$desc = "should detect Tidy's smart auto-fixup of paired unclosed formatting tags";
		$result = $this->parseWT( '<b>foo<b>\n<code>foo <span>x</span> bar<code>' );
		$this->assertEquals( 6, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[1]['type'], $desc );
		$this->assertEquals( 'b', $result[1]['params']['name'], $desc );
		$this->assertEquals( 'missing-end-tag', $result[3]['type'], $desc );
		$this->assertEquals( 'multiple-unclosed-formatting-tags', $result[4]['type'], $desc );
		$this->assertEquals( 'code', $result[4]['params']['name'], $desc );

		$desc = "should not flag Tidy's smart auto-fixup of paired unclosed " .
			"formatting tags where Tidy won't do it";
		$this->noLintsOfThisType( $desc, "<b>foo <b>\n<code>foo <span>x</span> <!--comment--><code>",
			'multiple-unclosed-formatting-tags' );

		$desc = "should not flag Tidy's smart auto-fixup of paired unclosed tags for non-formatting tags";
		$this->noLintsOfThisType( $desc, "<span>foo<span>\n<div>foo <span>x</span> bar<div>",
			'multiple-unclosed-formatting-tags' );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testUnclosedIBTagsInHeadings(): void {
		$desc = "should detect unclosed wikitext i tags in headings";
		$result = $this->parseWT( "==foo<span>''a</span>==\nx" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'unclosed-quotes-in-heading', $result[0]['type'], $desc );
		$this->assertEquals( 'i', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'h2', $result[0]['params']['ancestorName'], $desc );

		$desc = "should detect unclosed wikitext b tags in headings";
		$result = $this->parseWT( "==foo<span>'''a</span>==\nx" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'unclosed-quotes-in-heading', $result[0]['type'], $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'h2', $result[0]['params']['ancestorName'], $desc );

		$desc = "should not detect unclosed HTML i/b tags in headings";
		$result = $this->parseWT( "==foo<span><i>a</span>==\nx\n==foo<span><b>a</span>==\ny" );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testMultilineHtmlTablesInLists(): void {
		$desc = "should detect multiline HTML tables in lists (li)";
		$result = $this->parseWT( "* <table><tr><td>x</td></tr>\n</table>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'multiline-html-table-in-list', $result[0]['type'], $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'li', $result[0]['params']['ancestorName'], $desc );

		$desc = "should detect multiline HTML tables in lists (table in div)";
		$result = $this->parseWT( "* <div><table><tr><td>x</td></tr>\n</table></div>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'multiline-html-table-in-list', $result[0]['type'], $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'li', $result[0]['params']['ancestorName'], $desc );

		$desc = "should detect multiline HTML tables in lists (dt)";
		$result = $this->parseWT( "; <table><tr><td>x</td></tr>\n</table>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'multiline-html-table-in-list', $result[0]['type'], $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'dt', $result[0]['params']['ancestorName'], $desc );

		$desc = "should detect multiline HTML tables in lists (dd)";
		$result = $this->parseWT( ": <table><tr><td>x</td></tr>\n</table>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'multiline-html-table-in-list', $result[0]['type'], $desc );
		$this->assertEquals( 'table', $result[0]['params']['name'], $desc );
		$this->assertEquals( 'dd', $result[0]['params']['ancestorName'], $desc );

		$desc = "should not detect multiline HTML tables in HTML lists";
		$this->expectEmptyResults( $desc, "<ul><li><table>\n<tr><td>x</td></tr>\n</table>\n</li></ul>" );

		$desc = "should not detect single-line HTML tables in lists";
		$this->expectEmptyResults( $desc, "* <div><table><tr><td>x</td></tr></table></div>" );

		$desc = "should not detect multiline HTML tables in ref tags";
		$this->expectEmptyResults( $desc,
			"a <ref><table>\n<tr><td>b</td></tr>\n</table></ref> <references />" );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testLintIssueInRefTags(): void {
		$desc = "should attribute linter issues to the ref tag";
		$result = $this->parseWT( "a <ref><b>x</ref> <references/>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$desc = "should attribute linter issues to the ref tag even if references is templated";
		$result = $this->parseWT( "a <ref><b>x</ref> {{1x|<references/>}}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$desc = "should attribute linter issues to the ref tag even when " .
			"ref and references are both templated";
		$wt = "a <ref><b>x</ref> b <ref>{{1x|<b>x}}</ref> " .
			"{{1x|c <ref><b>y</ref>}} {{1x|<references/>}}";
		$result = $this->parseWT( $wt );
		$this->assertEquals( 3, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 'b', $result[0]['params']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 25, 36, null, null ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'b', $result[1]['params']['name'], $desc );
		$this->assertTrue( isset( $result[1]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[1]['templateInfo']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[2]['type'], $desc );
		$this->assertEquals( [ 43, 67, null, null ], $result[2]['dsr'], $desc );
		$this->assertTrue( isset( $result[2]['params'] ), $desc );
		$this->assertEquals( 'b', $result[2]['params']['name'], $desc );
		$this->assertTrue( isset( $result[2]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[2]['templateInfo']['name'], $desc );

		$desc = "should attribute linter issues properly when ref " .
			"tags are in non-templated references tag";
		$wt = "a <ref><s>x</ref> b <ref name='x' /> <references> " .
			"<ref name='x'>{{1x|<b>boo}}</ref> </references>";
		$result = $this->parseWT( $wt );
		$this->assertEquals( 2, count( $result ), $desc );
		$this->assertEquals( 'missing-end-tag', $result[0]['type'], $desc );
		$this->assertEquals( [ 7, 11, 3, 0 ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['params'] ), $desc );
		$this->assertEquals( 's', $result[0]['params']['name'], $desc );

		$this->assertEquals( 'missing-end-tag', $result[1]['type'], $desc );
		$this->assertEquals( [ 64, 77, null, null ], $result[1]['dsr'], $desc );
		$this->assertTrue( isset( $result[1]['params'] ), $desc );
		$this->assertEquals( 'b', $result[1]['params']['name'], $desc );
		$this->assertTrue( isset( $result[1]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[1]['templateInfo']['name'], $desc );

// PORT-FIXME this code is not yet supported
//		$desc = "should not get into a cycle trying to lint ref in ref";
//		return parseWT(
//          "{{#tag:ref|<ref name='y' />|name='x'}}{{#tag:ref|" .
//              "<ref name='x' />|name='y'}}<ref name='x' />" );
//			.then(function() {
//					return parseWT("{{#tag:ref|<ref name='x' />|name=x}}");
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testDivSpanFlipTidyBug(): void {
		$desc = "should not trigger this lint when there are no style or class attributes";
		$this->expectEmptyResults( $desc, "<span><div>x</div></span>" );

		$desc = "should trigger this lint when there is a style or class attribute (1)";
		$result = $this->parseWT( "<span class='x'><div>x</div></span>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misc-tidy-replacement-issues', $result[0]['type'], $desc );
		$this->assertEquals( 'div-span-flip', $result[0]['params']['subtype'], $desc );

		$desc = "should trigger this lint when there is a style or class attribute (2)";
		$result = $this->parseWT( "<span style='x'><div>x</div></span>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misc-tidy-replacement-issues', $result[0]['type'], $desc );
		$this->assertEquals( 'div-span-flip', $result[0]['params']['subtype'], $desc );

		$desc = "should trigger this lint when there is a style or class attribute (3)";
		$result = $this->parseWT( "<span><div class='x'>x</div></span>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misc-tidy-replacement-issues', $result[0]['type'], $desc );
		$this->assertEquals( 'div-span-flip', $result[0]['params']['subtype'], $desc );

		$desc = "should trigger this lint when there is a style or class attribute (4)";
		$result = $this->parseWT( "<span><div style='x'>x</div></span>" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'misc-tidy-replacement-issues', $result[0]['type'], $desc );
		$this->assertEquals( 'div-span-flip', $result[0]['params']['subtype'], $desc );
	}

	/**
	 * @covers \Wikimedia\Parsoid\Wt2Html\ParserPipeline
	 */
	public function testWikilinkInExternalLink(): void {
		$desc = "should lint wikilink in external link correctly";
		$result = $this->parseWT( "[http://google.com This is [[Google]]'s search page]" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'wikilink-in-extlink', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 52, 19, 1 ], $result[0]['dsr'], $desc );

		$desc = "should lint wikilink in external link correctly";
		$result = $this->parseWT(
			"[http://stackexchange.com is the official website for [[Stack Exchange]]]" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'wikilink-in-extlink', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 73, 26, 1 ], $result[0]['dsr'], $desc );

		$desc = "should lint wikilink in external link correctly";
		$result = $this->parseWT(
		"{{1x|foo <div> and [http://google.com [[Google]] bar] baz </div>}}" );
		$this->assertSame( 1, count( $result ), $desc );
		$this->assertEquals( 'wikilink-in-extlink', $result[0]['type'], $desc );
		$this->assertEquals( [ 0, 66, null, null ], $result[0]['dsr'], $desc );
		$this->assertTrue( isset( $result[0]['templateInfo'] ), $desc );
		$this->assertEquals( '1x', $result[0]['templateInfo']['name'], $desc );
	}

}
