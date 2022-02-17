<?php

namespace CirrusSearch\Parser\QueryStringRegex;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Parser\AST\NegatedNode;
use CirrusSearch\Query\InSourceFeature;
use CirrusSearch\Query\InTitleFeature;
use CirrusSearch\Query\LocalFeature;
use CirrusSearch\Query\MoreLikeFeature;
use CirrusSearch\Query\PreferRecentFeature;
use CirrusSearch\Query\PrefixFeature;
use CirrusSearch\SearchConfig;
use CirrusSearch\Test\MockKeyword;

/**
 * @covers \CirrusSearch\Parser\QueryStringRegex\KeywordParser
 * @covers \CirrusSearch\Query\SimpleKeywordFeature
 */
class KeywordParserTest extends CirrusTestCase {
	public function testSimple() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = 'intitle:test foo bar -intitle:"hop\"foo" ';
		$nodes = $parser->parse( $query, new InTitleFeature( new SearchConfig() ), new OffsetTracker() );
		$this->assertCount( 2, $nodes );

		/** @var KeywordFeatureNode $kw */
		$kw = $nodes[0];
		$this->assertInstanceOf( KeywordFeatureNode::class, $kw );
		$this->assertSame( 0, $kw->getStartOffset() );
		$this->assertSame( 12, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'intitle', $kw->getKey() );
		$this->assertSame( 'test', $kw->getValue() );
		$this->assertSame( 'test', $kw->getQuotedValue() );

		/** @var NegatedNode $kw */
		$kw = $nodes[1];
		$this->assertInstanceOf( NegatedNode::class, $kw );
		$this->assertSame( 21, $kw->getStartOffset() );
		$this->assertSame( 40, $kw->getEndOffset() );

