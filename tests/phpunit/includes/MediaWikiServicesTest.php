<?php

use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\HookContainer\StaticHookRegistry;
use MediaWiki\MediaWikiServices;
use Wikimedia\Services\DestructibleService;
use Wikimedia\Services\SalvageableService;
use Wikimedia\Services\ServiceDisabledException;

/**
 * @covers MediaWiki\MediaWikiServices
 *
 * @group MediaWiki
 */
class MediaWikiServicesTest extends MediaWikiIntegrationTestCase {
	private $deprecatedServices = [];

	public static $mockServiceWiring = [];

	/**
	 * @return Config
	 */
	private function newTestConfig() {
		$globalConfig = new GlobalVarConfig();

		$testConfig = new HashConfig();
		$testConfig->set( 'ServiceWiringFiles', $globalConfig->get( 'ServiceWiringFiles' ) );
		$testConfig->set( 'ConfigRegistry', $globalConfig->get( 'ConfigRegistry' ) );

		return $testConfig;
	}

	/**
	 * @return MediaWikiServices
	 */
	private function newMediaWikiServices() {
		$config = $this->newTestConfig();
		$instance = new MediaWikiServices( $config );

		// Load the default wiring from the specified files.
		$wiringFiles = $config->get( 'ServiceWiringFiles' );
		$instance->loadWiringFiles( $wiringFiles );

		return $instance;
	}

	private function newConfigWithMockWiring() {
		$config = new HashConfig;
		$config->set( 'ServiceWiringFiles', [ __DIR__ . '/MockServiceWiring.php' ] );
		return $config;
	}

	public function testGetInstance() {
		$services = MediaWikiServices::getInstance();
		$this->assertInstanceOf( MediaWikiServices::class, $services );
	}

