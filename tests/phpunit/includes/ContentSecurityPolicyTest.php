<?php

use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;

class ContentSecurityPolicyTest extends MediaWikiIntegrationTestCase {
	/** @var ContentSecurityPolicy */
	private $csp;

	protected function setUp() : void {
		global $wgUploadDirectory;
		$this->setMwGlobals( [
			'wgAllowExternalImages' => false,
			'wgAllowExternalImagesFrom' => [],
			'wgAllowImageTag' => false,
			'wgEnableImageWhitelist' => false,
			'wgLoadScript' => false,
			'wgExtensionAssetsPath' => false,
			'wgStylePath' => false,
			'wgResourceBasePath' => null,
			'wgCrossSiteAJAXdomains' => [
				'sister-site.somewhere.com',
				'*.wikipedia.org',
				'??.wikinews.org'
			],
			'wgScriptPath' => '/w',
			'wgForeignFileRepos' => [ [
				'class' => ForeignAPIRepo::class,
				'name' => 'wikimediacommons',
				'apibase' => 'https://commons.wikimedia.org/w/api.php',
				'url' => 'https://upload.wikimedia.org/wikipedia/commons',
				'thumbUrl' => 'https://upload.wikimedia.org/wikipedia/commons/thumb',
				'hashLevels' => 2,
				'transformVia404' => true,
				'fetchDescription' => true,
				'descriptionCacheExpiry' => 43200,
				'apiThumbCacheExpiry' => 0,
				'directory' => $wgUploadDirectory,
				'backend' => 'wikimediacommons-backend',
			] ],
			'wgCSPHeader' => true, // enable nonce by default
		] );
		// Note, there are some obscure globals which
		// could affect the results which aren't included above.

		$context = RequestContext::getMain();
		$resp = $context->getRequest()->response();
		$conf = $context->getConfig();
		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		$csp = new ContentSecurityPolicy( $resp, $conf, $hookContainer );
		$this->csp = TestingAccessWrapper::newFromObject( $csp );
		$this->csp->nonce = 'secret';

		parent::setUp();
	}

	/**
	 * @covers ContentSecurityPolicy::getAdditionalSelfUrls
	 */
	public function testGetAdditionalSelfUrlsRespectsUrlSettings() {
		$this->setMwGlobals( 'wgLoadScript', 'https://wgLoadScript.example.org/load.php' );
		$this->setMwGlobals( 'wgExtensionAssetsPath',
			'https://wgExtensionAssetsPath.example.org/assets/' );
		$this->setMwGlobals( 'wgStylePath', 'https://wgStylePath.example.org/style/' );
		$this->setMwGlobals( 'wgResourceBasePath', 'https://wgResourceBasePath.example.org/resources/' );

		$this->assertEquals(
			[
				'https://upload.wikimedia.org',
				'https://commons.wikimedia.org',
				'https://wgLoadScript.example.org',
				'https://wgExtensionAssetsPath.example.org',
				'https://wgStylePath.example.org',
				'https://wgResourceBasePath.example.org',
			],
			array_values( $this->csp->getAdditionalSelfUrls() )
		);
	}

	/**
	 * @dataProvider providerFalsePositiveBrowser
	 * @covers ContentSecurityPolicy::falsePositiveBrowser
	 */
	public function testFalsePositiveBrowser( $ua, $expected ) {
		$actual = ContentSecurityPolicy::falsePositiveBrowser( $ua );
		$this->assertSame( $expected, $actual, $ua );
	}

	public function providerFalsePositiveBrowser() {
		return [
			[
				'Mozilla/5.0 (X11; Linux i686; rv:41.0) Gecko/20100101 Firefox/41.0',
				true
			],
			[
				'Mozilla/5.0 (X11; U; Linux i686; en-ca) AppleWebKit/531.2+ (KHTML, like Gecko) ' .
					'Version/5.0 Safari/531.2+ Debian/squeeze (2.30.6-1) Epiphany/2.30.6',
				false
			],
		];
	}

