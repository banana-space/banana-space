<?php

use Wikimedia\Rdbms\IDatabase;

/**
 * @group Database
 * @covers \EchoEventMapper
 */
class EchoEventMapperTest extends MediaWikiTestCase {

	protected function setUp() : void {
		parent::setUp();
		$this->tablesUsed[] = 'echo_event';
		$this->tablesUsed[] = 'echo_notification';
		$this->tablesUsed[] = 'echo_target_page';
	}

	public function provideDataTestInsert() {
		return [
			[
				'successful insert with next sequence = 1',
				[ 'insert' => true, 'insertId' => 1 ],
				1
			],
			[
				'successful insert with insert id = 2',
				[ 'insert' => true, 'insertId' => 2 ],
				2
			]
		];
	}

	/**
	 * @dataProvider provideDataTestInsert
	 */
	public function testInsert( $message, $dbResult, $result ) {
		$event = $this->mockEchoEvent();
		$eventMapper = new EchoEventMapper( $this->mockMWEchoDbFactory( $dbResult ) );
		$this->assertEquals( $result, $eventMapper->insert( $event ), $message );
	}

	/**
	 * Successful fetchById()
	 */
	public function testSuccessfulFetchById() {
		$eventMapper = new EchoEventMapper(
			$this->mockMWEchoDbFactory(
				[
					'selectRow' => (object)[
						'event_id' => 1,
						'event_type' => 'test',
						'event_variant' => '',
						'event_extra' => '',
						'event_page_id' => '',
						'event_agent_id' => '',
						'event_agent_ip' => '',
						'event_deleted' => 0,
					]
				]
			)
		);
		$res = $eventMapper->fetchById( 1 );
		$this->assertInstanceOf( EchoEvent::class, $res );
	}

	public function testUnsuccessfulFetchById() {
		$eventMapper = new EchoEventMapper(
			$this->mockMWEchoDbFactory(
				[
					'selectRow' => false
				]
			)
		);
		$this->expectException( MWException::class );
		$eventMapper->fetchById( 1 );
	}

	/**
	 * @return EchoEvent
	 */
	protected function mockEchoEvent() {
		$event = $this->getMockBuilder( EchoEvent::class )
			->disableOriginalConstructor()
			->getMock();
		$event->expects( $this->any() )
			->method( 'toDbArray' )
			->will( $this->returnValue( [] ) );

		return $event;
	}

	/**
	 * @return MWEchoDbFactory
	 */
	protected function mockMWEchoDbFactory( $dbResult ) {
		$dbFactory = $this->getMockBuilder( MWEchoDbFactory::class )
			->disableOriginalConstructor()
			->getMock();
		$dbFactory->expects( $this->any() )
			->method( 'getEchoDb' )
			->will( $this->returnValue( $this->mockDb( $dbResult ) ) );

		return $dbFactory;
	}

	/**
	 * @return IDatabase
	 */
	protected function mockDb( array $dbResult ) {
		$dbResult += [
			'insert' => '',
			'insertId' => '',
			'select' => '',
			'selectRow' => ''
		];
		$db = $this->createMock( IDatabase::class );
		$db->expects( $this->any() )
			->method( 'insert' )
			->will( $this->returnValue( $dbResult['insert'] ) );
		$db->expects( $this->any() )
			->method( 'insertId' )
			->will( $this->returnValue( $dbResult['insertId'] ) );
		$db->expects( $this->any() )
			->method( 'select' )
			->will( $this->returnValue( $dbResult['select'] ) );
		$db->expects( $this->any() )
			->method( 'selectRow' )
			->will( $this->returnValue( $dbResult['selectRow'] ) );

		return $db;
	}

	/**
	 * @covers \EchoEventMapper::fetchIdsByPage
	 */
	public function testFetchByPage() {
		$user = $this->getTestUser()->getUser();
		$page = $this->getExistingTestPage();

		// Create a notification that is not associated with any page
		EchoEvent::create( [
			'type' => 'welcome',
			'agent' => $user,
		] );

		// Create a notification with a title
		$eventWithTitle = EchoEvent::create( [
			'type' => 'welcome',
			'agent' => $user,
			'title' => $page->getTitle()
		] );

		// Create a notification with a target-page
		$eventWithTargetPage = EchoEvent::create( [
			'type' => 'welcome',
			'agent' => $user,
			'extra' => [ 'target-page' => $page->getId() ]
		] );

		$eventMapper = new EchoEventMapper();

		$this->assertArrayEquals(
			[ $eventWithTitle->getId(), $eventWithTargetPage->getId() ],
			$eventMapper->fetchIdsByPage( $page->getId() )
		);
	}

}
