<?php

use Wikimedia\TestingAccessWrapper;

/** @covers \EchoPush\NotificationServiceClient */
class NotificationServiceClientTest extends MediaWikiIntegrationTestCase {

	public function testConstructRequest(): void {
		$client = EchoServices::getInstance()->getPushNotificationServiceClient();
		$client = TestingAccessWrapper::newFromObject( $client );
		$payload = [ 'deviceTokens' => [ 'foo' ], 'messageType' => 'checkEchoV1' ];
		$request = $client->constructRequest( 'fcm', $payload );
		$this->assertInstanceOf( MWHttpRequest::class, $request );
	}

}
