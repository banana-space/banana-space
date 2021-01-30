<?php

use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass EchoNotificationController
 */
class NotificationControllerUnitTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider PageLinkedTitleMutedByUserDataProvider
	 * @covers ::isPageLinkedTitleMutedByUser
	 * @param Title $title
	 * @param User $user
	 * @param bool $expected
	 */
	public function testIsPageLinkedTitleMutedByUser(
		Title $title, User $user, bool $expected ): void {
		$wrapper = TestingAccessWrapper::newFromClass( EchoNotificationController::class );
		$wrapper->mutedPageLinkedTitlesCache = $this->getMapCacheLruMock();
		$this->assertSame(
			$expected,
			$wrapper->isPageLinkedTitleMutedByUser( $title, $user )
		);
	}

	public function PageLinkedTitleMutedByUserDataProvider() :array {
		return [
			[
				$this->getMockTitle( 123 ),
				$this->getMockUser( [] ),
				false
			],
			[
				$this->getMockTitle( 123 ),
				$this->getMockUser( [ 123, 456, 789 ] ),
				true
			],
			[
				$this->getMockTitle( 456 ),
				$this->getMockUser( [ 489 ] ),
				false
			]

		];
	}

	private function getMockTitle( int $articleID ) {
		$title = $this->getMockBuilder( Title::class )
			->disableOriginalConstructor()
			->getMock();
		$title->method( 'getArticleID' )
			->willReturn( $articleID );
		return $title;
	}

	private function getMockUser( $mutedTitlePreferences = [] ) {
		$user = $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
		$user->method( 'getId' )
			->willReturn( 456 );
		$user->method( 'getOption' )
			->willReturn( implode( "\n", $mutedTitlePreferences ) );
		return $user;
	}

	private function getMapCacheLruMock() {
		return $this->getMockBuilder( MapCacheLRU::class )
			->disableOriginalConstructor()
			->getMock();
	}

}
