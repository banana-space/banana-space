<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Gadgets
 */
class GadgetTest extends MediaWikiUnitTestCase {
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
	 * @covers Gadget
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
		$this->assertSame( 0, count( $g->getLegacyScripts() ) );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::isAllowed
	 */
	public function testIsAllowed() {
		$user = $this->getMockBuilder( User::class )
			->setMethods( [ 'isAllowedAll' ] )
			->getMock();
		$user->method( 'isAllowedAll' )
			->willReturnCallback(
				function ( ...$rights ) {
					return array_diff( $rights, [ 'test' ] ) === [];
				}
			);

		/** @var User $user */
		$gUnset = $this->create( '*foo[ResourceLoader]|foo.js' );
		$gAllowed = $this->create( '*bar[ResourceLoader|rights=test]|bar.js' );
		$gNotAllowed = $this->create( '*baz[ResourceLoader|rights=nope]|baz.js' );
		$this->assertTrue( $gUnset->isAllowed( $user ) );
		$this->assertTrue( $gAllowed->isAllowed( $user ) );
		$this->assertFalse( $gNotAllowed->isAllowed( $user ) );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::isSkinSupported
	 */
	public function testSkinsTag() {
		$gUnset = $this->create( '*foo[ResourceLoader]|foo.js' );
		$gSkinSupported = $this->create( '*bar[ResourceLoader|skins=fallback]|bar.js' );
		$gSkinNotSupported = $this->create( '*baz[ResourceLoader|skins=bar]|baz.js' );
		$skin = new SkinFallback();
		$this->assertTrue( $gUnset->isSkinSupported( $skin ) );
		$this->assertTrue( $gSkinSupported->isSkinSupported( $skin ) );
		$this->assertFalse( $gSkinNotSupported->isSkinSupported( $skin ) );
	}

	/**
	 * @covers MediaWikiGadgetsDefinitionRepo::newFromDefinition
	 * @covers Gadget::getTargets
	 */
	public function testTargets() {
		$g = $this->create( '*foo[ResourceLoader]|foo.js' );
		$g2 = $this->create( '*bar[ResourceLoader|targets=desktop,mobile]|bar.js' );
		$this->assertEquals( [ 'desktop' ], $g->getTargets() );
		$this->assertEquals( [ 'desktop', 'mobile' ], $g2->getTargets() );
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
}
