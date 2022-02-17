<?php

use CirrusSearch\Api\ProfilesDump;
use CirrusSearch\Profile\StaticProfileOverride;

/**
 * @covers \CirrusSearch\Api\ProfilesDump
 */
class ProfilesDumpTest extends \CirrusSearch\CirrusIntegrationTestCase {
	/**
	 * @throws MWException
	 */
	public function test() {
		$request = new FauxRequest( [] );
		$context = new RequestContext();
		$context->setRequest( $request );
		$main = new ApiMain( $context );

		$api = new ProfilesDump( $main, 'name', '', $this->getService() );
		$api->execute();
		$this->assertEquals( [ 'profile3', 'profile4' ],
			$api->getResult()->getResultData( [ 'profiles', 'my_type1', 'repositories', 'my_repo2' ] ) );
		$this->assertEquals( 'profile1',
			$api->getResult()->getResultData( [ 'profiles', 'my_type1', 'contexts', 'context1', 'code_default' ] ) );
		$this->assertEquals( 'profile2',
			$api->getResult()->getResultData( [ 'profiles', 'my_type1', 'contexts', 'context1', 'actual_default' ] ) );

		$this->assertEquals( 'profile5',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'code_default' ] ) );
		$this->assertEquals( 'profile6',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'actual_default' ] ) );

		$this->assertEquals( 'uriParam',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'overriders', 0, 'type' ] ) );
		$this->assertEquals( 'userPreference',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'overriders', 1, 'type' ] ) );
		$this->assertEquals( 'contextual',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'overriders', 2, 'type' ] ) );
		$this->assertEquals( 'config',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'overriders', 3, 'type' ] ) );
		$this->assertEquals( 'static',
			$api->getResult()->getResultData( [ 'profiles', 'my_type2', 'contexts', 'context1', 'overriders', 4, 'type' ] ) );
	}

	public function testVerbose() {
		$request = new FauxRequest( [ 'verbose' => 1 ] );
		$context = new RequestContext();
		$context->setRequest( $request );
		$main = new ApiMain( $context );
		$api = new ProfilesDump( $main, 'name', '', $this->getService() );
		$api->execute();
		$this->assertEquals( [ 'profile1' => [ '1' => '2' ], 'profile2' => [ '2' => '3' ] ],
		$api->getResult()->getResultData( [ 'profiles', 'my_type1', 'repositories', 'my_repo1' ] ) );
	}

	private function getService() {
		$service = new \CirrusSearch\Profile\SearchProfileService( new FauxRequest() );
		$service->registerArrayRepository( 'my_type1', 'my_repo1', [
			'profile1' => [ '1' => '2' ],
			'profile2' => [ '2' => '3' ],
		] );
		$service->registerArrayRepository( 'my_type1', 'my_repo2', [
			'profile3' => [ '3' => '4' ],
			'profile4' => [ '4' => '5' ],
		] );
		$service->registerArrayRepository( 'my_type2', 'my_repo3', [
			'profile5' => [ '5' => '6' ],
			'profile6' => [ '6' => '7' ],
		] );
		$service->registerDefaultProfile( 'my_type1', 'context1', 'profile1' );
		$service->registerDefaultProfile( 'my_type1', 'context2', 'profile1' );
		$service->registerDefaultProfile( 'my_type2', 'context1', 'profile5' );
		$service->registerDefaultProfile( 'my_type2', 'context2', 'profile6' );

		$service->registerConfigOverride( 'my_type1', 'context1',
			new \CirrusSearch\HashSearchConfig( [ 'myConfigEntry' => 'profile2' ] ),
			'myConfigEntry' );

		$service->registerConfigOverride( 'my_type2', 'context1',
			new \CirrusSearch\HashSearchConfig( [ 'myConfigEntry' => 'profile6' ] ),
			'myConfigEntry' );
		$service->registerUriParamOverride( 'my_type2', 'context1', 'uriParam' );
		$service->registerUserPrefOverride( 'my_type2', 'context1', 'userPref' );
		$service->registerContextualOverride( 'my_type2', 'context1', 'foo-{lang}', [ '{lang}' => 'language' ] );
		$service->registerProfileOverride( 'my_type2', 'context1', new StaticProfileOverride( 'static', 9999 ) );
		return $service;
	}
}
