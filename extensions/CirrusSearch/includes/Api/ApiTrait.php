<?php

namespace CirrusSearch\Api;

use CirrusSearch\Connection;
use CirrusSearch\SearchConfig;
use CirrusSearch\Searcher;
use Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use PageArchive;
use Title;
use User;

trait ApiTrait {
	/** @var Connection */
	private $connection;
	/** @var SearchConfig */
	private $searchConfig;

	/**
	 * @return Connection
	 */
	public function getCirrusConnection() {
		if ( $this->connection === null ) {
			$this->connection = new Connection( $this->getSearchConfig() );
		}
		return $this->connection;
	}

	/**
	 * @return SearchConfig
	 */
	protected function getSearchConfig() {
		if ( $this->searchConfig === null ) {
			$this->searchConfig = MediaWikiServices::getInstance()
				->getConfigFactory()
				->makeConfig( 'CirrusSearch' );
		}
		return $this->searchConfig;
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function loadDocuments( Title $title ) {
		list( $docId, $hasRedirects ) = $this->determineCirrusDocId( $title );
		if ( $docId === null ) {
			return [];
		}
		// could be optimized by implementing multi-get but not
		// expecting much usage except debugging/tests.
		$searcher = new Searcher( $this->getCirrusConnection(), 0, 0, $this->getSearchConfig(), [], $this->getUser() );
		$esSources = $searcher->get( [ $docId ], true );
		$result = [];
		if ( $esSources->isOK() ) {
			foreach ( $esSources->getValue() as $esSource ) {
				// If we have followed redirects only report the
				// article dump if the redirect has been indexed. If it
				// hasn't been indexed this document does not represent
				// the original title.
				if ( $hasRedirects &&
					 !$this->hasRedirect( $esSource->getData(), $title )
				) {
					continue;
				}

				// If this was not a redirect and the title doesn't match that
				// means a page was moved, but elasticsearch has not yet been
				// updated. Don't return the document that doesn't actually
				// represent the page (yet).
				if ( !$hasRedirects && $esSource->getData()['title'] != $title->getText() ) {
					continue;
				}

				$result[] = [
					'index' => $esSource->getIndex(),
					'type' => $esSource->getType(),
					'id' => $esSource->getId(),
					'version' => $esSource->getVersion(),
					'source' => $esSource->getData(),
				];
			}
		}
		return $result;
	}

	/**
	 * Trace redirects to find the page id the title should be indexed to in
	 * cirrussearch. Differs from Updater::traceRedirects in that this also
	 * supports archived pages. Archive support is important for integration
	 * tests that need to know when a page that was deleted from SQL was
	 * finally removed from elasticsearch.
	 *
	 * This still fails to find the correct page id if something was moved, as
	 * that page is renamed rather than being moved to the archive. We could
	 * further complicate things by looking into move logs but not sure that
	 * is worth the complication.
	 *
	 * @param Title $title
	 * @return array Two element array containing first the cirrus doc id
	 *  the title should have been indexed into elasticsearch and second a
	 *  boolean indicating if redirects were followed. If the page would
	 *  not be indexed (for example a redirect loop, or redirect to
	 *  invalid page) the first array element will be null.
	 */
	private function determineCirrusDocId( Title $title ) {
		$hasRedirects = false;
		$seen = [];
		$now = wfTimestamp( TS_MW );
		$contentHandlerFactory = MediaWikiServices::getInstance()->getContentHandlerFactory();
		while ( true ) {
			if ( isset( $seen[$title->getPrefixedText()] ) || count( $seen ) > 10 ) {
				return [ null, $hasRedirects ];
			}
			$seen[$title->getPrefixedText()] = true;

			// To help the integration tests figure out when a deleted page has
			// been removed from the elasticsearch index we lookup the page in
			// the archive to get it's page id. getPreviousRevisionRecord will
			// check both the archive and live content to return the most recent.
			$revRecord = ( new PageArchive( $title, $this->getConfig() ) )
				->getPreviousRevisionRecord( $now );
			if ( !$revRecord ) {
				return [ null, $hasRedirects ];
			}

			$pageId = $revRecord->getPageId();
			$mainSlot = $revRecord->getSlot( SlotRecord::MAIN, RevisionRecord::RAW );
			$handler = $contentHandlerFactory->getContentHandler( $mainSlot->getModel() );
			if ( !$handler->supportsRedirects() ) {
				return [ $pageId, $hasRedirects ];
			}
			$content = $mainSlot->getContent();
			// getUltimateRedirectTarget() would be prefered, but it wont find
			// archive pages...
			if ( !$content->isRedirect() ) {
				return [ $this->getSearchConfig()->makeId( $pageId ), $hasRedirects ];
			}
			$redirect = $content->getRedirectTarget();
			if ( !$redirect ) {
				// TODO: Can this happen?
				return [ $pageId, $hasRedirects ];
			}

			$hasRedirects = true;
			$title = $redirect;
		}
	}

	/**
	 * @param array $source _source document from elasticsearch
	 * @param Title $title Title to check for redirect
	 * @return bool True when $title is stored as a redirect in $source
	 */
	private function hasRedirect( array $source, Title $title ) {
		if ( !isset( $source['redirect'] ) ) {
			return false;
		}
		foreach ( $source['redirect'] as $redirect ) {
			if ( $redirect['namespace'] === $title->getNamespace()
				&& $redirect['title'] === $title->getText()
			) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return Config
	 */
	abstract public function getConfig();

	/**
	 * @return User
	 */
	abstract public function getUser();

}
