<?php

namespace CirrusSearch\Search;

use Title;

class ArrayCirrusSearchResult extends CirrusSearchResult {
	const DOC_ID = 'doc_id';
	const SCORE = 'score';
	const EXPLANATION = 'explanation';
	const TEXT_SNIPPET = 'text_snippet';
	const TITLE_SNIPPET = 'title_snippet';
	const REDIRECT_SNIPPET = 'redirect_snippet';
	const REDIRECT_TITLE = 'redirect_title';
	const SECTION_SNIPPET = 'section_snippet';
	const SECTION_TITLE = 'section_title';
	const CATEGORY_SNIPPET = 'category_snippet';
	const TIMESTAMP = 'timestamp';
	const WORD_COUNT = 'word_count';
	const BYTE_SIZE = 'byte_size';
	const INTERWIKI_NAMESPACE_TEXT = 'interwiki_namespace_text';
	const IS_FILE_MATCH = 'is_file_match';

	/**
	 * @var array
	 */
	private $data;

	public function __construct( Title $title, array $data ) {
		parent::__construct( $title );
		$this->data = $data;
	}

	/**
	 * @return string
	 */
	public function getDocId() {
		return $this->data[self::DOC_ID];
	}

	/**
	 * @return float
	 */
	public function getScore() {
		return $this->data[self::SCORE] ?? 0.0;
	}

	/**
	 * @return array|null
	 */
	public function getExplanation() {
		return $this->data[self::EXPLANATION] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getTextSnippet( $terms = [] ) {
		return $this->data[self::TEXT_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitleSnippet() {
		return $this->data[self::TITLE_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirectSnippet() {
		return $this->data[self::REDIRECT_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getRedirectTitle() {
		return $this->data[self::REDIRECT_TITLE] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getSectionSnippet() {
		return $this->data[self::SECTION_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getSectionTitle() {
		return $this->data[self::SECTION_TITLE] ?? null;
	}

	/**
	 * @inheritDoc
	 */
	public function getCategorySnippet() {
		return $this->data[self::CATEGORY_SNIPPET] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function getTimestamp() {
		$ts = $this->data[self::TIMESTAMP] ?? null;
		return $ts !== null ? $ts->getTimestamp( TS_MW ) : '';
	}

	/**
	 * @inheritDoc
	 */
	public function getWordCount() {
		return $this->data[self::WORD_COUNT] ?? 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getByteSize() {
		return $this->data[self::BYTE_SIZE] ?? 0;
	}

	/**
	 * @inheritDoc
	 */
	public function getInterwikiPrefix() {
		return $this->getTitle()->getInterwiki();
	}

	/**
	 * @inheritDoc
	 */
	public function getInterwikiNamespaceText() {
		return $this->data[self::INTERWIKI_NAMESPACE_TEXT] ?? '';
	}

	/**
	 * @inheritDoc
	 */
	public function isFileMatch() {
		return $this->data[self::IS_FILE_MATCH] ?? false;
	}
}
