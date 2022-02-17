<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Extra\Query\SourceRegex;
use CirrusSearch\HashSearchConfig;
use Elastica\Query\BoolQuery;

/**
 * @covers \CirrusSearch\Query\InTitleFeature
 * @covers \CirrusSearch\Query\BaseRegexFeature
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 * @group CirrusSearch
 */
class InTitleFeatureTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	public function parseProvider() {
		$defaults = [
			'fields' => [ 'title', 'title.plain', 'redirect.title', 'redirect.title.plain' ],
			'default_operator' => 'AND',
			'allow_leading_wildcard' => true,
			'fuzzy_prefix_length' => 2,
			'rewrite' => 'top_terms_boost_1024',
		];
		return [
			'basic search' => [
				[ 'query_string' => $defaults + [
					'query' => 'bridge',
				] ],
				'bridge ',
				'intitle:bridge',
			],
			'fuzzy search' => [
				[ 'query_string' => $defaults + [
					'query' => 'bridge~2',
				] ],
				'bridge~2 ',
				'intitle:bridge~2',
			],
			'gracefully handles titles including ~' => [
				[ 'query_string' => $defaults + [
					'query' => 'this\~that',
				] ],
				'this~that ',
				'intitle:this~that',
			],
			'maintains provided quotes' => [
				[ 'query_string' => $defaults + [
					'query' => '"something or other"',
				] ],
				'"something or other" ',
				'intitle:"something or other"',
			],
			'contains a star' => [
				[ 'query_string' => [
					'query' => 'zomg*',
					'fields' => [ 'title.plain', 'redirect.title.plain' ],
				] + $defaults ],
				'zomg* ',
				'intitle:zomg*'
			],
		];
	}

	/**
	 * @dataProvider parseProvider
	 */
	public function testParse( array $expectedQuery, $expectedTerm, $term ) {
		$config = new HashSearchConfig( [
			'LanguageCode' => 'en',
			'CirrusSearchAllowLeadingWildcard' => true,
		] );
		$feature = new InTitleFeature( $config );
		$this->assertCrossSearchStrategy( $feature, $term, CrossSearchStrategy::allWikisStrategy() );
		$this->assertFilter( $feature, $term, $expectedQuery, [], $config );
		$this->assertNoHighlighting( $feature, $term );

		$this->assertRemaining( $feature, $term, $expectedTerm );
	}

	public function testNegatingDoesntKeepTerm() {
		$feature = new InTitleFeature( new HashSearchConfig( [] ) );
		$this->assertRemaining( $feature, '-intitle:mediawiki', '' );
	}

	/**
	 * @dataProvider provideRegexQueries
	 * @param $query
	 * @param $expectedRemaining
	 * @param $negated
	 * @param $filterValue
	 */
	public function testRegex( $query, $expectedRemaining, $negated, $filterValue, $insensitive ) {
		$filterCallback = null;
		if ( $filterValue !== null ) {
			$filterCallback = function ( BoolQuery $x ) use ( $filterValue, $insensitive ) {
				$this->assertTrue( $x->hasParam( 'should' ) );
				$this->assertIsArray( $x->getParam( 'should' ) );
				$this->assertCount( 2, $x->getParam( 'should' ) );
				$regex = $x->getParam( 'should' )[0];
				$this->assertInstanceOf( SourceRegex::class, $regex );
				$this->assertEquals( $filterValue, $regex->getParam( 'regex' ) );
				$this->assertEquals( 'title.trigram', $regex->getParam( 'ngram_field' ) );
				$this->assertEquals( !$insensitive, $regex->getParam( 'case_sensitive' ) );
				$regex = $x->getParam( 'should' )[1];
				$this->assertInstanceOf( SourceRegex::class, $regex );
				$this->assertEquals( $filterValue, $regex->getParam( 'regex' ) );
				$this->assertEquals( 'redirect.title.trigram', $regex->getParam( 'ngram_field' ) );
				$this->assertEquals( !$insensitive, $regex->getParam( 'case_sensitive' ) );

				return true;
			};
		}

		$feature = new InTitleFeature( new HashSearchConfig(
			[
				'CirrusSearchEnableRegex' => true,
				'CirrusSearchWikimediaExtraPlugin' => [ 'regex' => [ 'use' => true ] ]
			],
			[ HashSearchConfig::FLAG_INHERIT ]
		) );

		$this->assertFilter( $feature, $query, $filterCallback, [] );
		$this->assertExpandedData( $feature, $query, [], [] );
		if ( $filterValue !== null ) {
			$parsedValue = [
				'type' => 'regex',
				'pattern' => $filterValue,
				'insensitive' => $insensitive,
			];
			$this->assertParsedValue( $feature, $query, $parsedValue, [] );
			$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::hostWikiOnlyStrategy() );
			$highlightQuery = [
				'pattern' => $filterValue,
				'insensitive' => $insensitive
			];

			if ( !$negated ) {
				$this->assertHighlighting( $feature, $query,
					[ 'title.plain', 'redirect.title.plain' ],
					[ $highlightQuery, $highlightQuery ] );
			}
		}

		// TODO: remove, should be a parser test
		$this->assertRemaining( $feature, $query, $expectedRemaining );
	}

	public static function provideRegexQueries() {
		return [
			'supports simple regex' => [
				'intitle:/bar/',
				'',
				false,
				'bar',
				false,
			],
			'supports simple case insensitive regex' => [
				'intitle:/bar/i',
				'',
				false,
				'bar',
				true,
			],
			'supports negation' => [
				'-intitle:/bar/',
				'',
				true,
				'bar',
				false,
			],
			'supports negation simple case insensitive regex' => [
				'-intitle:/bar/i',
				'',
				true,
				'bar',
				true,
			],
			'do not unescape the regex' => [
				'intitle:/foo\/bar/',
				'',
				false,
				'foo\\/bar',
				false,
			],
			'do not unescape the regex and keep insensitive flag' => [
				'intitle:/foo\/bar/i',
				'',
				false,
				'foo\\/bar',
				true,
			],
		];
	}

	public function testEmpty() {
		// TODO: remove, should be a parser test
		$feature = new InTitleFeature( new HashSearchConfig( [] ) );
		$this->assertNotConsumed( $feature, "foo bar" );
	}

	public function testDisabled() {
		$feature = new InTitleFeature( new HashSearchConfig( [] ) );
		$this->assertParsedValue( $feature, 'intitle:/test/',
			[
				'type' => 'regex',
				'pattern' => 'test',
				'insensitive' => false,
			],
			[ [ 'cirrussearch-feature-not-available', 'intitle regex' ] ] );
	}
}
