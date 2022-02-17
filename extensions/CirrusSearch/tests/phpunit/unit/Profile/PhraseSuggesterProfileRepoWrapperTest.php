<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\PhraseSuggesterProfileRepoWrapper
 */
class PhraseSuggesterProfileRepoWrapperTest extends CirrusTestCase {

	public function testFromConfig() {
		$profiles = [
			'prof1' => [],
			'prof2' => [],
		];
		$config = $this->newHashSearchConfig( [
			'profiles' => $profiles
		] );
		$repo = PhraseSuggesterProfileRepoWrapper::fromConfig( 'phrase_suggester', 'my_name',
			'profiles', $config, $this->localServerCacheForProfileService() );
		$this->assertEquals( 'phrase_suggester', $repo->repositoryType() );
		$this->assertEquals( 'my_name', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'prof1' ) );
		$this->assertFalse( $repo->hasProfile( 'prof3' ) );
		$this->assertEquals( $profiles, $repo->listExposedProfiles() );
		$this->assertEquals( [], $repo->getProfile( 'prof1' ) );
		$this->assertNull( $repo->getProfile( 'prof3' ) );
		// TODO: test overrides by system message
	}

	public function testFromFile() {
		$repo = PhraseSuggesterProfileRepoWrapper::fromFile( 'phrase_suggester', 'my_name',
			__DIR__ . '/../../../../profiles/PhraseSuggesterProfiles.config.php', $this->localServerCacheForProfileService() );

		$this->assertEquals( 'phrase_suggester', $repo->repositoryType() );
		$this->assertEquals( 'my_name', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'default' ) );
		$this->assertFalse( $repo->hasProfile( 'not_found' ) );
		$this->assertIsArray( $repo->getProfile( 'default' ) );
		$this->assertNull( $repo->getProfile( 'not_found' ) );
	}
}
