<?php

namespace CirrusSearch\Search;

use File;
use MediaWiki\MediaWikiServices;
use SearchResult;
use SearchResultTrait;
use Title;

/**
 * Base class for SearchResult
 */
abstract class CirrusSearchResult extends SearchResult {
	use SearchResultTrait;

	/**
	 * @var Title
	 */
	private $title;

	/**
	 * @var File
	 */
	private $file;

	/**
	 * CirrusSearchResult constructor.
	 * @param Title $title
	 */
	public function __construct( Title $title ) {
		$this->title = $title;
		if ( $this->getTitle()->getNamespace() == NS_FILE ) {
			$this->file = MediaWikiServices::getInstance()->getRepoGroup()
				->findFile( $this->title );
		}
	}

	/**
	 * Initialize from a Title and if possible initializes a corresponding
	 * File.
	 *
	 * @param Title $title
	 */
	final protected function initFromTitle( $title ) {
		// Everything is done in the constructor.
		// XXX: we do not call the SearchResultInitFromTitle hook
		// this hook is designed to fetch a particular revision
		// but the way cirrus works does not allow to vary the revision
		// text being displayed at query time.
	}

	/**
	 * Check if this is result points to an invalid title
	 *
	 * @return bool
	 */
	final public function isBrokenTitle() {
		// Title is mandatory in the constructor it would have failed earlier if the Title was broken
		return false;
	}

	/**
	 * Check if target page is missing, happens when index is out of date
	 *
	 * @return bool
	 */
	final public function isMissingRevision() {
		global $wgCirrusSearchDevelOptions;
		if ( isset( $wgCirrusSearchDevelOptions['ignore_missing_rev'] ) ) {
			return true;
		}
		return !$this->getTitle()->isKnown();
	}

	/**
	 * @return Title
	 */
	final public function getTitle() {
		return $this->title;
	}

	/**
	 * Get the file for this page, if one exists
	 * @return File|null
	 */
	final public function getFile() {
		return $this->file;
	}

	/**
	 * Lazy initialization of article text from DB
	 */
	final protected function initText() {
		throw new \Exception( "initText() should not be called on CirrusSearchResult, " .
			"content must be fetched directly from the backend at query time." );
	}

	/**
	 * @return string
	 */
	abstract public function getDocId();

	/**
	 * @return float
	 */
	abstract public function getScore();

	/**
	 * @return array|null
	 */
	abstract public function getExplanation();
}
