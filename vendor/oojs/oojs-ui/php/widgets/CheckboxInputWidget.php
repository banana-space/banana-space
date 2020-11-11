<?php

namespace OOUI;

/**
 * Checkbox input widget.
 */
class CheckboxInputWidget extends InputWidget {

	/* Static Properties */

	public static $tagName = 'span';

	/* Properties */

	/**
	 * Whether the checkbox is selected.
	 *
	 * @var boolean
	 */
	protected $selected;

	/**
	 * @param array $config Configuration options
	 * @param bool $config['selected'] Whether the checkbox is initially selected
	 *   (default: false)
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Properties
		$this->checkIcon = new IconWidget( [
			'icon' => 'check',
			'classes' => [ 'oo-ui-checkboxInputWidget-checkIcon' ],
		] );

		// Initialization
		$this->addClasses( [ 'oo-ui-checkboxInputWidget' ] );
		// Required for pretty styling in WikimediaUI theme
		$this->appendContent( $this->checkIcon );
		$this->setSelected( isset( $config['selected'] ) ? $config['selected'] : false );
	}

	protected function getInputElement( $config ) {
		return ( new Tag( 'input' ) )->setAttributes( [ 'type' => 'checkbox' ] );
	}

	/**
	 * Set selection state of this checkbox.
	 *
	 * @param bool $state Whether the checkbox is selected
	 * @return $this
	 */
	public function setSelected( $state ) {
		$this->selected = (bool)$state;
		if ( $this->selected ) {
			$this->input->setAttributes( [ 'checked' => 'checked' ] );
		} else {
			$this->input->removeAttributes( [ 'checked' ] );
		}
		return $this;
	}

	/**
	 * Check if this checkbox is selected.
	 *
	 * @return bool Checkbox is selected
	 */
	public function isSelected() {
		return $this->selected;
	}

	public function getConfig( &$config ) {
		if ( $this->selected ) {
			$config['selected'] = $this->selected;
		}
		return parent::getConfig( $config );
	}
}
