<?php

namespace CirrusSearch\Search;

use CirrusSearch\CirrusTestCase;
use HtmlArmor;

/**
 * @covers \CirrusSearch\Search\ResultSet
 */
class ResultSetTest extends CirrusTestCase {
	public function testSuggestionSnippetHtmlEscape() {
		$results = new ResultSet( false, null, $this->mock( TitleHelper::class ) );
		$query = 'foo<script>';

		$results->setSuggestionQuery( $query );
		$this->assertSame( $query, $results->getSuggestionSnippet() );

		$results->setSuggestionQuery( $query, $query );
		$this->assertSame( $query, $results->getSuggestionSnippet() );

		$highlight = new HtmlArmor( '<span>foo</span>' . htmlspecialchars( '<script>' ) );
		$results->setSuggestionQuery( $query, $highlight );
		$this->assertSame( $highlight, $results->getSuggestionSnippet() );
	}

	public function testRewrittenQuerySnippetHtmlEscape() {
		$results = new ResultSet( false, null, $this->mock( TitleHelper::class ) );
		$query = 'foo<script>';

		$results->setRewrittenQuery( $query );
		$this->assertSame( $query, $results->getQueryAfterRewriteSnippet() );

		$results->setRewrittenQuery( $query, $query );
		$this->assertSame( $query, $results->getQueryAfterRewriteSnippet() );

		$highlight = new HtmlArmor( '<span>foo</span>' . htmlspecialchars( '<script>' ) );
		$results->setRewrittenQuery( $query, $highlight );
		$this->assertSame( $highlight, $results->getQueryAfterRewriteSnippet() );
	}

	private function mock( $className ) {
		return $this->getMockBuilder( $className )
			->disableOriginalConstructor()
			->getMock();
	}
}
