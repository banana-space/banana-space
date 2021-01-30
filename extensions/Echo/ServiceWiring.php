<?php

use EchoPush\NotificationServiceClient;
use EchoPush\SubscriptionManager;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\NameTableStore;

return [

	'EchoPushNotificationServiceClient' => function ( MediaWikiServices $services ):
	NotificationServiceClient {
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );
		$httpRequestFactory = $services->getHttpRequestFactory();
		$url = $echoConfig->get( 'EchoPushServiceBaseUrl' );
		$client = new NotificationServiceClient( $httpRequestFactory, $url );
		$client->setLogger( LoggerFactory::getInstance( 'Echo' ) );
		return $client;
	},

	'EchoPushSubscriptionManager' => function ( MediaWikiServices $services ): SubscriptionManager {
		$echoConfig = $services->getConfigFactory()->makeConfig( 'Echo' );
		// Use shared DB/cluster for push subscriptions
		$cluster = $echoConfig->get( 'EchoSharedTrackingCluster' );
		$database = $echoConfig->get( 'EchoSharedTrackingDB' );
		$loadBalancerFactory = $services->getDBLoadBalancerFactory();
		$loadBalancer = $cluster
			? $loadBalancerFactory->getExternalLB( $cluster )
			: $loadBalancerFactory->getMainLB( $database );
		$dbw = $loadBalancer->getLazyConnectionRef( DB_MASTER, [], $database );
		$dbr = $loadBalancer->getLazyConnectionRef( DB_REPLICA, [], $database );

		$centralIdLookup = CentralIdLookup::factory();

		$pushProviderStore = new NameTableStore(
			$loadBalancer,
			$services->getMainWANObjectCache(),
			LoggerFactory::getInstance( 'Echo' ),
			'echo_push_provider',
			'epp_id',
			'epp_name',
			null,
			$database
		);

		return new SubscriptionManager( $dbw, $dbr, $centralIdLookup, $pushProviderStore );
	}

];
