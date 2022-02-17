<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\EmptyInterwikiResolver;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\InterwikiResolver;
use CirrusSearch\LanguageDetector\Detector;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\Search\SearchQuery;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\SearchConfig;
use CirrusSearch\Searcher;
use CirrusSearch\Test\DummySearchResultSet;
use ISearchResultSet;

/**
 * @covers \CirrusSearch\Fallbacks\LangDetectFallbackMethod
 */
class LangDetectFallbackMethodTest extends CirrusIntegrationTestCase {

	public function getSearcherFactoryMock( SearchQuery $query = null, CirrusSearchResultSet $resultSet = null ) {
		$searcherMock = $this->createMock( Searcher::class );
		$searcherMock->expects( $query != null ? $this->once() : $this->never() )
			->method( 'search' )
			->with( $query )
			->willReturn( $resultSet === null ? \Status::newFatal( 'Error' ) : \Status::newGood( $resultSet ) );
		$searcherMock->expects( $query != null ? $this->atMost( 1 ) : $this->never() )
			->method( 'getSearchMetrics' )
			->willReturn( [ 'searcherMetrics' => 'called' ] );

		$mock = $this->createMock( SearcherFactory::class );
		$mock->expects( $this->any() )
			->method( 'makeSearcher' )
			->willReturn( $searcherMock );
		return $mock;
	}

	public function provideTest() {
		return [
			'fallback worked' => [
				'foobar',
				0.5,
				'fr',
				3,
				2,
				10,
			],
			'fallback not triggered because the initial set has enough results' => [
				'foobar',
				0.0,
				null,
				2,
				3,
				0,
			],
			'fallback triggered but it encountered an error during search' => [
				'foobar',
				0.5,
				'fr',
				3,
				2,
				-1,
			],
			'fallback not triggered because the query is complex' => [
				'foo NOT bar',
				0.0,
				null,
				3,
				0,
				0,
			],
			'fallback not triggered because lang detection failed' => [
				'foo',
				0.0,
				null,
				3,
				0,
				0,
			],
			'fallback not triggered because an unsupported language was detected' => [
				'foo',
				0.0,
				'pl',
				3,
				0,
				0,
			],
			'fallback not triggered because same lang was detected' => [
				'foo',
				0.0,
				'en',
				3,
				0,
				0,
			]
		];
	}

