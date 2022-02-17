<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Test\DummySearchResultSet;
use Elastica\ResultSet;

/**
 * @covers \CirrusSearch\Fallbacks\FallbackRunnerContextImpl
 */
class FallbackRunnerContextImplTest extends CirrusTestCase {

	public function testMethodResponse() {
		$context = new FallbackRunnerContextImpl( DummySearchResultSet::emptyResultSet(),
			$this->createMock( SearcherFactory::class ), $this->namespacePrefixParser() );
		$this->assertFalse( $context->hasMethodResponse() );
		$methodResponse = $this->createMock( ResultSet::class );
		$context->setSuggestResponse( $methodResponse );
		$this->assertTrue( $context->hasMethodResponse() );
		$this->assertSame( $methodResponse, $context->getMethodResponse() );
		$context->resetSuggestResponse();
		$this->assertFalse( $context->hasMethodResponse() );
	}
}
