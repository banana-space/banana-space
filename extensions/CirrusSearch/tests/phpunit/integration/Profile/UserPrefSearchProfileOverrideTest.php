<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\UserPrefSearchProfileOverride
 */
class UserPrefSearchProfileOverrideTest extends CirrusIntegrationTestCase {

	public function testNormalUseCase() {
		$override = new UserPrefSearchProfileOverride( $this->getMyTestUser(), 'test-profile-user-pref' );
		$this->assertEquals( SearchProfileOverride::USER_PREF_PRIO, $override->priority() );
		$this->assertEquals( 'overridden', $override->getOverriddenName( [] ) );
		$this->assertEquals(
			[
				'type' => 'userPreference',
				'priority' => SearchProfileOverride::USER_PREF_PRIO,
				'userPreference' => 'test-profile-user-pref'
			],
			$override->explain()
		);
	}

	public function testWithoutPref() {
		$override = new UserPrefSearchProfileOverride( $this->getMyTestUser(), 'test-profile-user-pref2' );
		$this->assertNull( $override->getOverriddenName( [] ) );
	}

	public function testCustomPrio() {
		$override = new UserPrefSearchProfileOverride( $this->getMyTestUser(), 'test-profile-user-pref', 123 );
		$this->assertEquals( 123, $override->priority() );
	}

	/**
	 * @return \User
	 */
	private function getMyTestUser() {
		$testUser = $this->getTestUser();
		$user = $testUser->getUser();
		$user->setOption( 'test-profile-user-pref', 'overridden' );
		return $user;
	}
}
