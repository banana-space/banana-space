<?php

use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers SimpleCaptcha
 */
class CaptchaTest extends MediaWikiTestCase {

	/** @var ScopedCallback[] */
	private $hold = [];

	public function tearDown() : void {
		// Destroy any ScopedCallbacks being held
		$this->hold = [];
		parent::tearDown();
	}

	/**
	 * @dataProvider provideSimpleTriggersCaptcha
	 */
	public function testTriggersCaptcha( $action, $expectedResult ) {
		$captcha = new SimpleCaptcha();
		$this->setMwGlobals( [
			'wgCaptchaTriggers' => [
				$action => $expectedResult,
			]
		] );
		$this->assertEquals( $expectedResult, $captcha->triggersCaptcha( $action ) );
	}

	public function provideSimpleTriggersCaptcha() {
		$data = [];
		$captchaTriggers = new ReflectionClass( CaptchaTriggers::class );
		$constants = $captchaTriggers->getConstants();
		foreach ( $constants as $const ) {
			$data[] = [ $const, true ];
			$data[] = [ $const, false ];
		}
		return $data;
	}

	/**
	 * @dataProvider provideNamespaceOverwrites
	 */
	public function testNamespaceTriggersOverwrite( $trigger, $expected ) {
		$captcha = new SimpleCaptcha();
		$this->setMwGlobals( [
			'wgCaptchaTriggers' => [
				$trigger => !$expected,
			],
			'wgCaptchaTriggersOnNamespace' => [
				0 => [
					$trigger => $expected,
				],
			],
		] );
		$title = Title::newFromText( 'Main' );
		$this->assertEquals( $expected, $captcha->triggersCaptcha( $trigger, $title ) );
	}

	public function provideNamespaceOverwrites() {
		return [
			[ 'edit', true ],
			[ 'edit', false ],
		];
	}

	private function setCaptchaTriggersAttribute( $trigger, $value ) {
		// XXX This is really hacky, but is needed to stop extensions from
		// being clobbered in subsequent tests. This should be fixed properly
		// by making extension registration happen in services instead of
		// globals.
		$keys =
			TestingAccessWrapper::newFromClass( ExtensionProcessor::class )->globalSettings;
		$globalsToStash = [];
		foreach ( $keys as $key ) {
			$globalsToStash["wg$key"] = $GLOBALS["wg$key"];
		}
		$this->setMwGlobals( $globalsToStash );

		$info = [
			'globals' => [],
			'callbacks' => [],
			'defines' => [],
			'credits' => [],
			'attributes' => [
				'CaptchaTriggers' => [
					$trigger => $value
				]
			],
			'autoloaderPaths' => []
		];
		$this->hold[] = ExtensionRegistry::getInstance()->setAttributeForTest(
			'CaptchaTriggers', [ $trigger => $value ]
		);
	}

	/**
	 * @dataProvider provideAttributeSet
	 */
	public function testCaptchaTriggersAttributeSetTrue( $trigger, $value ) {
		$this->setCaptchaTriggersAttribute( $trigger, $value );
		$captcha = new SimpleCaptcha();
		$this->assertEquals( $value, $captcha->triggersCaptcha( $trigger ) );
	}

	public function provideAttributeSet() {
		return [
			[ 'test', true ],
			[ 'test', false ],
		];
	}

	/**
	 * @dataProvider provideAttributeOverwritten
	 */
	public function testCaptchaTriggersAttributeGetsOverwritten( $trigger, $expected ) {
		$this->setMwGlobals( [
			'wgCaptchaTriggers' => [
				$trigger => $expected
			]
		] );
		$this->setCaptchaTriggersAttribute( $trigger, !$expected );
		$captcha = new SimpleCaptcha();
		$this->assertEquals( $expected, $captcha->triggersCaptcha( $trigger ) );
	}

	public function provideAttributeOverwritten() {
		return [
			[ 'edit', true ],
			[ 'edit', false ],
		];
	}

	/**
	 * @dataProvider provideCanSkipCaptchaUserright
	 */
	public function testCanSkipCaptchaUserright( $userIsAllowed, $expected ) {
		$testObject = new SimpleCaptcha();
		$user = $this->createMock( User::class );
		$user->method( 'isAllowed' )->willReturn( $userIsAllowed );

		$actual = $testObject->canSkipCaptcha( $user, RequestContext::getMain()->getConfig() );

		$this->assertEquals( $expected, $actual );
	}

	public function provideCanSkipCaptchaUserright() {
		return [
			[ true, true ],
			[ false, false ]
		];
	}

	/**
	 * @param $allowUserConfirmEmail
	 * @param $userIsMailConfirmed
	 * @param $expected
	 * @throws ConfigException
	 * @dataProvider provideCanSkipCaptchaMailconfirmed
	 */
	public function testCanSkipCaptchaMailconfirmed( $allowUserConfirmEmail,
		$userIsMailConfirmed, $expected ) {
		$testObject = new SimpleCaptcha();
		$user = $this->createMock( User::class );
		$user->method( 'isEmailConfirmed' )->willReturn( $userIsMailConfirmed );
		$config = $this->createMock( Config::class );
		$config->method( 'get' )->willReturn( $allowUserConfirmEmail );

		$actual = $testObject->canSkipCaptcha( $user, $config );

		$this->assertEquals( $expected, $actual );
	}

	public function provideCanSkipCaptchaMailconfirmed() {
		return [
			[ false, false, false ],
			[ false, true, false ],
			[ true, false, false ],
			[ true, true, true ],
		];
	}

	/**
	 * @param $requestIP
	 * @param $IPWhitelist
	 * @param $expected
	 * @throws ConfigException
	 * @dataProvider provideCanSkipCaptchaIPWhitelisted
	 */
	public function testCanSkipCaptchaIPWhitelisted( $requestIP, $IPWhitelist, $expected ) {
		$testObject = new SimpleCaptcha();
		$config = $this->createMock( Config::class );
		$request = $this->createMock( WebRequest::class );
		$request->method( 'getIP' )->willReturn( $requestIP );

		$this->setMwGlobals( [
			'wgRequest' => $request,
			'wgCaptchaWhitelistIP' => $IPWhitelist
		] );

		$actual = $testObject->canSkipCaptcha( RequestContext::getMain()->getUser(), $config );

		$this->assertEquals( $expected, $actual );
	}

	public function provideCanSkipCaptchaIPWhitelisted() {
		return ( [
			[ '127.0.0.1', [ '127.0.0.1', '127.0.0.2' ], true ],
			[ '127.0.0.1', [], false ]
		]
		);
	}
}
