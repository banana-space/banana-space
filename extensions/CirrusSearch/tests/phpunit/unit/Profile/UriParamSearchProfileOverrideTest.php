<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusTestCase;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\UriParamSearchProfileOverride
 */
class UriParamSearchProfileOverrideTest extends CirrusTestCase {

	public function testNormalUseCase() {
		$request = new \FauxRequest( [ 'paramOverride' => 'overridden' ] );
		$override = new UriParamSearchProfileOverride( $request, 'paramOverride' );
		$this->assertSame( SearchProfileOverride::URI_PARAM_PRIO, $override->priority() );
		$this->assertSame( 'overridden', $override->getOverriddenName( [] ) );
		$this->assertEquals(
			[
				'type' => 'uriParam',
				'priority' => StaticProfileOverride::URI_PARAM_PRIO,
				'uriParam' => 'paramOverride'
			],
			$override->explain()
		);
	}

	public function testWithoutUriParam() {
		$request = new \FauxRequest( [ 'paramOverride' => 'overridden' ] );
		$override = new UriParamSearchProfileOverride( $request, 'paramOverride2' );
		$this->assertNull( $override->getOverriddenName( [] ) );
	}

	public function testCustomPrio() {
		$request = new \FauxRequest( [ 'paramOverride' => 'overridden' ] );
		$override = new UriParamSearchProfileOverride( $request, 'paramOverride2', 123 );
		$this->assertSame( 123, $override->priority() );
	}
}
