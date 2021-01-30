<?php

namespace Flow\Exception;

/**
 * Specific exception thrown when a workflow is requested by id through
 * WorkflowLoaderFactory and it does not exist.
 */
class UnknownWorkflowIdException extends InvalidInputException {
	protected function getErrorCodeList() {
		// flow-error-invalid-input
		return [ 'invalid-input' ];
	}

	public function getPageTitle() {
		return wfMessage( 'flow-error-unknown-workflow-id-title' )->text();
	}
}
