<?php

/**
 * @covers QuestyCaptcha
 */
class QuestyCaptchaTest extends MediaWikiTestCase {

	public function setUp() : void {
		parent::setUp();

		$this->mergeMwGlobalArrayValue(
			'wgAutoloadClasses',
			[ 'QuestyCaptcha' => __DIR__ . '/../../QuestyCaptcha/includes/QuestyCaptcha.php' ]
		);
	}

	/**
	 * @covers QuestyCaptcha::getCaptcha
	 * @dataProvider provideGetCaptcha
	 */
	public function testGetCaptcha( $config, $expected ) {
		# setMwGlobals() requires $wgCaptchaQuestion to be set
		if ( !isset( $GLOBALS['wgCaptchaQuestions'] ) ) {
			$GLOBALS['wgCaptchaQuestions'] = [];
		}
		$this->setMwGlobals( 'wgCaptchaQuestions', $config );

		$qc = new QuestyCaptcha();
		$this->assertEquals( $expected, $qc->getCaptcha() );
	}

	public static function provideGetCaptcha() {
		return [
			[
				[
					[
						'question' => 'FooBar',
						'answer' => 'Answer!',
					],
				],
				[
					'question' => 'FooBar',
					'answer' => 'Answer!',
				],
			],
			[
				[
					'FooBar' => 'Answer!',
				],
				[
					'question' => 'FooBar',
					'answer' => 'Answer!',
				],
			]
		];
	}
}
