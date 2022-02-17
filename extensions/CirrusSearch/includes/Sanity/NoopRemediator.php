<?php

namespace CirrusSearch\Sanity;

use Title;
use WikiPage;

/**
 * Remediator that takes no actions.
 */
class NoopRemediator implements Remediator {

	/**
	 * @param WikiPage $page
	 */
	public function redirectInIndex( WikiPage $page ) {
	}

	/**
	 * @param WikiPage $page
	 */
	public function pageNotInIndex( WikiPage $page ) {
	}

	/**
	 * @param string $docId
	 * @param Title $title
	 */
	public function ghostPageInIndex( $docId, Title $title ) {
	}

	/**
	 * @param string $docId
	 * @param WikiPage $page
	 * @param string $indexType
	 */
	public function pageInWrongIndex( $docId, WikiPage $page, $indexType ) {
	}

	/**
	 * @param string $docId elasticsearch document id
	 * @param WikiPage $page page with outdated document in index
	 * @param string $indexType index contgaining outdated document
	 */
	public function oldVersionInIndex( $docId, WikiPage $page, $indexType ) {
	}

	/**
	 * @param WikiPage $page Page considered too old in index
	 */
	public function oldDocument( WikiPage $page ) {
	}
}
