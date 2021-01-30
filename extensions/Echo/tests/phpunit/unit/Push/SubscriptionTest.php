<?php

use EchoPush\Subscription;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/** @covers \EchoPush\Subscription */
class SubscriptionTest extends MediaWikiUnitTestCase {

	public function testNewFromRow(): void {
		$row = new stdClass();
		$row->eps_token = 'ABC123';
		$row->epp_name = 'fcm';
		$row->eps_updated = '2020-01-01 10:10:10';

		$subscription = Subscription::newFromRow( $row );
		$this->assertSame( 'ABC123', $subscription->getToken() );
		$this->assertSame( 'fcm', $subscription->getProvider() );
		$this->assertInstanceOf( ConvertibleTimestamp::class, $subscription->getUpdated() );
		$this->assertSame( '1577873410', $subscription->getUpdated()->getTimestamp() );
	}

}
