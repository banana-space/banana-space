<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;

class AnalysisConfigBuilderIntegrationTest extends CirrusIntegrationTestCase {

	/**
	 * @covers \CirrusSearch\Maintenance\AnalysisConfigBuilder::__construct
	 */
	public function testSimilarityHook() {
		$this->setTemporaryHook( 'CirrusSearchSimilarityConfig', function ( &$config ) {
			$config['custom'] = [ 'custom' => [] ];
		} );
		$builder = new AnalysisConfigBuilder( 'en', [], new HashSearchConfig( [ 'CirrusSearchSimilarityProfile' => 'default' ] ) );
		$sim = $builder->buildSimilarityConfig();
		$this->assertSame( [ 'custom' => [] ], $sim['custom'] );
	}
}
