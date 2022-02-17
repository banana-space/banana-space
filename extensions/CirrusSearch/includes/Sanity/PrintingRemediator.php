<?php

namespace CirrusSearch\Sanity;

use Title;
use WikiPage;

/**
 * Decorating Remediator that logs the prints the errors.
 */
class PrintingRemediator implements Remediator {
	private $next;

	/**
	 * Build the remediator.
	 * @param Remediator $next the remediator that this one decorates
	 */
	public function __construct( Remediator $next ) {
		$this->next = $next;
	}

	public function redirectInIndex( WikiPage $page ) {
		$this->log( $page->getId(), $page->getTitle(), 'Redirect in index' );
		$this->next->redirectInIndex( $page );
	}

	public function pageNotInIndex( WikiPage $page ) {
		$this->log( $page->getId(), $page->getTitle(), 'Page not in index' );
		$this->next->pageNotInIndex( $page );
	}

	/**
	 * @param string $docId
	 * @param Title $title
	 */
	public function ghostPageInIndex( $docId, Title $title ) {
		$this->log( $docId, $title, 'Deleted page in index' );
		$this->next->ghostPageInIndex( $docId, $title );
	}

	/**
	 * @param string $docId
	 * @param WikiPage $page
	 * @param string $indexType
	 */
	public function pageInWrongIndex( $docId, WikiPage $page, $indexType ) {
		$this->log( $page->getId(), $page->getTitle(), "Page in wrong index: $indexType" );
		$this->next->pageInWrongIndex( $docId, $page, $indexType );
	}

	/**
	 * @param string $docId elasticsearch document id
	 * @param WikiPage $page page with outdated document in index
	 * @param string $indexType index contgaining outdated document
	 */
	public function oldVersionInIndex( $docId, WikiPage $page, $indexType ) {
		$this->log( $page->getId(), $page->getTitle(), "Outdated page in index: $indexType" );
		$this->next->oldVersionInIndex( $docId, $page, $indexType );
	}

	/**
	 * @param WikiPage $page Page considered too old in index
	 */
	public function oldDocument( WikiPage $page ) {
		$this->log( $page->getId(), $page->getTitle(), "Old page in index" );
		$this->next->oldDocument( $page );
	}

	/**
	 * @param int|string $pageOrDocId
	 * @param Title $title
	 * @param string $message
	 */
	private function log( $pageOrDocId, $title, $message ) {
		printf( "%30s %10d %s\n", $message, $pageOrDocId, $title );
	}
}
