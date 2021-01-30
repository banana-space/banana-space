<?php

namespace Flow\Exception;

/**
 * Category: data persistency exception
 */
class DataPersistenceException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-process-data
		return [ 'process-data' ];
	}
}
