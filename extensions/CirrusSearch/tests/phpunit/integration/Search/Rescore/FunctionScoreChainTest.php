<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Search\Rescore\FunctionScoreChain
 */
class FunctionScoreChainTest extends CirrusIntegrationTestCase {

	public function testEmptyFunctionReturnsNull() {
		$chain = $this->createChain( null );
		$query = $chain->buildRescoreQuery();
		$this->assertNull( $query );
	}

	public function testOverrides() {
		$func = [
			'type' => 'script',
			'script' => '...',
			'weight' => 5,
		];
		$chain = $this->createChain( $func, [] );
		$query = $chain->buildRescoreQuery()->toArray();
		$this->assertEquals( 5, $query['function_score']['functions'][0]['weight'] );
		$this->removeTemporaryHook( 'CirrusSearchProfileService' );

		$chain = $this->createChain( $func, [
			'functions.0.weight' => 2,
		] );
		$query = $chain->buildRescoreQuery()->toArray();
		$this->assertEquals( 2, $query['function_score']['functions'][0]['weight'] );
		$this->removeTemporaryHook( 'CirrusSearchProfileService' );
	}

	public function implProvider() {
		return [
			[ [ 'type' => 'boostlinks' ] ],
			[ [ 'type' => 'recency' ] ],
			[ [ 'type' => 'templates' ] ],
			[ [ 'type' => 'namespaces' ] ],
			[ [ 'type' => 'language' ] ],
			[ [ 'type' => 'custom_field', 'params' => [] ] ],
			[ [ 'type' => 'script', 'script' => '...' ] ],
			[ [ 'type' => 'logscale_boost', 'params' => [
				'midpoint' => 5,
				'scale' => 100,
				'field' => 'magic!',
			] ] ],
			[ [ 'type' => 'satu', 'params' => [
				'k' => 42,
				'field' => 'more magic!',
			] ] ],
			[ [ 'type' => 'log_multi', 'params' => [
				'impact' => 5,
				'field' => 'really?',
			] ] ],
			[ [ 'type' => 'geomean', 'params' => [
				'impact' => 4,
				'members' => [
					[ 'type' => 'satu', 'params' => [ 'k' => 42, 'field' => 'x' ] ],
					[ 'type' => 'satu', 'params' => [ 'k' => 420, 'field' => 'y' ] ],
				],
			] ] ],
			[ [ 'type' => 'term_boost', 'params' => [
				'some field' => [ 'some field content' => 17 ],
			] ] ],
		];
	}

	private function createChain( $func, array $overrides = [] ) {
		$this->setTemporaryHook( 'CirrusSearchProfileService',
			function ( $service ) use ( $func ) {
				$service->registerArrayRepository(
					SearchProfileService::RESCORE_FUNCTION_CHAINS,
					'name',
					[
						'phpunit' => [
							'functions' => $func ? [ $func ] : [],
						],
					]
				);
			} );
		$config = $this->newHashSearchConfig( [
			'CirrusSearchPreferRecentDefaultDecayPortion' => 77,
			'CirrusSearchPreferRecentDefaultHalfLife' => 66,
			'CirrusSearchLanguageWeight' => [ 'user' => 5 ],
			'CirrusSearchBoostTemplates' => [
				'Some Page' => 1.23,
			],
			'CirrusSearchNamespaceWeights' => [],
		], [ HashSearchConfig::FLAG_INHERIT ], new HashSearchConfig( [] ) );
		$this->assertTrue( $config->isLocalWiki(), 'only local wiki runs profile hook' );
		$context = new SearchContext( $config );
		return new FunctionScoreChain( $context, 'phpunit', $overrides );
	}

	/**
	 * @dataProvider implProvider
	 */
	public function testImplementationAvailable( array $func ) {
		$chain = $this->createChain( $func );
		$this->assertNotNull( $chain->buildRescoreQuery() );
	}
}
