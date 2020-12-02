<?php

use Wikimedia\Rdbms\DBConnRef;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * @covers Wikimedia\Rdbms\DBConnRef
 */
class DBConnRefTest extends PHPUnit\Framework\TestCase {

	use MediaWikiCoversValidator;

	/**
	 * @return ILoadBalancer
	 */
	private function getLoadBalancerMock() {
		$lb = $this->createMock( ILoadBalancer::class );

		$lb->method( 'getConnection' )->willReturnCallback(
			function () {
				return $this->getDatabaseMock();
			}
		);

		$lb->method( 'getConnectionRef' )->willReturnCallback(
			function () use ( $lb ) {
				return $this->getDBConnRef( $lb );
			}
		);

		return $lb;
	}

	/**
	 * @return IDatabase
	 */
	private function getDatabaseMock() {
		$db = $this->getMockBuilder( IDatabase::class )
			->disableOriginalConstructor()
			->getMock();

		$open = true;
		$db->method( 'select' )->willReturnCallback( function () use ( &$open ) {
			if ( !$open ) {
				throw new LogicException( "Not open" );
			}

			return new FakeResultWrapper( [] );
		} );
		$db->method( 'close' )->willReturnCallback( function () use ( &$open ) {
			$open = false;

			return true;
		} );
		$db->method( 'isOpen' )->willReturnCallback( function () use ( &$open ) {
			return $open;
		} );

		return $db;
	}

	/**
	 * @return IDatabase
	 */
	private function getDBConnRef( ILoadBalancer $lb = null ) {
		$lb = $lb ?: $this->getLoadBalancerMock();
		return new DBConnRef( $lb, $this->getDatabaseMock(), DB_MASTER );
	}

	public function testConstruct() {
		$lb = $this->getLoadBalancerMock();
		$ref = new DBConnRef( $lb, $this->getDatabaseMock(), DB_MASTER );

		$this->assertInstanceOf( IResultWrapper::class, $ref->select( 'whatever', '*' ) );
	}

	public function testConstruct_params() {
		$lb = $this->createMock( ILoadBalancer::class );

		$lb->expects( $this->once() )
			->method( 'getConnection' )
			->with( DB_MASTER, [ 'test' ], 'dummy', ILoadBalancer::CONN_TRX_AUTOCOMMIT )
			->willReturnCallback(
				function () {
					return $this->getDatabaseMock();
				}
			);

		$ref = new DBConnRef(
			$lb,
			[ DB_MASTER, [ 'test' ], 'dummy', ILoadBalancer::CONN_TRX_AUTOCOMMIT ],
			DB_MASTER
		);

		$this->assertInstanceOf( IResultWrapper::class, $ref->select( 'whatever', '*' ) );
		$this->assertEquals( DB_MASTER, $ref->getReferenceRole() );

		$ref2 = new DBConnRef(
			$lb,
			[ DB_MASTER, [ 'test' ], 'dummy', ILoadBalancer::CONN_TRX_AUTOCOMMIT ],
			DB_REPLICA
		);
		$this->assertEquals( DB_REPLICA, $ref2->getReferenceRole() );
	}

	public function testDestruct() {
		$lb = $this->getLoadBalancerMock();

		$lb->expects( $this->once() )
			->method( 'reuseConnection' );

		$this->innerMethodForTestDestruct( $lb );
	}

	private function innerMethodForTestDestruct( ILoadBalancer $lb ) {
		$ref = $lb->getConnectionRef( DB_REPLICA );

		$this->assertInstanceOf( IResultWrapper::class, $ref->select( 'whatever', '*' ) );
	}

	public function testConstruct_failure() {
		$this->expectException( InvalidArgumentException::class );

		$lb = $this->getLoadBalancerMock();
		new DBConnRef( $lb, 17, DB_REPLICA ); // bad constructor argument
	}

	/**
	 * @covers Wikimedia\Rdbms\DBConnRef::getDomainId
	 */
	public function testGetDomainID() {
		$lb = $this->createMock( ILoadBalancer::class );

		// getDomainID is optimized to not create a connection
		$lb->expects( $this->never() )
			->method( 'getConnection' );

		$ref = new DBConnRef( $lb, [ DB_REPLICA, [], 'dummy', 0 ], DB_REPLICA );

		$this->assertSame( 'dummy', $ref->getDomainID() );
	}

	/**
	 * @covers Wikimedia\Rdbms\DBConnRef::select
	 */
	public function testSelect() {
		// select should get passed through normally
		$ref = $this->getDBConnRef();
		$this->assertInstanceOf( IResultWrapper::class, $ref->select( 'whatever', '*' ) );
	}

	public function testToString() {
		$ref = $this->getDBConnRef();
		$this->assertIsString( $ref->__toString() );

		$lb = $this->getLoadBalancerMock();
		$ref = new DBConnRef( $lb, [ DB_MASTER, [], 'test', 0 ], DB_MASTER );
		$this->assertIsString( $ref->__toString() );
	}

	/**
	 * @covers Wikimedia\Rdbms\DBConnRef::close
	 */
	public function testClose() {
		$lb = $this->getLoadBalancerMock();
		$ref = new DBConnRef( $lb, [ DB_REPLICA, [], 'dummy', 0 ], DB_MASTER );
		$this->expectException( \Wikimedia\Rdbms\DBUnexpectedError::class );
		$ref->close();
	}

	/**
	 * @covers Wikimedia\Rdbms\DBConnRef::getReferenceRole
	 */
	public function testGetReferenceRole() {
		$lb = $this->getLoadBalancerMock();
		$ref = new DBConnRef( $lb, [ DB_REPLICA, [], 'dummy', 0 ], DB_REPLICA );
		$this->assertSame( DB_REPLICA, $ref->getReferenceRole() );

		$ref = new DBConnRef( $lb, [ DB_MASTER, [], 'dummy', 0 ], DB_MASTER );
		$this->assertSame( DB_MASTER, $ref->getReferenceRole() );

		$ref = new DBConnRef( $lb, [ 1, [], 'dummy', 0 ], DB_REPLICA );
		$this->assertSame( DB_REPLICA, $ref->getReferenceRole() );

		$ref = new DBConnRef( $lb, [ 0, [], 'dummy', 0 ], DB_MASTER );
		$this->assertSame( DB_MASTER, $ref->getReferenceRole() );
	}

	/**
	 * @covers Wikimedia\Rdbms\DBConnRef::getReferenceRole
	 * @dataProvider provideRoleExceptions
	 */
	public function testRoleExceptions( $method, $args ) {
		$lb = $this->getLoadBalancerMock();
		$ref = new DBConnRef( $lb, [ DB_REPLICA, [], 'dummy', 0 ], DB_REPLICA );
		$this->expectException( Wikimedia\Rdbms\DBReadOnlyRoleError::class );
		$ref->$method( ...$args );
	}

	public function provideRoleExceptions() {
		return [
			[ 'insert', [ 'table', [ 'a' => 1 ] ] ],
			[ 'update', [ 'table', [ 'a' => 1 ], [ 'a' => 2 ] ] ],
			[ 'delete', [ 'table', [ 'a' => 1 ] ] ],
			[ 'replace', [ 'table', [ 'a' ], [ 'a' => 1 ] ] ],
			[ 'upsert', [ 'table', [ 'a' => 1 ], [ 'a' ], [ 'a = a + 1' ] ] ],
			[ 'lock', [ 'k', 'method' ] ],
			[ 'unlock', [ 'k', 'method' ] ],
			[ 'getScopedLockAndFlush', [ 'k', 'method', 1 ] ]
		];
	}
}
