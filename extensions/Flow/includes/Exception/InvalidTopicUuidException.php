<?php

namespace Flow\Exception;

/**
 * Specific exception thrown when a page within NS_TOPIC is requested
 * through WorkflowLoaderFactory and it is an invalid uuid
 */
class InvalidTopicUuidException extends InvalidInputException {
	protected function getErrorCodeList() {
		// flow-error-invalid-input
		return [ 'invalid-input' ];
	}

	public function getHTML() {
		return wfMessage( 'flow-error-invalid-topic-uuid' )->escaped();
	}

	public function getPageTitle() {
		return wfMessage( 'flow-error-invalid-topic-uuid-title' )->text();
	}
}
