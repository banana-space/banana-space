<?php

namespace Flow\Import\LiquidThreadsApi;

use ArrayIterator;

/**
 * The Moved* series of topics handle the LQT move stubs.  They need to
 * have their revision content rewriten from #REDIRECT to a template that
 * has visible output like lqt generated per-request.
 */
class MovedImportTopic extends ImportTopic {
	public function getReplies() {
		$topPost = new MovedImportPost( $this->importSource, $this->apiResponse );

		return new ArrayIterator( [ $topPost ] );
	}
}
