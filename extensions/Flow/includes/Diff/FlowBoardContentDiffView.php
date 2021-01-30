<?php

namespace Flow\Diff;

use DifferenceEngine;

/**
 * Provides a mechanism for handling diffs of Flow posts without throwing exceptions.
 */
class FlowBoardContentDiffView extends DifferenceEngine {

	public function getDiffBody() {
		return false;
	}
}
