<?php

namespace CirrusSearch;

use Elastica\Query;

/**
 * @covers \CirrusSearch\CirrusDebugOptions
 */
class CirrusDebugOptionsTest extends CirrusIntegrationTestCase {

	public function testEmptyOptions() {
		$request = new \FauxRequest();
		$debugOptions = CirrusDebugOptions::fromRequest( $request );
		$this->assertNone( $debugOptions );
	}

	public function testFullOptions() {
		$request = new \FauxRequest( [
			'cirrusMLRModel' => 'my_model',
			'cirrusSuppressSuggest' => '',
			'cirrusCompletionVariant' => [ 'foo', 'bar' ],
			'cirrusDumpQuery' => '',
			'cirrusDumpQueryAST' => '',
			'cirrusDumpResult' => '',
			'cirrusExplain' => 'pretty'
		] );
		$debugOptions = CirrusDebugOptions::fromRequest( $request );
		$this->assertEquals( 'my_model', $debugOptions->getCirrusMLRModel() );
		$this->assertEquals( 'pretty', $debugOptions->getCirrusExplainFormat() );
		$this->assertTrue( $debugOptions->isCirrusDumpQuery() );
		$this->assertTrue( $debugOptions->isCirrusDumpQueryAST() );
		$this->assertTrue( $debugOptions->isCirrusDumpResult() );
		$this->assertEquals( [ 'foo', 'bar' ], $debugOptions->getCirrusCompletionVariant() );
		$this->assertTrue( $debugOptions->isReturnRaw() );
		$this->assertTrue( $debugOptions->isDumpAndDie() );
	}

	public function testNone() {
		$this->assertNone( CirrusDebugOptions::defaultOptions() );
	}

	public function testUnitTests() {
		$debugOptions = CirrusDebugOptions::forDumpingQueriesInUnitTests();
		$this->assertNull( $debugOptions->getCirrusMLRModel() );
		$this->assertNull( $debugOptions->getCirrusExplainFormat() );
		$this->assertTrue( $debugOptions->isCirrusDumpQuery() );
		$this->assertFalse( $debugOptions->isCirrusDumpResult() );
		$this->assertNull( $debugOptions->getCirrusCompletionVariant() );
		$this->assertTrue( $debugOptions->isReturnRaw() );
		$this->assertFalse( $debugOptions->isDumpAndDie() );
	}

	public function testRelTest() {
		$debugOptions = CirrusDebugOptions::forRelevanceTesting( true );
		$this->assertFalse( $debugOptions->isReturnRaw() );
		$this->assertTrue( $debugOptions->getCirrusExplainFormat() );
	}

	private function assertNone( CirrusDebugOptions $debugOptions ) {
		$this->assertNull( $debugOptions->getCirrusMLRModel() );
		$this->assertNull( $debugOptions->getCirrusExplainFormat() );
		$this->assertFalse( $debugOptions->isCirrusDumpQuery() );
		$this->assertFalse( $debugOptions->isCirrusDumpQueryAST() );
		$this->assertFalse( $debugOptions->isCirrusDumpResult() );
		$this->assertNull( $debugOptions->getCirrusCompletionVariant() );
		$this->assertFalse( $debugOptions->isReturnRaw() );
		$this->assertFalse( $debugOptions->isDumpAndDie() );
	}

	public function testApplyToQuery() {
		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [ 'cirrusExplain' => 'pretty' ] ) );
		$query = new Query();
		$options->applyDebugOptions( $query );
		$this->assertTrue( $query->getParam( 'explain' ) );

		$options = CirrusDebugOptions::defaultOptions();
		$query = new Query();
		$options->applyDebugOptions( $query );
		$this->assertFalse( $query->hasParam( 'explain' ) );
	}

	public function testNeverCache() {
		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [] ) );
		$this->assertFalse( $options->mustNeverBeCached() );

		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [
			'cirrusExplain' => 'pretty'
		] ) );
		$this->assertTrue( $options->mustNeverBeCached() );

		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [
			'cirrusExplain' => 'raw'
		] ) );
		$this->assertTrue( $options->mustNeverBeCached() );

		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [
			'cirrusExplain' => 'unknown and ignored value'
		] ) );
		$this->assertFalse( $options->mustNeverBeCached() );

		$options = CirrusDebugOptions::fromRequest( new \FauxRequest( [
			'cirrusDumpResult' => '1'
		] ) );
		$this->assertTrue( $options->mustNeverBeCached() );
	}

}
