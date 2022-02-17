<?php

namespace CirrusSearch\Search\Fetch;

use CirrusSearch\Search\TitleHelper;
use CirrusSearch\Searcher;
use MediaWiki\Logger\LoggerFactory;
use Title;

trait HighlightingTrait {
	/**
	 * Escape highlighted text coming back from Elasticsearch.
	 *
	 * @param string $snippet highlighted snippet returned from elasticsearch
	 * @return string $snippet with html escaped _except_ highlighting pre and post tags
	 */
	protected function escapeHighlightedText( $snippet ) {
		/**
		 * \p{M} matches any combining Unicode character
		 * \P{M} matches any non-combining Unicode character
		 *
		 * For HIGHLIGHT_PRE_MARKER, move the marker earlier if it occurs before a
		 * combining character, and there is a non-combining character (and zero
		 * or more combining characters) directly before it.
		 *
		 * For HIGHLIGHT_POST_MARKER, move the marker later if it occurs before
		 * one or more combining characters.
		 */
		$snippet = preg_replace( '/(\P{M}\p{M}*)(' . Searcher::HIGHLIGHT_PRE_MARKER .
								 ')(\p{M}+)/u', '$2$1$3', $snippet );
		$snippet = preg_replace( '/(' . Searcher::HIGHLIGHT_POST_MARKER . ')(\p{M}+)/u',
			'$2$1', $snippet );
		return strtr( htmlspecialchars( $snippet ), [
			Searcher::HIGHLIGHT_PRE_MARKER => Searcher::HIGHLIGHT_PRE,
			Searcher::HIGHLIGHT_POST_MARKER => Searcher::HIGHLIGHT_POST
		] );
	}

	/**
	 * Build the redirect title from the highlighted redirect snippet.
	 *
	 * @param \Elastica\Result $result
	 * @param string $snippet Highlighted redirect snippet
	 * @return Title|null object representing the redirect
	 */
	protected function findRedirectTitle( \Elastica\Result $result, $snippet ) {
		$title = $this->stripHighlighting( $snippet );
		// Grab the redirect that matches the highlighted title with the lowest namespace.
		$redirects = $result->redirect;
		// That is pretty arbitrary but it prioritizes 0 over others.
		$best = null;
		if ( $redirects !== null ) {
			foreach ( $redirects as $redirect ) {
				if ( $redirect[ 'title' ] === $title && ( $best === null || $best[ 'namespace' ] > $redirect['namespace'] ) ) {
					$best = $redirect;
				}
			}
		}
		if ( $best === null ) {
			LoggerFactory::getInstance( 'CirrusSearch' )->warning(
				"Search backend highlighted a redirect ({title}) but didn't return it.",
				[ 'title' => $title ]
			);
			return null;
		}
		return $this->getTitleHelper()->makeRedirectTitle( $result, $best['title'], $best['namespace'] );
	}

	/**
	 * Checks if a snippet contains matches by looking for HIGHLIGHT_PRE.
	 *
	 * @param string $snippet highlighted snippet returned from elasticsearch
	 * @return bool true if $snippet contains matches, false otherwise
	 */
	protected function containsMatches( $snippet ) {
		return strpos( $snippet, Searcher::HIGHLIGHT_PRE_MARKER ) !== false;
	}

	/**
	 * @param string $highlighted
	 * @return string
	 */
	protected function stripHighlighting( $highlighted ) {
		$markers = [ Searcher::HIGHLIGHT_PRE_MARKER, Searcher::HIGHLIGHT_POST_MARKER ];
		return str_replace( $markers, '', $highlighted );
	}

	/**
	 * @param string $highlighted
	 * @param Title $title
	 * @return Title
	 */
	protected function findSectionTitle( $highlighted, Title $title ) {
		return $title->createFragmentTarget( $this->getTitleHelper()->sanitizeSectionFragment(
			$this->stripHighlighting( $highlighted )
		) );
	}

	/**
	 * @return TitleHelper
	 */
	abstract protected function getTitleHelper(): TitleHelper;
}
