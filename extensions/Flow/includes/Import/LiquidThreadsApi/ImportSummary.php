<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\IImportSummary;
use Flow\Import\ImportException;

class ImportSummary extends PageRevisionedObject implements IImportSummary {
	/** @var ImportSource */
	protected $source;

	/**
	 * @param array $apiResponse
	 * @param ImportSource $source
	 * @throws ImportException
	 */
	public function __construct( array $apiResponse, ImportSource $source ) {
		parent::__construct( $source, $apiResponse['pageid'] );
	}

	public function getObjectKey() {
		return $this->importSource->getObjectKey( 'summary_id', $this->pageId );
	}
}
