<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * Transitional trait used to share the methods between SearchResult and RevisionSearchResult.
 * All the content of this trait can be moved to RevisionSearchResult once SearchResult is finally
 * refactored into an abstract class.
 * NOTE: This trait MUST NOT be used by something else than SearchResult and RevisionSearchResult.
 * It will be removed without deprecation period once SearchResult
 */
trait RevisionSearchResultTrait {
	/**
	 * @var RevisionRecord
	 */
	protected $mRevisionRecord = null;

	/**
	 * @var File
	 */
	protected $mImage = null;

	/**
	 * @var Title|null
	 */
	protected $mTitle;

	/**
	 * @var string
	 */
	protected $mText;

	/**
	 * Initialize from a Title and if possible initializes a corresponding
	 * RevisionRecord and File.
	 *
	 * @param Title|null $title
	 */
	protected function initFromTitle( $title ) {
		$this->mTitle = $title;
		if ( $title !== null ) {
			$services = MediaWikiServices::getInstance();
			$id = false;
			Hooks::runner()->onSearchResultInitFromTitle( $title, $id );

			$this->mRevisionRecord = $services->getRevisionLookup()->getRevisionByTitle(
				$title,
				$id,
				RevisionLookup::READ_NORMAL
			);
			if ( $title->getNamespace() === NS_FILE ) {
				$this->mImage = $services->getRepoGroup()->findFile( $title );
			}
		}
	}

	/**
	 * Check if this is result points to an invalid title
	 *
	 * @return bool
	 */
	public function isBrokenTitle() {
		return $this->mTitle === null;
	}

	/**
	 * Check if target page is missing, happens when index is out of date
	 *
	 * @return bool
	 */
	public function isMissingRevision() {
		return !$this->mRevisionRecord && !$this->mImage;
	}

	/**
	 * @return Title|null
	 */
	public function getTitle() {
		return $this->mTitle;
	}

	/**
	 * Get the file for this page, if one exists
	 * @return File|null
	 */
	public function getFile() {
		return $this->mImage;
	}

	/**
	 * Lazy initialization of article text from DB
	 */
	protected function initText() {
		if ( !isset( $this->mText ) ) {
			if ( $this->mRevisionRecord != null ) {
				$content = $this->mRevisionRecord->getContent( SlotRecord::MAIN );
				$this->mText = $content !== null ? $content->getTextForSearchIndex() : '';
			} else { // TODO: can we fetch raw wikitext for commons images?
				$this->mText = '';
			}
		}
	}

	/**
	 * @param string[] $terms Terms to highlight (this parameter is deprecated and ignored)
	 * @return string Highlighted text snippet, null (and not '') if not supported
	 */
	public function getTextSnippet( $terms = [] ) {
		return '';
	}

	/**
	 * @return string Highlighted title, '' if not supported
	 */
	public function getTitleSnippet() {
		return '';
	}

	/**
	 * @return string Highlighted redirect name (redirect to this page), '' if none or not supported
	 */
	public function getRedirectSnippet() {
		return '';
	}

	/**
	 * @return Title|null Title object for the redirect to this page, null if none or not supported
	 */
	public function getRedirectTitle() {
		return null;
	}

	/**
	 * @return string Highlighted relevant section name, null if none or not supported
	 */
	public function getSectionSnippet() {
		return '';
	}

	/**
	 * @return Title|null Title object (pagename+fragment) for the section,
	 *  null if none or not supported
	 */
	public function getSectionTitle() {
		return null;
	}

	/**
	 * @return string Highlighted relevant category name or '' if none or not supported
	 */
	public function getCategorySnippet() {
		return '';
	}

	/**
	 * @return string Timestamp
	 */
	public function getTimestamp() {
		if ( $this->mRevisionRecord ) {
			return $this->mRevisionRecord->getTimestamp();
		} elseif ( $this->mImage ) {
			return $this->mImage->getTimestamp();
		}
		return '';
	}

	/**
	 * @return int Number of words
	 */
	public function getWordCount() {
		$this->initText();
		return str_word_count( $this->mText );
	}

	/**
	 * @return int Size in bytes
	 */
	public function getByteSize() {
		$this->initText();
		return strlen( $this->mText );
	}

	/**
	 * @return string Interwiki prefix of the title (return iw even if title is broken)
	 */
	public function getInterwikiPrefix() {
		return '';
	}

	/**
	 * @return string Interwiki namespace of the title (since we likely can't resolve it locally)
	 */
	public function getInterwikiNamespaceText() {
		return '';
	}

	/**
	 * Did this match file contents (eg: PDF/DJVU)?
	 * @return bool
	 */
	public function isFileMatch() {
		return false;
	}
}
