<?php

/**
 * @group Database
 * @covers \EchoUserLocator
 */
class EchoUserLocatorTest extends MediaWikiTestCase {

	protected $tablesUsed = [ 'user', 'watchlist' ];

	public function testLocateUsersWatchingTitle() {
		$title = Title::makeTitleSafe( NS_USER_TALK, 'Something_something_something' );
		$key = $title->getDBkey();

		for ( $i = 1000; $i < 1050; ++$i ) {
			$rows[] = [
				'wl_user' => $i,
				'wl_namespace' => NS_USER_TALK,
				'wl_title' => $key
			];
		}
		wfGetDB( DB_MASTER )->insert( 'watchlist', $rows, __METHOD__ );

		$event = $this->getMockBuilder( EchoEvent::class )
			->disableOriginalConstructor()
			->getMock();
		$event->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		$it = EchoUserLocator::locateUsersWatchingTitle( $event, 10 );
		$this->assertCount( 50, $it );
		// @todo assert more than one query was issued
	}

	public function locateTalkPageOwnerProvider() {
		return [
			[
				'Allows null event title',
				// expected user id's
				[],
				// event title
				null
			],

			[
				'No users selected for non-user talk namespace',
				// expected user id's
				[],
				// event title
				Title::newMainPage(),
			],

			[
				'Selects user from NS_USER_TALK',
				// callback returning expected user ids and event title.
				// required because database insert must be inside test.
				function () {
					$user = User::newFromName( 'UTUser' );
					$user->addToDatabase();

					return [
						[ $user->getId() ],
						$user->getTalkPage(),
					];
				}
			],
		];
	}

	/**
	 * @dataProvider locateTalkPageOwnerProvider
	 */
	public function testLocateTalkPageOwner( $message, $expect, Title $title = null ) {
		if ( $expect instanceof Closure ) {
			list( $expect, $title ) = $expect();
		}
		$event = $this->mockEchoEvent();
		$event->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );

		$users = EchoUserLocator::locateTalkPageOwner( $event );
		$this->assertEquals( $expect, array_keys( $users ), $message );
	}

	public function locateArticleCreatorProvider() {
		return [
			[
				'Something',
				function () {
					$user = User::newFromName( 'UTUser' );
					$user->addToDatabase();

					return [
						[ $user->getId() ],
						$user->getTalkPage(),
						$user
					];
				}
			],
		];
	}

	/**
	 * @dataProvider locateArticleCreatorProvider
	 */
	public function testLocateArticleCreator( $message, $initialize ) {
		list( $expect, $title, $user ) = $initialize();
		WikiPage::factory( $title )->doEditContent(
			/* $content = */ ContentHandler::makeContent( 'content', $title ),
			/* $summary = */ 'summary',
			/* $flags = */ 0,
			/* $baseRevId = */ false,
			/* $user = */ $user
		);

		$event = $this->mockEchoEvent();
		$event->expects( $this->any() )
			->method( 'getTitle' )
			->will( $this->returnValue( $title ) );
		$event->expects( $this->any() )
			->method( 'getAgent' )
			->will( $this->returnValue( User::newFromId( 123 ) ) );

		$users = EchoUserLocator::locateArticleCreator( $event );
		$this->assertEquals( $expect, array_keys( $users ), $message );
	}

	public static function locateEventAgentProvider() {
		return [
			[
				'Null event agent returns no users',
				// expected result user id's
				[],
				// event agent
				null,
			],

			[
				'Anonymous event agent returns no users',
				// expected result user id's
				[],
				// event agent
				User::newFromName( '4.5.6.7', /* $validate = */ false ),
			],

			[
				'Registed event agent returned as user',
				// expected result user id's
				[ 42 ],
				// event agent
				User::newFromId( 42 ),
			],
		];
	}

	/**
	 * @dataProvider locateEventAgentProvider
	 */
	public function testLocateEventAgent( $message, $expect, User $agent = null ) {
		$event = $this->mockEchoEvent();
		$event->expects( $this->any() )
			->method( 'getAgent' )
			->will( $this->returnValue( $agent ) );

		$users = EchoUserLocator::locateEventAgent( $event );
		$this->assertEquals( $expect, array_keys( $users ), $message );
	}

	public function locateFromEventExtraProvider() {
		return [
			[
				'Event without extra data returns empty result',
				// expected user list
				[],
				// event extra data
				[],
				// extra keys to get ids from
				[ 'foo' ],
			],

			[
				'Event with specified extra data returns expected result',
				// expected user list
				[ 123 ],
				// event extra data
				[ 'foo' => 123 ],
				// extra keys to get ids from
				[ 'foo' ],
			],

			[
				'Accepts User objects instead of user ids',
				// expected user list
				[ 123 ],
				// event extra data
				[ 'foo' => User::newFromId( 123 ) ],
				// extra keys to get ids from
				[ 'foo' ],
			],

			[
				'Allows inner key to be array of ids',
				// expected user list
				[ 123, 321 ],
				// event extra data
				[ 'foo' => [ 123, 321 ] ],
				// extra keys to get ids from
				[ 'foo' ],
			],

			[
				'Empty inner array causes no error',
				// expected user list
				[],
				// event extra data
				[ 'foo' => [] ],
				// extra keys to get ids from
				[ 'foo' ],
			],

			[
				'Accepts User object at inner level',
				// expected user list
				[ 123 ],
				// event extra data
				[ 'foo' => [ User::newFromId( 123 ) ] ],
				// extra keys to get ids from
				[ 'foo' ],
			],

		];
	}

	/**
	 * @dataProvider locateFromEventExtraProvider
	 */
	public function testLocateFromEventExtra( $message, $expect, array $extra, array $keys ) {
		$event = $this->mockEchoEvent();
		$event->expects( $this->any() )
			->method( 'getExtra' )
			->will( $this->returnValue( $extra ) );
		$event->expects( $this->any() )
			->method( 'getExtraParam' )
			->will( $this->returnValueMap( self::arrayToValueMap( $extra ) ) );

		$users = EchoUserLocator::locateFromEventExtra( $event, $keys );
		$this->assertEquals( $expect, array_keys( $users ), $message );
	}

	protected static function arrayToValueMap( array $array ) {
		$result = [];
		foreach ( $array as $key => $value ) {
			// EchoEvent::getExtraParam second argument defaults to null
			$result[] = [ $key, null, $value ];
		}

		return $result;
	}

	protected function mockEchoEvent() {
		return $this->getMockBuilder( EchoEvent::class )
			->disableOriginalConstructor()
			->getMock();
	}
}
