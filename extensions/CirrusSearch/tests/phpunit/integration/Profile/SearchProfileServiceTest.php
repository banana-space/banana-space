<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Dispatch\BasicSearchQueryRoute;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\SearchProfileService
 */
class SearchProfileServiceTest extends CirrusIntegrationTestCase {

	public function testSimpleSingleRepo() {
		$profiles = [
			'prof1' => [ 'inprof1' => [] ],
			'prof2' => [ 'inprof2' => [] ],
			'prof3' => [ 'inprof3' => [] ]
		];
		$service = new SearchProfileService();
		$service->registerArrayRepository( 'type', 'name', $profiles );
		$this->assertCount( 1, $service->listProfileRepositories( 'type' ) );
		$this->assertEquals( 'name', $service->listProfileRepositories( 'type' )['name']->repositoryName() );
		$this->simpleAssertions( $service );
	}

	public function testSimpleMultiRepo() {
		$profiles = [
			'prof1' => [ 'inprof1' => [] ],
			'prof2' => [ 'inprof2' => [] ],
		];
		$config = new HashSearchConfig( [ 'ConfigProfiles' => [
			'prof2' => [ 'hidden' => [] ],
			'prof3' => [ 'inprof3' => [] ],
		] ] );
		$service = new SearchProfileService();
		$service->registerArrayRepository( 'type', 'name', $profiles );
		$service->registerRepository( new ConfigProfileRepository( 'type', 'config_repo', 'ConfigProfiles', $config ) );
		$this->simpleAssertions( $service );
	}

	private function simpleAssertions( SearchProfileService $service ) {
		$service->registerDefaultProfile( 'type', 'context1', 'prof1' );
		$service->registerDefaultProfile( 'type', 'context2', 'prof2' );
		$this->assertContains( 'type', $service->listProfileTypes() );
		$this->assertArrayHasKey( 'context1', $service->listProfileContexts( 'type' ) );
		$this->assertArrayHasKey( 'context2', $service->listProfileContexts( 'type' ) );
		$this->assertEquals( $service->listProfileContexts( 'type' )['context1'], 'prof1' );

		try {
			$service->registerDefaultProfile( 'type', 'context2', 'prof2' );
			$this->fail( "Expected exception: " . SearchProfileException::class );
		} catch ( SearchProfileException $e ) {
		}
		$service->freeze();
		$this->assertEquals( 'prof1', $service->getProfileName( 'type', 'context1' ) );
		$this->assertEquals( 'prof2', $service->getProfileName( 'type', 'context2' ) );
		$expectedProfiles = [
			'prof1' => [ 'inprof1' => [] ],
			'prof2' => [ 'inprof2' => [] ],
			'prof3' => [ 'inprof3' => [] ]
		];
		$this->assertArrayEquals( $expectedProfiles, $service->listExposedProfiles( 'type' ) );

		$this->assertArrayEquals( [ 'inprof1' => [] ], $service->loadProfile( 'type', 'context1' ) );
		$this->assertArrayEquals( [ 'inprof2' => [] ], $service->loadProfile( 'type', 'context2' ) );
		$this->assertArrayEquals( [ 'inprof3' => [] ], $service->loadProfile( 'type', 'unused', 'prof3' ) );
		try {
			$service->getProfileName( 'type', 'context3' );
			$this->fail( "Expected exception: " . SearchProfileException::class );
		} catch ( SearchProfileException $e ) {
		}

		try {
			$service->loadProfileByName( 'type', 'unknown' );
			$this->fail( "Expected exception: " . SearchProfileException::class );
		} catch ( SearchProfileException $e ) {
		}
		$this->assertNull( $service->loadProfileByName( 'type', 'unknown', false ) );
	}

