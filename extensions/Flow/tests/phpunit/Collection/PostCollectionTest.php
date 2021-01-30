<?php

namespace Flow\Tests\Collection;

use Flow\Collection\PostCollection;
use Flow\Model\PostRevision;
use Flow\Tests\PostRevisionTestCase;

/**
 * @covers \Flow\Collection\AbstractCollection
 * @covers \Flow\Collection\LocalCacheAbstractCollection
 * @covers \Flow\Collection\PostCollection
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 *
 * @group Flow
 * @group Database
 */
class PostCollectionTest extends PostRevisionTestCase {
	/**
	 * @var array
	 */
	protected $tablesUsed = [ 'flow_revision', 'flow_tree_revision' ];

	protected function setUp() : void {
		parent::setUp();

		// recent changes isn't fully setup here, just skip it
		$this->clearExtraLifecycleHandlers();

		// generate a post with multiple revisions
		$revision = $this->generateObject( [
			'rev_content' => 'first revision',
		] );
		$this->store( $revision );

		$revision = $this->generateObject( [
			'rev_content' => 'second revision',
			'rev_change_type' => 'edit-post',
			'rev_parent_id' => $revision->getRevisionId()->getBinary(),
			'tree_rev_descendant_id' => $revision->getPostId()->getBinary(),
			'rev_type_id' => $revision->getPostId()->getBinary(),
		] );
		$this->store( $revision );

		$revision = $this->generateObject( [
			'rev_content' => 'third revision',
			'rev_change_type' => 'edit-post',
			'rev_parent_id' => $revision->getRevisionId()->getBinary(),
			'tree_rev_descendant_id' => $revision->getPostId()->getBinary(),
			'rev_type_id' => $revision->getPostId()->getBinary(),
		] );
		$this->store( $revision );
	}

	public function testGetCollection() {
		$revision = $this->revisions[0];
		$collection = $revision->getCollection();
		$this->assertInstanceOf( PostCollection::class, $collection );
	}

	public function testNewFromId() {
		$uuidPost = $this->revisions[0]->getPostId();
		$collection = PostCollection::newFromId( $uuidPost );
		$this->assertInstanceOf( PostCollection::class, $collection );
	}

	public function testNewFromRevision() {
		$revision = $this->revisions[0];
		$collection = PostCollection::newFromRevision( $revision );
		$this->assertInstanceOf( PostCollection::class, $collection );
	}

	public function testGetRevision() {
		$collection = $this->revisions[0]->getCollection();

		$expected = $this->revisions[1];
		$revision = $collection->getRevision( $expected->getRevisionId() );
		$this->assertInstanceOf( PostRevision::class, $revision );
		$this->assertTrue( $expected->getRevisionId()->equals( $revision->getRevisionId() ) );
	}

	/**
	 * Flaky test causing random failures, see T210204
	 *
	 * @group Broken
	 */
	public function testGetLastRevision() {
		$collection = $this->revisions[0]->getCollection();

		$expected = end( $this->revisions );
		$revision = $collection->getLastRevision();

		$this->assertInstanceOf( PostRevision::class, $revision );
		$this->assertTrue( $expected->getRevisionId()->equals( $revision->getRevisionId() ) );
	}

	/**
	 * Flaky test causing random failures, see T207173
	 *
	 * @group Broken
	 */
	public function testGetFirstRevision() {
		$collection = $this->revisions[1]->getCollection();

		$expected = reset( $this->revisions );
		$revision = $collection->getFirstRevision();

		$this->assertInstanceOf( PostRevision::class, $revision );
		$this->assertTrue( $expected->getRevisionId()->equals( $revision->getRevisionId() ) );
	}

	/**
	 * @group Broken
	 */
	public function testGetNextRevision() {
		$start = $this->revisions[0];
		$collection = $start->getCollection();

		$expected = $this->revisions[1];
		$revision = $collection->getNextRevision( $start );

		$this->assertInstanceOf( PostRevision::class, $revision );
		$this->assertTrue( $expected->getRevisionId()->equals( $revision->getRevisionId() ) );
	}

	public function testGetPrevRevision() {
		$start = $this->revisions[1];
		$collection = $start->getCollection();

		$expected = $this->revisions[0];
		$revision = $collection->getPrevRevision( $start );

		$this->assertInstanceOf( PostRevision::class, $revision );
		$this->assertTrue( $expected->getRevisionId()->equals( $revision->getRevisionId() ) );
	}

	public function testGetAllRevision() {
		$collection = $this->revisions[1]->getCollection();

		$revisions = $collection->getAllRevisions();

		$this->assertEquals( count( $this->revisions ), count( $revisions ) );
	}
}
