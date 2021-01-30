<?php

namespace Flow\Tests\Repository;

use Flow\Container;
use Flow\Repository\TreeRepository;
use Flow\Tests\FlowTestCase;

/**
 * @covers \Flow\Repository\TreeRepository
 *
 * @group Flow
 * @group Database
 */
class TreeRepositoryDbTest extends FlowTestCase {
	protected $tablesUsed = [ 'flow_tree_node' ];

	public function testSomething() {
		// meaningless set of ids used for repeatability
		$ids = array_map( [ \Flow\Model\UUID::class, 'create' ], [
			"s3z44zhp93j5vvc8", "s3z44zhqt7yt8220", "s46w00pmmw0otc0q",
			"s3qvc7cnor86wvb4", "s3qvc7bbcxr3f340",
			"s3gre9r27pobtg0n", "s3cdl3dfqf8brx18", "s3cdl3dhajnz43r0",
		] );

		// Use 2 repos with 2 caches, the one you insert with reads from cache
		// the other reads from db due to different cache
		$cache[] = $this->getCache();
		$cache[] = $this->getCache();
		$dbf = Container::get( 'db.factory' );
		/** @var TreeRepository[] $repo */
		$repo[] = new TreeRepository( $dbf, $cache[0] );
		$repo[] = new TreeRepository( $dbf, $cache[1] );

		// id0 as new root
		wfDebugLog( 'Flow', "\n\n************** id0 as new root ************" );
		$repo[0]->insert( $ids[0] );
		$this->assertEquals(
			[ $ids[0] ],
			$repo[0]->findRootPath( $ids[0] )
		);
		$this->assertEquals(
			[ $ids[0] ],
			$repo[1]->findRootPath( $ids[0] )
		);

		// id1 as child of id0
		wfDebugLog( 'Flow', "\n\n************** id1 as child of id0 ************" );
		$repo[0]->insert( $ids[1], $ids[0] );
		$this->assertEquals(
			[ $ids[0], $ids[1] ],
			$repo[0]->findRootPath( $ids[1] )
		);
		$this->assertEquals(
			[ $ids[0], $ids[1] ],
			$repo[1]->findRootPath( $ids[1] )
		);

		// id2 as child of id0
		wfDebugLog( 'Flow', "\n\n************** id2 as child of id0 ************" );
		$repo[0]->insert( $ids[2], $ids[0] );
		$this->assertEquals(
			[ $ids[0], $ids[2] ],
			$repo[0]->findRootPath( $ids[2] )
		);
		$this->assertEquals(
			[ $ids[0], $ids[2] ],
			$repo[1]->findRootPath( $ids[2] )
		);

		// id3 as child of id1
		wfDebugLog( 'Flow', "\n\n************** id3 as child of id1 ************" );
		$repo[0]->insert( $ids[3], $ids[1] );
		$this->assertEquals(
			[ $ids[0], $ids[1], $ids[3] ],
			$repo[0]->findRootPath( $ids[3] )
		);
		$this->assertEquals(
			[ $ids[0], $ids[1], $ids[3] ],
			$repo[1]->findRootPath( $ids[3] )
		);
	}
}
