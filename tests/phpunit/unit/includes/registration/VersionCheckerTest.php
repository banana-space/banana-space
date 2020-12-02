<?php

/**
 * @covers VersionChecker
 */
class VersionCheckerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideMediaWikiCheck
	 */
	public function testMediaWikiCheck( $coreVersion, $constraint, $expected ) {
		$checker = new VersionChecker( $coreVersion, '7.0.0', [] );
		$extDependencies = [ 'FakeExtension' => [ 'MediaWiki' => $constraint ] ];
		$this->assertSame( $expected, $checker->checkArray( $extDependencies ) === [] );
	}

	public static function provideMediaWikiCheck() {
		return [
			// [ MediaWiki version, constraint, expected ]
			[ '1.25alpha', '>= 1.26', false ],
			[ '1.25.0', '>= 1.26', false ],
			[ '1.26alpha', '>= 1.26', true ],
			[ '1.26alpha', '>= 1.26.0', true ],
			[ '1.26alpha', '>= 1.26.0-stable', false ],
			[ '1.26.0', '>= 1.26.0-stable', true ],
			[ '1.26.1', '>= 1.26.0-stable', true ],
			[ '1.27.1', '>= 1.26.0-stable', true ],
			[ '1.26alpha', '>= 1.26.1', false ],
			[ '1.26alpha', '>= 1.26alpha', true ],
			[ '1.26alpha', '>= 1.25', true ],
			[ '1.26.0-alpha.14', '>= 1.26.0-alpha.15', false ],
			[ '1.26.0-alpha.14', '>= 1.26.0-alpha.10', true ],
			[ '1.26.1', '>= 1.26.2, <=1.26.0', false ],
			[ '1.26.1', '^1.26.2', false ],
			// Accept anything for un-parsable version strings
			[ '1.26mwf14', '== 1.25alpha', true ],
			[ 'totallyinvalid', '== 1.0', true ],
		];
	}

	/**
	 * @dataProvider providePhpValidCheck
	 */
	public function testPhpValidCheck( $phpVersion, $constraint, $expected ) {
		$checker = new VersionChecker( '1.0.0', $phpVersion, [] );
		$extDependencies = [ 'FakeExtension' => [ 'platform' => [ 'php' => $constraint ] ] ];
		$this->assertSame( $expected, $checker->checkArray( $extDependencies ) === [] );
	}

	public static function providePhpValidCheck() {
		return [
			// [ phpVersion, constraint, expected ]
			[ '7.0.23', '>= 7.0.0', true ],
			[ '7.0.23', '^7.1.0', false ],
			[ '7.0.23', '7.0.23', true ],
		];
	}

	public function testPhpInvalidConstraint() {
		$checker = new VersionChecker( '1.0.0', '7.0.0', [] );
		$this->expectException( UnexpectedValueException::class );
		$checker->checkArray( [
			'FakeExtension' => [
				'platform' => [
					'php' => 'totallyinvalid',
				],
			],
		] );
	}

	/**
	 * @dataProvider providePhpInvalidVersion
	 */
	public function testPhpInvalidVersion( $phpVersion ) {
		$this->expectException( UnexpectedValueException::class );
		 $checker = new VersionChecker( '1.0.0', $phpVersion, [] );
	}

	public static function providePhpInvalidVersion() {
		return [
			// [ phpVersion ]
			[ '7.abc' ],
			[ '5.a.x' ],
		];
	}

	/**
	 * @dataProvider provideType
	 */
	public function testType( $given, $expected ) {
		$checker = new VersionChecker(
			'1.0.0',
			'7.0.0',
			[ 'phpLoadedExtension' ],
			[
				'presentAbility' => true,
				'presentAbilityWithMessage' => true,
				'missingAbility' => false,
				'missingAbilityWithMessage' => false,
			],
			[
				'presentAbilityWithMessage' => 'Present.',
				'missingAbilityWithMessage' => 'Missing.',
			]
		);
		$checker->setLoadedExtensionsAndSkins( [
				'FakeDependency' => [
					'version' => '1.0.0',
				],
				'NoVersionGiven' => [],
			] );
		$this->assertEquals( $expected, $checker->checkArray( [
			'FakeExtension' => $given,
		] ) );
	}

	public static function provideType() {
		return [
			// valid type
			[
				[
					'extensions' => [
						'FakeDependency' => '1.0.0',
					],
				],
				[],
			],
			[
				[
					'MediaWiki' => '1.0.0',
				],
				[],
			],
			[
				[
					'extensions' => [
						'NoVersionGiven' => '*',
					],
				],
				[],
			],
			[
				[
					'extensions' => [
						'NoVersionGiven' => '1.0',
					],
				],
				[
					[
						'incompatible' => 'FakeExtension',
						'type' => 'incompatible-extensions',
						'msg' => 'NoVersionGiven does not expose its version, but FakeExtension requires: 1.0.',
					],
				],
			],
			[
				[
					'extensions' => [
						'Missing' => '*',
					],
				],
				[
					[
						'missing' => 'Missing',
						'type' => 'missing-extensions',
						'msg' => 'FakeExtension requires Missing to be installed.',
					],
				],
			],
			[
				[
					'extensions' => [
						'FakeDependency' => '2.0.0',
					],
				],
				[
					[
						'incompatible' => 'FakeExtension',
						'type' => 'incompatible-extensions',
						// phpcs:ignore Generic.Files.LineLength.TooLong
						'msg' => 'FakeExtension is not compatible with the current installed version of FakeDependency (1.0.0), it requires: 2.0.0.',
					],
				],
			],
			[
				[
					'skins' => [
						'FakeSkin' => '*',
					],
				],
				[
					[
						'missing' => 'FakeSkin',
						'type' => 'missing-skins',
						'msg' => 'FakeExtension requires FakeSkin to be installed.',
					],
				],
			],
			[
				[
					'platform' => [
						'ext-phpLoadedExtension' => '*',
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ext-phpMissingExtension' => '*',
					],
				],
				[
					[
						'missing' => 'phpMissingExtension',
						'type' => 'missing-phpExtension',
						// phpcs:ignore Generic.Files.LineLength.TooLong
						'msg' => 'FakeExtension requires phpMissingExtension PHP extension to be installed.',
					],
				],
			],
			[
				[
					'platform' => [
						'ability-presentAbility' => true,
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ability-presentAbilityWithMessage' => true,
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ability-presentAbility' => false,
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ability-presentAbilityWithMessage' => false,
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ability-missingAbility' => true,
					],
				],
				[
					[
						'missing' => 'missingAbility',
						'type' => 'missing-ability',
						'msg' => 'FakeExtension requires "missingAbility" ability',
					],
				],
			],
			[
				[
					'platform' => [
						'ability-missingAbilityWithMessage' => true,
					],
				],
				[
					[
						'missing' => 'missingAbilityWithMessage',
						'type' => 'missing-ability',
						// phpcs:ignore Generic.Files.LineLength.TooLong
						'msg' => 'FakeExtension requires "missingAbilityWithMessage" ability: Missing.',
					],
				],
			],
			[
				[
					'platform' => [
						'ability-missingAbility' => false,
					],
				],
				[],
			],
			[
				[
					'platform' => [
						'ability-missingAbilityWithMessage' => false,
					],
				],
				[],
			],
		];
	}

	/**
	 * Check, if a non-parsable version constraint does not throw an exception or
	 * returns any error message.
	 */
	public function testInvalidConstraint() {
		$checker = new VersionChecker( '1.0.0', '7.0.0', [] );
		$checker->setLoadedExtensionsAndSkins( [
				'FakeDependency' => [
					'version' => 'not really valid',
				],
			] );
		$this->assertEquals( [
			[
				'type' => 'invalid-version',
				'msg' => "FakeDependency does not have a valid version string.",
			],
		], $checker->checkArray( [
			'FakeExtension' => [
				'extensions' => [
					'FakeDependency' => '1.24.3',
				],
			],
		] ) );

		$checker = new VersionChecker( '1.0.0', '7.0.0', [] );
		$checker->setLoadedExtensionsAndSkins( [
				'FakeDependency' => [
					'version' => '1.24.3',
				],
			] );

		$this->expectException( UnexpectedValueException::class );
		$checker->checkArray( [
			'FakeExtension' => [
				'FakeDependency' => 'not really valid',
			],
		] );
	}

	public function provideInvalidDependency() {
		return [
			[
				[
					'FakeExtension' => [
						'platform' => [
							'undefinedPlatformDependency' => '*',
						],
					],
				],
				'undefinedPlatformDependency',
			],
			[
				[
					'FakeExtension' => [
						'platform' => [
							'phpLoadedExtension' => '*',
						],
					],
				],
				'phpLoadedExtension',
			],
			[
				[
					'FakeExtension' => [
						'platform' => [
							'ability-invalidAbility' => true,
						],
					],
				],
				'ability-invalidAbility',
			],
			[
				[
					'FakeExtension' => [
						'platform' => [
							'presentAbility' => true,
						],
					],
				],
				'presentAbility',
			],
			[
				[
					'FakeExtension' => [
						'undefinedDependencyType' => '*',
					],
				],
				'undefinedDependencyType',
			],
			// T197478
			[
				[
					'FakeExtension' => [
						'skin' => [
							'FakeSkin' => '*',
						],
					],
				],
				'skin',
			],
		];
	}

	/**
	 * @dataProvider provideInvalidDependency
	 */
	public function testInvalidDependency( $dependency, $type ) {
		$checker = new VersionChecker(
			'1.0.0',
			'7.0.0',
			[ 'phpLoadedExtension' ],
			[
				'presentAbility' => true,
				'missingAbility' => false,
			]
		);
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage( "Dependency type $type unknown in FakeExtension" );
		$checker->checkArray( $dependency );
	}

	public function testInvalidPhpExtensionConstraint() {
		$checker = new VersionChecker( '1.0.0', '7.0.0', [ 'phpLoadedExtension' ] );
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage(
			'Version constraints for PHP extensions are not supported in FakeExtension' );
		$checker->checkArray( [
			'FakeExtension' => [
				'platform' => [
					'ext-phpLoadedExtension' => '1.0.0',
				],
			],
		] );
	}

	/**
	 * @dataProvider provideInvalidAbilityType
	 */
	public function testInvalidAbilityType( $value ) {
		$checker = new VersionChecker( '1.0.0', '7.0.0', [], [ 'presentAbility' => true ] );
		$this->expectException( UnexpectedValueException::class );
		$this->expectExceptionMessage(
			'Only booleans are allowed to to indicate the presence of abilities in FakeExtension' );
		$checker->checkArray( [
			'FakeExtension' => [
				'platform' => [
					'ability-presentAbility' => $value,
				],
			],
		] );
	}

	public function provideInvalidAbilityType() {
		return [
			[ null ],
			[ 1 ],
			[ '1' ],
		];
	}

}
