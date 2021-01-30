<?php

class NotificationStructureTest extends MediaWikiTestCase {
	/**
	 * @coversNothing
	 * @dataProvider provideNotificationTypes
	 *
	 * @param string $type
	 * @param array $info
	 */
	public function testNotificationTypes( $type, array $info ) {
		if ( isset( $info['presentation-model'] ) ) {
			self::assertTrue( class_exists( $info['presentation-model'] ),
				"Presentation model class {$info['presentation-model']} for {$type} must exist"
			);
		}

		if ( isset( $info['user-locators'] ) ) {
			$locators = $info['user-locators'];
			$locator = reset( $locators );
			if ( is_array( $locator ) ) {
				$locator = reset( $locator );
			}
			self::assertTrue( is_callable( $locator ),
				'User locator ' . print_r( $locator, true ) . " for {$type} must be callable"
			);
		}
	}

	public function provideNotificationTypes() {
		global $wgEchoNotifications;

		$result = [];
		foreach ( $wgEchoNotifications as $type => $info ) {
			$result[] = [ $type, $info ];
		}

		return $result;
	}
}
