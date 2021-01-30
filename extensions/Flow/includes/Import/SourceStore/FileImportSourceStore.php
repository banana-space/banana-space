<?php

namespace Flow\Import\SourceStore;

use Flow\Import\IImportObject;
use Flow\Model\UUID;

class FileImportSourceStore implements SourceStoreInterface {
	/** @var string */
	protected $filename;
	/** @var array */
	protected $data;

	public function __construct( $filename ) {
		$this->filename = $filename;
		$this->load();
	}

	protected function load() {
		if ( file_exists( $this->filename ) ) {
			$this->data = json_decode( file_get_contents( $this->filename ), true );
		} else {
			$this->data = [];
		}
	}

	public function save() {
		$bytesWritten = file_put_contents( $this->filename, json_encode( $this->data ) );
		if ( $bytesWritten === false ) {
			throw new Exception( 'Could not write out source store to ' . $this->filename );
		}
	}

	public function rollback() {
		$this->load();
	}

	public function setAssociation( UUID $objectId, $importSourceKey ) {
		$this->data[$importSourceKey] = $objectId->getAlphadecimal();
	}

	public function getImportedId( IImportObject $importObject ) {
		$importSourceKey = $importObject->getObjectKey();
		return isset( $this->data[$importSourceKey] )
			? UUID::create( $this->data[$importSourceKey] )
			: false;
	}
}