	/**
	 * @covers ContentSecurityPolicy::addScriptSrc
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testAddScriptSrc() {
		$this->csp->addScriptSrc( 'https://example.com:71' );
		$actual = $this->csp->makeCSPDirectives( true, ContentSecurityPolicy::FULL_MODE );
		$expected = "script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'" .
			" sister-site.somewhere.com *.wikipedia.org https://example.com:71; default-src *" .
			" data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri" .
			" /w/api.php?action=cspreport&format=json";
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers ContentSecurityPolicy::addStyleSrc
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testAddStyleSrc() {
		$this->csp->addStyleSrc( 'style.example.com' );
		$actual = $this->csp->makeCSPDirectives( true, ContentSecurityPolicy::REPORT_ONLY_MODE );
		$expected = "script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'" .
			" sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:;" .
			" style-src * data: blob: style.example.com 'unsafe-inline'; object-src 'none'; report-uri" .
			" /w/api.php?action=cspreport&format=json&reportonly=1";
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers ContentSecurityPolicy::addDefaultSrc
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testAddDefaultSrc() {
		$this->csp->addDefaultSrc( '*.example.com' );
		$actual = $this->csp->makeCSPDirectives( true, ContentSecurityPolicy::FULL_MODE );
		$expected = "script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'" .
			" sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:" .
			" *.example.com; style-src * data: blob: *.example.com 'unsafe-inline';" .
			" object-src 'none'; report-uri /w/api.php?action=cspreport&format=json";
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider providerMakeCSPDirectives
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testMakeCSPDirectives(
		$policy,
		$expectedFull,
		$expectedReport
	) {
		$actualFull = $this->csp->makeCSPDirectives( $policy, ContentSecurityPolicy::FULL_MODE );
		$actualReport = $this->csp->makeCSPDirectives(
			$policy, ContentSecurityPolicy::REPORT_ONLY_MODE
		);
		$policyJson = FormatJson::encode( $policy );
		$this->assertSame( $expectedFull, $actualFull, "full: " . $policyJson );
		$this->assertSame( $expectedReport, $actualReport, "report: " . $policyJson );
	}

	public function providerMakeCSPDirectives() {
		// @codingStandardsIgnoreStart Generic.Files.LineLength
		return [
			[ false, '', '' ],
			[
				[ 'useNonces' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
				"script-src 'unsafe-eval' blob: 'self' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'"
			],
			[
				true,
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			 ],
			[
				[ 'script-src' => [ 'http://example.com', 'http://something,else.com' ] ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' http://example.com http://something%2Celse.com 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' http://example.com http://something%2Celse.com 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'unsafeFallback' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'unsafeFallback' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'default-src' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'default-src' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org sister-site.somewhere.com *.wikipedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org sister-site.somewhere.com *.wikipedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org sister-site.somewhere.com *.wikipedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org sister-site.somewhere.com *.wikipedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'default-src' => [ 'https://foo.com', 'http://bar.com', 'baz.de' ] ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org https://foo.com http://bar.com baz.de sister-site.somewhere.com *.wikipedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org https://foo.com http://bar.com baz.de sister-site.somewhere.com *.wikipedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org https://foo.com http://bar.com baz.de sister-site.somewhere.com *.wikipedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org https://foo.com http://bar.com baz.de sister-site.somewhere.com *.wikipedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'includeCORS' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'includeCORS' => false, 'default-src' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline'; default-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org; style-src 'self' data: blob: https://upload.wikimedia.org https://commons.wikimedia.org 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'includeCORS' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'report-uri' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'",
			],
			[
				[ 'report-uri' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'report-uri' => 'https://example.com/index.php?foo;report=csp' ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri https://example.com/index.php?foo%3Breport=csp",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri https://example.com/index.php?foo%3Breport=csp",
			],
			[
				[ 'object-src' => false ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'object-src' => true ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'object-src' => "'self'" ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'self'; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'self'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
			[
				[ 'object-src' => [ "'self'", 'https://example.com/f;d' ] ],
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'self' https://example.com/f%3Bd; report-uri /w/api.php?action=cspreport&format=json",
				"script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'self' https://example.com/f%3Bd; report-uri /w/api.php?action=cspreport&format=json&reportonly=1",
			],
		];
	}

	/**
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testMakeCSPDirectivesImage() {
		global $wgAllowImageTag;
		$origImg = wfSetVar( $wgAllowImageTag, true );

		$actual = $this->csp->makeCSPDirectives( true, ContentSecurityPolicy::FULL_MODE );

		$wgAllowImageTag = $origImg;

		$expected = "script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json";
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @covers ContentSecurityPolicy::makeCSPDirectives
	 */
	public function testMakeCSPDirectivesReportUri() {
		$actual = $this->csp->makeCSPDirectives(
			true,
			ContentSecurityPolicy::REPORT_ONLY_MODE
		);
		$expected = "script-src 'unsafe-eval' blob: 'self' 'nonce-secret' 'unsafe-inline' sister-site.somewhere.com *.wikipedia.org; default-src * data: blob:; style-src * data: blob: 'unsafe-inline'; object-src 'none'; report-uri /w/api.php?action=cspreport&format=json&reportonly=1";
		$this->assertSame( $expected, $actual );
		// @codingStandardsIgnoreEnd Generic.Files.LineLength
	}

