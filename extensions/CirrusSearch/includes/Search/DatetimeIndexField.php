<?php

namespace CirrusSearch\Search;

use SearchEngine;

/**
 * Index field representing datetime field.
 * @package CirrusSearch
 */
class DatetimeIndexField extends CirrusIndexField {

	protected $typeName = 'date';

	public function getMapping( SearchEngine $engine ) {
		$config = parent::getMapping( $engine );
		$config['format'] = 'dateOptionalTime';
		return $config;
	}
}
