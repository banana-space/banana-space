<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusTestCase;

/**
 * @covers \CirrusSearch\Profile\StaticProfileOverride
 */
class StaticProfileOverrideTest extends CirrusTestCase {

	public function test() {
		$profile = new StaticProfileOverride( 'name', 123 );
		$this->assertSame( 'name', $profile->getOverriddenName( [] ) );
		$this->assertSame( 123, $profile->priority() );
		$this->assertEquals(
			[
				'type' => 'static',
				'priority' => 123,
				'value' => 'name',
			],
			$profile->explain()
		);
	}
}
