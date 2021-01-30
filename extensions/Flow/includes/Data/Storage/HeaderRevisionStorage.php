<?php

namespace Flow\Data\Storage;

/**
 * Generic storage implementation for Header revision instances
 */
class HeaderRevisionStorage extends RevisionStorage {
	protected function getRevType() {
		return 'header';
	}
}
