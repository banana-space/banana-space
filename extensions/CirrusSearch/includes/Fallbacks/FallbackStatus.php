<?php

namespace CirrusSearch\Fallbacks;

use CirrusSearch\Search\CirrusSearchResultSet;
use Closure;
use HtmlArmor;

/**
 * Representation of the result of running a FallbackMethod.
 *
 * Describes the change that the method has decided to apply to the search
 * result set. This primarily allows wrapping the changes into atomic units
 * of execution so the execution trace can be logged.
 */
class FallbackStatus {
	const NO_ACTION = 'noSuggestion';
	const ACTION_SUGGEST_QUERY = 'suggestQuery';
	const ACTION_REPLACE_LOCAL_RESULTS = 'replaceLocalResults';
	const ACTION_ADD_INTERWIKI_RESULTS = 'addInterwikiResults';

	/** @var string */
	private $actionName;

	/** @var Closure */
	private $fn;

	/**
	 * @param string $actionName An ACTION_* class constant, or NO_ACTION. Describes the action
	 *  to perform.
	 * @param Closure $fn Function accepting a CirrusSearchResultSet and returning a CirrusSearchResultSet.
	 *  Called to apply the chosen action to an existing result set. Implementations may return
	 *  the provided result set, or a completely different one if desired.
	 */
	private function __construct( string $actionName, Closure $fn ) {
		$this->actionName = $actionName;
		$this->fn = $fn;
	}

	public function apply( CirrusSearchResultSet $currentSet ): CirrusSearchResultSet {
		$fn = $this->fn;
		return $fn( $currentSet );
	}

	/**
	 * @return string The fallback action to perform
	 */
	public function getAction(): string {
		return $this->actionName;
	}

	/**
	 * @param string $query
	 * @param HtmlArmor|string|null $snippet
	 * @return FallbackStatus
	 */
	public static function suggestQuery( string $query, $snippet = null ): FallbackStatus {
		return new self( self::ACTION_SUGGEST_QUERY, function ( CirrusSearchResultSet $currentSet ) use ( $query, $snippet ) {
			$currentSet->setSuggestionQuery( $query, $snippet );
			return $currentSet;
		} );
	}

	/**
	 * @param CirrusSearchResultSet $rewrittenResults New result set to replace existing results with
	 * @param string $query The search query performed in the new result set.
	 * @param HtmlArmor|string|null $snippet A highlighted snippet showing the changes in $query.
	 * @return FallbackStatus
	 */
	public static function replaceLocalResults( CirrusSearchResultSet $rewrittenResults, string $query, $snippet = null ): FallbackStatus {
		return new self(
			self::ACTION_REPLACE_LOCAL_RESULTS,
			function ( CirrusSearchResultSet $currentSet ) use ( $rewrittenResults, $query, $snippet ) {
				$rewrittenResults->setRewrittenQuery( $query, $snippet );
				return $rewrittenResults;
			} );
	}

	/**
	 * @param CirrusSearchResultSet $results Interwiki results to add to current result set
	 * @param string $wikiId The wiki these results come from
	 * @return FallbackStatus
	 */
	public static function addInterwikiResults( CirrusSearchResultSet $results, string $wikiId ): FallbackStatus {
		return new self( self::ACTION_ADD_INTERWIKI_RESULTS, function ( CirrusSearchResultSet $currentSet ) use ( $results, $wikiId ) {
			$currentSet->addInterwikiResults( $results, \SearchResultSet::INLINE_RESULTS, $wikiId );
			return $currentSet;
		} );
	}

	public static function noSuggestion(): FallbackStatus {
		return new self( self::NO_ACTION, function ( CirrusSearchResultSet $currentSet ) {
			return $currentSet;
		} );
	}
}
