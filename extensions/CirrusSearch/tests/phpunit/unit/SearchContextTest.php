<?php

namespace CirrusSearch;

use CirrusSearch\Search\SearchContext;

/**
 * @covers \CirrusSearch\Search\SearchContext
 */
class SearchContextTest extends CirrusTestCase {

	/**
	 * @var SearchContext
	 */
	private $context;

	public function setUp() : void {
		parent::setUp();
		$this->context = new SearchContext( $this->newHashSearchConfig() );
	}

	public function testNoSyntax() {
		// No syntax is classified as full_text
		$this->context->addSyntaxUsed( 'full_text' );
		$this->assertTrue( $this->context->isSyntaxUsed() );
		$this->assertFalse( $this->context->isSpecialKeywordUsed() );
		$this->assertFalse( $this->context->isSyntaxUsed( 'accio' ) );
		$this->assertEquals( 'full_text', $this->context->getSearchType() );
	}

	public function testCheapSyntax() {
		$this->context->addSyntaxUsed( 'accio' );
		$this->context->addSyntaxUsed( 'full_text' );
		$this->assertTrue( $this->context->isSyntaxUsed() );
		$this->assertTrue( $this->context->isSyntaxUsed( 'accio' ) );
		$this->assertFalse( $this->context->isSyntaxUsed( 'prefix' ) );
		$this->assertEquals( 'full_text', $this->context->getSearchType() );
	}

	public function testNoncheapSyntax() {
		$this->context->addSyntaxUsed( 'full_text' );
		$this->context->addSyntaxUsed( 'more_like' );
		$this->assertTrue( $this->context->isSyntaxUsed( 'more_like' ) );
		$this->assertEquals( 'more_like', $this->context->getSearchType() );
	}

	public function testNoncheapSyntaxCustom() {
		$this->context->addSyntaxUsed( 'more_like' );
		$this->context->addSyntaxUsed( 'even_more_like', 101 );
		$this->assertTrue( $this->context->isSyntaxUsed( 'even_more_like' ) );
		$this->assertEquals( 'even_more_like', $this->context->getSearchType() );
	}

	public function testSyntaxOrder() {
		$syntaxes = [ 'full_text', 'more_like', 'regex' ];
		foreach ( $syntaxes as $syntax ) {
			$this->context->addSyntaxUsed( $syntax );
			$this->assertEquals( $syntax, $this->context->getSearchType() );
		}
	}

}
