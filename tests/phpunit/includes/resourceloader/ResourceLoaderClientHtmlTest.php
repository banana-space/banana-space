<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group ResourceLoader
 * @covers ResourceLoaderClientHtml
 */
class ResourceLoaderClientHtmlTest extends PHPUnit\Framework\TestCase {

	use MediaWikiCoversValidator;

	public function testGetData() {
		$context = self::makeContext();
		$context->getResourceLoader()->register( self::makeSampleModules() );

		$client = new ResourceLoaderClientHtml( $context );
		$client->setModules( [
			'test',
			'test.private',
			'test.shouldembed.empty',
			'test.shouldembed',
			'test.user',
			'test.unregistered',
		] );
		$client->setModuleStyles( [
			'test.styles.mixed',
			'test.styles.user.empty',
			'test.styles.private',
			'test.styles.pure',
			'test.styles.shouldembed',
			'test.styles.deprecated',
			'test.unregistered.styles',
		] );

		$expected = [
			'states' => [
				// The below are NOT queued for loading via `mw.loader.load(Array)`.
				// Instead we tell the client to set their state to "loading" so that
				// if they are needed as dependencies, the client will not try to
				// load them on-demand, because the server is taking care of them already.
				// Either:
				// - Embedded as inline scripts in the HTML (e.g. user-private code, and
				//   previews). Once that script tag is reached, the state is "loaded".
				// - Loaded directly from the HTML with a dedicated HTTP request (e.g.
				//   user scripts, which vary by a 'user' and 'version' parameter that
				//   the static user-agnostic startup module won't have).
				'test.private' => 'loading',
				'test.shouldembed' => 'loading',
				'test.user' => 'loading',
				// The below are known to the server to be empty scripts, or to be
				// synchronously loaded stylesheets. These start in the "ready" state.
				'test.shouldembed.empty' => 'ready',
				'test.styles.pure' => 'ready',
				'test.styles.user.empty' => 'ready',
				'test.styles.private' => 'ready',
				'test.styles.shouldembed' => 'ready',
				'test.styles.deprecated' => 'ready',
			],
			'general' => [
				'test',
			],
			'styles' => [
				'test.styles.pure',
				'test.styles.deprecated',
			],
			'embed' => [
				'styles' => [ 'test.styles.private', 'test.styles.shouldembed' ],
				'general' => [
					'test.private',
					'test.shouldembed',
					'test.user',
				],
			],
			'styleDeprecations' => [
				Xml::encodeJsCall(
					'mw.log.warn',
					[ 'This page is using the deprecated ResourceLoader module "test.styles.deprecated".
Deprecation message.' ]
				)
			],
		];

		$access = TestingAccessWrapper::newFromObject( $client );
		$this->assertEquals( $expected, $access->getData() );
	}

	public function testGetHeadHtml() {
		$context = self::makeContext();
		$context->getResourceLoader()->register( self::makeSampleModules() );

		$client = new ResourceLoaderClientHtml( $context, [
			'nonce' => false,
		] );
		$client->setConfig( [ 'key' => 'value' ] );
		$client->setModules( [
			'test',
			'test.private',
		] );
		$client->setModuleStyles( [
			'test.styles.pure',
			'test.styles.private',
			'test.styles.deprecated',
		] );
		$client->setExemptStates( [
			'test.exempt' => 'ready',
		] );

		// phpcs:disable Generic.Files.LineLength
		$expected = '<script>'
			. 'document.documentElement.className="client-js";'
			. 'RLCONF={"key":"value"};'
			. 'RLSTATE={"test.exempt":"ready","test.private":"loading","test.styles.pure":"ready","test.styles.private":"ready","test.styles.deprecated":"ready"};'
			. 'RLPAGEMODULES=["test"];'
			. '</script>' . "\n"
			. '<script>(RLQ=window.RLQ||[]).push(function(){'
			. 'mw.loader.implement("test.private@{blankVer}",null,{"css":[]});'
			. '});</script>' . "\n"
			. '<link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.styles.deprecated%2Cpure&amp;only=styles"/>' . "\n"
			. '<style>.private{}</style>' . "\n"
			. '<script async="" src="/w/load.php?lang=nl&amp;modules=startup&amp;only=scripts&amp;raw=1"></script>';
		// phpcs:enable
		$expected = self::expandVariables( $expected );

		$this->assertSame( $expected, (string)$client->getHeadHtml() );
	}

	/**
	 * Confirm that 'target' is passed down to the startup module's load url.
	 */
	public function testGetHeadHtmlWithTarget() {
		$client = new ResourceLoaderClientHtml(
			self::makeContext(),
			[ 'target' => 'example' ]
		);

		// phpcs:disable Generic.Files.LineLength
		$expected = '<script>document.documentElement.className="client-js";</script>' . "\n"
			. '<script async="" src="/w/load.php?lang=nl&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;target=example"></script>';
		// phpcs:enable

		$this->assertSame( $expected, (string)$client->getHeadHtml() );
	}

	/**
	 * Confirm that 'safemode' is passed down to startup.
	 */
	public function testGetHeadHtmlWithSafemode() {
		$client = new ResourceLoaderClientHtml(
			self::makeContext(),
			[ 'safemode' => '1' ]
		);

		// phpcs:disable Generic.Files.LineLength
		$expected = '<script>document.documentElement.className="client-js";</script>' . "\n"
			. '<script async="" src="/w/load.php?lang=nl&amp;modules=startup&amp;only=scripts&amp;raw=1&amp;safemode=1"></script>';
		// phpcs:enable

		$this->assertSame( $expected, (string)$client->getHeadHtml() );
	}

	/**
	 * Confirm that a null 'target' is the same as no target.
	 */
	public function testGetHeadHtmlWithNullTarget() {
		$client = new ResourceLoaderClientHtml(
			self::makeContext(),
			[ 'target' => null ]
		);

		// phpcs:disable Generic.Files.LineLength
		$expected = '<script>document.documentElement.className="client-js";</script>' . "\n"
			. '<script async="" src="/w/load.php?lang=nl&amp;modules=startup&amp;only=scripts&amp;raw=1"></script>';
		// phpcs:enable

		$this->assertSame( $expected, (string)$client->getHeadHtml() );
	}

	public function testGetBodyHtml() {
		$context = self::makeContext();
		$context->getResourceLoader()->register( self::makeSampleModules() );

		$client = new ResourceLoaderClientHtml( $context, [ 'nonce' => false ] );
		$client->setConfig( [ 'key' => 'value' ] );
		$client->setModules( [
			'test',
			'test.private.bottom',
		] );
		$client->setModuleStyles( [
			'test.styles.deprecated',
		] );
		// phpcs:disable Generic.Files.LineLength
		$expected = '<script>(RLQ=window.RLQ||[]).push(function(){'
			. 'mw.log.warn("This page is using the deprecated ResourceLoader module \"test.styles.deprecated\".\nDeprecation message.");'
			. '});</script>';
		// phpcs:enable

		$this->assertSame( $expected, (string)$client->getBodyHtml() );
	}

	public static function provideMakeLoad() {
		// phpcs:disable Generic.Files.LineLength
		return [
			[
				'context' => [],
				'modules' => [ 'test.unknown' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '',
			],
			[
				'context' => [],
				'modules' => [ 'test.styles.private' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '<style>.private{}</style>',
			],
			[
				'context' => [],
				'modules' => [ 'test.private' ],
				'only' => ResourceLoaderModule::TYPE_COMBINED,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.implement("test.private@{blankVer}",null,{"css":[]});});</script>',
			],
			[
				'context' => [],
				'modules' => [ 'test.scripts' ],
				'only' => ResourceLoaderModule::TYPE_SCRIPTS,
				// Eg. startup module
				'extra' => [ 'raw' => '1' ],
				'output' => '<script async="" src="/w/load.php?lang=nl&amp;modules=test.scripts&amp;only=scripts&amp;raw=1"></script>',
			],
			[
				'context' => [],
				'modules' => [ 'test.scripts.user' ],
				'only' => ResourceLoaderModule::TYPE_SCRIPTS,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.load("/w/load.php?lang=nl\u0026modules=test.scripts.user\u0026only=scripts\u0026user=Example\u0026version={blankCombi}");});</script>',
			],
			[
				'context' => [],
				'modules' => [ 'test.user' ],
				'only' => ResourceLoaderModule::TYPE_COMBINED,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.load("/w/load.php?lang=nl\u0026modules=test.user\u0026user=Example\u0026version={blankCombi}");});</script>',
			],
			[
				'context' => [ 'debug' => 'true' ],
				'modules' => [ 'test.styles.pure', 'test.styles.mixed' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '<link rel="stylesheet" href="/w/load.php?debug=true&amp;lang=nl&amp;modules=test.styles.mixed&amp;only=styles"/>' . "\n"
					. '<link rel="stylesheet" href="/w/load.php?debug=true&amp;lang=nl&amp;modules=test.styles.pure&amp;only=styles"/>',
			],
			[
				'context' => [ 'debug' => 'false' ],
				'modules' => [ 'test.styles.pure', 'test.styles.mixed' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '<link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.styles.mixed%2Cpure&amp;only=styles"/>',
			],
			[
				'context' => [],
				'modules' => [ 'test.styles.noscript' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '<noscript><link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.styles.noscript&amp;only=styles"/></noscript>',
			],
			[
				'context' => [],
				'modules' => [ 'test.shouldembed' ],
				'only' => ResourceLoaderModule::TYPE_COMBINED,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.implement("test.shouldembed@{blankVer}",null,{"css":[]});});</script>',
			],
			[
				'context' => [],
				'modules' => [ 'test.styles.shouldembed' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' => '<style>.shouldembed{}</style>',
			],
			[
				'context' => [],
				'modules' => [ 'test.scripts.shouldembed' ],
				'only' => ResourceLoaderModule::TYPE_SCRIPTS,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.state({"test.scripts.shouldembed":"ready"});});</script>',
			],
			[
				'context' => [],
				'modules' => [ 'test', 'test.shouldembed' ],
				'only' => ResourceLoaderModule::TYPE_COMBINED,
				'extra' => [],
				'output' => '<script>(RLQ=window.RLQ||[]).push(function(){mw.loader.load("/w/load.php?lang=nl\u0026modules=test");mw.loader.implement("test.shouldembed@{blankVer}",null,{"css":[]});});</script>',
			],
			[
				'context' => [],
				'modules' => [ 'test.styles.pure', 'test.styles.shouldembed' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' =>
					'<link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.styles.pure&amp;only=styles"/>' . "\n"
					. '<style>.shouldembed{}</style>'
			],
			[
				'context' => [],
				'modules' => [ 'test.ordering.a', 'test.ordering.e', 'test.ordering.b', 'test.ordering.d', 'test.ordering.c' ],
				'only' => ResourceLoaderModule::TYPE_STYLES,
				'extra' => [],
				'output' =>
					'<link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.ordering.a%2Cb&amp;only=styles"/>' . "\n"
					. '<style>.orderingC{}.orderingD{}</style>' . "\n"
					. '<link rel="stylesheet" href="/w/load.php?lang=nl&amp;modules=test.ordering.e&amp;only=styles"/>'
			],
		];
		// phpcs:enable
	}

	/**
	 * @dataProvider provideMakeLoad
	 * @covers ResourceLoaderClientHtml
	 * @covers ResourceLoaderModule::getModuleContent
	 * @covers ResourceLoader
	 */
	public function testMakeLoad(
		array $contextQuery,
		array $modules,
		$type,
		array $extraQuery,
		$expected
	) {
		$context = self::makeContext( $contextQuery );
		$context->getResourceLoader()->register( self::makeSampleModules() );
		$actual = ResourceLoaderClientHtml::makeLoad( $context, $modules, $type, $extraQuery, false );
		$expected = self::expandVariables( $expected );
		$this->assertSame( $expected, (string)$actual );
	}

	public function testGetDocumentAttributes() {
		$client = new ResourceLoaderClientHtml( self::makeContext() );
		$this->assertIsArray( $client->getDocumentAttributes() );
	}

	private static function expandVariables( $text ) {
		return strtr( $text, [
			'{blankCombi}' => ResourceLoaderTestCase::BLANK_COMBI,
			'{blankVer}' => ResourceLoaderTestCase::BLANK_VERSION
		] );
	}

	private static function makeContext( $extraQuery = [] ) {
		$conf = new HashConfig( [
			'EnableJavaScriptTest' => false,
			'LoadScript' => '/w/load.php',
		] );
		return new ResourceLoaderContext(
			new ResourceLoader( $conf ),
			new FauxRequest( array_merge( [
				'lang' => 'nl',
				'skin' => 'fallback',
				'user' => 'Example',
				'target' => 'phpunit',
			], $extraQuery ) )
		);
	}

	private static function makeModule( array $options = [] ) {
		return $options + [ 'class' => ResourceLoaderTestModule::class ];
	}

	private static function makeSampleModules() {
		$modules = [
			'test' => [],
			'test.private' => [ 'group' => 'private' ],
			'test.shouldembed.empty' => [ 'shouldEmbed' => true, 'isKnownEmpty' => true ],
			'test.shouldembed' => [ 'shouldEmbed' => true ],
			'test.user' => [ 'group' => 'user' ],

			'test.styles.pure' => [ 'type' => ResourceLoaderModule::LOAD_STYLES ],
			'test.styles.mixed' => [],
			'test.styles.noscript' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'group' => 'noscript',
			],
			'test.styles.user' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'group' => 'user',
			],
			'test.styles.user.empty' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'group' => 'user',
				'isKnownEmpty' => true,
			],
			'test.styles.private' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'group' => 'private',
				'styles' => '.private{}',
			],
			'test.styles.shouldembed' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'shouldEmbed' => true,
				'styles' => '.shouldembed{}',
			],
			'test.styles.deprecated' => [
				'type' => ResourceLoaderModule::LOAD_STYLES,
				'deprecated' => 'Deprecation message.',
			],

			'test.scripts' => [],
			'test.scripts.user' => [ 'group' => 'user' ],
			'test.scripts.user.empty' => [ 'group' => 'user', 'isKnownEmpty' => true ],
			'test.scripts.shouldembed' => [ 'shouldEmbed' => true ],

			'test.ordering.a' => [ 'shouldEmbed' => false ],
			'test.ordering.b' => [ 'shouldEmbed' => false ],
			'test.ordering.c' => [ 'shouldEmbed' => true, 'styles' => '.orderingC{}' ],
			'test.ordering.d' => [ 'shouldEmbed' => true, 'styles' => '.orderingD{}' ],
			'test.ordering.e' => [ 'shouldEmbed' => false ],
		];
		return array_map( function ( $options ) {
			return self::makeModule( $options );
		}, $modules );
	}
}
