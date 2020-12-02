<?php

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\UsernameAuthenticationRequest;
use MediaWiki\MediaWikiServices;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers CaptchaPreAuthenticationProvider
 * @group Database
 */
class CaptchaPreAuthenticationProviderTest extends MediaWikiTestCase {
	public function setUp() : void {
		parent::setUp();
		$this->setMwGlobals( [
			'wgCaptchaClass' => SimpleCaptcha::class,
			'wgCaptchaBadLoginAttempts' => 1,
			'wgCaptchaBadLoginPerUserAttempts' => 1,
			'wgCaptchaStorageClass' => CaptchaHashStore::class,
			'wgMainCacheType' => __METHOD__,
		] );
		CaptchaStore::unsetInstanceForTests();
		CaptchaStore::get()->clearAll();
		$services = \MediaWiki\MediaWikiServices::getInstance();
		if ( method_exists( $services, 'getLocalClusterObjectCache' ) ) {
			$this->setService( 'LocalClusterObjectCache', new HashBagOStuff() );
		}
		ObjectCache::$instances[__METHOD__] = new HashBagOStuff();
	}

	public function tearDown() : void {
		parent::tearDown();
		// make sure $wgCaptcha resets between tests
		TestingAccessWrapper::newFromClass( ConfirmEditHooks::class )->instanceCreated = false;
	}

