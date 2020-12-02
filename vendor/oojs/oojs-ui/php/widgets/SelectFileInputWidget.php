<?php

namespace OOUI;

/**
 * Generic widget for buttons.
 */
class SelectFileInputWidget extends InputWidget {

	/* Static Properties */

	protected $accept, $placeholder;

	/**
	 * @param array $config Configuration options
	 *      - string[]|null $config['accept'] MIME types to accept. null accepts all types.
	 *  (default: null)
	 *      - bool $config['multiple'] Allow multiple files to be selected. (default: false)
	 *      - string $config['placeholder'] Text to display when no file is selected.
	 *      - array $config['button'] Config to pass to select file button.
	 *      - string $config['icon'] Icon to show next to file info
	 *  and show a preview (for performance).
	 */
	public function __construct( array $config = [] ) {
		// Config initialization
		$config = array_merge( [
			'accept' => null,
			'multiple' => false,
			'placeholder' => null,
			'button' => null,
			'icon' => null,
		], $config );

		// Parent constructor
		parent::__construct( $config );

		// Properties
		$this->accept = $config['accept'];
		$this->multiple = $config['multiple'];
		$this->placeholder = $config['placeholder'];
		$this->button = $config['button'];
		$this->icon = $config['icon'];

		$this->addClasses( [ 'oo-ui-selectFileWidget' ] );

		// Initialization
		$this->input->setAttributes( [
			'type' => 'file'
		] );
		if ( $this->multiple ) {
			$this->input->setAttributes( [
				'multiple' => ''
			] );
		}
		if ( $this->accept ) {
			$this->input->setAttributes( [
				'accept' => implode( ',', $this->accept )
			] );
		}
	}

	public function getConfig( &$config ) {
		if ( $this->accept !== null ) {
			$config['accept'] = $this->accept;
		}
		if ( $this->multiple !== null ) {
			$config['multiple'] = $this->multiple;
		}
		if ( $this->placeholder !== null ) {
			$config['placeholder'] = $this->placeholder;
		}
		if ( $this->button !== null ) {
			$config['button'] = $this->button;
		}
		if ( $this->icon !== null ) {
			$config['icon'] = $this->icon;
		}
		return parent::getConfig( $config );
	}
}
