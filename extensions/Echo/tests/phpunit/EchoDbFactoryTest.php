<?php

use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * @covers \MWEchoDbFactory
 */
class MWEchoDbFactoryTest extends MediaWikiTestCase {

	public function testNewFromDefault() {
		$db = MWEchoDbFactory::newFromDefault();
		$this->assertInstanceOf( MWEchoDbFactory::class, $db );

		return $db;
	}

	/**
	 * @depends testNewFromDefault
	 */
	public function testGetEchoDb( MWEchoDbFactory $db ) {
		$this->assertInstanceOf( IDatabase::class, $db->getEchoDb( DB_MASTER ) );
		$this->assertInstanceOf( IDatabase::class, $db->getEchoDb( DB_REPLICA ) );
	}

	/**
	 * @depends testNewFromDefault
	 */
	public function testGetLB( MWEchoDbFactory $db ) {
		$reflection = new ReflectionClass( MWEchoDbFactory::class );
		$method = $reflection->getMethod( 'getLB' );
		$method->setAccessible( true );
		$this->assertInstanceOf( ILoadBalancer::class, $method->invoke( $db ) );
	}

}
