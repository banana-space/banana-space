<?php

namespace Flow\Import\LiquidThreadsApi;

use ArrayIterator;
use Flow\Import\IImportHeader;
use Flow\Import\IObjectRevision;
use MWTimestamp;
use Title;

class ImportHeader extends PageRevisionedObject implements IImportHeader {
	/** @var ApiBackend */
	protected $api;
	/** @var string */
	protected $title;
	/** @var array|null */
	protected $pageData;
	/** @var ImportSource */
	protected $source;

	public function __construct( ApiBackend $api, ImportSource $source, $title ) {
		$this->api = $api;
		$this->title = $title;
		$this->source = $source;
		$this->pageData = null;
	}

	public function getRevisions() {
		if ( $this->pageData === null ) {
			// Previous revisions of the header are preserved in the underlying wikitext
			// page history. Only the top revision is imported.
			$response = $this->api->retrieveTopRevisionByTitle( [ $this->title ] );
			$this->pageData = reset( $response );
		}

		$revisions = [];

		if ( isset( $this->pageData['revisions'] ) && count( $this->pageData['revisions'] ) > 0 ) {
			$lastLqtRevision = new ImportRevision(
				end( $this->pageData['revisions'] ), $this, $this->source->getScriptUser()
			);

			$titleObject = Title::newFromText( $this->title );
			$cleanupRevision = $this->createHeaderCleanupRevision( $lastLqtRevision, $titleObject );

			$revisions = [
				$lastLqtRevision,
				$cleanupRevision
			];
		}

		return new ArrayIterator( $revisions );
	}

	/**
	 * @param IObjectRevision $lastRevision last imported header revision
	 * @param Title $archiveTitle archive page title associated with header
	 * @return IObjectRevision generated revision for cleanup edit
	 */
	protected function createHeaderCleanupRevision( IObjectRevision $lastRevision, Title $archiveTitle ) {
		$wikitextForLastRevision = $lastRevision->getText();
		// This is will remove all instances, without attempting to check if it's in
		// nowiki, etc.  It also ignores case and spaces in places where it doesn't
		// matter.
		$newWikitext = ConversionStrategy::removeLqtMagicWord( $wikitextForLastRevision );
		$templateName = wfMessage( 'flow-importer-lqt-converted-template' )->inContentLanguage(
		)->plain();
		$arguments = implode(
			'|',
			[
				'archive=' . $archiveTitle->getPrefixedText(),
				'date=' . MWTimestamp::getInstance()->timestamp->format( 'Y-m-d' ),
			]
		);

		$newWikitext .= "\n\n{{{$templateName}|$arguments}}";

		$cleanupRevision = new ScriptedImportRevision(
			$this, $this->source->getScriptUser(), $newWikitext, $lastRevision
		);

		return $cleanupRevision;
	}

	public function getObjectKey() {
		return $this->source->getObjectKey( 'header_for', $this->title );
	}
}
