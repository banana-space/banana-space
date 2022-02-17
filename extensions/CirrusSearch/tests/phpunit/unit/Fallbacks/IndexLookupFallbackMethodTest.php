<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusIntegrationTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Profile\SearchProfileException;
use CirrusSearch\Search\SearchQueryBuilder;
use CirrusSearch\Test\DummySearchResultSet;
use Elastica\Client;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet\DefaultBuilder;
use HtmlArmor;

/**
 * @covers \CirrusSearch\Fallbacks\IndexLookupFallbackMethod
 * @covers \CirrusSearch\Fallbacks\FallbackMethodTrait
 */
class IndexLookupFallbackMethodTest extends BaseFallbackMethodTest {

	public function provideTest() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'indexLookupFallbackMethodResponses/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$resp = new Response( $fixture['response'], 200 );
			$resultSet = ( new DefaultBuilder() )->buildResultSet( $resp, new Query() );
			$tests[$testName] = [
				$fixture['query'],
				$resultSet,
				$fixture['approxScore'],
				$fixture['suggestion'],
				$fixture['suggestionSnippet'],
				$fixture['rewritten'] ?? false,
			];
		}

		return $tests;
	}

	/**
	 * @dataProvider provideTest
	 */
	public function test(
		$queryString,
		\Elastica\ResultSet $response,
		$expectedApproxScore,
		$suggestion,
		$suggestionSnippet,
		$rewritten
	) {
		$config = new HashSearchConfig( [] );
		$query = SearchQueryBuilder::newFTSearchQueryBuilder( $config, $queryString, $this->namespacePrefixParser() )
			->setAllowRewrite( true )
			->build();

		$rewrittenResults = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), 1 );
		$rewrittenQuery = null;
		if ( $suggestion != null && $rewritten ) {
			$rewrittenQuery = SearchQueryBuilder::forRewrittenQuery( $query, $suggestion, $this->namespacePrefixParser() )
				->build();
		}
		$searcherFactory = $this->getSearcherFactoryMock( $rewrittenQuery, $rewrittenResults );
		/**
		 * @var IndexLookupFallbackMethod $fallback
		 */
		$fallback = new IndexLookupFallbackMethod( $query, 'lookup_index', [],
			'lookup_suggestion_field', [], [], [] );
		$this->assertNotNull( $fallback->getSearchRequest(
			$this->getMockBuilder( Client::class )->disableOriginalConstructor()->getMock() ) );
		$initialResults = DummySearchResultSet::fakeTotalHits( $this->newTitleHelper(), $rewritten ? 0 : 1 );
		$context = new FallbackRunnerContextImpl( $initialResults, $searcherFactory, $this->namespacePrefixParser() );
		$this->assertSame( 0.0, $fallback->successApproximation( $context ), "No success without a response" );
		$context->setSuggestResponse( $response );
		$this->assertSame( $expectedApproxScore, $fallback->successApproximation( $context ) );
		if ( $expectedApproxScore > 0 ) {
			$status = $fallback->rewrite( $context );
			$actualNewResults = $status->apply( $initialResults );
			if ( $rewritten ) {
				$this->assertSame( FallbackStatus::ACTION_REPLACE_LOCAL_RESULTS, $status->getAction() );
				$this->assertSame( $rewrittenResults, $actualNewResults );
				$this->assertSame( $suggestion, $rewrittenResults->getQueryAfterRewrite() );
				$this->assertSame( $suggestionSnippet,
					HtmlArmor::getHtml( $rewrittenResults->getQueryAfterRewriteSnippet() ) );
			} else {
				$this->assertSame( FallbackStatus::ACTION_SUGGEST_QUERY, $status->getAction() );
				$this->assertSame( $initialResults, $actualNewResults );
				$this->assertSame( $suggestion, $actualNewResults->getSuggestionQuery() );
				$this->assertSame( $suggestionSnippet, HtmlArmor::getHtml( $actualNewResults->getSuggestionSnippet() ) );
			}
		}
	}

	public function provideTestLookupQueries() {
		$tests = [];
		foreach ( CirrusIntegrationTestCase::findFixtures( 'indexLookupFallbackMethod/*.config' ) as $testFile ) {
			$testName = substr( basename( $testFile ), 0, -7 );
			$fixture = CirrusIntegrationTestCase::loadFixture( $testFile );
			$expectedFile = dirname( $testFile ) . "/$testName.expected";
			$tests[$testName] = [
				$expectedFile,
				$fixture['query'],
				$fixture['namespaces'],
				$fixture['offset'],
				$fixture['with_dym'] ?? true,
				$fixture['profile'],
				$fixture['profile_params'] ?? [],
			];
		}
		return $tests;
	}

	/**
	 * @dataProvider provideTestLookupQueries
	 */
	public function testSuggestQuery(
		$expectedFile,
		$query,
		$namespaces,
		$offset,
		$withDYMSuggestion,
		$profile,
		array $profileParams
	) {
		$config = [
			'_wikiID' => 'my_test_wiki',
			'CirrusSearchIndexLookupFallbackProfiles' => [
				'my_profile' => $profile
			]
		];
		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				$this->newHashSearchConfig( $config ),
				$query,
				$this->namespacePrefixParser()
			)
			->setInitialNamespaces( $namespaces )
			->setOffset( $offset )
			->setWithDYMSuggestion( $withDYMSuggestion )
			->build();
		/**
		 * @var IndexLookupFallbackMethod $method
		 */
		$method = IndexLookupFallbackMethod::build( $query,
			[ 'profile' => 'my_profile', 'profile_params' => $profileParams ] );
		$searchQuery = null;
		if ( $method !== null ) {
			$query = $method->getSearchRequest(
				$this->getMockBuilder( Client::class )
					->disableOriginalConstructor()
					->getMock()
			);
			if ( $query !== null ) {
				$searchQuery = [
					'path' => $query->getPath(),
					'options' => $query->getOptions(),
					'query' => $query->getQuery()->toArray(),
				];
			}
		}

		$this->assertFileContains(
			CirrusIntegrationTestCase::fixturePath( $expectedFile ),
			CirrusIntegrationTestCase::encodeFixture( $searchQuery ),
			self::canRebuildFixture()
		);
	}

	public function testBuild() {
		$config = [
			'CirrusSearchIndexLookupFallbackProfiles' => [
				'my_profile' => [
					'index' => 'lookup_index',
					'params' => [
						'match.lookup_suggestion_field' => '__query__',
					],
					'query' => [
						'match' => [
							'lookup_query_field' => '{{query}}',
						]
					],
					'suggestion_field' => 'lookup_suggestion_field',
					'metric_fields' => []
				]
			]
		];

		$params = [ 'profile' => 'my_profile' ];

		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				$this->newHashSearchConfig( $config ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setWithDYMSuggestion( false )
			->build();
		$this->assertNull( IndexLookupFallbackMethod::build( $query, $params ) );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				$this->newHashSearchConfig( $config ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setWithDYMSuggestion( true )
			->build();
		$this->assertNotNull( IndexLookupFallbackMethod::build( $query, $params ) );

		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				$this->newHashSearchConfig( $config ),
				'foo bar',
				$this->namespacePrefixParser()
			)
			->setWithDYMSuggestion( true )
			->setOffset( 10 )
			->build();
		$this->assertNull( IndexLookupFallbackMethod::build( $query, $params ) );
	}

	/**
	 *
	 */
	public function profileInvalidProfileParams() {
		return [
			[
				[
					'query' => 'random'
				],
				"Invalid profile parameter [random]"
			],
			[
				[
					'query' => 'random:test'
				],
				"Unsupported profile parameter type [random]"
			],
			[
				[
					'query' => 'params:missing'
				],
				"Missing profile parameter [missing]"
			]
		];
	}

	/**
	 * @dataProvider profileInvalidProfileParams
	 */
	public function testInvalidProfileParam( array $queryParams, $excMessage ) {
		$query = SearchQueryBuilder::newFTSearchQueryBuilder(
				$this->newHashSearchConfig( [] ),
				'foo',
				$this->namespacePrefixParser()
			)
			->setWithDYMSuggestion( true )
			->build();
		$lookup = new IndexLookupFallbackMethod( $query, 'index', [ 'query' => 'test' ],
			'field', $queryParams, [], [] );
		try {
			$lookup->getSearchRequest( $this->createMock( Client::class ) );
			$this->fail( "Expected " . SearchProfileException::class . " to be thrown" );
		} catch ( SearchProfileException $e ) {
			$this->assertSame( $excMessage, $e->getMessage() );
		}
	}
}
