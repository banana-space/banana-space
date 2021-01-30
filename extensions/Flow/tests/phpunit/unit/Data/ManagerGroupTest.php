<?php

namespace Flow\Tests\Data;

use Flow\Container;
use Flow\Data\ManagerGroup;

/**
 * @covers \Flow\Data\ManagerGroup
 *
 * @group Flow
 */
class ManagerGroupTest extends \MediaWikiUnitTestCase {
	protected function mockStorage() {
		$container = new Container;
		foreach ( range( 'A', 'D' ) as $letter ) {
			$container[$letter] = $this->getMockBuilder( \Flow\Data\ObjectManager::class )
				->disableOriginalConstructor()
				->getMock();
		}

		$storage = new ManagerGroup( $container, [
			'A' => 'A',
			'B' => 'B',
			'C' => 'C',
			'D' => 'D',
			'stdClass' => 'D',
		] );

		return [ $storage, $container ];
	}

	public function testClearOnlyCallsRequestedManagers() {
		list( $storage, $container ) = $this->mockStorage();
		$container['A']->expects( $this->never() )->method( 'clear' );
		$container['B']->expects( $this->once() )->method( 'clear' );
		$container['C']->expects( $this->never() )->method( 'clear' );
		$container['D']->expects( $this->never() )->method( 'clear' );

		$storage->getStorage( 'B' );
		$storage->clear();
	}

	public function testClearCallsNoManagersWhenUnused() {
		list( $storage, $container ) = $this->mockStorage();
		$container['A']->expects( $this->never() )->method( 'clear' );
		$container['B']->expects( $this->never() )->method( 'clear' );
		$container['C']->expects( $this->never() )->method( 'clear' );
		$container['D']->expects( $this->never() )->method( 'clear' );

		$storage->clear();
	}

	public function testCachePurgeCallsAppropriateManager() {
		$object = new \stdClass;

		list( $storage, $container ) = $this->mockStorage();
		$container['A']->expects( $this->never() )->method( 'clear' );
		$container['B']->expects( $this->never() )->method( 'clear' );
		$container['C']->expects( $this->never() )->method( 'clear' );
		$container['D']->expects( $this->once() )
			->method( 'cachePurge' )
			->with( $this->identicalTo( $object ) );

		$storage->cachePurge( $object );
	}
}
