<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Searcher;
use CirrusSearch\Test\DummySearchResultSet;
use Elastica\Query;
use Elastica\Response;
use Elastica\Result;

class FallbackMethodTraitTest extends BaseFallbackMethodTest {
	public function provideTestResultThreshold() {
		return [
			'threshold is not reached' => [
				1,
				0,
				[],
				false
			],
			'threshold is not reached even with interwiki results' => [
				1,
				0,
				[ 0, 0 ],
				false,
			],
			'threshold is reached' => [
				1,
				1,
				[],
				true
			],
			'threshold is reached reading interwiki results' => [
				1,
				0,
				[ 0, 1, 0 ],
				true
			],
			'threshold can be greater than 1 and not reached' => [
				3,
				2,
				[],
				false
			],
			'threshold can be greater than 1 and not reached with interwiki results' => [
				3,
				0,
				[ 0, 2, 0 ],
				false,
			],
			'threshold can be greater than 1 and reached' => [
				3,
				3,
				[],
				true
			],
			'threshold can be greater than 1 and reached with interwiki results' => [
				3,
				0,
				[ 0, 3, 0 ],
				true,
			],
			'threshold can be greater than 1 and exceeded' => [
				3,
				5,
				[],
				true
			],
			'threshold can be greater than 1 and exceeded with interwiki results' => [
				3,
				0,
				[ 0, 5, 0 ],
				true,
			],
		];
	}

	/**
	 * @dataProvider provideTestResultThreshold
	 * @covers \CirrusSearch\Fallbacks\FallbackMethodTrait::resultsThreshold()
	 */
	public function testResultThreshold( $threshold, $mainTotal, array $interwikiTotals, $met ) {
		$resultSet = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), $mainTotal, $interwikiTotals );
		$mock = $this->getMockForTrait( FallbackMethodTrait::class );
		$this->assertEquals( $met, $mock->resultsThreshold( $resultSet, $threshold ) );
		if ( $threshold === 1 ) {
			// Test default method param
			$this->assertEquals( $mock->resultsThreshold( $resultSet ),
				$mock->resultsThreshold( $resultSet, $threshold ) );
		}
	}

	/**
	 * @covers \CirrusSearch\Fallbacks\FallbackMethodTrait::resultContainsFullyHighlightedMatch()
	 */
	public function testResultContainsFullyHighlightedMatch() {
		$mock = $this->getMockForTrait( FallbackMethodTrait::class );

		$resultset = new \Elastica\ResultSet( new Response( [] ), new Query(), [] );
		$this->assertFalse( $mock->resultContainsFullyHighlightedMatch( $resultset ) );

		$resultset = new \Elastica\ResultSet( new Response( [] ), new Query(), [
			new Result( [] )
		] );
		$this->assertFalse( $mock->resultContainsFullyHighlightedMatch( $resultset ) );

		$resultset = new \Elastica\ResultSet( new Response( [] ), new Query(), [
			new Result( [
				'highlight' => [
					'title' => 'foo' . Searcher::HIGHLIGHT_PRE_MARKER . 'bar' . Searcher::HIGHLIGHT_POST_MARKER
				]
			] )
		] );
		$this->assertFalse( $mock->resultContainsFullyHighlightedMatch( $resultset ) );

		$resultset = new \Elastica\ResultSet( new Response( [] ), new Query(), [
			new Result( [
				'highlight' => [
					'title' => Searcher::HIGHLIGHT_PRE_MARKER . 'foo bar' . Searcher::HIGHLIGHT_POST_MARKER
				]
			] )
		] );
		$this->assertFalse( $mock->resultContainsFullyHighlightedMatch( $resultset ) );
	}

	public function provideTestNotRewrittenOnQueryNotRewritable() {
		return [
			'rewritten if query is rewritable, threshold met' => [
				[], 2, "foo", true, "bar", true, true
			],
			'not rewritten if query is not rewritable' => [
				[], 1, "foo", false, "bar", true, false
			],
			'not rewritten if query if threshold not met' => [
				[], 0, "foo", true, "bar", true, false
			],
			'not rewritten if rewritten query is too long' => [
				[ 'CirrusSearchMaxFullTextQueryLength' => 3 ], 2, "foo", true, "foobar", true, false
			],
			'not rewritten if costly call not allowed' => [
				[], 2, "foo", true, "bar", false, false
			],
			'not rewritten if not a simple bag of word query' => [
				[], 2, "foo AND bar", true, "bar", true, false
			],
		];
	}

	/**
	 * @dataProvider provideTestNotRewrittenOnQueryNotRewritable
	 * @covers \CirrusSearch\Fallbacks\FallbackMethodTrait::maybeSearchAndRewrite()
	 */
	public function testNotRewrittenOnQueryNotRewritable(
		array $config,
		int $threshold,
		string $initialQueryString,
		bool $allowRewrite,
		string $rewrittenQueryString,
		bool $costlyCallAllowed,
		bool $isRewritten
	) {
		/**
		 * @var FallbackMethodTrait $mock
		 */
		$mock = $this->getMockForTrait( FallbackMethodTrait::class );
		$initialResult = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 1 );

		$rewrittenResults = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 2 );

		$searchConfig = $this->newHashSearchConfig( $config );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( $searchConfig, $initialQueryString, $this->namespacePrefixParser() )
			->setAllowRewrite( $allowRewrite )
			->build();
		if ( $isRewritten ) {
			$rewritten = SearchQueryBuilder::forRewrittenQuery( $query, $rewrittenQueryString, $this->namespacePrefixParser() )->build();
		} else {
			$rewritten = null;
		}
		$context = new FallbackRunnerContextImpl( $initialResult,
			$this->getSearcherFactoryMock( $rewritten, $rewrittenResults ),
			$this->namespacePrefixParser() );

		if ( !$costlyCallAllowed ) {
			$dummyQuery = SearchQueryBuilder::newFTSearchQueryBuilder( $searchConfig, 'foo', $this->namespacePrefixParser() )
				->build();
			$context->makeSearcher( $dummyQuery );
		}

		$status = $mock->maybeSearchAndRewrite( $context, $query, $rewrittenQueryString, $rewrittenQueryString, $threshold );
		$actuallyRewritten = $status->apply( $initialResult );
		if ( $isRewritten ) {
			$this->assertSame( $actuallyRewritten, $rewrittenResults );
		} else {
			$this->assertSame( $actuallyRewritten, $initialResult );
		}
	}
}
