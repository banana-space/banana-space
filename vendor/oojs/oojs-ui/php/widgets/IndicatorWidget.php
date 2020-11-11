<?php

namespace OOUI;

/**
 * Indicator widget.
 *
 * See IndicatorElement for more information.
 */
class IndicatorWidget extends Widget {
	use IndicatorElement;
	use TitledElement;

	/* Static Properties */

	public static $tagName = 'span';

	/**
	 * @param array $config Configuration options
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeIndicatorElement(
			array_merge( $config, [ 'indicatorElement' => $this ] ) );
		$this->initializeTitledElement(
			array_merge( $config, [ 'titled' => $this ] ) );

		// Initialization
		$this->addClasses( [ 'oo-ui-indicatorWidget' ] );
	}
}
