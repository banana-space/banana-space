<?php

namespace Flow\Tests\Data;

use Flow\Data\Index\FeatureIndex;
use Flow\Data\Index\TopKIndex;
use Flow\Data\Index\UniqueFeatureIndex;
use Flow\Tests\FlowTestCase;

/**
 * @covers \Flow\Data\Index\FeatureIndex
 * @covers \Flow\Data\Index\TopKIndex
 * @covers \Flow\Data\Index\UniqueFeatureIndex
 *
 * @group Flow
 */
class IndexTest extends FlowTestCase {

	public function testShallow() {
		global $wgFlowCacheVersion;
		$cache = $this->getCache();

		// fake ObjectMapper that doesn't roundtrip to- & fromStorageRow
		$mapper = $this->getMockBuilder( \Flow\Data\Mapper\BasicObjectMapper::class )
			->disableOriginalConstructor()
			->getMock();
		$mapper->expects( $this->any() )
			->method( 'normalizeRow' )
			->will( $this->returnArgument( 0 ) );

		// As we are only testing the cached result, storage should never be called
		// not sure how to test that
		$storage = $this->createMock( \Flow\Data\ObjectStorage::class );

		$unique = new UniqueFeatureIndex(
			$cache, $storage, $mapper, 'unique',
			[ 'id' ]
		);

		$secondary = new TopKIndex(
			$cache, $storage, $mapper, 'secondary',
			[ 'name' ], // keys indexed in this array
			[
				'shallow' => $unique,
				'sort' => 'id',
			]
		);

		$db = FeatureIndex::cachedDbId();
		$v = $wgFlowCacheVersion;
		$cache->set( "global:unique:$db:" . md5( '1' ) . ":$v", [ [ 'id' => 1, 'name' => 'foo', 'other' => 'ppp' ] ] );
		$cache->set( "global:unique:$db:" . md5( '2' ) . ":$v", [ [ 'id' => 2, 'name' => 'foo', 'other' => 'qqq' ] ] );
		$cache->set( "global:unique:$db:" . md5( '3' ) . ":$v", [ [ 'id' => 3, 'name' => 'baz', 'other' => 'lll' ] ] );

		$cache->set( "global:secondary:$db:" . md5( 'foo' ) . ":$v", [ [ 'id' => 1 ], [ 'id' => 2 ] ] );
		$cache->set( "global:secondary:$db:" . md5( 'baz' ) . ":$v", [ [ 'id' => 3 ] ] );

		$expect = [
			[ 'id' => 1, 'name' => 'foo', 'other' => 'ppp', ],
			[ 'id' => 2, 'name' => 'foo', 'other' => 'qqq', ],
		];
		$this->assertEquals( $expect, $secondary->find( [ 'name' => 'foo' ] ) );

		$expect = [
			[ 'id' => 3, 'name' => 'baz', 'other' => 'lll' ],
		];
		$this->assertEquals( $expect, $secondary->find( [ 'name' => 'baz' ] ) );
	}

	public function testCompositeShallow() {
		global $wgFlowCacheVersion;
		$cache = $this->getCache();
		$storage = $this->createMock( \Flow\Data\ObjectStorage::class );

		// fake ObjectMapper that doesn't roundtrip to- & fromStorageRow
		$mapper = $this->getMockBuilder( \Flow\Data\Mapper\BasicObjectMapper::class )
			->disableOriginalConstructor()
			->getMock();
		$mapper->expects( $this->any() )
			->method( 'normalizeRow' )
			->will( $this->returnArgument( 0 ) );

		$unique = new UniqueFeatureIndex(
			$cache, $storage, $mapper, 'unique',
			[ 'id', 'ot' ]
		);

		$secondary = new TopKIndex(
			$cache, $storage, $mapper, 'secondary',
			[ 'name' ], // keys indexed in this array
			[
				'shallow' => $unique,
				'sort' => 'id',
			]
		);

		// remember: unique index still stores an array of results to be consistent with other indexes
		// even though, due to uniqueness, there is only one value per set of keys
		$db = FeatureIndex::cachedDbId();
		$v = $wgFlowCacheVersion;
		$cache->set( "global:unique:$db:" . md5( '1:9' ) . ":$v", [ [ 'id' => 1, 'ot' => 9, 'name' => 'foo' ] ] );
		$cache->set( "global:unique:$db:" . md5( '1:8' ) . ":$v", [ [ 'id' => 1, 'ot' => 8, 'name' => 'foo' ] ] );
		$cache->set( "global:unique:$db:" . md5( '3:7' ) . ":$v", [ [ 'id' => 3, 'ot' => 7, 'name' => 'baz' ] ] );

		$cache->set( "global:secondary:$db:" . md5( 'foo' ) . ":$v", [
			[ 'id' => 1, 'ot' => 9 ],
			[ 'id' => 1, 'ot' => 8 ],
		] );
		$cache->set( "global:secondary:$db:" . md5( 'baz' ) . ":$v", [
			[ 'id' => 3, 'ot' => 7 ],
		] );

		$expect = [
			[ 'id' => 1, 'ot' => 9, 'name' => 'foo' ],
			[ 'id' => 1, 'ot' => 8, 'name' => 'foo' ],
		];
		$this->assertEquals( $expect, $secondary->find( [ 'name' => 'foo' ] ) );

		$expect = [
			[ 'id' => 3, 'ot' => 7, 'name' => 'baz' ],
		];
		$this->assertEquals( $expect, $secondary->find( [ 'name' => 'baz' ] ) );
	}
}
