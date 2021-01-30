<?php

/**
 * @covers \EchoAbstractMapper
 */
class EchoAbstractMapperTest extends MediaWikiUnitTestCase {

	/**
	 * @return array [ 'mapper' => EchoAbstractMapper, 'property' => ReflectionProperty ]
	 */
	public function testAttachListener() {
		$mapper = new EchoAbstractMapperStub();
		$mapper->attachListener( 'testMethod', 'key_a', function () {
		} );

		$class = new ReflectionClass( EchoAbstractMapperStub::class );
		$property = $class->getProperty( 'listeners' );
		$property->setAccessible( true );
		$listeners = $property->getValue( $mapper );

		$this->assertArrayHasKey( 'testMethod', $listeners );
		$this->assertArrayHasKey( 'key_a', $listeners['testMethod'] );
		$this->assertTrue( is_callable( $listeners['testMethod']['key_a'] ) );

		return [ 'mapper' => $mapper, 'property' => $property ];
	}

	public function testAttachListenerWithException() {
		$mapper = new EchoAbstractMapperStub();
		$this->expectException( MWException::class );
		$mapper->attachListener( 'nonExistingMethod', 'key_a', function () {
		} );
	}

	/**
	 * @depends testAttachListener
	 */
	public function testGetMethodListeners( $data ) {
		/** @var EchoAbstractMapper $mapper */
		$mapper = $data['mapper'];

		$listeners = $mapper->getMethodListeners( 'testMethod' );
		$this->assertArrayHasKey( 'key_a', $listeners );
		$this->assertTrue( is_callable( $listeners['key_a'] ) );
	}

	/**
	 * @depends testAttachListener
	 */
	public function testGetMethodListenersWithException( $data ) {
		/** @var EchoAbstractMapper $mapper */
		$mapper = $data['mapper'];

		$this->expectException( MWException::class );
		$mapper->getMethodListeners( 'nonExistingMethod' );
	}

	/**
	 * @depends testAttachListener
	 */
	public function testDetachListener( $data ) {
		/** @var EchoAbstractMapper $mapper */
		$mapper = $data['mapper'];
		/** @var ReflectionProperty $property */
		$property = $data['property'];

		$mapper->detachListener( 'testMethod', 'key_a' );
		$listeners = $property->getValue( $mapper );
		$this->assertArrayHasKey( 'testMethod', $listeners );
		$this->assertTrue( !isset( $listeners['testMethod']['key_a'] ) );
	}

}
