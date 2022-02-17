<?php

use CirrusSearch\Api\ConfigDump;

/**
 * @covers \CirrusSearch\Api\ProfilesDump
 */
class ConfigDumpTest extends \CirrusSearch\CirrusIntegrationTestCase {
	/**
	 * @throws MWException
	 */
	public function testHappyPath() {
		$request = new FauxRequest( [] );
		$context = new RequestContext();
		$context->setRequest( $request );
		$main = new ApiMain( $context );

		$api = new ConfigDump( $main, 'name', '' );
		$api->execute();

		$result = $api->getResult();
		$this->assertNull( $result->getResultData( [ 'wgSecretKey' ] ),
			"MW Core config should not be exported" );
		$this->assertNotNull( $result->getResultData( [ 'CirrusSearchConnectionAttempts' ] ),
			"CirrusSearch config should be exported" );

		$namespaceMap = $result->getResultData( [ 'CirrusSearchConcreteNamespaceMap' ] );
		$this->assertNotNull( $namespaceMap, "Must include namespace map" );
		// Arbitrary selection of namespaces that should exist.
		foreach ( [ NS_MAIN, NS_TALK, NS_HELP ] as $ns ) {
			$this->assertArrayHasKey( $ns, $namespaceMap );
		}
	}
}
