<?php

namespace Flow\Tests;

use EchoNotificationController;
use ExtensionRegistry;
use Flow\Container;
use Flow\Model\UserTuple;
use Flow\Model\UUID;
use MediaWiki\MediaWikiServices;
use User;

/**
 * @covers \Flow\Notifications\FlowPresentationModel
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\Notifications\NewTopicPresentationModel
 * @covers \Flow\Notifications\PostReplyPresentationModel
 *
 * @group Flow
 * @group Database
 */
class NotifiedUsersTest extends PostRevisionTestCase {
	protected $tablesUsed = [
		'echo_event',
		'echo_notification',
		'flow_revision',
		'flow_topic_list',
		'flow_tree_node',
		'flow_tree_revision',
		'flow_workflow',
		'page',
		'revision',
		'ip_changes',
		'text',
	];

	protected function setUp() : void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			$this->markTestSkipped();
			return;
		}
	}

	/**
	 * @todo FIXME This logs an unhandled exception. (T249839)
	 *
	 * ```
	 * Flow\Exception\InvalidDataException: Invalid metadata for topic|post revision …
	 * at NotificationListener->notifyPostChange
	 * at …
	 * at NotifiedUsersTest->getTestData
	 * ```
	 *
	 * @group Broken
	 */
	public function testWatchingTopic() {
		$data = $this->getTestData();
		if ( !$data ) {
			$this->markTestSkipped();
			return;
		}

		/** @var User $user */
		$user = $data['user'];

		MediaWikiServices::getInstance()->getWatchedItemStore()->addWatchBatchForUser(
			$user,
			[
				$data['topicWorkflow']->getArticleTitle()->getSubjectPage(),
				$data['topicWorkflow']->getArticleTitle()->getTalkPage()
			]
		);
		$user->invalidateCache();

		$events = $data['notificationController']->notifyPostChange( 'flow-post-reply',
			[
				'topic-workflow' => $data['topicWorkflow'],
				'title' => $data['boardWorkflow']->getOwnerTitle(),
				'user' => $data['agent'],
				'reply-to' => $data['topic'],
				'topic-title' => $data['topic'],
				'revision' => $data['post-2'],
			] );

		$this->assertNotifiedUser( $events, $user, $data['agent'] );
	}

	/**
	 * @todo FIXME This logs an unhandled exception. (T249839)
	 *
	 * ```
	 * Flow\Exception\InvalidDataException: Invalid metadata for topic|post revision …
	 * at NotificationListener->notifyPostChange
	 * at …
	 * at NotifiedUsersTest->getTestData
	 * ```
	 *
	 * @group Broken
	 */
	public function testWatchingBoard() {
		$data = $this->getTestData();
		if ( !$data ) {
			$this->markTestSkipped();
			return;
		}

		/** @var User $user */
		$user = $data['user'];

		MediaWikiServices::getInstance()->getWatchedItemStore()->addWatchBatchForUser(
			$user,
			[
				$data['boardWorkflow']->getArticleTitle()->getSubjectPage(),
				$data['boardWorkflow']->getArticleTitle()->getTalkPage()
			]
		);
		$user->invalidateCache();

		$events = $data['notificationController']->notifyNewTopic( [
			'board-workflow' => $data['boardWorkflow'],
			'topic-workflow' => $data['topicWorkflow'],
			'topic-title' => $data['topic'],
			'first-post' => $data['post'],
			'user' => $data['agent'],
		] );

		$this->assertNotifiedUser( $events, $user, $data['agent'] );
	}

	protected function assertNotifiedUser( array $events, User $notifiedUser, User $notNotifiedUser ) {
		$users = [];
		foreach ( $events as $event ) {
			$iterator = EchoNotificationController::getUsersToNotifyForEvent( $event );
			foreach ( $iterator as $user ) {
				$users[] = $user;
			}
		}

		// convert user objects back into user ids to simplify assertion
		$users = array_map(
			function ( $user ) {
				return $user->getId();
			},
			$users
		);

		$this->assertContains( $notifiedUser->getId(), $users );
		$this->assertNotContains( $notNotifiedUser->getId(), $users );
	}

	/**
	 * @return array [
	 *   'boardWorkflow' => \Flow\Model\Workflow
	 *   'topicWorkflow' => \Flow\Model\Workflow
	 *   'post' => \Flow\Model\PostRevision
	 *   'post-2' => \Flow\Model\PostRevision
	 *   'topic' => \Flow\Model\PostRevision
	 *   'user' => \User
	 *   'agent' => \User
	 *   'notificationController' => \Flow\NotificationController
	 *  ]
	 */
	protected function getTestData() {
		$user = User::newFromName( 'Flow Test User' );
		$user->addToDatabase();
		$agent = User::newFromName( 'Flow Test Agent' );
		$agent->addToDatabase();

		$tuple = UserTuple::newFromUser( $agent );
		$topicTitle = $this->generateObject( [
			'rev_user_wiki' => $tuple->wiki,
			'rev_user_id' => $tuple->id,
			'rev_user_ip' => $tuple->ip,

			'rev_flags' => 'wikitext',
			'rev_content' => 'some content',
		] );

		/*
		 * We don't really *have* to store everything for this test. We could
		 * just work off of the object we have here.
		 * However, our current CI setup forces us to not use Parsoid & write
		 * wikitext instead.
		 * Notifications need to convert the content to HTML & in order to do so
		 * have to know the title of the board the post is on (to resolve links
		 * & stuff).
		 * For those combined reasons, we'll store everything.
		 */
		$this->store( $topicTitle );

		$boardWorkflow = $topicTitle->getCollection()->getBoardWorkflow();
		$topicWorkflow = $topicTitle->getCollection()->getWorkflow();
		$firstPost = $topicTitle->reply( $topicWorkflow, $agent, 'ffuts dna ylper', 'wikitext' );
		$this->store( $firstPost );

		/*
		 * Generation of the 2nd post will be a bit hacky: there's some code to ensure
		 * that first replies are ignored when sending notifications, and that is done
		 * by checking timestamps. We want our tests to run fast so I won't sleep for
		 * a second. Instead, I'll just inject the new timestamp (which is 2 seconds
		 * in the future) in there.
		 */
		$secondPost = $topicTitle->reply( $topicWorkflow, $agent, 'lorem ipsum', 'wikitext' );
		$newId = UUID::getComparisonUUID( (int)$secondPost->getPostId()->getTimestamp( TS_UNIX ) + 2 );
		$reflection = new \ReflectionProperty( $secondPost, 'postId' );
		$reflection->setAccessible( true );
		$reflection->setValue( $secondPost, $newId );
		$this->store( $secondPost );

		return [
			'boardWorkflow' => $boardWorkflow,
			'topicWorkflow' => $topicWorkflow,
			'post' => $firstPost,
			'post-2' => $secondPost,
			'topic' => $topicTitle,
			'user' => $user,
			'agent' => $agent,
			'notificationController' => Container::get( 'controller.notification' ),
		];
	}
}
