<?php

use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \EchoTargetPageMapper
 */
class EchoTargetPageMapperTest extends MediaWikiUnitTestCase {

	public function provideDataTestInsert() {
		return [
			[
				'successful insert with next sequence = 1',
				[ 'insert' => true, 'insertId' => 2 ],
				1
			],
			[
				'successful insert with insert id = 2',
				[ 'insert' => true, 'insertId' => 2 ],
				2
			],
		];
	}

	/**
	 * @dataProvider provideDataTestInsert
	 */
	public function testInsert( $message, $dbResult, $result ) {
		$target = $this->mockEchoTargetPage();
		$targetMapper = new EchoTargetPageMapper( $this->mockMWEchoDbFactory( $dbResult ) );
		$this->assertEquals( $result, $targetMapper->insert( $target ), $message );
	}

	/**
	 * Mock object of EchoTargetPage
	 */
	protected function mockEchoTargetPage() {
		$target = $this->getMockBuilder( EchoTargetPage::class )
			->disableOriginalConstructor()
			->getMock();
		$target->expects( $this->any() )
			->method( 'toDbArray' )
			->will( $this->returnValue( [] ) );
		$target->expects( $this->any() )
			->method( 'getPageId' )
			->will( $this->returnValue( 2 ) );
		$target->expects( $this->any() )
			->method( 'getEventId' )
			->will( $this->returnValue( 3 ) );

		return $target;
	}

	/**
	 * Mock object of MWEchoDbFactory
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
	 * Returns a mock database object
	 * @return \Wikimedia\Rdbms\IDatabase
	 */
	protected function mockDb( array $dbResult ) {
		$dbResult += [
			'insert' => '',
			'insertId' => '',
			'select' => '',
			'delete' => ''
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
			->method( 'delete' )
			->will( $this->returnValue( $dbResult['delete'] ) );

		return $db;
	}

}