	public function testForceGlobalInstance() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );

		$this->assertInstanceOf( MediaWikiServices::class, $oldServices );
		$this->assertNotSame( $oldServices, $newServices );

		$theServices = MediaWikiServices::getInstance();
		$this->assertSame( $theServices, $newServices );

		MediaWikiServices::forceGlobalInstance( $oldServices );

		$theServices = MediaWikiServices::getInstance();
		$this->assertSame( $theServices, $oldServices );
	}

	public function testResetGlobalInstance() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );

		$service1 = $this->createMock( SalvageableService::class );
		$service1->expects( $this->never() )
			->method( 'salvage' );

		$newServices->defineService(
			'Test',
			function () use ( $service1 ) {
				return $service1;
			}
		);

		// force instantiation
		$newServices->getService( 'Test' );

		MediaWikiServices::resetGlobalInstance( $this->newTestConfig() );
		$theServices = MediaWikiServices::getInstance();

		$this->assertSame(
			$service1,
			$theServices->getService( 'Test' ),
			'service definition should survive reset'
		);

		$this->assertNotSame( $theServices, $newServices );
		$this->assertNotSame( $theServices, $oldServices );

		MediaWikiServices::forceGlobalInstance( $oldServices );
	}

	public function testResetGlobalInstance_quick() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );

		$service1 = $this->createMock( SalvageableService::class );
		$service1->expects( $this->never() )
			->method( 'salvage' );

		$service2 = $this->createMock( SalvageableService::class );
		$service2->expects( $this->once() )
			->method( 'salvage' )
			->with( $service1 );

		// sequence of values the instantiator will return
		$instantiatorReturnValues = [
			$service1,
			$service2,
		];

		$newServices->defineService(
			'Test',
			function () use ( &$instantiatorReturnValues ) {
				return array_shift( $instantiatorReturnValues );
			}
		);

		// force instantiation
		$newServices->getService( 'Test' );

		MediaWikiServices::resetGlobalInstance( $this->newTestConfig(), 'quick' );
		$theServices = MediaWikiServices::getInstance();

		$this->assertSame( $service2, $theServices->getService( 'Test' ) );

		$this->assertNotSame( $theServices, $newServices );
		$this->assertNotSame( $theServices, $oldServices );

		MediaWikiServices::forceGlobalInstance( $oldServices );
	}

	public function testResetGlobalInstance_T263925() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );
		self::$mockServiceWiring = [
			'HookContainer' => function ( MediaWikiServices $services ) {
				return new HookContainer(
					new StaticHookRegistry(
						[],
						[
							'MediaWikiServices' => [
								[
									'handler' => [
										'name' => 'test',
										'factory' => function () {
											return new class implements MediaWikiServicesHook {
												public function onMediaWikiServices( $services ) {
												}
											};
										}
									],
									'deprecated' => false,
									'extensionPath' => 'path'
								],
							]
						],
						[]
					),
					$this->createSimpleObjectFactory()
				);
			}
		];
		$newServices->redefineService( 'HookContainer',
			self::$mockServiceWiring['HookContainer'] );

		$newServices->getHookContainer()->run( 'MediaWikiServices', [ $newServices ] );
		MediaWikiServices::resetGlobalInstance( $this->newConfigWithMockWiring(), 'quick' );
		$this->assertTrue( true, 'expected no exception from above' );

		self::$mockServiceWiring = [];
		MediaWikiServices::forceGlobalInstance( $oldServices );
	}

	public function testDisableStorageBackend() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );

		$lbFactory = $this->getMockBuilder( \Wikimedia\Rdbms\LBFactorySimple::class )
			->disableOriginalConstructor()
			->getMock();

		$newServices->redefineService(
			'DBLoadBalancerFactory',
			function () use ( $lbFactory ) {
				return $lbFactory;
			}
		);

		// force the service to become active, so we can check that it does get destroyed
		$newServices->getService( 'DBLoadBalancerFactory' );

		MediaWikiServices::disableStorageBackend(); // should destroy DBLoadBalancerFactory

		try {
			MediaWikiServices::getInstance()->getService( 'DBLoadBalancerFactory' );
			$this->fail( 'DBLoadBalancerFactory should have been disabled' );
		}
		catch ( ServiceDisabledException $ex ) {
			// ok, as expected
		} catch ( Throwable $ex ) {
			$this->fail( 'ServiceDisabledException expected, caught ' . get_class( $ex ) );
		}

		MediaWikiServices::forceGlobalInstance( $oldServices );
		$newServices->destroy();

		// No exception was thrown, avoid being risky
		$this->assertTrue( true );
	}

	public function testResetChildProcessServices() {
		$newServices = $this->newMediaWikiServices();
		$oldServices = MediaWikiServices::forceGlobalInstance( $newServices );

		$service1 = $this->createMock( DestructibleService::class );
		$service1->expects( $this->once() )
			->method( 'destroy' );

		$service2 = $this->createMock( DestructibleService::class );
		$service2->expects( $this->never() )
			->method( 'destroy' );

		// sequence of values the instantiator will return
		$instantiatorReturnValues = [
			$service1,
			$service2,
		];

		$newServices->defineService(
			'Test',
			function () use ( &$instantiatorReturnValues ) {
				return array_shift( $instantiatorReturnValues );
			}
		);

		// force the service to become active, so we can check that it does get destroyed
		$oldTestService = $newServices->getService( 'Test' );

		MediaWikiServices::resetChildProcessServices();
		$finalServices = MediaWikiServices::getInstance();

		$newTestService = $finalServices->getService( 'Test' );
		$this->assertNotSame( $oldTestService, $newTestService );

		MediaWikiServices::forceGlobalInstance( $oldServices );
	}

	public function testResetServiceForTesting() {
		$services = $this->newMediaWikiServices();
		$serviceCounter = 0;

		$services->defineService(
			'Test',
			function () use ( &$serviceCounter ) {
				$serviceCounter++;
				$service = $this->createMock( Wikimedia\Services\DestructibleService::class );
				$service->expects( $this->once() )->method( 'destroy' );
				return $service;
			}
		);

		// This should do nothing. In particular, it should not create a service instance.
		$services->resetServiceForTesting( 'Test' );
		$this->assertSame( 0, $serviceCounter, 'No service instance should be created yet.' );

		$oldInstance = $services->getService( 'Test' );
		$this->assertSame( 1, $serviceCounter, 'A service instance should exit now.' );

		// The old instance should be detached, and destroy() called.
		$services->resetServiceForTesting( 'Test' );
		$newInstance = $services->getService( 'Test' );

		$this->assertNotSame( $oldInstance, $newInstance );

		// Satisfy the expectation that destroy() is called also for the second service instance.
		$newInstance->destroy();
	}

	public function testResetServiceForTesting_noDestroy() {
		$services = $this->newMediaWikiServices();

		$services->defineService(
			'Test',
			function () {
				$service = $this->createMock( Wikimedia\Services\DestructibleService::class );
				$service->expects( $this->never() )->method( 'destroy' );
				return $service;
			}
		);

		$oldInstance = $services->getService( 'Test' );

		// The old instance should be detached, but destroy() not called.
		$services->resetServiceForTesting( 'Test', false );
		$newInstance = $services->getService( 'Test' );

		$this->assertNotSame( $oldInstance, $newInstance );
	}

	public function provideGetters() {
		$getServiceCases = $this->provideGetService();
		$getterCases = [];

		// All getters should be named just like the service, with "get" added.
		foreach ( $getServiceCases as $name => $case ) {
			if ( $name[0] === '_' ) {
				// Internal service, no getter
				continue;
			}
			list( $service, $class ) = $case;
			$getterCases[$name] = [
				'get' . $service,
				$class,
				in_array( $service, $this->deprecatedServices )
			];
		}

		return $getterCases;
	}

	/**
	 * @dataProvider provideGetters
	 */
	public function testGetters( $getter, $type, $isDeprecated = false ) {
		if ( $isDeprecated ) {
			$this->hideDeprecated( MediaWikiServices::class . "::$getter" );
		}

		// Test against the default instance, since the dummy will not know the default services.
		$services = MediaWikiServices::getInstance();
		$service = $services->$getter();
		$this->assertInstanceOf( $type, $service );
	}

	public function provideGetService() {
		global $IP;
		$serviceList = require "$IP/includes/ServiceWiring.php";
		$ret = [];
		foreach ( $serviceList as $name => $callback ) {
			$fun = new ReflectionFunction( $callback );
			if ( !$fun->hasReturnType() ) {
				throw new MWException( 'All service callbacks must have a return type defined, ' .
					"none found for $name" );
			}

			$returnType = $fun->getReturnType();
			$ret[$name] = [ $name, $returnType->getName() ];
		}
		return $ret;
	}

	/**
	 * @dataProvider provideGetService
	 */
	public function testGetService( $name, $type ) {
		// Test against the default instance, since the dummy will not know the default services.
		$services = MediaWikiServices::getInstance();

		$service = $services->getService( $name );
		$this->assertInstanceOf( $type, $service );
	}

	public function testDefaultServiceInstantiation() {
		// Check all services in the default instance, not a dummy instance!
		// Note that we instantiate all services here, including any that
		// were registered by extensions.
		$services = MediaWikiServices::getInstance();
		$names = $services->getServiceNames();

		foreach ( $names as $name ) {
			$this->assertTrue( $services->hasService( $name ) );
			$service = $services->getService( $name );
			$this->assertIsObject( $service );
		}
	}

	public function testDefaultServiceWiringServicesHaveTests() {
		global $IP;
		$testedServices = array_keys( $this->provideGetService() );
		$allServices = array_keys( require "$IP/includes/ServiceWiring.php" );
		$this->assertEquals(
			[],
			array_diff( $allServices, $testedServices ),
			'The following services have not been added to MediaWikiServicesTest::provideGetService'
		);
	}

	public function testGettersAreSorted() {
		$methods = ( new ReflectionClass( MediaWikiServices::class ) )
			->getMethods( ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC );

		$names = array_map( function ( $method ) {
			return $method->getName();
		}, $methods );
		$serviceNames = array_map( function ( $name ) {
			return "get$name";
		}, array_keys( $this->provideGetService() ) );
		$names = array_values( array_filter( $names, function ( $name ) use ( $serviceNames ) {
			return in_array( $name, $serviceNames );
		} ) );

		$sortedNames = $names;
		natcasesort( $sortedNames );

		$this->assertSame( $sortedNames, $names,
			'Please keep service getters sorted alphabetically' );
	}
}
