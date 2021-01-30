<?php

use EchoPush\NotificationServiceClient;
use EchoPush\SubscriptionManager;
use MediaWiki\MediaWikiServices;

class EchoServices {

	/** @var MediaWikiServices */
	private $services;

	/** @return EchoServices */
	public static function getInstance(): EchoServices {
		return new self( MediaWikiServices::getInstance() );
	}

	/**
	 * @param MediaWikiServices $services
	 * @return EchoServices
	 */
	public static function wrap( MediaWikiServices $services ): EchoServices {
		return new self( $services );
	}

	/** @param MediaWikiServices $services */
	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	/** @return NotificationServiceClient */
	public function getPushNotificationServiceClient(): NotificationServiceClient {
		return $this->services->getService( 'EchoPushNotificationServiceClient' );
	}

	/** @return SubscriptionManager */
	public function getPushSubscriptionManager(): SubscriptionManager {
		return $this->services->getService( 'EchoPushSubscriptionManager' );
	}

}
