<?php

namespace Flow\Exception;

/**
 * Category: wikitext/html conversion exception
 */
class WikitextException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-process-wikitext
		return [ 'process-wikitext' ];
	}
}
