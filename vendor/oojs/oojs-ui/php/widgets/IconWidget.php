<?php

namespace OOUI;

/**
 * Icon widget.
 *
 * See IconElement for more information.
 */
class IconWidget extends Widget {
	use IconElement;
	use TitledElement;
	use FlaggedElement;

	/* Static Properties */

	public static $tagName = 'span';

	/**
	 * @param array $config Configuration options
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeIconElement(
			array_merge( $config, [ 'iconElement' => $this ] ) );
		$this->initializeTitledElement(
			array_merge( $config, [ 'titled' => $this ] ) );
		$this->initializeFlaggedElement( array_merge( $config, [ 'flagged' => $this ] ) );

		// Initialization
		$this->addClasses( [ 'oo-ui-iconWidget' ] );
	}
}
