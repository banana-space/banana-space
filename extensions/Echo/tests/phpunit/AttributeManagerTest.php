<?php

/**
 * @covers \EchoAttributeManager
 */
class EchoAttributeManagerTest extends MediaWikiTestCase {

	public function testNewFromGlobalVars() {
		$this->assertInstanceOf( EchoAttributeManager::class, EchoAttributeManager::newFromGlobalVars() );
	}

	public static function getUserLocatorsProvider() {
		return [
			[
				'No errors when requesting unknown type',
				// expected result
				[],
				// event type
				'foo',
				// notification configuration
				[],
			],

			[
				'Returns selected notification configuration',
				// expected result
				[ 'woot!' ],
				// event type
				'magic',
				// notification configuration
				[
					'foo' => [
						EchoAttributeManager::ATTR_LOCATORS => [ 'frown' ],
					],
					'magic' => [
						EchoAttributeManager::ATTR_LOCATORS => [ 'woot!' ],
					],
				],
			],

			[
				'Accepts user-locators as string and returns array',
				// expected result
				[ 'sagen' ],
				// event type
				'challah',
				// notification configuration
				[
					'challah' => [
						EchoAttributeManager::ATTR_LOCATORS => 'sagen',
					],
				],
			],
		];
	}

	/**
	 * @dataProvider getUserLocatorsProvider
	 */
	public function testGetUserLocators( $message, $expect, $type, $notifications ) {
		$manager = new EchoAttributeManager( $notifications, [], [], [] );

		$result = $manager->getUserCallable( $type, EchoAttributeManager::ATTR_LOCATORS );
		$this->assertEquals( $expect, $result, $message );
	}

