<?php

namespace Flow\Exception;

/**
 * Category: commit failure exception
 */
class FailCommitException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-fail-commit
		return [ 'fail-commit' ];
	}
}
