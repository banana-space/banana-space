<?php

namespace Flow\Import\Plain;

use ArrayIterator;
use Flow\Import\IImportHeader;
use Flow\Import\IObjectRevision;

class ImportHeader implements IImportHeader {
	/** @var IObjectRevision[] */
	protected $revisions;
	/** @var string */
	protected $objectKey;

	/**
	 * @param IObjectRevision[] $revisions
	 * @param string $objectKey
	 */
	public function __construct( array $revisions, $objectKey ) {
		$this->revisions = $revisions;
		$this->objectKey = $objectKey;
	}

	public function getRevisions() {
		return new ArrayIterator( $this->revisions );
	}

	public function getObjectKey() {
		return $this->objectKey;
	}
}
