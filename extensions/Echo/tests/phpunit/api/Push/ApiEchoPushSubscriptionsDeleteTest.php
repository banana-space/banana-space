<?php

/**
 * @group medium
 * @group API
 * @group Database
 * @covers \EchoPush\Api\ApiEchoPushSubscriptionsDelete
 */
class ApiEchoPushSubscriptionsDeleteTest extends ApiTestCase {

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

	public function testApiDeleteSubscription(): void {
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'delete',
			'providertoken' => 'XYZ789',
		];
		$result = $this->doApiRequestWithToken( $params, null, $this->user );
		$this->assertEquals( 'Success', $result[0]['delete']['result'] );
	}

	public function testApiDeleteSubscriptionNotFound(): void {
		$params = [
			'action' => 'echopushsubscriptions',
			'command' => 'delete',
			'providertoken' => 'ABC123',
		];
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( $params, null, $this->user );
	}

	private function createTestData(): void {
		$subscriptionManager = EchoServices::getInstance()->getPushSubscriptionManager();
		$subscriptionManager->create( $this->user, 'fcm', 'XYZ789' );
	}

}
