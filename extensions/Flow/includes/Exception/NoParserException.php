<?php

namespace Flow\Exception;

/**
 * Category: Parsoid
 */
class NoParserException extends FlowException {
	protected function getErrorCodeList() {
		// flow-error-process-wikitext
		return [ 'process-wikitext' ];
	}
}