	/**
	 * @dataProvider provideTest
	 * @throws \Exception
	 */
	public function test(
		$query,
		$expectedScoreApprox,
		$returnedLang,
		$threshold,
		$initialNumResults,
		$secondTryNumResults
	) {
		$config = $this->newHashSearchConfig( [
			'CirrusSearchInterwikiThreshold' => $threshold,
			'CirrusSearchEnableAltLanguage' => true,
			'LanguageCode' => 'en',
		] );
		$targetWikiConfig = new HashSearchConfig( [
			'_wikiID' => 'targetwiki',
			'LanguageCode' => 'fr',
		] );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( $config, $query, $this->namespacePrefixParser() )
			->setAllowRewrite( true )
			->build();
		$expectedRewrittenResults = $secondTryNumResults >= 0 ?
			DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), $secondTryNumResults ) : null;
		$searcherFactory = null;
		if ( $expectedScoreApprox > 0 ) {
			$searcherFactory = $this->getSearcherFactoryMock(
				SearchQueryBuilder::forCrossLanguageSearch( $targetWikiConfig, $query )->build(),
				$expectedRewrittenResults
			);
		} else {
			$searcherFactory = $this->getSearcherFactoryMock( null, null );
		}
		$fallback = new LangDetectFallbackMethod( $query,
			[
				'never_works_detector' => $this->getLanguageDetector( null ),
				'always_works_but_useless' => $this->getLanguageDetector( 'en' ),
				'tested_detector' => $this->getLanguageDetector( $returnedLang )
			],
			$this->getInterwikiMock( $targetWikiConfig, $returnedLang !== 'en' ? $returnedLang : null ) );

		$initialResults = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), $initialNumResults );
		$context = new FallbackRunnerContextImpl( $initialResults, $searcherFactory, $this->namespacePrefixParser() );
		$this->assertEquals( $expectedScoreApprox, $fallback->successApproximation( $context ) );
		if ( $expectedScoreApprox > 0 ) {
			$status = $fallback->rewrite( $context );
			$rewrittenResults = $status->apply( $initialResults );
			$this->assertSame( $initialResults, $rewrittenResults );
			if ( $expectedRewrittenResults !== null ) {
				$this->assertSame( FallbackStatus::ACTION_ADD_INTERWIKI_RESULTS, $status->getAction() );
				$crossRes = $rewrittenResults->getInterwikiResults( ISearchResultSet::INLINE_RESULTS );
				$this->assertNotNull( $crossRes );
				$this->assertArrayHasKey( $targetWikiConfig->getWikiId(), $crossRes );
				$this->assertSame( $expectedRewrittenResults, $crossRes[$targetWikiConfig->getWikiId()] );
			} else {
				$this->assertSame( FallbackStatus::NO_ACTION, $status->getAction() );
				$this->assertEmpty( $rewrittenResults->getInterwikiResults( ISearchResultSet::INLINE_RESULTS ) );
			}
		}
	}

	public function provideTestNotRunWhenRewriteDisabled() {
		return [
			'allowed' => [ true, 0.5 ],
			'not allowed' => [ false, 0.0 ],
		];
	}

	/**
	 * @dataProvider provideTestNotRunWhenRewriteDisabled()
	 */
	public function testNotRunWhenRewriteDisabled( $allowRewrite, $expectedScore ) {
		$config = new HashSearchConfig( [
			'CirrusSearchInterwikiThreshold' => 2,
			'CirrusSearchEnableAltLanguage' => true,
			'LanguageCode' => 'en',
		] );
		$targetWikiConfig = new HashSearchConfig( [
			'_wikiID' => 'targetwiki',
			'LanguageCode' => 'fr',
		] );

		$query =
			SearchQueryBuilder::newFTSearchQueryBuilder( $config, 'foo', $this->namespacePrefixParser() )
				->setAllowRewrite( $allowRewrite )
				->build();

		$searcherFactory = $this->getSearcherFactoryMock( null, null );
		$fallback = new LangDetectFallbackMethod( $query,
			[ 'tested_detector' => $this->getLanguageDetector( 'fr' ) ],
			$this->getInterwikiMock( $targetWikiConfig, $allowRewrite ? 'fr' : null ) );

		$initialResults = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 0 );
		$context = new FallbackRunnerContextImpl( $initialResults, $searcherFactory, $this->namespacePrefixParser() );
		$this->assertEquals( $expectedScore, $fallback->successApproximation( $context ) );
	}

	public function getLanguageDetector( $expectedLang ) {
		$mock = $this->createMock( Detector::class );
		$mock->expects( $this->atMost( 1 ) )
			->method( 'detect' )
			->willReturn( $expectedLang );
		return $mock;
	}

	public function getInterwikiMock( SearchConfig $targetWikiConfig, $detectedLang = null ) {
		$mock = $this->createMock( InterwikiResolver::class );
		$mock->expects( $detectedLang != null ? $this->once() : $this->never() )
			->method( 'getSameProjectConfigByLang' )
			->willReturn( $detectedLang === 'fr' ? [ 'fr' => $targetWikiConfig ] : [] );
		return $mock;
	}

	public function testBuild() {
		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				new HashSearchConfig( [] ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setCrossLanguageSearch( CrossSearchStrategy::hostWikiOnlyStrategy() )
			->build();
		$this->assertNull( LangDetectFallbackMethod::build( $query, [], new EmptyInterwikiResolver() ) );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				new HashSearchConfig( [ 'CirrusSearchEnableAltLanguage' => false ] ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setCrossLanguageSearch( CrossSearchStrategy::hostWikiOnlyStrategy() )
			->build();
		$this->assertNull( LangDetectFallbackMethod::build( $query, [], new EmptyInterwikiResolver() ) );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				new HashSearchConfig( [ 'CirrusSearchEnableAltLanguage' => false ] ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setCrossLanguageSearch( CrossSearchStrategy::hostWikiOnlyStrategy() )
			->build();
		$this->assertNull( LangDetectFallbackMethod::build( $query, [], new EmptyInterwikiResolver() ) );
	}
}
