<?php

class EchoHooksTest extends MediaWikiTestCase {
	/**
	 * @covers \EchoHooks::onUserGetDefaultOptions()
	 */
	public function testOnUserGetDefaultOptions() {
		$this->setMwGlobals( [
			'wgEchoNotificationCategories' => [
				'emailuser' => [
					'priority' => 9,
					'tooltip' => 'echo-pref-tooltip-emailuser',
				],
				'mention' => [
					'priority' => 4,
					'tooltip' => 'echo-pref-tooltip-mention',
				],
				'system' => [
					'priority' => 9,
					'no-dismiss' => [
						'all'
					],
				],
				'some-custom-category' => [
					'priority' => 9001,
				],
			],
			'wgAllowHTMLEmail' => true,
		] );

		$defaults = [
			'something' => 'unrelated',
			// T174220: don't overwrite defaults set elsewhere
			'echo-subscriptions-web-mention' => false,
		];
		EchoHooks::onUserGetDefaultOptions( $defaults );
		self::assertEquals(
			[
				'something' => 'unrelated',
				'echo-email-format' => 'html',
				'echo-subscriptions-email-mention' => false,
				'echo-subscriptions-web-mention' => false,
				'echo-subscriptions-email-emailuser' => false,
				'echo-subscriptions-web-emailuser' => true,
				'echo-subscriptions-email-system' => true,
				'echo-subscriptions-web-system' => true,
				'echo-subscriptions-email-some-custom-category' => false,
				'echo-subscriptions-web-some-custom-category' => true,
			],
			$defaults
		);
	}
}
