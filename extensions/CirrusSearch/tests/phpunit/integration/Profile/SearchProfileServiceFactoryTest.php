<?php

namespace CirrusSearch\Profile;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\InterwikiResolverFactory;
use EmptyBagOStuff;

/**
 * @group CirrusSearch
 * @covers \CirrusSearch\Profile\SearchProfileServiceFactory
 */
class SearchProfileServiceFactoryTest extends CirrusIntegrationTestCase {

	/**
	 * @dataProvider provideTypeAndContext
	 * @param string $type
	 * @param string $context
	 * @throws \Exception
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public function testSaneDefaults( $type, $context ) {
		// Even with an empty search config we should have default profiles
		// available
		$factory = $this->getFactory();
		$service = $factory->loadService( new HashSearchConfig( [] ) );
		$this->assertNotNull( $service->getProfileName( $type, $context ) );
		$this->assertNotNull( $service->loadProfile( $type, $context ) );
	}

	public static function provideTypeAndContext() {
		return [
			'rescore fulltext' => [ SearchProfileService::RESCORE, SearchProfileService::CONTEXT_DEFAULT ],
			'rescore prefix' => [ SearchProfileService::RESCORE, SearchProfileService::CONTEXT_PREFIXSEARCH ],
			'similarity prefix' => [ SearchProfileService::SIMILARITY, SearchProfileService::CONTEXT_DEFAULT ],
			'crossproject block order' => [ SearchProfileService::CROSS_PROJECT_BLOCK_SCORER, SearchProfileService::CONTEXT_DEFAULT ],
			'completion' => [ SearchProfileService::COMPLETION, SearchProfileService::CONTEXT_DEFAULT ],
			'fallback' => [ SearchProfileService::FALLBACKS, SearchProfileService::CONTEXT_DEFAULT ],
			'fulltext query builder' => [ SearchProfileService::FT_QUERY_BUILDER, SearchProfileService::CONTEXT_DEFAULT ],
		];
	}

	/**
	 * @dataProvider provideOverrides
	 * @param string $type
	 * @param string $context
	 * @param string $overrideType
	 * @param string $overrideKey
	 * @param array[] $profiles
	 * @param bool $forceMwGlobals
	 * @throws \Exception
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public function testOverrides( $type, $context, $overrideType, $overrideKey, $profiles ) {
		$factory = $this->getFactory();
		$this::setTemporaryHook( 'CirrusSearchProfileService',
			function ( SearchProfileService $service ) use ( $type, $profiles ) {
				$service->registerArrayRepository( $type, 'unit_test', $profiles );
			}
		);

		$profileName = key( $profiles );

		// Don't use TestUser it may have been polluted with default config from other tests.
		$user = $this->getMockBuilder( \User::class )
			->getMock();
		$user->expects( $this->any() )
			->method( 'getOption' )
			->will( $this->returnValue( null ) );

		if ( $overrideType === 'uri' ) {
			$request = new \FauxRequest( [ $overrideKey => $profileName ] );
			$config = new HashSearchConfig( [] );
		} elseif ( $overrideType === 'pref' ) {
			$request = new \FauxRequest();
			$user = $this->getTestUser( [ 'cirrus-profiles', $type, $context, $overrideKey ] )->getUser();
			$user->setOption( $overrideKey, $profileName );
			$config = new HashSearchConfig( [] );
		} elseif ( $overrideType === 'config' ) {
			$request = new \FauxRequest();
			$config = new HashSearchConfig( [ $overrideKey => $profileName ] );
		} else {
			throw new \RuntimeException( "Unknown override type $overrideType" );
		}
		$service = $factory->loadService( $config, $request, $user, true );
		$this->assertEquals( key( $profiles ), $service->getProfileName( $type, $context ) );
		$this->assertEquals( reset( $profiles ), $service->loadProfile( $type, $context ) );
	}

	public static function provideOverrides() {
		return [
			'rescore fulltext by uri' => [
				SearchProfileService::RESCORE, SearchProfileService::CONTEXT_DEFAULT,
				'uri', 'cirrusRescoreProfile', [ 'unittest' => [] ]
			],
			'rescore fulltext by config' => [
				SearchProfileService::RESCORE, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchRescoreProfile', [ 'unittest' => [] ]
			],
			'rescore prefix by uri' => [
				SearchProfileService::RESCORE, SearchProfileService::CONTEXT_PREFIXSEARCH,
				'uri', 'cirrusRescoreProfile', [ 'unittest' => [] ]
			],
			'rescore prefix by config' => [
				SearchProfileService::RESCORE, SearchProfileService::CONTEXT_PREFIXSEARCH,
				'config', 'CirrusSearchPrefixSearchRescoreProfile', [ 'unittest' => [] ]
			],
			'similarity by config' => [
				SearchProfileService::SIMILARITY, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchSimilarityProfile', [ 'unittest' => [] ]
			],
			'crossproject block scorer by config' => [
				SearchProfileService::CROSS_PROJECT_BLOCK_SCORER, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchCrossProjectOrder', [ 'unittest' => [] ]
			],
			'completion by user pref' => [
				SearchProfileService::COMPLETION, SearchProfileService::CONTEXT_DEFAULT,
				'pref', 'cirrussearch-pref-completion-profile', [ 'unittest' => [] ]
			],
			'completion by config' => [
				SearchProfileService::COMPLETION, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchCompletionSettings', [ 'unittest' => [] ],
			],
			'fallbacks by config' => [
				SearchProfileService::FALLBACKS, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchFallbackProfile', [ 'unittest' => [] ],
			],
			'fallbacks by uri' => [
				SearchProfileService::FALLBACKS, SearchProfileService::CONTEXT_DEFAULT,
				'uri', 'cirrusFallbackProfile', [ 'unittest' => [] ],
			],
			'fulltext query builder by uri' => [
				SearchProfileService::FT_QUERY_BUILDER, SearchProfileService::CONTEXT_DEFAULT,
				'uri', 'cirrusFTQBProfile', [ 'unittest' => [] ],
			],
			'fulltext query builder by config' => [
				SearchProfileService::FT_QUERY_BUILDER, SearchProfileService::CONTEXT_DEFAULT,
				'config', 'CirrusSearchFullTextQueryBuilderProfile', [ 'unittest' => [] ],
			],
		];
	}

	/**
	 * @dataProvider provideExposedProfileType
	 * @throws \Exception
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public function testExportedProfilesWithI18N( $type, array $must_have ) {
		$factory = $this->getFactory();
		$service = $factory->loadService( new HashSearchConfig( [] ) );
		$profiles = $service->listExposedProfiles( $type );

		$seen = [];
		foreach ( $profiles as $name => $profile ) {
			$this->assertArrayHasKey( 'i18n_msg', $profile, "Profile $name in $type has i18n_msg key" );
			$this->assertTrue( wfMessage( $profile['i18n_msg'] )->exists(),
				"Profile $name in $type has i18n message set" );
			$seen[] = $name;
		}
		$missing = array_diff( $must_have, $seen );
		$this->assertEmpty( $missing, "Profiles of type $type must include all must_have profiles" );
	}

	public static function provideExposedProfileType() {
		return [
			'rescore' => [
				SearchProfileService::RESCORE,
				[ 'classic', 'empty', 'classic_noboostlinks', 'wsum_inclinks',
				  'wsum_inclinks_pv', 'popular_inclinks_pv', 'popular_inclinks' ]
			],
			'completion' => [
				SearchProfileService::COMPLETION,
				[ 'classic', 'fuzzy', 'normal', 'strict' ]
			]
		];
	}

	public function provideTestInterwikiOverrides() {
		$baseConfig = [
			'CirrusSearchInterwikiSources' => [
				'my' => 'mywiki',
			],
		];
		return [
			'rescore' => [
				$baseConfig + [
					'CirrusSearchCrossProjectProfiles' => [
						'my' => [
							'rescore' => 'overridden'
						]
					]
				],
				[
					'_wikiID' => 'mywiki',
					'CirrusSearchRescoreProfiles' => [
						'default' => [],
						'overridden' => [ 'INTERWIKI' ]
					],
					'CirrusSearchRescoreProfile' => 'default',
				],
				SearchProfileService::RESCORE,
				'overridden'
			],
			'ftbuilder' => [
				$baseConfig + [
					'CirrusSearchCrossProjectProfiles' => [
						'my' => [
							'ftbuilder' => 'overridden'
						]
					]
				],
				[
					'_wikiID' => 'mywiki',
					'CirrusSearchFullTextQueryBuilderProfiles' => [
						'default' => [],
						'overridden' => [ 'INTERWIKI' ]
					],
					'CirrusSearchFullTextQueryBuilderProfile' => 'test',
				],
				SearchProfileService::FT_QUERY_BUILDER,
				'overridden'
			]
		];
	}

	/**
	 * @dataProvider provideTestInterwikiOverrides
	 * @param array $hostWikiConfig
	 * @param array $targetWikiConfig
	 * @param string $profileType
	 * @param string $defaultProfile
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public function testInterwikiOverrides( array $hostWikiConfig, array $targetWikiConfig, $profileType, $overridden ) {
		$factory = $this->getFactory( $hostWikiConfig );
		$service = $factory->loadService( new HashSearchConfig( $targetWikiConfig ) );
		$this->assertEquals( $overridden,
			$service->getProfileName( $profileType, SearchProfileService::CONTEXT_DEFAULT ) );
		$this->assertEquals( [ 'INTERWIKI' ], $service->loadProfile( $profileType ) );
	}

	private function getFactory( array $hostWikiConfig = [] ) {
		$config = new HashSearchConfig( $hostWikiConfig );
		$resolver = ( new InterwikiResolverFactory() )->getResolver( $config );
		return new SearchProfileServiceFactory( $resolver, $config, new EmptyBagOStuff() );
	}
}
