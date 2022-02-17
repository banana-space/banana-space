<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Test\DummySearchResultSet;
use HtmlArmor;

/**
 * @covers \CirrusSearch\Fallbacks\FallbackStatus
 */
class FallbackStatusTest extends CirrusTestCase {
	public function testNoSuggestion() {
		$status = FallbackStatus::noSuggestion();
		$results = DummySearchResultSet::emptyResultSet();
		$afterApply = $status->apply( $results );
		$this->assertSame( $results, $afterApply );
		$this->assertSame( FallbackStatus::NO_ACTION, $status->getAction() );
	}

	public function testAddInterwikiResults() {
		$results = DummySearchResultSet::emptyResultSet();
		$iwResults = DummySearchResultSet::emptyResultSet();
		$status = FallbackStatus::addInterwikiResults( $iwResults, 'phpunitwiki' );
		$this->assertSame( FallbackStatus::ACTION_ADD_INTERWIKI_RESULTS, $status->getAction() );
		$afterApply = $status->apply( $results );
		$this->assertSame( $results, $afterApply );
		// $this->assertSomething( we have interwiki results for phpunitwiki )
	}

	public function testReplaceLocalResults() {
		$results = DummySearchResultSet::emptyResultSet();
		$replacement = DummySearchResultSet::emptyResultSet();

		// Default snippet
		$status = FallbackStatus::replaceLocalResults( $replacement, 'php unit' );
		$this->assertSame( FallbackStatus::ACTION_REPLACE_LOCAL_RESULTS, $status->getAction() );
		$afterApply = $status->apply( $results );
		$this->assertSame( $replacement, $afterApply );
		$this->assertSame( 'php unit', $replacement->getQueryAfterRewrite() );
		$this->assertSame( 'php unit', $replacement->getQueryAfterRewriteSnippet() );

		// Explicit snippet
		$status = FallbackStatus::replaceLocalResults( $replacement, 'php unit', new HtmlArmor( '<em>php</em> unit' ) );
		$this->assertSame( FallbackStatus::ACTION_REPLACE_LOCAL_RESULTS, $status->getAction() );
		$afterApply = $status->apply( $results );
		$this->assertSame( $replacement, $afterApply );
		$this->assertSame( 'php unit', $replacement->getQueryAfterRewrite() );
		$this->assertEquals( new HtmlArmor( '<em>php</em> unit' ), $replacement->getQueryAfterRewriteSnippet() );
	}

	public function testSuggestQuery() {
		$results = DummySearchResultSet::emptyResultSet();
		$status = FallbackStatus::suggestQuery( 'catapults pickles' );
		$this->assertSame( FallbackStatus::ACTION_SUGGEST_QUERY, $status->getAction() );
		$afterApply = $status->apply( $results );
		$this->assertSame( 'catapults pickles', $afterApply->getSuggestionQuery() );
		$this->assertSame( 'catapults pickles', $afterApply->getSuggestionSnippet() );
	}
}
