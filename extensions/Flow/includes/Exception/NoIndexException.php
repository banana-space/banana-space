<?php

namespace Flow\Exception;

/**
 * Category: Data Index
 */
class NoIndexException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-no-index
		return [ 'no-index' ];
	}
}