		$kw = $kw->getChild();
		/** @var KeywordFeatureNode $kw */
		$this->assertInstanceOf( KeywordFeatureNode::class, $kw );
		$this->assertSame( 22, $kw->getStartOffset() );
		$this->assertSame( 40, $kw->getEndOffset() );
		$this->assertSame( '"', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'intitle', $kw->getKey() );
		$this->assertSame( 'hop"foo', $kw->getValue() );
		$this->assertSame( '"hop\"foo"', $kw->getQuotedValue() );
	}

	public function testWithAlias() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = 'mock2:test foo bar -mock2:"hop\"foo" ';
		$nodes = $parser->parse( $query, new MockKeyword(), new OffsetTracker() );
		$this->assertCount( 2, $nodes );

		/** @var KeywordFeatureNode $kw */
		$kw = $nodes[0];
		$this->assertInstanceOf( KeywordFeatureNode::class, $kw );
		$this->assertSame( 0, $kw->getStartOffset() );
		$this->assertSame( 10, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'mock2', $kw->getKey() );
		$this->assertSame( 'test', $kw->getValue() );
		$this->assertSame( 'test', $kw->getQuotedValue() );

		/** @var NegatedNode $kw */
		$kw = $nodes[1];
		$this->assertInstanceOf( NegatedNode::class, $kw );
		$this->assertSame( 19, $kw->getStartOffset() );
		$this->assertSame( 36, $kw->getEndOffset() );

		$kw = $kw->getChild();
		/** @var KeywordFeatureNode $kw */
		$this->assertInstanceOf( KeywordFeatureNode::class, $kw );
		$this->assertSame( 20, $kw->getStartOffset() );
		$this->assertSame( 36, $kw->getEndOffset() );
		$this->assertSame( '"', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'mock2', $kw->getKey() );
		$this->assertSame( 'hop"foo', $kw->getValue() );
		$this->assertSame( '"hop\"foo"', $kw->getQuotedValue() );
	}

	public function testGreedyHeader() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = ' morelike:"test foo " bar ';
		$nodes = $parser->parse( $query, new MoreLikeFeature( new SearchConfig() ), new OffsetTracker() );
		$this->assertCount( 1, $nodes );

		$kw = $nodes[0];
		$this->assertSame( 1, $kw->getStartOffset() );
		$this->assertSame( 26, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'morelike', $kw->getKey() );
		$this->assertSame( '"test foo " bar ', $kw->getValue() );
		$this->assertSame( '"test foo " bar ', $kw->getQuotedValue() );
	}

	public function testGreedy() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = ' prefix:"test foo " bar ';
		$nodes = $parser->parse( $query, new PrefixFeature(), new OffsetTracker() );
		$this->assertCount( 1, $nodes );

		$kw = $nodes[0];
		$this->assertSame( 1, $kw->getStartOffset() );
		$this->assertSame( 24, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'prefix', $kw->getKey() );
		$this->assertSame( '"test foo " bar ', $kw->getValue() );
		$this->assertSame( '"test foo " bar ', $kw->getQuotedValue() );
	}

	public function testHeader() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = ' local:local:"test foo " bar ';
		$nodes = $parser->parse( $query, new LocalFeature(), new OffsetTracker() );
		$this->assertCount( 2, $nodes );

		$kw = $nodes[0];
		$this->assertSame( 1, $kw->getStartOffset() );
		$this->assertSame( 7, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'local', $kw->getKey() );
		$this->assertSame( '', $kw->getValue() );
		$this->assertSame( '', $kw->getQuotedValue() );
		// FIXME: figure out if this is the right behavior
		$kw = $nodes[1];
		$this->assertSame( 7, $kw->getStartOffset() );
		$this->assertSame( 13, $kw->getEndOffset() );
		$this->assertSame( '', $kw->getDelimiter() );
		$this->assertSame( '', $kw->getSuffix() );
		$this->assertSame( 'local', $kw->getKey() );
		$this->assertSame( '', $kw->getValue() );
		$this->assertSame( '', $kw->getQuotedValue() );
	}

	public function testRegex() {
		$parser = new KeywordParser();
		// .      00000000001111111111222222 22223333333333
		// .      01234567890123456789012345 67890123456789
		$query = ' unrelated insource:/test\\/"/i ';
		$config = new HashSearchConfig( [
			'CirrusSearchEnableRegex' => false,
		], [ HashSearchConfig::FLAG_INHERIT ] );

		$nodes = $parser->parse( $query, new InSourceFeature( $config ), new OffsetTracker() );
		$this->assertCount( 1, $nodes );

		$kw = $nodes[0];
		$this->assertSame( 11, $kw->getStartOffset() );
		$this->assertSame( 30, $kw->getEndOffset() );
		$this->assertSame( '/', $kw->getDelimiter() );
		$this->assertSame( 'i', $kw->getSuffix() );
		$this->assertSame( 'insource', $kw->getKey() );
		$this->assertSame( 'test/"', $kw->getValue() );
		$this->assertSame( '/test\\/"/', $kw->getQuotedValue() );
	}

	public function testOptionalValue() {
		$parser = new KeywordParser();
		// .      0000000000111111111122222222223333333333
		// .      0123456789012345678901234567890123456789
		$query = 'prefer-recent:intitle:test';
		$config = new HashSearchConfig( [], [ HashSearchConfig::FLAG_INHERIT ] );

		$assertFunc = function ( array $nodes ) {
			uasort( $nodes, function ( KeywordFeatureNode $a, KeywordFeatureNode $b ) {
				return $a->getStartOffset() - $b->getStartOffset();
			} );
			$this->assertCount( 2, $nodes );

			/**
			 * @var KeywordFeatureNode $kw
			 */
			$kw = $nodes[0];
			$this->assertSame( 0, $kw->getStartOffset() );
			$this->assertSame( 14, $kw->getEndOffset() );
			$this->assertSame( '', $kw->getDelimiter() );
			$this->assertSame( '', $kw->getSuffix() );
			$this->assertSame( 'prefer-recent', $kw->getKey() );
			$this->assertSame( '', $kw->getValue() );
			$this->assertSame( '', $kw->getQuotedValue() );
			$this->assertNull( $kw->getParsedValue() );

			$kw = $nodes[1];
			$this->assertSame( 14, $kw->getStartOffset() );
			$this->assertSame( 26, $kw->getEndOffset() );
			$this->assertSame( '', $kw->getDelimiter() );
			$this->assertSame( '', $kw->getSuffix() );
			$this->assertSame( 'intitle', $kw->getKey() );
			$this->assertSame( 'test', $kw->getValue() );
			$this->assertSame( 'test', $kw->getQuotedValue() );
			$this->assertNull( $kw->getParsedValue() );
		};

		$ot = new OffsetTracker();
		$nodes = $parser->parse( $query, new PreferRecentFeature( $config ), $ot );
		$ot->appendNodes( $nodes );
		$nodes = array_merge( $nodes, $parser->parse( $query, new InTitleFeature( $config ), $ot ) );
		$assertFunc( $nodes );

		// XXX: currently keyword parsing is order dependent
		/*
		$ot = new OffsetTracker();
		$nodes = $parser->parse( $query, new InTitleFeature( $config ), $ot );
		$nodes = array_merge( $nodes, $parser->parse( $query, new PreferRecentFeature( $config ), $ot ) );
		$assertFunc( $nodes );
		*/
	}
}
