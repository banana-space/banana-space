<?php

use MediaWiki\Extension\OATHAuth\OATHAuth;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\MediaWikiServices;

return [
	'OATHAuth' => function ( MediaWikiServices $services ) {
		return new OATHAuth(
			$services->getMainConfig(),
			$services->getDBLoadBalancerFactory()
		);
	},
	'OATHUserRepository' => function ( MediaWikiServices $services ) {
		global $wgOATHAuthDatabase;
		$auth = $services->getService( 'OATHAuth' );
		return new OATHUserRepository(
			$services->getDBLoadBalancerFactory()->getMainLB( $wgOATHAuthDatabase ),
			new \HashBagOStuff( [
				'maxKey' => 5
			] ),
			$auth
		);
	}
];
