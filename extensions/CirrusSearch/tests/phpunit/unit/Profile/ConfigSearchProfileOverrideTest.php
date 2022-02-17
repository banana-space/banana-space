<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\ConfigSearchProfileOverride
 */
class ConfigSearchProfileOverrideTest extends CirrusTestCase {

	public function testNormalUseCase() {
		$config = new \HashConfig( [ 'paramOverride' => 'overridden' ] );
		$override = new ConfigSearchProfileOverride( $config, 'paramOverride' );
		$this->assertEquals( SearchProfileOverride::CONFIG_PRIO, $override->priority() );
		$this->assertEquals( 'overridden', $override->getOverriddenName( [] ) );
		$this->assertEquals(
			[
				'type' => 'config',
				'priority' => SearchProfileOverride::CONFIG_PRIO,
				'configEntry' => 'paramOverride',
				'value' => 'overridden'
			],
			$override->explain()
		);
	}

	public function testWithoutConfigParam() {
		$config = new \HashConfig( [ 'paramOverride' => 'overridden' ] );
		$override = new ConfigSearchProfileOverride( $config, 'paramOverride2' );
		$this->assertNull( $override->getOverriddenName( [] ) );
	}

	public function testCustomPrio() {
		$config = new \HashConfig( [ 'paramOverride' => 'overridden' ] );
		$override = new ConfigSearchProfileOverride( $config, 'paramOverride2', 123 );
		$this->assertEquals( 123, $override->priority() );
	}
}
