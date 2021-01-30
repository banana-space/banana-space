<?php

namespace Flow\Import\Plain;

use Flow\Import\IObjectRevision;

class ObjectRevision implements IObjectRevision {
	/** @var string */
	protected $text;
	/** @var string */
	protected $timestamp;
	/** @var string */
	protected $author;
	/** @var string */
	protected $objectKey;

	/**
	 * @param string $text The content of the revision
	 * @param string $timestamp wfTimestamp() compatible creation timestamp
	 * @param string $author Name of the user that created the revision
	 * @param string $objectKey Unique key identifying this revision
	 */
	public function __construct( $text, $timestamp, $author, $objectKey ) {
		$this->text = $text;
		$this->timestamp = $timestamp;
		$this->author = $author;
		$this->objectKey = $objectKey;
	}

	public function getText() {
		return $this->text;
	}

	public function getTimestamp() {
		return $this->timestamp;
	}

	public function getAuthor() {
		return $this->author;
	}

	public function getObjectKey() {
		return $this->objectKey;
	}
}
