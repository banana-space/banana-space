<?php

namespace CirrusSearch\Parser;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Query\InCategoryFeature;
use CirrusSearch\Query\InSourceFeature;
use CirrusSearch\Query\InTitleFeature;
use CirrusSearch\Query\KeywordFeature;
use CirrusSearch\Query\MoreLikeFeature;
use CirrusSearch\Query\PrefixFeature;

/**
 * @covers \CirrusSearch\Parser\FullTextKeywordRegistry
 * @group CirrusSearch
 */
class FullTextKeywordRegistryTest extends CirrusTestCase {

	public function test() {
		$registry = new FullTextKeywordRegistry( new HashSearchConfig( [] ) );
		// Just verify that some important keywords are provided
		$missingKw = [
			InTitleFeature::class => true,
			InSourceFeature::class => true,
			InCategoryFeature::class => true,
			MoreLikeFeature::class => true,
			PrefixFeature::class => true,
		];
		foreach ( $registry->getKeywords() as $kw ) {
			$this->assertInstanceOf( KeywordFeature::class, $kw );
			$missingKw[get_class( $kw )] = false;

		}
		$this->assertNotContains( true, $missingKw,
			"Keywords implementation " . implode( ",", array_keys( array_filter( $missingKw ) ) ) .
			" must be provided." );
	}
}
