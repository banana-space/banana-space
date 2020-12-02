<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @group Gadgets
 */
class GadgetHooksTest extends MediaWikiIntegrationTestCase {
	/**
	 * @var User
	 */
	protected $user;

	public function setUp() : void {
		global $wgGroupPermissions;

		parent::setUp();

		$wgGroupPermissions['unittesters'] = [
			'test' => true,
		];
		$this->user = $this->getTestUser( [ 'unittesters' ] )->getUser();
	}

	public function tearDown() : void {
		GadgetRepo::setSingleton();
		parent::tearDown();
	}

	/**
	 * @covers Gadget
	 * @covers GadgetHooks::getPreferences
	 * @covers GadgetRepo
	 * @covers MediaWikiGadgetsDefinitionRepo
	 */
	public function testPreferences() {
		$prefs = [];
		$repo = TestingAccessWrapper::newFromObject( new MediaWikiGadgetsDefinitionRepo() );
		// Force usage of a MediaWikiGadgetsDefinitionRepo
		GadgetRepo::setSingleton( $repo );

		/** @var MediaWikiGadgetsDefinitionRepo $repo */
		$gadgets = $repo->fetchStructuredList( '* foo | foo.js
==keep-section1==
* bar| bar.js
==remove-section==
* baz [rights=embezzle] |baz.js
==keep-section2==
* quux [rights=test] | quux.js' );
		$this->assertGreaterThanOrEqual( 2, count( $gadgets ), "Gadget list parsed" );

		$repo->definitionCache = $gadgets;
		GadgetHooks::getPreferences( $this->user, $prefs );

		$options = $prefs['gadgets']['options'];
		$this->assertArrayNotHasKey( '⧼gadget-section-remove-section⧽', $options,
			'Must not show empty sections' );
		$this->assertArrayHasKey( '⧼gadget-section-keep-section1⧽', $options );
		$this->assertArrayHasKey( '⧼gadget-section-keep-section2⧽', $options );
	}
}
