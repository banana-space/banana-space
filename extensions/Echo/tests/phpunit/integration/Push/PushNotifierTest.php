<?php

use EchoPush\NotificationRequestJob;
use EchoPush\PushNotifier;
use Wikimedia\TestingAccessWrapper;

/** @covers \EchoPush\PushNotifier */
class PushNotifierTest extends MediaWikiIntegrationTestCase {

	public function testCreateJob(): void {
		$notifier = TestingAccessWrapper::newFromClass( PushNotifier::class );
		$user = $this->getTestUser()->getUser();
		$centralId = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
		$job = $notifier->createJob( $user );
		$this->assertInstanceOf( NotificationRequestJob::class, $job );
		$this->assertSame( 'EchoPushNotificationRequest', $job->getType() );
		$this->assertSame( $centralId, $job->getParams()['centralId'] );
	}

}
