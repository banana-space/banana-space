<?php

namespace OOUI;

/**
 * Input widget with a text field.
 */
class SearchInputWidget extends TextInputWidget {

	public function __construct( array $config = [] ) {
		// Config initialization
		$config = array_merge( [
			'icon' => 'search',
		], $config );

		// Parent constructor
		parent::__construct( $config );
	}

	protected function getSaneType( $config ) {
		return 'search';
	}
}
