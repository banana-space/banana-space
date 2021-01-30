<?php

namespace Flow\Tests\Data;

use Flow\Repository\UserName\UserNameQuery;
use Flow\Repository\UserNameBatch;
use Flow\Tests\FlowTestCase;

/**
 * @covers \Flow\Repository\UserNameBatch
 *
 * @group Database
 * @group Flow
 */
class UserNameBatchTest extends FlowTestCase {

	public function testAllowsAddingNames() {
		$batch = new UserNameBatch( $this->createUncalledQuery() );
		$batch->add( 'fakewiki', 42, 'Whale' );
		$this->assertEquals( 'Whale', $batch->get( 'fakewiki', 42 ) );
	}

	public static function acceptsStringOrIntIdsProvider() {
		return [
			[ 42, 42 ],
			[ 42, '42' ],
			[ '42', 42 ],
			[ '42', '42' ],
		];
	}

	/**
	 * @dataProvider acceptsStringOrIntIdsProvider
	 */
	public function testAcceptsStringOrIntIds( $a, $b ) {
		$batch = new UserNameBatch( $this->createUncalledQuery() );
		$batch->add( 'fakewiki', $a, 'Whale' );
		$this->assertEquals( 'Whale', $batch->get( 'fakewiki', $b ) );
	}

	public function testQueueUsernames() {
		$query = $this->createMock( UserNameQuery::class );
		$query->expects( $this->once() )
			->method( 'execute' )
			->with( 'fakewiki', [ 12, 27, 18 ] );

		$batch = new UserNameBatch( $query );
		$batch->add( 'fakewiki', 12 );
		$batch->add( 'fakewiki', '27' );
		$batch->add( 'fakewiki', 18 );
		$batch->resolve( 'fakewiki' );
	}

	public function testMissingAsFalse() {
		$query = $this->createMock( UserNameQuery::class );
		$query->expects( $this->once() )
			->method( 'execute' )
			->with( 'fakewiki', [ 42 ] );
		$batch = new UserNameBatch( $query );

		$this->assertFalse( $batch->get( 'fakewiki', 42 ) );
	}

	public function testPartialMissingAsFalse() {
		$query = $this->createMock( UserNameQuery::class );
		$query->expects( $this->once() )
			->method( 'execute' )
			->with( 'fakewiki', [ 610, 408 ] )
			->will( $this->returnValue( [
				(object)[ 'user_id' => '408', 'user_name' => 'chuck' ]
			] ) );

		$batch = new UserNameBatch( $query );
		$batch->add( 'fakewiki', 610 );
		$batch->add( 'fakewiki', 408 );

		$this->assertFalse( $batch->get( 'fakewiki', 610 ) );
	}

	/**
	 * Create a mock UserNameQuery that must not be called
	 * @return UserNameQuery
	 */
	protected function createUncalledQuery() {
		$query = $this->createMock( UserNameQuery::class );
		$query->expects( $this->never() )
			->method( 'execute' );

		return $query;
	}
}
