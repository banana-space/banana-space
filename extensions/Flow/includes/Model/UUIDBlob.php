<?php

namespace Flow\Model;

use Wikimedia\Rdbms\Blob;

/**
 * Extend Blob so we can identify UUID specific blobs
 */
class UUIDBlob extends Blob {
	/**
	 * We'll want to be able to compare the (string) value of 2 blobs.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->fetch();
	}
}