	/**
	 * @dataProvider provideGetAuthenticationRequests
	 */
	public function testGetAuthenticationRequests(
		$action, $username, $triggers, $needsCaptcha, $preTestCallback = null
	) {
		$this->setTriggers( $triggers );
		if ( $preTestCallback ) {
			$fn = array_shift( $preTestCallback );
			call_user_func_array( [ $this, $fn ], $preTestCallback );
		}

		/** @var FauxRequest $request */
		$request = RequestContext::getMain()->getRequest();
		$request->setCookie( 'UserName', $username );

		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );
		$reqs = $provider->getAuthenticationRequests( $action, [ 'username' => $username ] );
		if ( $needsCaptcha ) {
			$this->assertCount( 1, $reqs );
			$this->assertInstanceOf( CaptchaAuthenticationRequest::class, $reqs[0] );
		} else {
			$this->assertEmpty( $reqs );
		}
	}

	public function provideGetAuthenticationRequests() {
		return [
			[ AuthManager::ACTION_LOGIN, null, [], false ],
			[ AuthManager::ACTION_LOGIN, null, [ 'badlogin' ], false ],
			[ AuthManager::ACTION_LOGIN, null, [ 'badlogin' ], true, [ 'blockLogin', 'Foo' ] ],
			[ AuthManager::ACTION_LOGIN, null, [ 'badloginperuser' ], false, [ 'blockLogin', 'Foo' ] ],
			[ AuthManager::ACTION_LOGIN, 'Foo', [ 'badloginperuser' ], false, [ 'blockLogin', 'Bar' ] ],
			[ AuthManager::ACTION_LOGIN, 'Foo', [ 'badloginperuser' ], true, [ 'blockLogin', 'Foo' ] ],
			[ AuthManager::ACTION_LOGIN, null, [ 'badloginperuser' ], true, [ 'flagSession' ] ],
			[ AuthManager::ACTION_CREATE, null, [], false ],
			[ AuthManager::ACTION_CREATE, null, [ 'createaccount' ], true ],
			[ AuthManager::ACTION_CREATE, 'UTSysop', [ 'createaccount' ], false ],
			[ AuthManager::ACTION_LINK, null, [], false ],
			[ AuthManager::ACTION_CHANGE, null, [], false ],
			[ AuthManager::ACTION_REMOVE, null, [], false ],
		];
	}

	public function testGetAuthenticationRequests_store() {
		$this->setTriggers( [ 'createaccount' ] );
		$captcha = new SimpleCaptcha();
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );

		$reqs = $provider->getAuthenticationRequests( AuthManager::ACTION_CREATE,
			[ 'username' => 'Foo' ] );

		$this->assertCount( 1, $reqs );
		$this->assertInstanceOf( CaptchaAuthenticationRequest::class, $reqs[0] );

		$id = $reqs[0]->captchaId;
		$data = TestingAccessWrapper::newFromObject( $reqs[0] )->captchaData;
		$this->assertEquals( $captcha->retrieveCaptcha( $id ), $data + [ 'index' => $id ] );
	}

	/**
	 * @dataProvider provideTestForAuthentication
	 */
	public function testTestForAuthentication( $req, $isBadLoginTriggered,
		$isBadLoginPerUserTriggered, $result
	) {
		$this->setTemporaryHook( 'PingLimiter', function ( $user, $action, &$result ) {
			$result = false;
			return false;
		} );
		CaptchaStore::get()->store( '345', [ 'question' => '2+2', 'answer' => '4' ] );
		$captcha = $this->getMockBuilder( SimpleCaptcha::class )
			->setMethods( [ 'isBadLoginTriggered', 'isBadLoginPerUserTriggered' ] )
			->getMock();
		$captcha->expects( $this->any() )->method( 'isBadLoginTriggered' )
			->willReturn( $isBadLoginTriggered );
		$captcha->expects( $this->any() )->method( 'isBadLoginPerUserTriggered' )
			->willReturn( $isBadLoginPerUserTriggered );
		$this->setMwGlobals( 'wgCaptcha', $captcha );
		TestingAccessWrapper::newFromClass( ConfirmEditHooks::class )->instanceCreated = true;
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );

		$status = $provider->testForAuthentication( $req ? [ $req ] : [] );
		$this->assertEquals( $result, $status->isGood() );
	}

	public function provideTestForAuthentication() {
		$fallback = new UsernameAuthenticationRequest();
		$fallback->username = 'Foo';
		return [
			// [ auth request, bad login?, bad login per user?, result ]
			'no need to check' => [ $fallback, false, false, true ],
			'badlogin' => [ $fallback, true, false, false ],
			'badloginperuser, no username' => [ null, false, true, true ],
			'badloginperuser' => [ $fallback, false, true, false ],
			'non-existent captcha' => [ $this->getCaptchaRequest( '123', '4' ), true, true, false ],
			'wrong captcha' => [ $this->getCaptchaRequest( '345', '6' ), true, true, false ],
			'correct captcha' => [ $this->getCaptchaRequest( '345', '4' ), true, true, true ],
		];
	}

	/**
	 * @dataProvider provideTestForAccountCreation
	 */
	public function testTestForAccountCreation( $req, $creator, $result, $disableTrigger = false ) {
		$this->setTemporaryHook( 'PingLimiter', function ( $user, $action, &$result ) {
			$result = false;
			return false;
		} );
		$this->setTriggers( $disableTrigger ? [] : [ 'createaccount' ] );
		CaptchaStore::get()->store( '345', [ 'question' => '2+2', 'answer' => '4' ] );
		$user = User::newFromName( 'Foo' );
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );

		$status = $provider->testForAccountCreation( $user, $creator, $req ? [ $req ] : [] );
		$this->assertEquals( $result, $status->isGood() );
	}

	public function provideTestForAccountCreation() {
		$user = User::newFromName( 'Bar' );
		$sysop = User::newFromName( 'UTSysop' );
		return [
			// [ auth request, creator, result, disable trigger? ]
			'no captcha' => [ null, $user, false ],
			'non-existent captcha' => [ $this->getCaptchaRequest( '123', '4' ), $user, false ],
			'wrong captcha' => [ $this->getCaptchaRequest( '345', '6' ), $user, false ],
			'correct captcha' => [ $this->getCaptchaRequest( '345', '4' ), $user, true ],
			'user is exempt' => [ null, $sysop, true ],
			'disabled' => [ null, $user, true, 'disable' ],
		];
	}

	public function testPostAuthentication() {
		$this->setTriggers( [ 'badlogin', 'badloginperuser' ] );
		$captcha = new SimpleCaptcha();
		$user = User::newFromName( 'Foo' );
		$anotherUser = User::newFromName( 'Bar' );
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );

		$this->assertFalse( $captcha->isBadLoginTriggered() );
		$this->assertFalse( $captcha->isBadLoginPerUserTriggered( $user ) );

		$provider->postAuthentication( $user, \MediaWiki\Auth\AuthenticationResponse::newFail(
			wfMessage( '?' ) ) );

		$this->assertTrue( $captcha->isBadLoginTriggered() );
		$this->assertTrue( $captcha->isBadLoginPerUserTriggered( $user ) );
		$this->assertFalse( $captcha->isBadLoginPerUserTriggered( $anotherUser ) );

		$provider->postAuthentication( $user, \MediaWiki\Auth\AuthenticationResponse::newPass( 'Foo' ) );

		$this->assertFalse( $captcha->isBadLoginPerUserTriggered( $user ) );
	}

	public function testPostAuthentication_disabled() {
		$this->setTriggers( [] );
		$captcha = new SimpleCaptcha();
		$user = User::newFromName( 'Foo' );
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );

		$this->assertFalse( $captcha->isBadLoginTriggered() );
		$this->assertFalse( $captcha->isBadLoginPerUserTriggered( $user ) );

		$provider->postAuthentication( $user, \MediaWiki\Auth\AuthenticationResponse::newFail(
			wfMessage( '?' ) ) );

		$this->assertFalse( $captcha->isBadLoginTriggered() );
		$this->assertFalse( $captcha->isBadLoginPerUserTriggered( $user ) );
	}

	/**
	 * @dataProvider providePingLimiter
	 */
	public function testPingLimiter( array $attempts ) {
		$this->mergeMwGlobalArrayValue(
			'wgRateLimits',
			[
				'badcaptcha' => [
					'user' => [ 1, 1 ],
				],
			]
		);
		$provider = new CaptchaPreAuthenticationProvider();
		$provider->setManager( MediaWikiServices::getInstance()->getAuthManager() );
		$providerAccess = TestingAccessWrapper::newFromObject( $provider );

		$disablePingLimiter = false;
		$this->setTemporaryHook( 'PingLimiter',
			function ( &$user, $action, &$result ) use ( &$disablePingLimiter ) {
				if ( $disablePingLimiter ) {
					$result = false;
					return false;
				}
				return null;
			}
		);
		foreach ( $attempts as $attempt ) {
			$disablePingLimiter = !empty( $attempts[3] );
			$captcha = new SimpleCaptcha();
			CaptchaStore::get()->store( '345', [ 'question' => '7+7', 'answer' => '14' ] );
			$success = $providerAccess->verifyCaptcha( $captcha, [ $attempts[0] ], $attempts[1] );
			$this->assertEquals( $attempts[2], $success );
		}
	}

	public function providePingLimiter() {
		$sysop = User::newFromName( 'UTSysop' );
		return [
			// sequence of [ auth request, user, result, disable ping limiter? ]
			'no failure' => [
				[ $this->getCaptchaRequest( '345', '14' ), new User(), true ],
				[ $this->getCaptchaRequest( '345', '14' ), new User(), true ],
			],
			'limited' => [
				[ $this->getCaptchaRequest( '345', '33' ), new User(), false ],
				[ $this->getCaptchaRequest( '345', '14' ), new User(), false ],
			],
			'exempt user' => [
				[ $this->getCaptchaRequest( '345', '33' ), $sysop, false ],
				[ $this->getCaptchaRequest( '345', '14' ), $sysop, true ],
			],
			'pinglimiter disabled' => [
				[ $this->getCaptchaRequest( '345', '33' ), new User(), false, 'disable' ],
				[ $this->getCaptchaRequest( '345', '14' ), new User(), true, 'disable' ],
			],
		];
	}

	protected function getCaptchaRequest( $id, $word, $username = null ) {
		$req = new CaptchaAuthenticationRequest( $id, [ 'question' => '?', 'answer' => $word ] );
		$req->captchaWord = $word;
		$req->username = $username;
		return $req;
	}

	protected function blockLogin( $username ) {
		$captcha = new SimpleCaptcha();
		$captcha->increaseBadLoginCounter( $username );
	}

	protected function flagSession() {
		RequestContext::getMain()->getRequest()->getSession()
			->set( 'ConfirmEdit:loginCaptchaPerUserTriggered', true );
	}

	protected function setTriggers( $triggers ) {
		$types = [ 'edit', 'create', 'sendemail', 'addurl', 'createaccount', 'badlogin',
			'badloginperuser' ];
		$captchaTriggers = array_combine( $types, array_map( function ( $type ) use ( $triggers ) {
			return in_array( $type, $triggers, true );
		}, $types ) );
		$this->setMwGlobals( 'wgCaptchaTriggers', $captchaTriggers );
	}

}
