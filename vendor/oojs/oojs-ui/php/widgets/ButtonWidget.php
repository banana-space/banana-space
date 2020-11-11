<?php

namespace OOUI;

/**
 * Generic widget for buttons.
 */
class ButtonWidget extends Widget {
	use ButtonElement;
	use IconElement;
	use IndicatorElement;
	use LabelElement;
	use TitledElement;
	use FlaggedElement;
	use TabIndexedElement;
	use AccessKeyedElement;

	/* Static Properties */

	public static $tagName = 'span';

	/* Properties */

	/**
	 * Whether button is active.
	 *
	 * @var boolean
	 */
	protected $active = false;

	/**
	 * Hyperlink to visit when clicked.
	 *
	 * @var string
	 */
	protected $href = null;

	/**
	 * Target to open hyperlink in.
	 *
	 * @var string
	 */
	protected $target = null;

	/**
	 * Search engine traversal hint.
	 *
	 * True if search engines should avoid following this hyperlink.
	 *
	 * @var boolean
	 */
	protected $noFollow = true;

	/**
	 * @param array $config Configuration options
	 * @param bool $config['active'] Whether button should be shown as active (default: false)
	 * @param string $config['href'] Hyperlink to visit when clicked
	 * @param string $config['target'] Target to open hyperlink in
	 * @param bool $config['noFollow'] Search engine traversal hint (default: true)
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeButtonElement( $config );
		$this->initializeIconElement( $config );
		$this->initializeIndicatorElement( $config );
		$this->initializeLabelElement( $config );
		$this->initializeTitledElement(
			array_merge( $config, [ 'titled' => $this->button ] ) );
		$this->initializeFlaggedElement( $config );
		$this->initializeTabIndexedElement(
			array_merge( $config, [ 'tabIndexed' => $this->button ] ) );
		$this->initializeAccessKeyedElement(
			array_merge( $config, [ 'accessKeyed' => $this->button ] ) );

		// Initialization
		$this->button->appendContent( $this->icon, $this->label, $this->indicator );
		$this
			->addClasses( [ 'oo-ui-buttonWidget' ] )
			->appendContent( $this->button );

		$this->setActive( isset( $config['active'] ) ? $config['active'] : false );
		$this->setHref( isset( $config['href'] ) ? $config['href'] : null );
		$this->setTarget( isset( $config['target'] ) ? $config['target'] : null );
		$this->setNoFollow( isset( $config['noFollow'] ) ? $config['noFollow'] : true );
	}

	/**
	 * Get hyperlink location.
	 *
	 * @return string Hyperlink location
	 */
	public function getHref() {
		return $this->href;
	}

	/**
	 * Get hyperlink target.
	 *
	 * @return string Hyperlink target
	 */
	public function getTarget() {
		return $this->target;
	}

	/**
	 * Get search engine traversal hint.
	 *
	 * @return bool Whether search engines should avoid traversing this hyperlink
	 */
	public function getNoFollow() {
		return $this->noFollow;
	}

	/**
	 * Set hyperlink location.
	 *
	 * @param string|null $href Hyperlink location, null to remove
	 * @return $this
	 */
	public function setHref( $href ) {
		$this->href = is_string( $href ) ? $href : null;

		$this->updateHref();

		return $this;
	}

	/**
	 * Update the href attribute, in case of changes to href or disabled
	 * state.
	 *
	 * @return $this
	 */
	public function updateHref() {
		if ( $this->href !== null && !$this->isDisabled() ) {
			$this->button->setAttributes( [ 'href' => $this->href ] );
		} else {
			$this->button->removeAttributes( [ 'href' ] );
		}
		return $this;
	}

	/**
	 * Set hyperlink target.
	 *
	 * @param string|null $target Hyperlink target, null to remove
	 * @return $this
	 */
	public function setTarget( $target ) {
		$this->target = is_string( $target ) ? $target : null;

		if ( $this->target !== null ) {
			$this->button->setAttributes( [ 'target' => $target ] );
		} else {
			$this->button->removeAttributes( [ 'target' ] );
		}

		return $this;
	}

	/**
	 * Set search engine traversal hint.
	 *
	 * @param bool $noFollow True if search engines should avoid traversing this hyperlink
	 * @return $this
	 */
	public function setNoFollow( $noFollow ) {
		$this->noFollow = is_bool( $noFollow ) ? $noFollow : true;

		if ( $this->noFollow ) {
			$this->button->setAttributes( [ 'rel' => 'nofollow' ] );
		} else {
			$this->button->removeAttributes( [ 'rel' ] );
		}

		return $this;
	}

	/**
	 * Toggle active state.
	 *
	 * A button should be marked as active when clicking it would only refresh the page.
	 *
	 * @param bool $active Make button active
	 * @return $this
	 */
	public function setActive( $active = null ) {
		$this->active = !!$active;
		$this->toggleClasses( [ 'oo-ui-buttonElement-active' ], $this->active );
		return $this;
	}

	/**
	 * Check if button is active.
	 *
	 * @return bool Button is active
	 */
	public function isActive() {
		return $this->active;
	}

	public function getConfig( &$config ) {
		if ( $this->active !== false ) {
			$config['active'] = $this->active;
		}
		if ( $this->href !== null ) {
			$config['href'] = $this->href;
		}
		if ( $this->target !== null ) {
			$config['target'] = $this->target;
		}
		if ( $this->noFollow !== true ) {
			$config['noFollow'] = $this->noFollow;
		}
		return parent::getConfig( $config );
	}
}
