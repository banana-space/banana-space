<?php

/**
 * @group medium
 * @group API
 * @covers \EchoPush\Api\ApiEchoPushSubscriptions
 */
class ApiEchoPushSubscriptionsTest extends ApiTestCase {

	public function testRequiresToken(): void {
		$this->setMwGlobals( 'wgEchoEnablePush', true );
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'create',
			'platform' => 'apns',
			'platformtoken' => 'ABC123',
		];
		$this->expectException( ApiUsageException::class );
		$this->doApiRequest( $params );
	}

}