	/**
	 * @covers ContentSecurityPolicy::getHeaderName
	 */
	public function testGetHeaderName() {
		$this->assertSame(
			'Content-Security-Policy-Report-Only',
			$this->csp->getHeaderName( ContentSecurityPolicy::REPORT_ONLY_MODE )
		);
		$this->assertSame(
			'Content-Security-Policy',
			$this->csp->getHeaderName( ContentSecurityPolicy::FULL_MODE )
		);
	}

	/**
	 * @covers ContentSecurityPolicy::getReportUri
	 */
	public function testGetReportUri() {
		$full = $this->csp->getReportUri( ContentSecurityPolicy::FULL_MODE );
		$fullExpected = '/w/api.php?action=cspreport&format=json';
		$this->assertSame( $fullExpected, $full, 'normal report uri' );

		$report = $this->csp->getReportUri( ContentSecurityPolicy::REPORT_ONLY_MODE );
		$reportExpected = $fullExpected . '&reportonly=1';
		$this->assertSame( $reportExpected, $report, 'report only' );

		global $wgScriptPath;
		$origPath = wfSetVar( $wgScriptPath, '/tl;dr/a,%20wiki' );
		$esc = $this->csp->getReportUri( ContentSecurityPolicy::FULL_MODE );
		$escExpected = '/tl%3Bdr/a%2C%20wiki/api.php?action=cspreport&format=json';
		$wgScriptPath = $origPath;
		$this->assertSame( $escExpected, $esc, 'test esc rules' );
	}

	/**
	 * @dataProvider providerPrepareUrlForCSP
	 * @covers ContentSecurityPolicy::prepareUrlForCSP
	 */
	public function testPrepareUrlForCSP( $url, $expected ) {
		$actual = $this->csp->prepareUrlForCSP( $url );
		$this->assertSame( $expected, $actual, $url );
	}

	public function providerPrepareUrlForCSP() {
		global $wgServer;
		return [
			[ $wgServer, false ],
			[ 'https://example.com', 'https://example.com' ],
			[ 'https://example.com:200', 'https://example.com:200' ],
			[ 'http://example.com', 'http://example.com' ],
			[ 'example.com', 'example.com' ],
			[ '*.example.com', '*.example.com' ],
			[ 'https://*.example.com', 'https://*.example.com' ],
			[ '//example.com', 'example.com' ],
			[ 'https://example.com/path', 'https://example.com' ],
			[ 'https://example.com/path:', 'https://example.com' ],
			[ 'https://example.com/Wikipedia:NPOV', 'https://example.com' ],
			[ 'https://tl;dr.com', 'https://tl%3Bdr.com' ],
			[ 'yes,no.com', 'yes%2Cno.com' ],
			[ '/relative-url', false ],
			[ '/relativeUrl:withColon', false ],
			[ 'data:', 'data:' ],
			[ 'blob:', 'blob:' ],
		];
	}

	/**
	 * @covers ContentSecurityPolicy::escapeUrlForCSP
	 */
	public function testEscapeUrlForCSP() {
		$escaped = $this->csp->escapeUrlForCSP( ',;%2B' );
		$this->assertSame( '%2C%3B%2B', $escaped );
	}

	/**
	 * @dataProvider providerCSPIsEnabled
	 * @covers ContentSecurityPolicy::isNonceRequired
	 */
	public function testCSPIsEnabled( $main, $reportOnly, $expected ) {
		$this->setMwGlobals( 'wgCSPReportOnlyHeader', $reportOnly );
		$this->setMwGlobals( 'wgCSPHeader', $main );
		$res = ContentSecurityPolicy::isNonceRequired( RequestContext::getMain()->getConfig() );
		$this->assertSame( $expected, $res );
	}

	public function providerCSPIsEnabled() {
		return [
			[ true, true, true ],
			[ false, true, true ],
			[ true, false, true ],
			[ false, false, false ],
			[ false, [], true ],
			[ [], false, true ],
			[ [ 'default-src' => [ 'foo.example.com' ] ], false, true ],
			[ [ 'useNonces' => false ], [ 'useNonces' => false ], false ],
			[ [ 'useNonces' => true ], [ 'useNonces' => false ], true ],
			[ [ 'useNonces' => false ], [ 'useNonces' => true ], true ],
		];
	}
}
