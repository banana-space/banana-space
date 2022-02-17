<?php
/**
 * Services for CirrusSearch extensions
 */

use CirrusSearch\Query\DeepcatFeature;
use MediaWiki\MediaWikiServices;
use MediaWiki\Sparql\SparqlClient;

return [
	// SPARQL client for deep category search
	'CirrusCategoriesClient' => function ( MediaWikiServices $services ) {
		$config = $services->getMainConfig();
		$client = new SparqlClient( $config->get( 'CirrusSearchCategoryEndpoint' ),
			$services->getHttpRequestFactory() );
		$client->setTimeout( DeepcatFeature::TIMEOUT );
		$client->setClientOptions( [
			'userAgent' => DeepcatFeature::USER_AGENT,
		] );
		return $client;
	},
];
