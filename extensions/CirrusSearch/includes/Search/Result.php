<?php

namespace CirrusSearch\Search;

use CirrusSearch\Search\Fetch\HighlightingTrait;
use MWTimestamp;
use Title;

/**
 * An individual search result from Elasticsearch.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class Result extends CirrusSearchResult {
	use HighlightingTrait;

	/** @var string */
	private $titleSnippet = '';
	/** @var Title|null */
	private $redirectTitle = null;
	/** @var string */
	private $redirectSnippet = '';
	/** @var Title|null */
	private $sectionTitle = null;
	/** @var string */
	private $sectionSnippet = '';
	/** @var string */
	private $categorySnippet = '';
	/** @var string */
	private $textSnippet;
	/** @var bool */
	private $isFileMatch = false;
	/** @var string */
	private $namespaceText = '';
	/** @var int */
	private $wordCount;
	/** @var int */
	private $byteSize;
	/** @var string */
	private $timestamp;
	/** @var string */
	private $docId;
	/** @var float */
	private $score;
	/** @var array */
	private $explanation;
	/** @var TitleHelper */
	private $titleHelper;

	/**
	 * Build the result.
	 *
	 * @param mixed $results Unused
	 * @param \Elastica\Result $result containing the given search result
	 * @param TitleHelper|null $titleHelper
	 */
	public function __construct( $results, $result, TitleHelper $titleHelper = null ) {
		$this->titleHelper = $titleHelper ?: new TitleHelper();
		parent::__construct( $this->titleHelper->makeTitle( $result ) );
		$this->namespaceText = $result->namespace_text;
		$this->docId = $result->getId();

		$fields = $result->getFields();
		// Not all results requested a word count. Just pretend we have none if so
		$this->wordCount = isset( $fields['text.word_count'] ) ? $fields['text.word_count'][ 0 ] : 0;
		$this->byteSize = $result->text_bytes;
		$this->timestamp = new MWTimestamp( $result->timestamp );
		$highlights = $result->getHighlights();
		// Evil hax to not special case .plain fields for intitle regex
		foreach ( [ 'title', 'redirect.title' ] as $field ) {
			if ( isset( $highlights["$field.plain"] ) && !isset( $highlights[$field] ) ) {
				$highlights[$field] = $highlights["$field.plain"];
				unset( $highlights["$field.plain"] );
			}
		}
		if ( isset( $highlights[ 'title' ] ) ) {
			$nstext = $this->getTitle()->getNamespace() === 0 ? '' :
				$this->titleHelper->getNamespaceText( $this->getTitle() ) . ':';
			$this->titleSnippet = $nstext . $this->escapeHighlightedText( $highlights[ 'title' ][ 0 ] );
		} elseif ( $this->getTitle()->isExternal() ) {
			// Interwiki searches are weird. They won't have title highlights by design, but
			// if we don't return a title snippet we'll get weird display results.
			$this->titleSnippet = $this->getTitle()->getText();
		}

		if ( !isset( $highlights[ 'title' ] ) && isset( $highlights[ 'redirect.title' ] ) ) {
			// Make sure to find the redirect title before escaping because escaping breaks it....
			$this->redirectTitle = $this->findRedirectTitle( $result, $highlights[ 'redirect.title' ][ 0 ] );
			if ( $this->redirectTitle !== null ) {
				$this->redirectSnippet = $this->escapeHighlightedText( $highlights[ 'redirect.title' ][ 0 ] );
			}
		}

		$this->textSnippet = $this->escapeHighlightedText( $this->pickTextSnippet( $highlights ) );

		if ( isset( $highlights[ 'heading' ] ) ) {
			$this->sectionSnippet = $this->escapeHighlightedText( $highlights[ 'heading' ][ 0 ] );
			$this->sectionTitle = $this->findSectionTitle( $highlights[ 'heading' ][ 0 ], $this->getTitle() );
		}

		if ( isset( $highlights[ 'category' ] ) ) {
			$this->categorySnippet = $this->escapeHighlightedText( $highlights[ 'category' ][ 0 ] );
		}
		$this->score = $result->getScore();
		$this->explanation = $result->getExplanation();
	}

	/**
	 * @param string[] $highlights
	 * @return string
	 */
	private function pickTextSnippet( $highlights ) {
		// This can get skipped if there the page was sent to Elasticsearch without text.
		// This could be a bug or it could be that the page simply doesn't have any text.
		$mainSnippet = '';
		// Prefer source_text.plain it's likely a regex
		// TODO: use the priority system from the FetchPhaseConfigBuilder
		if ( isset( $highlights[ 'source_text.plain' ] ) ) {
			$sourceSnippet = $highlights[ 'source_text.plain' ][ 0 ];
			if ( $this->containsMatches( $sourceSnippet ) ) {
				return $sourceSnippet;
			}
		}
		if ( isset( $highlights[ 'text' ] ) ) {
			$mainSnippet = $highlights[ 'text' ][ 0 ];
			if ( $this->containsMatches( $mainSnippet ) ) {
				return $mainSnippet;
			}
		}
		if ( isset( $highlights[ 'auxiliary_text' ] ) ) {
			$auxSnippet = $highlights[ 'auxiliary_text' ][ 0 ];
			if ( $this->containsMatches( $auxSnippet ) ) {
				return $auxSnippet;
			}
		}
		if ( isset( $highlights[ 'file_text' ] ) ) {
			$fileSnippet = $highlights[ 'file_text' ][ 0 ];
			if ( $this->containsMatches( $fileSnippet ) ) {
				$this->isFileMatch = true;
				return $fileSnippet;
			}
		}
		return $mainSnippet;
	}

	/**
	 * @return string
	 */
	public function getTitleSnippet() {
		return $this->titleSnippet;
	}

	/**
	 * @return Title|null
	 */
	public function getRedirectTitle() {
		return $this->redirectTitle;
	}

	/**
	 * @return string
	 */
	public function getRedirectSnippet() {
		return $this->redirectSnippet;
	}

	/**
	 * @param array $terms
	 * @return string|null
	 */
	public function getTextSnippet( $terms = [] ) {
		return $this->textSnippet;
	}

	/**
	 * @return string
	 */
	public function getSectionSnippet() {
		return $this->sectionSnippet;
	}

	/**
	 * @return Title|null
	 */
	public function getSectionTitle() {
		return $this->sectionTitle;
	}

	/**
	 * @return string
	 */
	public function getCategorySnippet() {
		return $this->categorySnippet;
	}

	/**
	 * @return int
	 */
	public function getWordCount() {
		return $this->wordCount;
	}

	/**
	 * @return int
	 */
	public function getByteSize() {
		return $this->byteSize;
	}

	/**
	 * @return string
	 */
	public function getTimestamp() {
		return $this->timestamp->getTimestamp( TS_MW );
	}

	/**
	 * @return bool
	 */
	public function isFileMatch() {
		return $this->isFileMatch;
	}

	/**
	 * @return string
	 */
	public function getInterwikiPrefix() {
		return $this->getTitle()->getInterwiki();
	}

	/**
	 * @return string
	 */
	public function getInterwikiNamespaceText() {
		// Seems to be only useful for API
		return $this->namespaceText;
	}

	/**
	 * @return string
	 */
	public function getDocId() {
		return $this->docId;
	}

	/**
	 * @return float the score
	 */
	public function getScore() {
		return $this->score;
	}

	/**
	 * @return array lucene score explanation
	 */
	public function getExplanation() {
		return $this->explanation;
	}

	/**
	 * @return TitleHelper
	 */
	protected function getTitleHelper(): TitleHelper {
		return $this->titleHelper;
	}
}
