<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\ContextualProfileOverride
 */
class ContextualProfileOverrideTest extends CirrusTestCase {
	public function testNormalUseCase() {
		$override = new ContextualProfileOverride(
			'my-profile-{lang}',
			[ '{lang}' => 'language' ] );

		$this->assertNull( $override->getOverriddenName( [] ) );
		$this->assertNull( $override->getOverriddenName( [ 'zork' => 'slay kobold' ] ) );
		$this->assertSame( 'my-profile-es', $override->getOverriddenName( [ 'language' => 'es' ] ) );
		$this->assertSame( 'my-profile-es', $override->getOverriddenName( [
			'language' => 'es',
			'zork' => 'slay kobold',
		] ) );
		$this->assertEquals(
			[
				'type' => 'contextual',
				'priority' => StaticProfileOverride::CONTEXTUAL_PRIO,
				'template' => 'my-profile-{lang}'
			],
			$override->explain()
		);
	}

	public function testCustomPrio() {
		$priority = 123;
		$override = new ContextualProfileOverride( 'foo', [ 'bar' => 'baz' ], $priority );
		$this->assertSame( $priority, $override->priority() );
	}
}
