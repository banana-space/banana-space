<?php

namespace CirrusSearch\Sanity;

use CirrusSearch\Assignment\ClusterAssignment;
use CirrusSearch\CirrusTestCase;
use CirrusSearch\Job\DeletePages;
use CirrusSearch\Job\LinksUpdate;
use JobQueueGroup;

/**
 * @covers \CirrusSearch\Sanity\AllClustersQueueingRemediator
 */
class AllClustersQueuingRemediatorTest extends CirrusTestCase {

	public function testCanSendOptimizedJob() {
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$clusters = [ 'one', 'two' ];
		$clusterAssigment = $this->createMock( ClusterAssignment::class );
		$clusterAssigment->expects( $this->once() )
			->method( 'getWritableClusters' )
			->willReturn( $clusters );
		$allClustersRemediator = new AllClustersQueueingRemediator( $clusterAssigment, $jobQueueGroup );
		$this->assertTrue( $allClustersRemediator->canSendOptimizedJob( $clusters ) );
		$this->assertTrue( $allClustersRemediator->canSendOptimizedJob( [ 'one', 'two' ] ) );
		$this->assertTrue( $allClustersRemediator->canSendOptimizedJob( [ 'two', 'one' ] ) );
		$this->assertFalse( $allClustersRemediator->canSendOptimizedJob( [ 'one' ] ) );
		$this->assertFalse( $allClustersRemediator->canSendOptimizedJob( [ 'one', 'two', 'three' ] ) );
		$this->assertFalse( $allClustersRemediator->canSendOptimizedJob( [] ) );
	}

	public function testDelegation() {
		$title = \Title::makeTitle( NS_MAIN, 'Test' );
		$wp = $this->createMock( \WikiPage::class );
		$wp->method( 'getTitle' )->willReturn( $title );
		$wrongIndex = 'wrongType';
		$docId = '123';
		$linksUpdateJob = new LinksUpdate( $title, [
			'addedLinks' => [],
			'removedLinks' => [],
			'cluster' => null,
		] );

		$deletePageJob = new DeletePages( $title, [
			'docId' => $docId,
			'cluster' => null,
		] );

		$wrongIndexDelete = new DeletePages( $title, [
			'indexType' => $wrongIndex,
			'docId' => $docId,
			'cluster' => null,
		] );
		$jobQueueGroup = $this->createMock( JobQueueGroup::class );
		$clusterAssigment = $this->createMock( ClusterAssignment::class );
		$clusterAssigment->expects( $this->once() )
			->method( 'getWritableClusters' )
			->willReturn( [ 'one', 'two' ] );
		$jobQueueGroup->expects( $this->exactly( 7 ) )
			->method( 'push' )
			->withConsecutive(
				[ $this->equalTo( $linksUpdateJob ) ], // oldDocument
				[ $this->equalTo( $linksUpdateJob ) ], // pageNotIndex
				[ $this->equalTo( $linksUpdateJob ) ], // redirectInIndex
				[ $this->equalTo( $linksUpdateJob ) ], // oldVersionInIndex
				[ $this->equalTo( $wrongIndexDelete ) ], // pageInWrongIndex step1
				[ $this->equalTo( $linksUpdateJob ) ], // pageInWrongIndex step2
				[ $this->equalTo( $deletePageJob ) ] // ghostPageInIndex
			);

		$allClustersRemediator = new AllClustersQueueingRemediator( $clusterAssigment, $jobQueueGroup );
		$allClustersRemediator->oldDocument( $wp );
		$allClustersRemediator->pageNotInIndex( $wp );
		$allClustersRemediator->redirectInIndex( $wp );
		$allClustersRemediator->oldVersionInIndex( $docId, $wp, $wrongIndex );
		$allClustersRemediator->pageInWrongIndex( $docId, $wp, $wrongIndex );
		$allClustersRemediator->ghostPageInIndex( $docId, $title );
	}
}
