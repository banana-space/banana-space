<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Query\PrefixSearchQueryBuilder
 */
class PrefixSearchQueryBuilderIntegrationTest extends CirrusIntegrationTestCase {

	public function testRejectsOversizeQueries() {
		$qb = new PrefixSearchQueryBuilder();
		$config = $this->newHashSearchConfig( [
			'CirrusSearchPrefixSearchStartsWithAnyWord' => false,
			'CirrusSearchPrefixWeights' => [],
		] );
		$context = new SearchContext( $config );
		// TODO: move to unit once we stop relying on ApiUsageException...
		$this->expectException( \ApiUsageException::class );
		$qb->build( $context, str_repeat( 'a', 4096 ) );
	}
}
