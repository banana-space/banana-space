<?php

namespace Flow\Exception;

/**
 * Category: data model processing exception
 */
class DataModelException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-process-data
		return [ 'process-data' ];
	}
}
