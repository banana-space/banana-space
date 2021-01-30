<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\IRevisionableObject;

abstract class PageRevisionedObject implements IRevisionableObject {
	/** @var int */
	protected $pageId;

	/**
	 * @var ImportSource
	 */
	protected $importSource;

	/**
	 * @param ImportSource $source
	 * @param int $pageId ID of the remote page
	 */
	public function __construct( $source, $pageId ) {
		$this->importSource = $source;
		$this->pageId = $pageId;
	}

	/**
	 * Gets the raw revisions, after filtering but before being converted to
	 * ImportRevision.
	 *
	 * @return array Page data with filtered revisions
	 */
	protected function getRevisionData() {
		$pageData = $this->importSource->getPageData( $this->pageId );
		// filter revisions without content (deleted)
		foreach ( $pageData['revisions'] as $key => $value ) {
			if ( isset( $value['texthidden'] ) ) {
				unset( $pageData['revisions'][$key] );
			}
		}
		// the iterators expect this to be a 0 indexed list
		$pageData['revisions'] = array_values( $pageData['revisions'] );
		return $pageData;
	}

	public function getRevisions() {
		$pageData = $this->getRevisionData();
		$scriptUser = $this->importSource->getScriptUser();
		return new RevisionIterator( $pageData, $this, function ( $data, $parent ) use ( $scriptUser ) {
			return new ImportRevision( $data, $parent, $scriptUser );
		} );
	}
}
