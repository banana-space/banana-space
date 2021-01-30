<?php

namespace Flow\Exception;

/**
 * Category: invalid input exception
 *
 * This is not logged, and must *only* be used when the error is caused by invalid end-user
 * input.  The same applies to the subclasses.
 *
 * If it is a logic error (including a missing or incorrect parameter not directly caused
 * by user input), or another kind of failure, another (loggable) exception must be used.
 */
class InvalidInputException extends FlowException {
	protected function getErrorCodeList() {
		// Comments are i18n messages, for grepping
		return [
			'invalid-input',
			// flow-error-invalid-input
			'missing-revision',
			// flow-error-missing-revision
			'revision-comparison',
			// flow-error-revision-comparison
			'invalid-workflow',
			// flow-error-invalid-workflow
		];
	}

	/**
	 * Bad request
	 * @return int
	 */
	public function getStatusCode() {
		return 400;
	}

	/**
	 * Do not log exception resulting from input error
	 * @return bool
	 */
	public function isLoggable() {
		return false;
	}
}
