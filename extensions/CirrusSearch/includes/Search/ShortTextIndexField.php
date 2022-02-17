<?php

namespace CirrusSearch\Search;

/**
 * Index field representing a short technical text.
 * ShortText uses a language agnostic analyzer.
 * @package CirrusSearch
 */
class ShortTextIndexField extends CirrusIndexField {
	protected $typeName = 'text';

	public function getMapping( \SearchEngine $engine ) {
		$config = parent::getMapping( $engine );
		$config['analyzer'] = 'short_text';
		$config['search_analyzer'] = 'short_text_search';
		// NOTE: these fields are not used for scoring yet. We should
		// reevaluate these options to
		// - norms => true
		// if we plan to use such fields for scoring and:
		// - index_options => 'offsets'
		// if we plan to support highlighting
		$config += [
			// Omit the length norm because we use it only for filtering
			'norms' => false,
		];
		return $config;
	}
}
