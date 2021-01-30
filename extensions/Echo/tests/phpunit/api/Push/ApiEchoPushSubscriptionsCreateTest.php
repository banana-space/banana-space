<?php

/**
 * @group medium
 * @group API
 * @group Database
 * @covers \EchoPush\Api\ApiEchoPushSubscriptionsCreate
 */
class ApiEchoPushSubscriptionsCreateTest extends ApiTestCase {

	/** @var User */
	private $user;

	public function setUp(): void {
		parent::setUp();
		$this->setMwGlobals( 'wgEchoEnablePush', true );
		$this->tablesUsed[] = 'echo_push_subscription';
		$this->tablesUsed[] = 'echo_push_provider';
		$this->user = $this->getTestUser()->getUser();
		$this->createTestData();
	}

	public function testApiCreateSubscription(): void {
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'create',
			'provider' => 'fcm',
			'providertoken' => 'ABC123',
		];
		$result = $this->doApiRequestWithToken( $params, null, $this->user );
		$this->assertEquals( 'Success', $result[0]['create']['result'] );
	}

	public function testApiCreateSubscriptionTokenExists(): void {
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'create',
			'provider' => 'fcm',
			'providertoken' => 'XYZ789',
		];
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( $params, null, $this->user );
	}

	private function createTestData(): void {
		$subscriptionManager = EchoServices::getInstance()->getPushSubscriptionManager();
		$subscriptionManager->create( $this->user, 'fcm', 'XYZ789' );
	}

}
