<?php

namespace CirrusSearch\Query;

use CirrusSearch\Hooks;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use Elastica\Query\MoreLikeThis;
use Title;
use WikiPage;

trait MoreLikeTrait {
	/**
	 * @param string $key
	 * @param string $term
	 * @param WarningCollector $warningCollector
	 * @return Title[]
	 */
	protected function doExpand( $key, $term, WarningCollector $warningCollector ) {
		// If no fields have been set we return no results. This can happen if
		// the user override this setting with field names that are not allowed
		// in $this->getConfig()->get( 'CirrusSearchMoreLikeThisAllowedFields' )
		// (see Hooks.php)
		if ( !$this->getConfig()->get( 'CirrusSearchMoreLikeThisFields' ) ) {
			$warningCollector->addWarning( "cirrussearch-mlt-not-configured",  $key );
			return [];
		}
		$titles = $this->collectTitles( $term );
		if ( $titles === [] ) {
			$warningCollector->addWarning( "cirrussearch-mlt-feature-no-valid-titles", $key );
		}
		return $titles;
	}

	/**
	 * @param string $term
	 * @return Title[]
	 */
	private function collectTitles( $term ) {
		if ( $this->getConfig()->getElement( 'CirrusSearchDevelOptions',
			'morelike_collect_titles_from_elastic' )
		) {
			return $this->collectTitlesFromElastic( $term );
		} else {
			return $this->collectTitlesFromDB( $term );
		}
	}

	/**
	 * Use for devel purpose only
	 * @param string $terms
	 * @return Title[]
	 */
	private function collectTitlesFromElastic( $terms ) {
		$titles = [];
		foreach ( explode( '|', $terms ) as $term ) {
			$title = null;
			Hooks::onSearchGetNearMatch( $term, $title );
			if ( $title != null ) {
				$titles[] = $title;
			}
		}
		return $titles;
	}

	/**
	 * @param string $term
	 * @return Title[]
	 */
	private function collectTitlesFromDB( $term ) {
		$titles = [];
		$found = [];
		foreach ( explode( '|', $term ) as $title ) {
			$title = Title::newFromText( trim( $title ) );
			while ( true ) {
				if ( !$title ) {
					continue 2;
				}
				$titleText = $title->getFullText();
				if ( isset( $found[$titleText] ) ) {
					continue 2;
				}
				$found[$titleText] = true;
				if ( !$title->exists() ) {
					continue 2;
				}
				if ( !$title->isRedirect() ) {
					break;
				}
				// If the page was a redirect loop the while( true ) again.
				$page = WikiPage::factory( $title );
				if ( !$page->exists() ) {
					continue 2;
				}
				$title = $page->getRedirectTarget();
			}
			$titles[] = $title;
		}

		return $titles;
	}

	/**
	 * Builds a more like this query for the specified titles. Take care that
	 * this outputs a stable result, regardless of order of configuration
	 * parameters and input titles. The result of this is hashed to generate an
	 * application side cache key. If the result is unstable we will see a
	 * reduced hit rate, and waste cache storage space.
	 *
	 * @param Title[] $titles
	 * @return MoreLikeThis
	 */
	protected function buildMoreLikeQuery( array $titles ) {
		sort( $titles, SORT_STRING );
		$docIds = [];
		$likeDocs = [];
		foreach ( $titles as $title ) {
			$docId = $this->getConfig()->makeId( $title->getArticleID() );
			$docIds[] = $docId;
			$likeDocs[] = [ '_id' => $docId ];
		}

		$moreLikeThisFields = $this->getConfig()->get( 'CirrusSearchMoreLikeThisFields' );
		sort( $moreLikeThisFields );
		$query = new MoreLikeThis();
		$query->setParams( $this->getConfig()->get( 'CirrusSearchMoreLikeThisConfig' ) );
		$query->setFields( $moreLikeThisFields );

		/** @phan-suppress-next-line PhanTypeMismatchArgument library is mis-annotated */
		$query->setLike( $likeDocs );

		return $query;
	}

	/**
	 * @return SearchConfig
	 */
	abstract public function getConfig(): SearchConfig;
}
