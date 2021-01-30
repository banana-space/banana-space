<?php

namespace Flow\Tests\Data;

use Flow\Data\Mapper\CachingObjectMapper;
use Flow\Tests\FlowTestCase;

/**
 * @covers \Flow\Data\Mapper\CachingObjectMapper
 *
 * @group Flow
 */
class CachingObjectMapperTest extends FlowTestCase {

	public function testReturnsSameObject() {
		$mapper = $this->createMapper();
		$object = $mapper->fromStorageRow( [ 'id' => 1 ] );
		$this->assertSame( $object, $mapper->fromStorageRow( [ 'id' => 1 ] ) );
	}

	public function testAllowsNullPkOnPut() {
		$this->createMapper()->toStorageRow( (object)[ 'id' => null ] );
		$this->assertTrue( true );
	}

	protected function createMapper() {
		$toStorageRow = function ( $object ) {
			return (array)$object;
		};
		$fromStorageRow = function ( array $row, $object ) {
			if ( $object ) {
				return (object)( $row + (array)$object );
			}
			return (object)$row;
		};
		return new CachingObjectMapper( $toStorageRow, $fromStorageRow, [ 'id' ] );
	}
}
