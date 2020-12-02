<?php

namespace OOUI;

/**
 * OptionWidgets are special elements that can be selected and configured with data. The
 * data is often unique for each option, but it does not have to be.
 *
 * OptionWidgets are used SelectWidget to create a selection of mutually exclusive options.
 */
class OptionWidget extends Widget {

	use LabelElement;
	use FlaggedElement;
	use AccessKeyedElement;
	use TitledElement;

	/**
	 * @var bool
	 */
	protected $selected;

	/**
	 * @param array $config Configuration options
	 *      - bool $config['selected'] Whether to mark the option as selected
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		$this->initializeFlaggedElement( $config );
		$this->initializeTitledElement( array_merge( [ 'titled' => $this ], $config ) );
		$this->initializeAccessKeyedElement( array_merge( [ 'accessKeyed' => $this ], $config ) );
		$this->initializeLabelElement( $config );

		$this->appendContent( $this->label );

		$this->setSelected( $config['selected'] ?? false );

		$this->addClasses( [ 'oo-ui-optionWidget' ] );
		$this->setAttributes( [
			'role' => 'option'
		] );
	}

	/**
	 * Set the selected state of the option
	 *
	 * @param bool $selected The options is selected
	 * @return $this
	 */
	public function setSelected( bool $selected ) {
		$this->selected = $selected;
		$this->toggleClasses( [ 'oo-ui-optionWidget-selected' ], $selected );
		$this->setAttributes( [
			// 'selected' is not a config option, so set aria-selected false by default (same as js)
			'aria-selected' => $selected ? 'true' : 'false',
		] );
		return $this;
	}

	public function isSelected() {
		return $this->selected;
	}

	public function getConfig( &$config ) {
		$selected = $this->hasClass( 'oo-ui-optionWidget-selected' );
		if ( $this->selected ) {
			$config['selected'] = $this->selected;
		}
		return parent::getConfig( $config );
	}
}
