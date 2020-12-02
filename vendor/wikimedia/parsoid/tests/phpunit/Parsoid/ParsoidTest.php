<?php

namespace Test\Parsoid;

use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Mocks\MockDataAccess;
use Wikimedia\Parsoid\Mocks\MockPageConfig;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * Test the entrypoint to the library.
 *
 * @coversDefaultClass \Wikimedia\Parsoid\Parsoid
 */
class ParsoidTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @covers ::wikitext2html
	 * @dataProvider provideWt2Html
	 */
	public function testWt2Html( $wt, $expected, $parserOpts = [] ) {
		$opts = [];

		$siteConfig = new MockSiteConfig( $opts );
		$dataAccess = new MockDataAccess( $opts );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageContent = new MockPageContent( [ 'main' => $wt ] );
		$pageConfig = new MockPageConfig( $opts, $pageContent );
		$out = $parsoid->wikitext2html( $pageConfig, $parserOpts );
		if ( !empty( $parserOpts['pageBundle'] ) ) {
			$this->assertTrue( $out instanceof PageBundle );
			$this->assertEquals( $expected, $out->html );
		} else {
			$this->assertEquals( $expected, $out );
		}
	}

	public function provideWt2Html() {
		return [
			[
				"'''hi ho'''",
				"<p data-parsoid='{\"dsr\":[0,11,0,0]}'><b data-parsoid='{\"dsr\":[0,11,3,3]}'>hi ho</b></p>",
				[
					'body_only' => true,
					'wrapSections' => false,
				]
			],
			[
				"'''hi ho'''",
				"<p id=\"mwAQ\"><b id=\"mwAg\">hi ho</b></p>",
				[
					'body_only' => true,
					'wrapSections' => false,
					'pageBundle' => true,
				]
			],
		];
	}

	/**
	 * @covers ::wikitext2lint
	 * @dataProvider provideWt2Lint
	 */
	public function testWt2Lint( $wt, $expected, $parserOpts = [] ) {
		$opts = [
			'linting' => true,
		];

		$siteConfig = new MockSiteConfig( $opts );
		$dataAccess = new MockDataAccess( $opts );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageContent = new MockPageContent( [ 'main' => $wt ] );
		$pageConfig = new MockPageConfig( $opts, $pageContent );
		$lint = $parsoid->wikitext2lint( $pageConfig, $parserOpts );
		$this->assertEquals( $expected, $lint );
	}

	public function provideWt2Lint() {
		return [
			[
				"[http://google.com This is [[Google]]'s search page]",
				[
					[
						'type' => 'wikilink-in-extlink',
						'dsr' => [ 0, 52, 19, 1 ],
						'params' => [],
					]
				]
			]
		];
	}

	/**
	 * @covers ::html2wikitext
	 * @dataProvider provideHtml2Wt
	 */
	public function testHtml2Wt( $input, $expected, $parserOpts = [] ) {
		$opts = [];

		$siteConfig = new MockSiteConfig( $opts );
		$dataAccess = new MockDataAccess( $opts );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageContent = new MockPageContent( [ 'main' => '' ] );
		$pageConfig = new MockPageConfig( $opts, $pageContent );
		$wt = $parsoid->html2wikitext( $pageConfig, $input, $parserOpts );
		$this->assertEquals( $expected, $wt );
	}

	public function provideHtml2Wt() {
		return [
			[
				"<pre>hi</pre>\n<div>ho</div>",
				" hi\n<div>ho</div>"
			],
			[
				"<h2></h2>",
				"==<nowiki/>==\n",
				[
					'scrubWikitext' => false,
				]
			],
			[
				"<h2></h2>",
				'',
				[
					'scrubWikitext' => true,
				]
			]
		];
	}

	/**
	 * @covers ::html2html
	 * @dataProvider provideHtml2Html
	 */
	public function testHtml2Html( $update, $input, $expected, $testOpts = [] ) {
		$opts = [];

		$siteConfig = new MockSiteConfig( $opts );
		$dataAccess = new MockDataAccess( $opts );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageContent = new MockPageContent( [ 'main' => '' ] );
		$pageConfig = new MockPageConfig( [
			'pageLanguage' => $testOpts['pageLanguage'] ?? 'en'
		], $pageContent );
		$wt = $parsoid->html2html( $pageConfig, $update, $input, $testOpts );
		$this->assertEquals( $expected, $wt );
	}

	public function provideHtml2Html() {
		// phpcs:disable Generic.Files.LineLength.TooLong
		return [
			[
				'redlinks',
				'<p><a rel="mw:WikiLink" href="./Special:Version" title="Special:Version">Special:Version</a> <a rel="mw:WikiLink" href="./Doesnotexist" title="Doesnotexist">Doesnotexist</a> <a rel="mw:WikiLink" href="./Redirected" title="Redirected">Redirected</a></p>',
				'<p><a rel="mw:WikiLink" href="./Special:Version" title="Special:Version">Special:Version</a> <a rel="mw:WikiLink" href="./Doesnotexist" title="Doesnotexist" class="new">Doesnotexist</a> <a rel="mw:WikiLink" href="./Redirected" title="Redirected" class="mw-redirect">Redirected</a></p>',
				[
					'body_only' => true,
				]
			],
			[
				'variant',
				// bogus <meta> w/ a data-parsoid attribute, just to verify
				// that data-parsoid attributes are preserved and not erased.
				'<meta data-parsoid=\'{"test":1}\'><p>абвг abcd x</p>',
				'<meta data-parsoid=\'{"test":1}\'/><p data-mw-variant-lang="sr-ec">abvg <span typeof="mw:LanguageVariant" data-mw-variant=\'{"twoway":[{"l":"sr-ec","t":"abcd"},{"l":"sr-el","t":"abcd"}],"rt":true}\'>abcd</span> x</p>',
				[
					'body_only' => true,
					'pageLanguage' => 'sr',
					'variant' => [
						'source' => null,
						'target' => 'sr-el',
					]
				]
			],
			[
				'variant',
				'<p>абвг abcd x</p>',
				'<p data-mw-variant-lang="sr-ec">abvg <span typeof="mw:LanguageVariant" data-mw-variant=\'{"twoway":[{"l":"sr-ec","t":"abcd"},{"l":"sr-el","t":"abcd"}],"rt":true}\'>abcd</span> x</p>',
				[
					'body_only' => true,
					'pageLanguage' => 'sr',
					'variant' => [
						'source' => 'sr-ec',
						'target' => 'sr-el',
					]
				]
			],
			[
				'variant',
				'<p>абвг abcd x</p>',
				'<p data-mw-variant-lang="sr-el"><span typeof="mw:LanguageVariant" data-mw-variant=\'{"twoway":[{"l":"sr-el","t":"абвг"},{"l":"sr-ec","t":"абвг"}],"rt":true}\'>абвг</span> абцд x</p>',
				[
					'body_only' => true,
					'pageLanguage' => 'sr',
					'variant' => [
						'source' => 'sr-el',
						'target' => 'sr-ec',
					]
				]
			]
		];
		// phpcs:enable Generic.Files.LineLength.TooLong
	}

	/**
	 * @covers ::pb2pb
	 * @dataProvider providePb2Pb
	 */
	public function testPb2Pb( $update, $input, $expected, $testOpts = [] ) {
		$opts = [];

		$siteConfig = new MockSiteConfig( $opts );
		$dataAccess = new MockDataAccess( $opts );
		$parsoid = new Parsoid( $siteConfig, $dataAccess );

		$pageContent = new MockPageContent( [ 'main' => '' ] );
		$pageConfig = new MockPageConfig( [
			'pageLanguage' => $testOpts['pageLanguage'] ?? 'en'
		], $pageContent );
		$pb = new PageBundle(
			$input['html'],
			PHPUtils::jsonDecode( $input['parsoid'] ?? 'null' ),
			PHPUtils::jsonDecode( $input['mw'] ?? 'null' ),
			$input['version'] ?? null,
			$input['headers'] ?? null,
			$input['contentmodel'] ?? null
		);
		$out = $parsoid->pb2pb( $pageConfig, $update, $pb, $testOpts );
		$this->assertTrue( $out instanceof PageBundle );
		$this->assertEquals( $expected['html'], $out->html );
		$this->assertEquals( $expected['parsoid'] ?? 'null', PHPUtils::jsonEncode( $out->parsoid ) );
		$this->assertEquals( $expected['mw'] ?? 'null', PHPUtils::jsonEncode( $out->mw ) );
		$this->assertEquals( $expected['version'] ?? null, $out->version );
		if ( isset( $expected['headers'] ) ) {
			$this->assertEquals( $expected['headers'] ?? null, $out->headers );
		}
	}

	public function providePb2Pb() {
		// phpcs:disable Generic.Files.LineLength.TooLong
		return [
			[
				'variant',
				[
					'html' => '<body id="mwAA" lang="en" class="mw-content-ltr sitedir-ltr ltr mw-body-content parsoid-body mediawiki mw-parser-output" dir="ltr"><p id="mwAQ"><b id="mwAg">abcd</b></p></body>',
					'parsoid' => '{"counter":2,"ids":{"mwAA":{"dsr":[0,11,0,0]},"mwAQ":{"dsr":[0,10,0,0]},"mwAg":{"dsr":[0,10,3,3]}},"offsetType":"byte"}',
					'mw' => '{"ids":[]}',
				],
				[
					'html' => '<p id="mwAQ" data-mw-variant-lang="sr-el"><b id="mwAg">абцд</b></p>',
					'parsoid' => '{"counter":-1,"ids":{"mwAA":{"dsr":[0,11,0,0]},"mwAQ":{"dsr":[0,10,0,0]},"mwAg":{"dsr":[0,10,3,3]}},"offsetType":"byte"}',
					'mw' => '{"ids":[]}',
					'version' => '2.1.0',
				],
				[
					'body_only' => true,
					'pageLanguage' => 'sr',
					'variant' => [
						'source' => 'sr-el',
						'target' => 'sr-ec',
					]
				]
			],
			// Note that id attributes are preserved, even if no data-parsoid
			// is provided.
			[
				'variant',
				[
					'html' => '<body id="mwAA" lang="en" class="mw-content-ltr sitedir-ltr ltr mw-body-content parsoid-body mediawiki mw-parser-output" dir="ltr"><p id="mwAQ"><b id="mwAg">abcd</b></p></body>',
					'parsoid' => null,
					'mw' => null,
				],
				[
					'html' => '<p id="mwAQ" data-mw-variant-lang="sr-el"><b id="mwAg">абцд</b></p>',
					'parsoid' => '{"counter":-1,"ids":[],"offsetType":"byte"}',
					'mw' => '{"ids":[]}',
					'version' => '2.1.0',
				],
				[
					'body_only' => true,
					'pageLanguage' => 'sr',
					'variant' => [
						'source' => 'sr-el',
						'target' => 'sr-ec',
					]
				]
			],
		];
		// phpcs:enable Generic.Files.LineLength.TooLong
	}
}