	public function testOverrides() {
		$request = new \FauxRequest( [ 'profile' => 'prof3' ] );
		$user = $this->getTestUser()->getUser();
		$user->setOption( 'profile-pref', 'prof4' );
		$config = new HashSearchConfig( [ 'ConfigDefault' => 'prof2' ] );

		$profiles = [
			'prof1' => [ 'inprof1' => [] ],
			'prof2' => [ 'inprof2' => [] ],
			'prof3' => [ 'inprof3' => [] ],
			'prof4' => [ 'inprof4' => [] ],
			'prof5' => [ 'inprof5' => [] ],
		];

		$service = new SearchProfileService( $request, $user );
		// prepare multiple profile contexts so that we test different kind of overrides
		// with all_override containing all of them
		$service->registerArrayRepository( 'type', 'unit_test', $profiles );
		$service->registerDefaultProfile( 'type', 'no_override', 'prof1' );
		$service->registerDefaultProfile( 'type', 'config_override', 'prof1' );
		$service->registerDefaultProfile( 'type', 'uri_param_override', 'prof1' );
		$service->registerDefaultProfile( 'type', 'user_pref_override', 'prof1' );
		$service->registerDefaultProfile( 'type', 'contextual_override', 'prof1' );
		$service->registerDefaultProfile( 'type', 'all_override', 'prof1' );

		$service->registerConfigOverride( 'type', [ 'config_override', 'all_override' ], $config, 'ConfigDefault' );
		$service->registerUriParamOverride( 'type', [ 'uri_param_override', 'all_override' ], 'profile' );
		$service->registerUserPrefOverride( 'type', [ 'user_pref_override', 'all_override' ], 'profile-pref' );
		$service->registerContextualOverride( 'type', [ 'contextual_override', 'all_override' ], 'prof{n}', [ '{n}' => 'n' ] );

		$this->assertCount( 1, $service->listProfileOverrides( 'type', 'user_pref_override' ) );
		$this->assertInstanceOf( UserPrefSearchProfileOverride::class,
			$service->listProfileOverrides( 'type', 'user_pref_override' )[0] );
		$service->freeze();
		$this->assertEquals( 'prof1', $service->getProfileName( 'type', 'no_override' ) );
		$this->assertEquals( 'prof2', $service->getProfileName( 'type', 'config_override' ) );
		$this->assertEquals( 'prof3', $service->getProfileName( 'type', 'uri_param_override' ) );
		$this->assertEquals( 'prof4', $service->getProfileName( 'type', 'user_pref_override' ) );
		$this->assertEquals( 'prof5', $service->getProfileName( 'type', 'contextual_override', [ 'n' => 5 ] ) );
		// URI param wins it has lower prio
		$this->assertEquals( 'prof3', $service->getProfileName( 'type', 'all_override' ) );
	}

	public function testFrozen() {
		$service = new SearchProfileService();
		$service->freeze();
		$this->expectException( SearchProfileException::class );
		$service->registerArrayRepository( 'type', 'name', [] );
	}

	public function testRegisterRoute() {
		$service = new SearchProfileService();
		$service->registerSearchQueryRoute( new BasicSearchQueryRoute( SearchQuery::SEARCH_TEXT,
			[ 0 ], [], 'foo', 0.5 ) );
		$service->registerFTSearchQueryRoute( 'bar', 0.4, [ 1 ] );
		$service->freeze();
		$dispatch = $service->getDispatchService();
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 0 ] )
			->build();
		$route = $dispatch->bestRoute( $query );
		$this->assertEquals( 'foo', $route->getProfileContext() );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 1 ] )
			->build();
		$route = $dispatch->bestRoute( $query );
		$this->assertEquals( 'bar', $route->getProfileContext() );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder( new HashSearchConfig( [] ), 'foo', $this->namespacePrefixParser() )
			->setInitialNamespaces( [ 2 ] )
			->build();
		$route = $dispatch->bestRoute( $query );
		$this->assertEquals( SearchProfileService::CONTEXT_DEFAULT, $route->getProfileContext() );
	}
}
