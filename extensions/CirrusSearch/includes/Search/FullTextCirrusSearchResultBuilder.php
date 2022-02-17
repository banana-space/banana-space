<?php

namespace CirrusSearch\Search;

use CirrusSearch\Search\Fetch\HighlightedField;
use CirrusSearch\Search\Fetch\HighlightingTrait;
use MWTimestamp;
use Title;

class FullTextCirrusSearchResultBuilder {
	use HighlightingTrait;

	/** @var CirrusSearchResultBuilder|null */
	private $builder;

	/** @var TitleHelper */
	private $titleHelper;

	/** @var HighlightedField[][] indexed per target and ordered by priority */
	private $highligtedFields;

	/**
	 * @param TitleHelper $titleHelper
	 * @param HighlightedField[][] $hlFieldsPerTarget list of highlighted field indexed per target and sorted by priority
	 */
	public function __construct( TitleHelper $titleHelper, array $hlFieldsPerTarget ) {
		$this->titleHelper = $titleHelper;
		$this->highligtedFields = $hlFieldsPerTarget;
	}

	/**
	 * @param Title $title
	 * @param string $docId
	 * @return CirrusSearchResultBuilder
	 */
	private function newBuilder( Title $title, $docId ): CirrusSearchResultBuilder {
		if ( $this->builder === null ) {
			$this->builder = new CirrusSearchResultBuilder( $title, $docId );
		} else {
			$this->builder->reset( $title, $docId );
		}
		return $this->builder;
	}

	/**
	 * @param \Elastica\Result $result
	 * @return CirrusSearchResult
	 */
	public function build( \Elastica\Result $result ): CirrusSearchResult {
		$title = $this->getTitleHelper()->makeTitle( $result );
		$fields = $result->getFields();
		$builder = $this->newBuilder( $title, $result->getId() )
			->wordCount( $fields['text.word_count'][0] ?? 0 )
			->byteSize( $result->text_bytes ?? 0 )
			->timestamp( new MWTimestamp( $result->timestamp ) )
			->score( $result->getScore() )
			->explanation( $result->getExplanation() );

		if ( isset( $result->namespace_text ) ) {
			$builder->interwikiNamespaceText( $result->namespace_text );
		}

		$highlights = $result->getHighlights();
		$this->doTitleSnippet( $title, $result, $highlights );
		$this->doMainSnippet( $highlights );
		$this->doHeadings( $title, $highlights );
		$this->doCategory( $highlights );

		return $builder->build();
	}

	/**
	 * @return TitleHelper
	 */
	protected function getTitleHelper(): TitleHelper {
		return $this->titleHelper;
	}

	private function doTitleSnippet( Title $title, \Elastica\Result $result, array $highlights ) {
		$matched = false;
		foreach ( $this->highligtedFields[ArrayCirrusSearchResult::TITLE_SNIPPET] as $hlField ) {
			if ( isset( $highlights[$hlField->getFieldName()] ) ) {
				$nstext = $title->getNamespace() === 0 ? '' :
					$this->titleHelper->getNamespaceText( $title ) . ':';
				$this->builder->titleSnippet( $nstext . $this->escapeHighlightedText( $highlights[ $hlField->getFieldName() ][ 0 ] ) );
				$matched = true;
				break;
			}
		}
		if ( !$matched && $title->isExternal() ) {
			// Interwiki searches are weird. They won't have title highlights by design, but
			// if we don't return a title snippet we'll get weird display results.
			$this->builder->titleSnippet( $title->getText() );
		}

		if ( !$matched && isset( $this->highligtedFields[ArrayCirrusSearchResult::REDIRECT_SNIPPET] ) ) {
			foreach ( $this->highligtedFields[ArrayCirrusSearchResult::REDIRECT_SNIPPET] as $hlField ) {
				// Make sure to find the redirect title before escaping because escaping breaks it....
				if ( !isset( $highlights[$hlField->getFieldName()][0] ) ) {
					continue;
				}
				$redirTitle = $this->findRedirectTitle( $result, $highlights[$hlField->getFieldName()][0] );
				if ( $redirTitle !== null ) {
					$this->builder->redirectTitle( $redirTitle )
						->redirectSnippet( $this->escapeHighlightedText( $highlights[$hlField->getFieldName()][0] ) );
					break;
				}
			}
		}
	}

	private function doMainSnippet( $highlights ) {
		$hasTextSnippet = false;
		foreach ( $this->highligtedFields[ArrayCirrusSearchResult::TEXT_SNIPPET] as $hlField ) {
			if ( isset( $highlights[$hlField->getFieldName()][0] ) ) {
				$snippet = $highlights[$hlField->getFieldName()][0];
				if ( $this->containsMatches( $snippet ) ) {
					$this->builder->textSnippet( $this->escapeHighlightedText( $snippet ) )->
						fileMatch( $hlField->getFieldName() === 'file_text' );
					$hasTextSnippet = true;
					break;
				}
			}
		}

		// Hardcode the fallback to the "text" highlight, it generally contains the beginning of the
		// text content if nothing has matched.
		if ( !$hasTextSnippet && isset( $highlights['text'][0] ) ) {
			$this->builder->textSnippet( $this->escapeHighlightedText( $highlights['text'][0] ) );
		}
	}

	private function doHeadings( Title $title, $highlights ) {
		if ( !isset( $this->highligtedFields[ArrayCirrusSearchResult::SECTION_SNIPPET] ) ) {
			return;
		}
		foreach ( $this->highligtedFields[ArrayCirrusSearchResult::SECTION_SNIPPET] as $hlField ) {
			if ( isset( $highlights[$hlField->getFieldName()] ) ) {
				$this->builder->sectionSnippet( $this->escapeHighlightedText( $highlights[$hlField->getFieldName()][0] ) )
					->sectionTitle( $this->findSectionTitle( $highlights[$hlField->getFieldName()][0], $title ) );
				break;
			}
		}
	}

	private function doCategory( $highlights ) {
		if ( !isset( $this->highligtedFields[ArrayCirrusSearchResult::CATEGORY_SNIPPET] ) ) {
			return;
		}
		foreach ( $this->highligtedFields[ArrayCirrusSearchResult::CATEGORY_SNIPPET] as $hlField ) {
			if ( isset( $highlights[$hlField->getFieldName()] ) ) {
				$this->builder->categorySnippet( $this->escapeHighlightedText( $highlights[$hlField->getFieldName()][0] ) );
			}
			break;
		}
	}
}
