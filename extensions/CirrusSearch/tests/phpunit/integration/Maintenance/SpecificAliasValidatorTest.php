<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Maintenance\Validators\SpecificAliasValidator;
use Elastica\Client;
use Elastica\Index;

/**
 * @covers \CirrusSearch\Maintenance\Validators\SpecificAliasValidator
 */
class SpecificAliasValidatorTest extends CirrusIntegrationTestCase {

	public function testValidator() {
		$client = $this->createMock( Client::class );

		$index = new Index( $client, 'indexName' );

		$client->method( "getIndex" )
			->willReturn( $index );

		$validator = new SpecificAliasValidator(
			$client,
			"aliasName",
			"specificIndexName",
			true,
			$this->createMock( Reindexer::class ),
			[],
			[],
			false,
			false,
			$this->createMock( Printer::class )
		);

		$this->assertEquals(
			\Status::newGood(),
			$validator->updateFreeIndices( [ "indexName" ] )
		);
	}
}
