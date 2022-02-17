<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Connection;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileService;
use Elastica\Client;
use Elastica\Response;
use RuntimeException;

/**
 * @covers \CirrusSearch\Maintenance\IndexTemplateBuilder
 */
class IndexTemplateBuilderTest extends CirrusIntegrationTestCase {
	public function test() {
		$testProfile = 'glent';
		$expected = CirrusIntegrationTestCase::fixturePath( "indexTemplateBuilder/$testProfile.expected" );
		$client = $this->createMock( Client::class );
		$client->expects( $this->once() )
			->method( 'request' )
			->will( $this->returnCallback(
				function ( $path, $method, $data ) use( $expected ) {
					$fixture = [
						'path' => $path,
						'method' => $method,
						'data' => $data
					];
					$this->assertFileContains(
						$expected,
						CirrusIntegrationTestCase::encodeFixture( $fixture ),
						CirrusIntegrationTestCase::canRebuildFixture()
					);
					return new Response( [], 200 );
				} )
			);
		$config = new HashSearchConfig( [] );
		$connection = $this->createMock( Connection::class );
		$connection->method( 'getClient' )
			->willReturn( $client );

		$connection->method( 'getConfig' )
			->willReturn( $config );

		$profile = ( $config )->getProfileService()
			->loadProfileByName( SearchProfileService::INDEX_LOOKUP_FALLBACK, $testProfile );
		$this->assertArrayHasKey( 'index_template', $profile );
		$tmplBuilder = IndexTemplateBuilder::build( $connection, $profile['index_template'], [ 'analysis-icu' ] );
		$tmplBuilder->execute();
	}

	public function testFailure() {
		$testProfile = 'glent';
		$client = $this->createMock( Client::class );
		$client->expects( $this->once() )
			->method( 'request' )
			->willReturn( new Response( [], 400 ) );
		$config = new HashSearchConfig( [] );
		$connection = $this->createMock( Connection::class );
		$connection->method( 'getClient' )
			->willReturn( $client );

		$connection->method( 'getConfig' )
			->willReturn( $config );

		$profile = ( $config )->getProfileService()
			->loadProfileByName( SearchProfileService::INDEX_LOOKUP_FALLBACK, $testProfile );
		$this->assertArrayHasKey( 'index_template', $profile );
		/** @var Connection $connection */
		$tmplBuilder = IndexTemplateBuilder::build(
			$connection,
			$profile['index_template'],
			[ 'analysis-icu' ]
		);
		$this->expectException( RuntimeException::class );
		$tmplBuilder->execute();
	}
}
