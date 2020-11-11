<?php

namespace OOUI;

/**
 * Group widget for multiple related buttons.
 *
 * Use together with ButtonWidget.
 */
class ButtonGroupWidget extends Widget {
	use GroupElement;

	/* Static Properties */

	public static $tagName = 'span';

	/**
	 * @param array $config Configuration options
	 * @param ButtonWidget[] $config['items'] Buttons to add
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeGroupElement( array_merge( $config, [ 'group' => $this ] ) );

		// Initialization
		$this->addClasses( [ 'oo-ui-buttonGroupWidget' ] );
		if ( isset( $config['items'] ) ) {
			$this->addItems( $config['items'] );
		}
	}
}
