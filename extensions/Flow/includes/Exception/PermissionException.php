<?php

namespace Flow\Exception;

/**
 * Category: permission related exception
 */
class PermissionException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-insufficient-permission
		return [ 'insufficient-permission' ];
	}

	/**
	 * Do not log exception resulting from user requesting
	 * disallowed content.
	 * @return bool
	 */
	public function isLoggable() {
		return false;
	}
}
