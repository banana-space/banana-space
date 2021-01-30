<?php

namespace Flow\Tests\Data\Listener;

use Flow\Container;
use Flow\Data\Listener\RecentChangesListener;
use Flow\Model\PostRevision;
use Flow\Model\Workflow;
use Title;
use User;

/**
 * @covers \Flow\Data\Listener\AbstractListener
 * @covers \Flow\Data\Listener\RecentChangesListener
 *
 * @group Flow
 */
class RecentChangesListenerTest extends \MediaWikiTestCase {

	public function somethingProvider() {
		return [
			[
				'Reply recent change goes to the topic',
				NS_TOPIC,
				function ( $workflow, $user ) {
					$first = PostRevision::createTopicPost( $workflow, $user, 'blah blah' );
					return $first->reply( $workflow, $user, 'fofofo', 'wikitext' );
				},
			],
		];
	}

	/**
	 * @dataProvider somethingProvider
	 */
	public function testSomething( $message, $expect, $init ) {
		$actions = Container::get( 'flow_actions' );
		$usernames = $this->getMockBuilder( \Flow\Repository\UserNameBatch::class )
			->disableOriginalConstructor()
			->getMock();
		$rcFactory = $this->getMockBuilder( \Flow\Data\Utils\RecentChangeFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$ircFormatter = $this->getMockBuilder( \Flow\Formatter\IRCLineUrlFormatter::class )
			->disableOriginalConstructor()
			->getMock();

		$rc = new RecentChangesListener( $actions, $usernames, $rcFactory, $ircFormatter );
		$change = $this->createMock( \RecentChange::class );
		$rcFactory->expects( $this->once() )
			->method( 'newFromRow' )
			->will( $this->returnCallback( function ( $obj ) use ( &$ref, $change ) {
				$ref = $obj;
				return $change;
			} ) );

		$title = Title::newMainPage();
		$user = User::newFromName( '127.0.0.1', false );
		$workflow = Workflow::create( 'topic', $title );

		$revision = $init( $workflow, $user );

		$rc->onAfterInsert(
			$revision,
			[ 'rev_user_id' => 0, 'rev_user_ip' => '127.0.0.1' ],
			[ 'workflow' => $workflow ]
		);
		$this->assertNotNull( $ref );
		$this->assertEquals( $expect, $ref->rc_namespace, $message );
	}
}
