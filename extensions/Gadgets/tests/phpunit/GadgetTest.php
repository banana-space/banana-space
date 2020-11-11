<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Gadgets
 */
class GadgetTest extends MediaWikiTestCase {

	public function tearDown() {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}

	/**
	 * @param string $line
	 * @return Gadget
	 */
	private function create( $line ) {
		$repo = new MediaWikiGadgetsDefinitionRepo();
		$g = $repo->newFromDefinition( $line, 'misc' );
		$this->assertInstanceOf( Gadget::class, $g );
		return $g;
	}

	private function getModule( Gadget $g ) {
		$module = TestingAccessWrapper::newFromObject(
			new GadgetResourceLoaderModule( [ 'id' => null ] )
		);
		$module->gadget = $g;
		return $module;
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 */
	public function testInvalidLines() {
		$repo = new MediaWikiGadgetsDefinitionRepo();
		$this->assertFalse( $repo->newFromDefinition( '', 'misc' ) );
		$this->assertFalse( $repo->newFromDefinition( '<foo|bar>', 'misc' ) );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::__construct
	 * @covers Gadget::getName
	 * @covers Gadget::getModuleName
	 */
	public function testSimpleCases() {
		$g = $this->create( '* foo bar| foo.css|foo.js|foo.bar' );
		$this->assertEquals( 'foo_bar', $g->getName() );
		$this->assertEquals( 'ext.gadget.foo_bar', Gadget::getModuleName( $g->getName() ) );
		$this->assertEquals( [ 'MediaWiki:Gadget-foo.js' ], $g->getScripts() );
		$this->assertEquals( [ 'MediaWiki:Gadget-foo.css' ], $g->getStyles() );
		$this->assertEquals( [ 'MediaWiki:Gadget-foo.js', 'MediaWiki:Gadget-foo.css' ],
			$g->getScriptsAndStyles() );
		$this->assertEquals( [ 'MediaWiki:Gadget-foo.js' ], $g->getLegacyScripts() );
		$this->assertFalse( $g->supportsResourceLoader() );
		$this->assertTrue( $g->hasModule() );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::supportsResourceLoader
	 * @covers Gadget::getLegacyScripts
	 */
	public function testRLtag() {
		$g = $this->create( '*foo [ResourceLoader]|foo.js|foo.css' );
		$this->assertEquals( 'foo', $g->getName() );
		$this->assertTrue( $g->supportsResourceLoader() );
		$this->assertEquals( 0, count( $g->getLegacyScripts() ) );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::getDependencies
	 */
	public function testDependencies() {
		$g = $this->create( '* foo[ResourceLoader|dependencies=jquery.ui]|bar.js' );
		$this->assertEquals( [ 'MediaWiki:Gadget-bar.js' ], $g->getScripts() );
		$this->assertTrue( $g->supportsResourceLoader() );
		$this->assertEquals( [ 'jquery.ui' ], $g->getDependencies() );
	}

	public static function provideGetType() {
		return [
			[
				'Default (mixed)',
				'* foo[ResourceLoader]|bar.css|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
			[
				'Default (styles only)',
				'* foo[ResourceLoader]|bar.css',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			],
			[
				'Default (scripts only)',
				'* foo[ResourceLoader]|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
			[
				'Default (styles only with dependencies)',
				'* foo[ResourceLoader|dependencies=jquery.ui]|bar.css',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
			[
				'Styles type (mixed)',
				'* foo[ResourceLoader|type=styles]|bar.css|bar.js',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			],
			[
				'Styles type (styles only)',
				'* foo[ResourceLoader|type=styles]|bar.css',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			],
			[
				'Styles type (scripts only)',
				'* foo[ResourceLoader|type=styles]|bar.js',
				'styles',
				ResourceLoaderModule::LOAD_STYLES,
			],
			[
				'General type (mixed)',
				'* foo[ResourceLoader|type=general]|bar.css|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
			[
				'General type (styles only)',
				'* foo[ResourceLoader|type=general]|bar.css',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
			[
				'General type (scripts only)',
				'* foo[ResourceLoader|type=general]|bar.js',
				'general',
				ResourceLoaderModule::LOAD_GENERAL,
			],
		];
	}

	/**
	 * @dataProvider provideGetType
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::getType
	 * @covers GadgetResourceLoaderModule::getType
	 */
	public function testType( $message, $definition, $gType, $mType ) {
		$g = $this->create( $definition );
		$this->assertEquals( $gType, $g->getType(), "Gadget: $message" );
		$this->assertEquals( $mType, $this->getModule( $g )->getType(), "Module: $message" );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::isHidden
	 */
	public function testIsHidden() {
		$g = $this->create( '* foo[hidden]|bar.js' );
		$this->assertTrue( $g->isHidden() );

		$g = $this->create( '* foo[ResourceLoader|hidden]|bar.js' );
		$this->assertTrue( $g->isHidden() );

		$g = $this->create( '* foo[ResourceLoader]|bar.js' );
		$this->assertFalse( $g->isHidden() );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::fetchStructuredList
	 * @covers GadgetHooks::getPreferences
	 */
	public function testPreferences() {
		$prefs = [];
		$repo = TestingAccessWrapper::newFromObject( new MediaWikiGadgetsDefinitionRepo() );
		// Force usage of a MediaWikiGadgetsDefinitionRepo
		GadgetRepo::setSingleton( $repo );

		$gadgets = $repo->fetchStructuredList( '* foo | foo.js
==keep-section1==
* bar| bar.js
==remove-section==
* baz [rights=embezzle] |baz.js
==keep-section2==
* quux [rights=read] | quux.js' );
		$this->assertGreaterThanOrEqual( 2, count( $gadgets ), "Gadget list parsed" );

		$repo->definitionCache = $gadgets;
		$this->assertTrue( GadgetHooks::getPreferences( new User, $prefs ),
			'GetPrefences hook should return true' );

		$options = $prefs['gadgets']['options'];
		$this->assertArrayNotHasKey( '⧼gadget-section-remove-section⧽', $options,
			'Must not show empty sections' );
		$this->assertArrayHasKey( '⧼gadget-section-keep-section1⧽', $options );
		$this->assertArrayHasKey( '⧼gadget-section-keep-section2⧽', $options );
	}
}
