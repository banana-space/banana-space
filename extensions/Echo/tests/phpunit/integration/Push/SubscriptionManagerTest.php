<?php

/**
 * @group Database
 * @covers \EchoPush\SubscriptionManager
 */
class SubscriptionManagerTest extends MediaWikiIntegrationTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->tablesUsed[] = 'echo_push_subscription';
		$this->tablesUsed[] = 'echo_push_provider';
	}

	public function testManagePushSubscriptions(): void {
		$subscriptionManager = EchoServices::getInstance()->getPushSubscriptionManager();
		$user = $this->getTestUser()->getUser();
		$centralId = CentralIdLookup::factory()->centralIdFromLocalUser( $user );
		$subscriptionManager->create( $user, 'test', 'ABC123' );
		$subscriptions = $subscriptionManager->getSubscriptionsForUser( $centralId );
		$this->assertCount( 1, $subscriptions );
		$subscriptionManager->delete( $user, 'ABC123' );
		$subscriptions = $subscriptionManager->getSubscriptionsForUser( $centralId );
		$this->assertCount( 0, $subscriptions );
	}

}
