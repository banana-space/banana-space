<?php

namespace OOUI;

/**
 * Element with a button.
 *
 * Buttons are used for controls which can be clicked. They can be configured to use tab indexing
 * and access keys for accessibility purposes.
 *
 * @abstract
 */
trait ButtonElement {

	/**
	 * Button is framed.
	 *
	 * @var boolean
	 */
	protected $framed = false;

	/**
	 * @var Tag
	 */
	protected $button;

	/**
	 * @param array $config Configuration options
	 * @param bool $config['framed'] Render button with a frame (default: true)
	 */
	public function initializeButtonElement( array $config = [] ) {
		// Properties
		if ( ! $this instanceof Element ) {
			throw new Exception( "ButtonElement trait can only be used on Element instances" );
		}
		$target = isset( $config['button'] ) ? $config['button'] : new Tag( 'a' );
		$this->button = $target;

		// Initialization
		$this->addClasses( [ 'oo-ui-buttonElement' ] );
		$this->button->addClasses( [ 'oo-ui-buttonElement-button' ] );
		$this->toggleFramed( isset( $config['framed'] ) ? $config['framed'] : true );

		// Add `role="button"` on `<a>` elements, where it's needed
		if ( strtolower( $this->button->getTag() ) === 'a' ) {
			$this->button->setAttributes( [
				'role' => 'button',
			] );
		}

		$this->registerConfigCallback( function ( &$config ) {
			if ( $this->framed !== true ) {
				$config['framed'] = $this->framed;
			}
		} );
	}

	/**
	 * Toggle frame.
	 *
	 * @param bool $framed Make button framed, omit to toggle
	 * @return $this
	 */
	public function toggleFramed( $framed = null ) {
		$this->framed = $framed !== null ? !!$framed : !$this->framed;
		$this->toggleClasses( [ 'oo-ui-buttonElement-framed' ], $this->framed );
		$this->toggleClasses( [ 'oo-ui-buttonElement-frameless' ], !$this->framed );
		return $this;
	}

	/**
	 * Check if button has a frame.
	 *
	 * @return bool Button is framed
	 */
	public function isFramed() {
		return $this->framed;
	}
}
