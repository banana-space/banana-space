<?php

namespace CirrusSearch;

use CirrusSearch\BuildDocument\Completion\DefaultSortSuggestionsBuilder;
use CirrusSearch\BuildDocument\Completion\SuggestBuilder;
use CirrusSearch\BuildDocument\Completion\SuggestScoringMethodFactory;

/**
 * @covers \CirrusSearch\BuildDocument\Completion\SuggestBuilder
 */
class SuggestBuilderIntegrationTest extends \MediaWikiIntegrationTestCase {

	/**
	 * Building crossns  suggestion call Title::getArticleID() which relies on MWServices
	 * and this cannot be a unit test
	 */
	public function testCrossNSRedirects() {
		$builder = $this->buildBuilder();
		$score = 10;
		$doc = [
			'id' => 123,
			'title' => 'Navigation',
			'namespace' => 12,
			'redirect' => [
				[ 'title' => 'WP:HN', 'namespace' => 0 ],
				[ 'title' => 'WP:NAV', 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];

		$score = (int)( SuggestBuilder::CROSSNS_DISCOUNT * $score );

		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Navigation',
					'namespace' => 12
				],
				'suggest' => [
					'input' => [ 'WP:HN' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'WP:HN' ],
					'weight' => $score
				],
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Navigation',
					'namespace' => 12
				],
				'suggest' => [
					'input' => [ 'WP:NAV' ],
					'weight' => $score
				],
				'suggest-stop' => [
					'input' => [ 'WP:NAV' ],
					'weight' => $score
				],
			]
		];
		$suggestions = $this->buildSuggestions( $builder, $doc );
		$this->assertSame( $expected, $suggestions );
	}

	public function testDefaultSortAndCrossNS() {
		$score = 10;
		$crossNsScore = (int)( $score * SuggestBuilder::CROSSNS_DISCOUNT );
		// Test Cross namespace the defaultsort should not be added
		// to cross namespace redirects
		$doc = [
			'id' => 123,
			'title' => 'Guidelines for XYZ',
			'namespace' => NS_HELP,
			'defaultsort' => 'XYZ, Guidelines',
			'redirect' => [
				[ 'title' => "GXYZ", 'namespace' => 0 ],
				[ 'title' => "XYZG", 'namespace' => 0 ],
			],
			'incoming_links' => $score
		];
		$expected = [
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Guidelines for XYZ',
					'namespace' => NS_HELP,
				],
				'suggest' => [
					'input' => [ 'GXYZ' ],
					'weight' => $crossNsScore
				],
				'suggest-stop' => [
					'input' => [ 'GXYZ' ],
					'weight' => $crossNsScore
				]
			],
			[
				'source_doc_id' => 123,
				'target_title' => [
					'title' => 'Guidelines for XYZ',
					'namespace' => NS_HELP,
				],
				'suggest' => [
					'input' => [ 'XYZG' ],
					'weight' => $crossNsScore
				],
				'suggest-stop' => [
					'input' => [ 'XYZG' ],
					'weight' => $crossNsScore
				]
			]
		];

		$suggestions = $this->buildSuggestions( $this->buildBuilder(), $doc );
		$this->assertSame( $expected, $suggestions );
	}

	private function buildSuggestions( $builder, $doc ) {
		$id = $doc['id'];
		unset( $doc['id'] );
		return array_map(
			function ( $x ) {
				$dat = $x->getData();
				unset( $dat['batch_id'] );
				return $dat;
			},
			$builder->build( [ [ 'id' => $id, 'source' => $doc ] ] )
		);
	}

	private function buildBuilder() {
		$extra = [
			new DefaultSortSuggestionsBuilder(),
		];
		return new SuggestBuilder( SuggestScoringMethodFactory::getScoringMethod( 'incomingLinks' ), $extra );
	}
}
