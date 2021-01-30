<?php

namespace Flow\Tests\Import;

use Flow\Container;
use Flow\Hooks;
use Flow\Import\PageImportState;
use Flow\Import\Postprocessor\ProcessorGroup;
use Flow\Import\SourceStore\NullImportSourceStore;
use Flow\Import\TalkpageImportOperation;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\TopicListEntry;
use Flow\Model\Workflow;
use Flow\Tests\Mock\MockImportHeader;
use Flow\Tests\Mock\MockImportPost;
use Flow\Tests\Mock\MockImportRevision;
use Flow\Tests\Mock\MockImportSource;
use Flow\Tests\Mock\MockImportSummary;
use Flow\Tests\Mock\MockImportTopic;
use Psr\Log\NullLogger;
use SplQueue;
use Title;
use User;

/**
 * @covers \Flow\Import\TalkpageImportOperation
 *
 * @group Flow
 * @group Database
 */
class TalkpageImportOperationTest extends \MediaWikiTestCase {
	protected $tablesUsed = [
		// importer will ensureFlowRevision(), which will insert into these core tables
		'page',
		'revision',
		'ip_changes',
		'text',
	];

	protected function setUp() : void {
		parent::setUp();

		// reset flow state, so everything ($container['permissions'])
		// uses this particular $user
		Hooks::resetFlowExtension();
		Container::reset();
		$container = Container::getContainer();
		$container['user'] = User::newFromName( '127.0.0.1', false );
	}

	/**
	 * This is a horrible test, it basically runs the whole thing
	 * and sees if it falls over.
	 */
	public function testImportDoesntCompletelyFail() {
		$workflow = Workflow::create(
			'discussion',
			Title::newFromText( 'TalkpageImportOperationTest' )
		);
		$storage = $this->getMockBuilder( \Flow\Data\ManagerGroup::class )
			->disableOriginalConstructor()
			->getMock();
		$stored = [];
		$storage->expects( $this->any() )
			->method( 'put' )
			->will( $this->returnCallback( function ( $obj ) use( &$stored ) {
				$stored[] = $obj;
			} ) );
		$storage->expects( $this->any() )
			->method( 'multiPut' )
			->will( $this->returnCallback( function ( $objs ) use( &$stored ) {
				$stored = array_merge( $stored, $objs );
			} ) );

		$now = time();
		$source = new MockImportSource(
			new MockImportHeader( [
				// header revisions
				new MockImportRevision( [ 'createdTimestamp' => $now ] ),
			] ),
			[
				new MockImportTopic(
					new MockImportSummary( [
						new MockImportRevision( [ 'createdTimestamp' => $now - 250 ] ),
					] ),
					[
						// topic title revisions
						new MockImportRevision( [ 'createdTimestamp' => $now - 1000 ] ),
					],
					[
						// replies
						new MockImportPost(
							[
								// revisions
								new MockImportRevision( [ 'createdTimestmap' => $now - 1000 ] ),
							],
							[
								// replies
								new MockImportPost(
									[
										// revisions
										new MockImportRevision( [
											'createdTimestmap' => $now - 500,
											'user' => User::newFromName( '10.0.0.2', false ),
										] ),
									],
									[
										// replies
									]
								),
							]
						),
					]
				)
			]
		);

		$occupationController = Container::get( 'occupation_controller' );
		$op = new TalkpageImportOperation(
			$source,
			$occupationController->getTalkpageManager(),
			$occupationController
		);

		$store = new NullImportSourceStore;
		$op->import( new PageImportState(
			$workflow,
			$storage,
			$store,
			new NullLogger(),
			Container::get( 'db.factory' ),
			new ProcessorGroup,
			new SplQueue
		) );

		// Count what actually came through
		$storedHeader = $storedDiscussion = $storedTopics = $storedTopicListEntry = $storedSummary = $storedPosts = 0;
		foreach ( $stored as $obj ) {
			if ( $obj instanceof Workflow ) {
				if ( $obj->getType() === 'discussion' ) {
					$this->assertSame( $workflow, $obj );
					$storedDiscussion++;
				} else {
					$alpha = $obj->getId()->getAlphadecimal();
					if ( !isset( $seenWorkflow[$alpha] ) ) {
						$seenWorkflow[$alpha] = true;
						$this->assertEquals( 'topic', $obj->getType() );
						$storedTopics++;
						$topicWorkflow = $obj;
					}
				}
			} elseif ( $obj instanceof PostSummary ) {
				$storedSummary++;
			} elseif ( $obj instanceof PostRevision ) {
				$storedPosts++;
				if ( $obj->isTopicTitle() ) {
					$topicTitle = $obj;
				}
			} elseif ( $obj instanceof TopicListEntry ) {
				$storedTopicListEntry++;
			} elseif ( $obj instanceof Header ) {
				$storedHeader++;
			} else {
				$this->fail( 'Unexpected object stored:' . get_class( $obj ) );
			}
		}

		// Verify we wrote the expected objects to storage

		$this->assertSame( 1, $storedHeader );

		$this->assertSame( 1, $storedDiscussion );
		$this->assertSame( 1, $storedTopics );
		$this->assertSame( 1, $storedTopicListEntry );
		$this->assertSame( 1, $storedSummary );
		$this->assertSame( 3, $storedPosts );

		// This total expected number of insertions should match the sum of the left assertEquals parameters above.
		$this->assertCount( 8, array_unique( array_map( 'spl_object_hash', $stored ) ) );

		// Other special cases we need to check
		$this->assertTrue(
			$topicTitle->getPostId()->equals( $topicWorkflow->getId() ),
			'Root post id must match its workflow'
		);
	}
}
