<?php

namespace Flow\Import\LiquidThreadsApi;

/**
 * Cached MediaWiki page data.
 */
class CachedPageData extends CachedApiData {
	protected function retrieve( array $ids ) {
		return $this->backend->retrievePageDataById( $ids );
	}
}
