<?php

namespace CirrusSearch\Sanity;

use Title;
use WikiPage;

/**
 * A remediator that simply records all actions scheduled to it.
 * These actions can then be replayed on an arbitrary remediator by calling replayOn( Remediator ).
 * The actions can be reset by calling resetActions()
 */
class BufferedRemediator implements Remediator {
	private $actions = [];

	/**
	 * @inheritDoc
	 */
	public function redirectInIndex( WikiPage $page ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * @inheritDoc
	 */
	public function pageNotInIndex( WikiPage $page ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * @inheritDoc
	 */
	public function ghostPageInIndex( $docId, Title $title ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * @inheritDoc
	 */
	public function pageInWrongIndex( $docId, WikiPage $page, $indexType ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * @inheritDoc
	 */
	public function oldVersionInIndex( $docId, WikiPage $page, $indexType ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * @inheritDoc
	 */
	public function oldDocument( WikiPage $page ) {
		$this->actions[] = [ substr( __METHOD__, strlen( __CLASS__ ) + 2 ), func_get_args() ];
	}

	/**
	 * The list of recorded actions (for Unit Tests)
	 * @return array
	 */
	public function getActions() {
		return $this->actions;
	}

	/**
	 * Check if the actions recorded on this remediator are the same
	 * as the actions recorded on $remediator.
	 * @param BufferedRemediator $remediator
	 * @return bool
	 */
	public function hasSameActions( BufferedRemediator $remediator ) {
		return $this->actions === $remediator->actions;
	}

	/**
	 * @param Remediator $remediator
	 */
	public function replayOn( Remediator $remediator ) {
		foreach ( $this->actions as $action ) {
			list( $method, $args ) = $action;
			call_user_func_array( [ $remediator, $method ], $args );
		}
	}

	/**
	 * Reset actions recorded by this remediator
	 */
	public function resetActions() {
		$this->actions = [];
	}
}
