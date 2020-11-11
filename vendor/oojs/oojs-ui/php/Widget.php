<?php

namespace OOUI;

/**
 * User interface control.
 *
 * @abstract
 */
class Widget extends Element {

	/* Properties */

	/**
	 * Disabled.
	 *
	 * @var boolean Widget is disabled
	 */
	protected $disabled = false;

	/* Methods */

	/**
	 * @param array $config Configuration options
	 * @param bool $config['disabled'] Disable (default: false)
	 */
	public function __construct( array $config = [] ) {
		// Initialize config
		$config = array_merge( [ 'disabled' => false ], $config );

		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'oo-ui-widget' ] );
		$this->setDisabled( $config['disabled'] );
	}

	/**
	 * Check if the widget is disabled.
	 *
	 * @return bool Button is disabled
	 */
	public function isDisabled() {
		return $this->disabled;
	}

	/**
	 * Set the disabled state of the widget.
	 *
	 * This should probably change the widgets' appearance and prevent it from being used.
	 *
	 * @param bool $disabled Disable widget
	 * @return $this
	 */
	public function setDisabled( $disabled ) {
		$this->disabled = !!$disabled;
		$this->toggleClasses( [ 'oo-ui-widget-disabled' ], $this->disabled );
		$this->toggleClasses( [ 'oo-ui-widget-enabled' ], !$this->disabled );
		$this->setAttributes( [ 'aria-disabled' => $this->disabled ? 'true' : 'false' ] );

		return $this;
	}

	/**
	 * Get an ID of a labelable node which is part of this widget, if any, to be used for
	 * `<label for>` value.
	 *
	 * @return string|null The ID of the labelable node
	 */
	public function getInputId() {
		return null;
	}

	public function getConfig( &$config ) {
		if ( $this->disabled ) {
			$config['disabled'] = $this->disabled;
		}
		return parent::getConfig( $config );
	}
}
