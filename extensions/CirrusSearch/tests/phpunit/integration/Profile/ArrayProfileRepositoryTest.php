<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\ArrayProfileRepository
 */
class ArrayProfileRepositoryTest extends CirrusIntegrationTestCase {

	public function testSimpleArray() {
		$profiles = [
			'prof1' => [],
			'prof2' => [],
		];
		$repo = ArrayProfileRepository::fromArray( 'my_type', 'my_name',  $profiles );
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_name', $repo->repositoryName() );
		$this->assertTrue( $repo->hasProfile( 'prof1' ) );
		$this->assertFalse( $repo->hasProfile( 'prof3' ) );
		$this->assertArrayEquals( $profiles, $repo->listExposedProfiles() );
		$this->assertEquals( [], $repo->getProfile( 'prof1' ) );
		$this->assertNull( $repo->getProfile( 'prof3' ) );
	}

	public function testLazyLoaded() {
		$loaded = false;
		$profiles = [
			'prof1' => [],
			'prof2' => [],
		];
		$loader = function () use ( &$loaded, $profiles ) {
			$loaded = true;
			return $profiles;
		};
		$repo = ArrayProfileRepository::lazyLoaded( 'my_type', 'my_name',  $loader );
		$this->assertEquals( 'my_type', $repo->repositoryType() );
		$this->assertEquals( 'my_name', $repo->repositoryName() );
		$this->assertFalse( $loaded, "accessing simple repo metadata should not load the array" );
		$this->assertTrue( $repo->hasProfile( 'prof1' ) );
		$this->assertFalse( $repo->hasProfile( 'prof3' ) );
		$this->assertArrayEquals( $profiles, $repo->listExposedProfiles() );
		$this->assertEquals( [], $repo->getProfile( 'prof1' ) );
		$this->assertNull( $repo->getProfile( 'prof3' ) );
		$this->assertTrue( $loaded );
	}

	public function testBadCallback() {
		$loader = function () {
			return 'meh';
		};
		$repo = ArrayProfileRepository::lazyLoaded( 'my_type', 'my_name',  $loader );
		$this->expectException( SearchProfileException::class );
		$repo->hasProfile( 'meh' );
	}
}
