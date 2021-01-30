<?php

use MediaWiki\Block\DatabaseBlock;

/**
 * Unit tests for the Thanks API module
 *
 * @group Thanks
 * @group API
 *
 * @author Addshore
 */
class ApiCoreThankUnitTest extends MediaWikiTestCase {

	protected static $moduleName = 'thank';

	protected function getModule() {
		return new ApiCoreThank( new ApiMain(), self::$moduleName );
	}

	private function createBlock( $options ) {
		$options = array_merge( [
			'address' => 'Test user',
			'by' => 1,
			'reason' => __METHOD__,
			'timestamp' => wfTimestamp( TS_MW ),
			'expiry' => 'infinity',
		], $options );
		return new DatabaseBlock( $options );
	}

	/**
	 * @dataProvider provideDieOnBadUser
	 * @covers ApiThank::dieOnBadUser
	 * @covers ApiThank::dieOnSitewideBlockedUser
	 */
	public function testDieOnBadUser( $user, $dieMethod, $expectedError ) {
		$module = $this->getModule();
		$method = new ReflectionMethod( $module, $dieMethod );
		$method->setAccessible( true );

		if ( $expectedError ) {
			$this->expectException( ApiUsageException::class );
			$this->expectExceptionMessage( $expectedError );
		}

		$method->invoke( $module, $user );
		// perhaps the method should return true.. For now we must do this
		$this->assertTrue( true );
	}

	public function provideDieOnBadUser() {
		$testCases = [];

		$mockUser = $this->createMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( true ) );

		$testCases[ 'anon' ] = [
			$mockUser,
			'dieOnBadUser',
			'Anonymous users cannot send thanks'
		];

		$mockUser = $this->createMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->will( $this->returnValue( true ) );

		$testCases[ 'ping' ] = [
			$mockUser,
			'dieOnBadUser',
			"You've exceeded your rate limit. Please wait some time and try again"
		];

		$mockUser = $this->createMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'isBlockedGlobally' )
			->will( $this->returnValue( true ) );
		$mockUser->expects( $this->once() )
			->method( 'getGlobalBlock' )
			->will( $this->returnValue(
				$this->createBlock( [] )
			) );

		$testCases[ 'globally blocked' ] = [
			$mockUser,
			'dieOnBadUser',
			'You have been blocked from editing'
		];

		$mockUser = $this->createMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'getBlock' )
			->will( $this->returnValue(
				$this->createBlock( [] )
			) );

		$testCases[ 'sitewide blocked' ] = [
			$mockUser,
			'dieOnSitewideBlockedUser',
			'You have been blocked from editing'
		];

		$mockUser = $this->createMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'getBlock' )
			->will( $this->returnValue(
				$this->createBlock( [
					'sitewide' => false
				] )
			) );

		$testCases[ 'partial blocked' ] = [
			$mockUser,
			'dieOnSitewideBlockedUser',
			false
		];

		return $testCases;
	}

	// @todo test userAlreadySentThanksForRevision
	// @todo test getRevisionFromParams
	// @todo test getTitleFromRevision
	// @todo test getSourceFromParams
	// @todo test getUserIdFromRevision
	// @todo test markResultSuccess
	// @todo test sendThanks

}
