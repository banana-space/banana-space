<?php
/*
 * @file
 * @ingroup skins
 */

use Vector\Hooks;

const SKIN_PREFS_SECTION = 'rendering/skin/skin-prefs';

/**
 * Integration tests for Vector Hooks.
 *
 * @group Vector
 * @coversDefaultClass \Vector\Hooks
 */
class VectorHooksTest extends \MediaWikiTestCase {
	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesDisabled() {
		$config = new HashConfig( [
			'VectorShowSkinPreferences' => false,
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertSame( $prefs, [], 'No preferences are added.' );
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionFoundLegacy() {
		$config = new HashConfig( [
			'VectorShowSkinPreferences' => true,
			// '1' is Legacy.
			'VectorDefaultSkinVersionForExistingAccounts' => '1',
			'VectorDefaultSidebarVisibleForAuthorisedUser' => true
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [
			'foo' => [],
			'skin' => [],
			'bar' => []
		];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertSame(
			$prefs,
			[
				'foo' => [],
				'skin' => [],
				'VectorSkinVersion' => [
					'type' => 'toggle',
					'label-message' => 'prefs-vector-enable-vector-1-label',
					'help-message' => 'prefs-vector-enable-vector-1-help',
					'section' => SKIN_PREFS_SECTION,
					// '1' is enabled which means Legacy.
					'default' => '1',
					'hide-if' => [ '!==', 'wpskin', 'vector' ]
				],
				'VectorSidebarVisible' => [
					'type' => 'api',
					'default' => true
				],
				'bar' => []
			],
			'Preferences are inserted directly after skin.'
		);
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionMissingLegacy() {
		$config = new HashConfig( [
			'VectorShowSkinPreferences' => true,
			// '1' is Legacy.
			'VectorDefaultSkinVersionForExistingAccounts' => '1',
			'VectorDefaultSidebarVisibleForAuthorisedUser' => true
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [
			'foo' => [],
			'bar' => []
		];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertSame(
			$prefs,
			[
				'foo' => [],
				'bar' => [],
				'VectorSkinVersion' => [
					'type' => 'toggle',
					'label-message' => 'prefs-vector-enable-vector-1-label',
					'help-message' => 'prefs-vector-enable-vector-1-help',
					'section' => SKIN_PREFS_SECTION,
					// '1' is enabled which means Legacy.
					'default' => '1',
					'hide-if' => [ '!==', 'wpskin', 'vector' ]
				],
				'VectorSidebarVisible' => [
					'type' => 'api',
					'default' => true
				],
			],
			'Preferences are appended.'
		);
	}

	/**
	 * @covers ::onGetPreferences
	 */
	public function testOnGetPreferencesShowPreferencesEnabledSkinSectionMissingLatest() {
		$config = new HashConfig( [
			'VectorShowSkinPreferences' => true,
			// '2' is latest.
			'VectorDefaultSkinVersionForExistingAccounts' => '2',
			'VectorDefaultSidebarVisibleForAuthorisedUser' => true
		] );
		$this->setService( 'Vector.Config', $config );

		$prefs = [
			'foo' => [],
			'bar' => [],
		];
		Hooks::onGetPreferences( $this->getTestUser()->getUser(), $prefs );
		$this->assertSame(
			$prefs,
			[
				'foo' => [],
				'bar' => [],
				'VectorSkinVersion' => [
					'type' => 'toggle',
					'label-message' => 'prefs-vector-enable-vector-1-label',
					'help-message' => 'prefs-vector-enable-vector-1-help',
					'section' => SKIN_PREFS_SECTION,
					// '0' is disabled (which means latest).
					'default' => '0',
					'hide-if' => [ '!==', 'wpskin', 'vector' ]
				],
				'VectorSidebarVisible' => [
					'type' => 'api',
					'default' => true
				],
			],
			'Legacy skin version is disabled.'
		);
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorEnabledLegacyNewPreference() {
		$formData = [
			'skin' => 'vector',
			// True is Legacy.
			'VectorSkinVersion' => true,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( \User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			// '1' is Legacy.
			->with( 'VectorSkinVersion', '1' );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorEnabledLatestNewPreference() {
		$formData = [
			'skin' => 'vector',
			// False is latest.
			'VectorSkinVersion' => false,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( \User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			// '2' is latest.
			->with( 'VectorSkinVersion', '2' );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorEnabledNoNewPreference() {
		$formData = [
			'skin' => 'vector',
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( \User::class );
		$user->expects( $this->never() )
			->method( 'setOption' );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorDisabledNoOldPreference() {
		$formData = [
			// False is latest.
			'VectorSkinVersion' => false,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( \User::class );
		$user->expects( $this->never() )
			->method( 'setOption' );
		$result = true;
		$oldPreferences = [];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onPreferencesFormPreSave
	 */
	public function testOnPreferencesFormPreSaveVectorDisabledOldPreference() {
		$formData = [
			// False is latest.
			'VectorSkinVersion' => false,
		];
		$form = $this->createMock( HTMLForm::class );
		$user = $this->createMock( \User::class );
		$user->expects( $this->once() )
			->method( 'setOption' )
			->with( 'VectorSkinVersion', 'old' );
		$result = true;
		$oldPreferences = [
			'VectorSkinVersion' => 'old',
		];

		Hooks::onPreferencesFormPreSave( $formData, $form, $user, $result, $oldPreferences );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLegacy() {
		$config = new HashConfig( [
			// '1' is Legacy.
			'VectorDefaultSkinVersionForNewAccounts' => '1',
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( \User::class );
		$user->expects( $this->once() )
		->method( 'setOption' )
			// '1' is Legacy.
			->with( 'VectorSkinVersion', '1' );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onLocalUserCreated
	 */
	public function testOnLocalUserCreatedLatest() {
		$config = new HashConfig( [
			// '2' is latest.
			'VectorDefaultSkinVersionForNewAccounts' => '2',
		] );
		$this->setService( 'Vector.Config', $config );

		$user = $this->createMock( \User::class );
		$user->expects( $this->once() )
		->method( 'setOption' )
			// '2' is latest.
			->with( 'VectorSkinVersion', '2' );
		$isAutoCreated = false;
		Hooks::onLocalUserCreated( $user, $isAutoCreated );
	}

	/**
	 * @covers ::onSkinTemplateNavigation
	 */
	public function testOnSkinTemplateNavigation() {
		$this->setMwGlobals( [
			'wgVectorUseIconWatch' => true
		] );
		$skin = new SkinVector();
		$contentNavWatch = [
			'actions' => [
				'watch' => [ 'class' => 'watch' ],
			]
		];
		$contentNavUnWatch = [
			'actions' => [
				'move' => [ 'class' => 'move' ],
				'unwatch' => [],
			],
		];

		Hooks::onSkinTemplateNavigation( $skin, $contentNavUnWatch );
		Hooks::onSkinTemplateNavigation( $skin, $contentNavWatch );

		$this->assertTrue(
			strpos( $contentNavWatch['views']['watch']['class'], 'icon' ) !== false,
			'Watch list items require an "icon" class'
		);
		$this->assertTrue(
			strpos( $contentNavUnWatch['views']['unwatch']['class'], 'icon' ) !== false,
			'Unwatch list items require an "icon" class'
		);
		$this->assertFalse(
			strpos( $contentNavUnWatch['actions']['move']['class'], 'icon' ) !== false,
			'List item other than watch or unwatch should not have an "icon" class'
		);
	}
}
