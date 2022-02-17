<?php

namespace CirrusSearch\Query;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Extra\Query\SourceRegex;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\Escaper;
use CirrusSearch\Search\Filters;

/**
 * @covers \CirrusSearch\Query\BaseRegexFeature
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 * @covers \CirrusSearch\Query\InSourceFeature
 * @group CirrusSearch
 */
class InSourceTest extends CirrusTestCase {
	use SimpleKeywordFeatureTestTrait;

	/**
	 * @dataProvider provideSimpleQueries
	 * @param string $query
	 * @param string $expectedRemaining
	 * @param string|null $filterValue
	 * @param bool $negated
	 * @throws \MWException
	 */
	public function testSimple( $query, $expectedRemaining, $filterValue, $negated = false ) {
		$qsQuery = null;
		if ( $filterValue !== null ) {
			$qsQuery = Filters::insource( new Escaper( 'en', true ),
				$filterValue );
		}

		$config = new HashSearchConfig( [
			'LanguageCode' => 'en',
			'CirrusSearchAllowLeadingWildcard' => true,
		] );
		$feature = new InSourceFeature( $config );
		if ( $filterValue !== null ) {
			$this->assertCrossSearchStrategy( $feature, $query,	CrossSearchStrategy::allWikisStrategy() );
		}
		if ( $filterValue !== null && !$negated ) {
			$this->assertHighlighting( $feature, $query, 'source_text.plain', [ 'query' => $qsQuery ] );
		} else {
			$this->assertNoHighlighting( $feature, $query );
		}
		$this->assertFilter( $feature, $query, $qsQuery, [], $config );
		// TODO: remove should be a parser test, the keyword is not responsible for this
		$this->assertRemaining( $feature, $query, $expectedRemaining );
	}

	public static function provideSimpleQueries() {
		return [
			'supports unquoted value' => [
				'insource:bar',
				'',
				'bar',
			],
			'stop on first quote' => [
				'insource:bar"bar"',
				'"bar"',
				'bar',
			],
			'FIXME: but does not support escaping quotes' => [
				'insource:bar\"bar',
				'"bar',
				'bar\\',
			],
			'doesnt stop on /' => [
				'insource:bar/bar',
				'',
				'bar/bar',
			],
			'supports negation' => [
				'-insource:bar',
				'',
				'bar',
				true
			],
			'can be combined' => [
				'foo insource:bar baz',
				'foo baz',
				'bar',
			],
			'can be quoted (emits a querystring phrase query)' => [
				'insource:"foo bar"',
				'',
				'"foo bar"',
			],
			'can be quoted with escaped quotes (remains escaped in the query)' => [
				'insource:"foo\"bar"',
				'',
				'"foo\"bar"',
			],
			'is not confused by regex suffix' => [
				'insource:"foo bar"i',
				'i',
				'"foo bar"',
			],
			'can be quoted and combined  (emits a querystring phrase query)' => [
				'boo insource:"foo bar" baz',
				'boo baz',
				'"foo bar"',
			],
			'gracefully handles text including ~' => [
				'insource:this~that',
				'',
				'this~that',
			],
			'do not espcape fuzzy char' => [
				'insource:fuzzy~2',
				'',
				'fuzzy~2',
			],
			'do not escape wildcard char' => [
				'insource:fuzzy*',
				'',
				'fuzzy*',
			],
		];
	}

	public function testNothing() {
		$feature = new InSourceFeature( new HashSearchConfig( [] ) );
		$this->assertNotConsumed( $feature, 'foo bar' );
		$config = new HashSearchConfig( [
			'CirrusSearchEnableRegex' => true,
			'CirrusSearchWikimediaExtraPlugin' => [ 'regex' => [ 'use' => true ] ]
		], [ HashSearchConfig::FLAG_INHERIT ] );
		$feature = new InSourceFeature( $config );
		$this->assertNotConsumed( $feature, 'foo bar' );
	}

	/**
	 * @dataProvider provideRegexQueries
	 * @param string $query
	 * @param string $expectedRemaining
	 * @param mixed $filterValue
	 * @param bool $insensitive
	 * @throws \MWException
	 */
	public function testRegex( $query, $expectedRemaining, $filterValue, $insensitive ) {
		$filterCallback = function ( SourceRegex $x ) use ( $filterValue, $insensitive ) {
			return $filterValue === $x->getParam( 'regex' ) &&
				   $x->getParam( 'field' ) === 'source_text' &&
				   $x->getParam( 'ngram_field' ) === 'source_text.trigram' &&
				   !$insensitive === $x->getParam( 'case_sensitive' );
		};
		$config = new HashSearchConfig( [
				'CirrusSearchEnableRegex' => true,
				'CirrusSearchWikimediaExtraPlugin' => [ 'regex' => [ 'use' => true ] ]
			], [ HashSearchConfig::FLAG_INHERIT ] );
		$feature = new InSourceFeature( $config );

		if ( $filterValue !== null ) {
			$parsedValue = [
				'type' => 'regex',
				'pattern' => $filterValue,
				'insensitive' => $insensitive,
			];
			$this->assertParsedValue( $feature, $query, $parsedValue, [] );
			$this->assertCrossSearchStrategy( $feature, $query, CrossSearchStrategy::hostWikiOnlyStrategy() );
		}
		$this->assertFilter( $feature, $query, $filterCallback, [] );
		// TODO: remove should be a parser test, the keyword is not responsible for this
		$this->assertRemaining( $feature, $query, $expectedRemaining );
	}

	public static function provideRegexQueries() {
		return [
			'supports simple regex' => [
				'insource:/bar/',
				'',
				'bar',
				false,
			],
			'supports simple case insensitive regex' => [
				'insource:/bar/i',
				'',
				'bar',
				true,
			],
			'supports negation' => [
				'-insource:/bar/',
				'',
				'bar',
				false,
			],
			'supports negation simple case insensitive regex' => [
				'-insource:/bar/i',
				'',
				'bar',
				true,
			],
			'do not unescape the regex' => [
				'insource:/foo\/bar/',
				'',
				'foo\/bar',
				false,
			],
			'do not unescape the regex and keep insensitive flag' => [
				'insource:/foo\/bar/i',
				'',
				'foo\/bar',
				true,
			],
			'do not stop on quote' => [
				'insource:/foo"bar/i',
				'',
				'foo"bar',
				true,
			],
		];
	}

	public function testDisabled() {
		$feature = new InSourceFeature( new HashSearchConfig( [] ) );
		$this->assertParsedValue( $feature, 'insource:/test/',
			[
				'type' => 'regex',
				'pattern' => 'test',
				'insensitive' => false,
			],
			[ [ 'cirrussearch-feature-not-available', 'insource regex' ] ] );
	}
}
