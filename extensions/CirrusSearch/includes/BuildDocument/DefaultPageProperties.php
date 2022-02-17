<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\Util;
use Elastica\Document;
use IDatabase;
use MWTimestamp;
use Title;
use WikiPage;

/**
 * Default properties attached to all page documents.
 */
class DefaultPageProperties implements PagePropertyBuilder {
	/** @var IDatabase $db Wiki database to query additional page properties from. */
	private $db;

	/**
	 * @param IDatabase $db Wiki database to query additional page properties from.
	 */
	public function __construct( IDatabase $db ) {
		$this->db = $db;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc The document to be populated
	 * @param WikiPage $page The page to scope operation to
	 */
	public function initialize( Document $doc, WikiPage $page ): void {
		$title = $page->getTitle();
		$doc->set( 'wiki', wfWikiID() );
		$doc->set( 'namespace',
			$title->getNamespace() );
		$doc->set( 'namespace_text',
			Util::getNamespaceText( $title ) );
		$doc->set( 'title', $title->getText() );
		$doc->set( 'timestamp',
			wfTimestamp( TS_ISO_8601, $page->getTimestamp() ) );
		$createTs = $this->loadCreateTimestamp(
			$page->getId(), TS_ISO_8601 );
		if ( $createTs !== false ) {
			$doc->set( 'create_timestamp', $createTs );
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function finishInitializeBatch(): void {
		// NOOP
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc
	 * @param Title $title
	 */
	public function finalize( Document $doc, Title $title ): void {
		// NOOP
	}

	/**
	 * Timestamp the oldest revision of this page was created.
	 * @param int $pageId
	 * @param int $style TS_* output format constant
	 * @return string|bool Formatted timestamp or false on failure
	 */
	private function loadCreateTimestamp( int $pageId, int $style ) {
		$row = $this->db->selectRow(
			'revision',
			'rev_timestamp',
			[ 'rev_page' => $pageId ],
			__METHOD__,
			[ 'ORDER BY' => 'rev_timestamp ASC' ]
		);
		if ( !$row ) {
			return false;
		}
		return MWTimestamp::convert( $style, $row->rev_timestamp );
	}
}
