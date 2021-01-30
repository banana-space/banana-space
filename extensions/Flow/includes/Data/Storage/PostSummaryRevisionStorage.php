<?php

namespace Flow\Data\Storage;

/**
 * Generic storage implementation for PostSummary instances
 */
class PostSummaryRevisionStorage extends RevisionStorage {
	protected function getRevType() {
		return 'post-summary';
	}
}
