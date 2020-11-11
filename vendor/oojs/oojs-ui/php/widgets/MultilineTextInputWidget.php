<?php

namespace OOUI;

/**
 * Input widget with a text field.
 */
class MultilineTextInputWidget extends TextInputWidget {

	/**
	 * Allow multiple lines of text.
	 *
	 * @var boolean
	 */
	protected $multiline = true;

	/**
	 * @param array $config Configuration options
	 * @param int $config['rows'] If multiline, number of visible lines in textarea
	 */
	public function __construct( array $config = [] ) {
		// Config initialization
		$config = array_merge( [
			'readOnly' => false,
			'autofocus' => false,
			'required' => false,
			'multiline' => true,
		], $config );

		// Parent constructor
		parent::__construct( $config );
	}

	/**
	 * Check if input supports multiple lines.
	 *
	 * @return bool
	 */
	public function isMultiline() {
		return true;
	}
}
