<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\CompletionSearchProfileRepository
 */
class CompletionSearchProfileRepositoryTest extends CirrusIntegrationTestCase {

	public function test() {
		// Without subphrases the normal-subphrases is hidden
		$profiles = [
			'normal' => [
				'fst' => [
					'plain-normal' => [
						'field' => 'suggest',
					],
					'plain-stop-normal' => [
						'field' => 'suggest-stop'
					],
				],
			],
			'normal-subphrases' => [
				'fst' => [
					'plain-normal' => [
						'field' => 'suggest',
					],
					'plain-stop-normal' => [
						'field' => 'suggest-stop',
					],
					'plain-subphrase' => [
						'field' => 'suggest-subphrases',
					],
				],
			],
		];
		$configArray = [
			'CirrusSearchCompletionSuggesterSubphrases' => [
				'use' => false,
			],
			'profiles' => $profiles,
		];
		$config = new HashSearchConfig( $configArray );
		$repo = CompletionSearchProfileRepository::fromConfig( 'my_type', 'my_repo', 'profiles', $config );
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_repo', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'normal' ) );
		$this->assertFalse( $repo->hasProfile( 'normal-subphrases' ) );
		$this->assertNotNull( $repo->getProfile( 'normal' ) );
		$this->assertNull( $repo->getProfile( 'normal-subphrases' ) );
		$this->assertArrayEquals( [ 'normal' => $profiles['normal'] ], $repo->listExposedProfiles() );

		$configArray = [
			'CirrusSearchCompletionSuggesterSubphrases' => [
				'use' => true,
			],
			'profiles' => $profiles,
		];

		// Without subphrases the normal-subphrases is visible
		$config = new HashSearchConfig( $configArray );
		$repo = CompletionSearchProfileRepository::fromConfig( 'my_type', 'my_repo', 'profiles', $config );
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_repo', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'normal' ) );
		$this->assertTrue( $repo->hasProfile( 'normal-subphrases' ) );
		$this->assertNotNull( $repo->getProfile( 'normal' ) );
		$this->assertNotNull( $repo->getProfile( 'normal-subphrases' ) );
		$this->assertArrayEquals( $profiles, $repo->listExposedProfiles() );
	}

	public function testFromFile() {
		$configArray = [
			'CirrusSearchCompletionSuggesterSubphrases' => [
				'use' => false,
			],
		];

		// Without subphrases the normal-subphrases is visible
		$config = new HashSearchConfig( $configArray );
		$repo = CompletionSearchProfileRepository::fromFile( 'my_type', 'my_repo',
			__DIR__ . '/../../../../profiles/SuggestProfiles.config.php', $config );
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_repo', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'fuzzy' ) );
	}
}
