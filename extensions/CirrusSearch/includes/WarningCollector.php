<?php

namespace CirrusSearch;

/**
 * Warning collector interface
 */
interface WarningCollector {

	/**
	 * Add a warning
	 *
	 * @param string $message i18n message key
	 * @param mixed ...$params
	 */
	public function addWarning( $message, ...$params );
}