	public function testGetCategoryEligibility() {
		$notif = [
			'event_one' => [
				'category' => 'category_one'
			],
		];
		$category = [
			'category_one' => [
				'priority' => 10
			]
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertTrue( $manager->getCategoryEligibility( $this->mockUser(), 'category_one' ) );
		$category = [
			'category_one' => [
				'priority' => 10,
				'usergroups' => [
					'sysop'
				]
			]
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertFalse( $manager->getCategoryEligibility( $this->mockUser(), 'category_one' ) );
	}

	public function testGetNotificationCategory() {
		$notif = [
			'event_one' => [
				'category' => 'category_one'
			],
		];
		$category = [
			'category_one' => [
				'priority' => 10
			]
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertEquals( $manager->getNotificationCategory( 'event_one' ), 'category_one' );

		$manager = new EchoAttributeManager( $notif, [], [], [] );
		$this->assertEquals( $manager->getNotificationCategory( 'event_one' ), 'other' );

		$notif = [
			'event_one' => [
				'category' => 'category_two'
			],
		];
		$category = [
			'category_one' => [
				'priority' => 10
			]
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertEquals( $manager->getNotificationCategory( 'event_one' ), 'other' );
	}

	public function testGetCategoryPriority() {
		$notif = [
			'event_one' => [
				'category' => 'category_two'
			],
		];
		$category = [
			'category_one' => [
				'priority' => 6
			],
			'category_two' => [
				'priority' => 100
			],
			'category_three' => [
				'priority' => -10
			],
			'category_four' => []
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertSame( 6, $manager->getCategoryPriority( 'category_one' ) );
		$this->assertSame( 10, $manager->getCategoryPriority( 'category_two' ) );
		$this->assertSame( 10, $manager->getCategoryPriority( 'category_three' ) );
		$this->assertSame( 10, $manager->getCategoryPriority( 'category_four' ) );
	}

	public function testGetNotificationPriority() {
		$notif = [
			'event_one' => [
				'category' => 'category_one'
			],
			'event_two' => [
				'category' => 'category_two'
			],
			'event_three' => [
				'category' => 'category_three'
			],
			'event_four' => [
				'category' => 'category_four'
			]
		];
		$category = [
			'category_one' => [
				'priority' => 6
			],
			'category_two' => [
				'priority' => 100
			],
			'category_three' => [
				'priority' => -10
			],
			'category_four' => []
		];
		$manager = new EchoAttributeManager( $notif, $category, [], [] );
		$this->assertSame( 6, $manager->getNotificationPriority( 'event_one' ) );
		$this->assertSame( 10, $manager->getNotificationPriority( 'event_two' ) );
		$this->assertSame( 10, $manager->getNotificationPriority( 'event_three' ) );
		$this->assertSame( 10, $manager->getNotificationPriority( 'event_four' ) );
	}

	public static function getEventsForSectionProvider() {
		$notifications = [
			'event_one' => [
				'category' => 'category_one',
				'section' => 'message',
			],
			'event_two' => [
				'category' => 'category_two',
				'section' => 'invalid',
			],
			'event_three' => [
				'category' => 'category_three',
				'section' => 'message',
			],
			'event_four' => [
				'category' => 'category_four',
				// Omitted
			],
			'event_five' => [
				'category' => 'category_two',
				'section' => 'alert',
			],
		];

		return [
			[
				[ 'event_one', 'event_three' ],
				$notifications,
				'message',
				'Messages',
			],

			[
				[ 'event_two', 'event_four', 'event_five' ],
				$notifications,
				'alert',
				'Alerts',
			],
		];
	}

	/**
	 * @dataProvider getEventsForSectionProvider
	 */
	public function testGetEventsForSection( $expected, $notificationTypes, $section, $message ) {
		$am = new EchoAttributeManager( $notificationTypes, [], [], [] );
		$actual = $am->getEventsForSection( $section );
		$this->assertEquals( $expected, $actual, $message );
	}

	public function testGetUserEnabledEvents() {
		$notif = [
			'event_one' => [
				'category' => 'category_one'
			],
			'event_two' => [
				'category' => 'category_two'
			],
			'event_three' => [
				'category' => 'category_three'
			],
			'event_four' => [
				'category' => 'category_four'
			]
		];
		$category = [
			'category_one' => [
				'priority' => 10,
				'usergroups' => [
					'sysop'
				]
			],
			'category_two' => [
				'priority' => 10,
				'usergroups' => [
					'echo_group'
				]
			],
			'category_three' => [
				'priority' => 10,
			],
			'category_four' => [
				'priority' => 10,
			]
		];
		$defaultNotifyTypeAvailability = [
			'web' => true,
			'email' => true,
		];
		$notifyTypeAvailabilityByCategory = [
			'category_three' => [
				'web' => false,
				'email' => true,
			]
		];
		$manager = new EchoAttributeManager(
			$notif,
			$category,
			$defaultNotifyTypeAvailability,
			$notifyTypeAvailabilityByCategory
		);
		$this->assertEquals(
			[ 'event_two', 'event_four' ],
			$manager->getUserEnabledEvents( $this->mockUser(), 'web' )
		);
	}

	public function testGetUserEnabledEventsbySections() {
		$notif = [
			'event_one' => [
				'category' => 'category_one'
			],
			'event_two' => [
				'category' => 'category_two',
				'section' => 'message'
			],
			'event_three' => [
				'category' => 'category_three',
				'section' => 'alert'
			],
			'event_four' => [
				'category' => 'category_three',
			],
			'event_five' => [
				'category' => 'category_five'
			]
		];
		$category = [
			'category_one' => [
				'priority' => 10,
			],
			'category_two' => [
				'priority' => 10,
			],
			'category_three' => [
				'priority' => 10
			],
			'category_five' => [
				'priority' => 10
			]
		];
		$defaultNotifyTypeAvailability = [
			'web' => true,
			'email' => true,
		];
		$notifyTypeAvailabilityByCategory = [
			'category_five' => [
				'web' => false,
				'email' => true,
			]
		];
		$manager = new EchoAttributeManager(
			$notif,
			$category,
			$defaultNotifyTypeAvailability,
			$notifyTypeAvailabilityByCategory
		);
		$expected = [ 'event_one', 'event_three', 'event_four' ];
		$actual = $manager->getUserEnabledEventsbySections( $this->mockUser(), 'web', [ 'alert' ] );
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );

		$expected = [ 'event_two' ];
		$actual = $manager->getUserEnabledEventsbySections( $this->mockUser(), 'web', [ 'message' ] );
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );

		$expected = [ 'event_one', 'event_two', 'event_three', 'event_four' ];
		$actual = $manager->getUserEnabledEventsbySections( $this->mockUser(), 'web',
			[ 'message', 'alert' ] );
		sort( $expected );
		sort( $actual );
		$this->assertEquals( $expected, $actual );
	}

	public static function getEventsByCategoryProvider() {
		return [
			[
				'Mix of populated and empty categories handled appropriately',
				[
					'category_one' => [
						'event_two',
						'event_five',
					],
					'category_two' => [
						'event_one',
						'event_three',
						'event_four',
					],
					'category_three' => [],
				],
				[
					'category_one' => [],
					'category_two' => [],
					'category_three' => [],
				],
				[
					'event_one' => [
						'category' => 'category_two',
					],
					'event_two' => [
						'category' => 'category_one',
					],
					'event_three' => [
						'category' => 'category_two',
					],
					'event_four' => [
						'category' => 'category_two',
					],
					'event_five' => [
						'category' => 'category_one',
					],
				]
			]
		];
	}

	/**
	 * @dataProvider getEventsByCategoryProvider
	 */
	public function testGetEventsByCategory(
		$message,
		$expectedMapping,
		$categories,
		$notifications
	) {
		$am = new EchoAttributeManager( $notifications, $categories, [], [] );
		$actualMapping = $am->getEventsByCategory();
		$this->assertEquals( $expectedMapping, $actualMapping, $message );
	}

	public static function isNotifyTypeAvailableForCategoryProvider() {
		return [
			[
				'Fallback to default entirely',
				true,
				'category_one',
				'web',
				[ 'web' => true, 'email' => true ],
				[]
			],
			[
				'Fallback to default for single type',
				false,
				'category_two',
				'email',
				[ 'web' => true, 'email' => false ],
				[
					'category_two' => [
						'web' => true,
					],
				]
			],
			[
				'Use override',
				false,
				'category_three',
				'web',
				[ 'web' => true, 'email' => true ],
				[
					'category_three' => [
						'web' => false,
					],
				],
			],
		];
	}

	/**
	 * @dataProvider isNotifyTypeAvailableForCategoryProvider
	 */
	public function testIsNotifyTypeAvailableForCategory(
		$message,
		$expected,
		$categoryName,
		$notifyType,
		$defaultNotifyTypeAvailability,
		$notifyTypeAvailabilityByCategory
	) {
		$am = new EchoAttributeManager( [], [], $defaultNotifyTypeAvailability,
			$notifyTypeAvailabilityByCategory );
		$actual = $am->isNotifyTypeAvailableForCategory( $categoryName, $notifyType );
		$this->assertEquals( $expected, $actual, $message );
	}

	public static function isNotifyTypeDismissableForCategoryProvider() {
		return [
			[
				'Not dismissable because of all',
				false,
				[
					'category_one' => [
						'no-dismiss' => [ 'all' ],
					]
				],
				'category_one',
				'web',
			],
			[
				'Not dismissable because of specific notify type',
				false,
				[
					'category_two' => [
						'no-dismiss' => [ 'email' ],
					]
				],
				'category_two',
				'email',
			],
			[
				'Dismissable because of different affected notify type',
				true,
				[
					'category_three' => [
						'no-dismiss' => [ 'web' ],
					]
				],
				'category_three',
				'email',
			],
		];
	}

	/**
	 * @dataProvider isNotifyTypeDismissableForCategoryProvider
	 */
	public function testIsNotifyTypeDismissableForCategory(
		$message,
		$expected,
		$categories,
		$categoryName,
		$notifyType
	) {
		$am = new EchoAttributeManager( [], $categories, [], [] );
		$actual = $am->isNotifyTypeDismissableForCategory( $categoryName, $notifyType );
		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Mock object of User
	 */
	protected function mockUser() {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->expects( $this->any() )
			->method( 'getID' )
			->will( $this->returnValue( 1 ) );
		$user->expects( $this->any() )
			->method( 'getOption' )
			->will( $this->returnValue( true ) );
		$user->expects( $this->any() )
			->method( 'getGroups' )
			->will( $this->returnValue( [ 'echo_group' ] ) );

		return $user;
	}
}
