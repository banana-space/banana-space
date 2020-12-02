<?php

namespace OOUI;

/**
 * Dropdown input widget, wrapping a `<select>` element. Intended to be used within a
 * OO.ui.FormLayout.
 */
class DropdownInputWidget extends InputWidget {
	/**
	 * HTML `<option>` tags for this widget.
	 * @var Tag[]
	 */
	protected $options = [];

	/**
	 * @param array $config Configuration options
	 *      - array[] $config['options'] Array of menu options in the format
	 * described in DropdownInputWidget::setOptions().
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->setOptions( $config['options'] ?? [] );
		$this->addClasses( [ 'oo-ui-dropdownInputWidget', 'oo-ui-dropdownInputWidget-php' ] );
		$this->input->addClasses( [ 'oo-ui-indicator-down' ] );
	}

	protected function getInputElement( $config ) {
		return new Tag( 'select' );
	}

	public function setValue( $value ) {
		$this->value = $this->cleanUpValue( $value );
		foreach ( $this->options as &$opt ) {
			if ( $opt->getAttribute( 'value' ) === $this->value ) {
				$opt->setAttributes( [ 'selected' => 'selected' ] );
			} else {
				$opt->removeAttributes( [ 'selected' ] );
			}
		}
		return $this;
	}

	/**
	 * Set the options available for this input.
	 *
	 * Each element of the `$options` array should be an array with one of the
	 * following structures:
	 *
	 *   -# For normal menu items (`label` and `disabled` are optional; if no
	 *      label is provided, the 'data' value will be also used as the label):
	 * ~~~~~
	 * [ 'data' => 'optionvalue', 'label' => 'Option Label', 'disabled' => true ]
	 * ~~~~~
	 *   -# For option groups ('disabled' is optional):
	 * ~~~~~
	 * [ 'optgroup' => 'Group Label', 'disabled' => true ]
	 * ~~~~~
	 *
	 * An `optgroup` will contain all subsequent options up until the next
	 * `optgroup` or the end of the array.
	 *
	 * @param array[] $options Array of options in format described above.
	 * @return $this
	 */
	public function setOptions( $options ) {
		$value = $this->getValue();
		$isValueAvailable = false;
		$this->options = [];
		$container = $this->input;

		// Rebuild the dropdown menu
		$this->input->clearContent();
		foreach ( $options as $opt ) {
			if ( empty( $opt['optgroup'] ) ) {
				$optValue = $this->cleanUpValue( $opt['data'] );
				$option = ( new Tag( 'option' ) )
					->setAttributes( [ 'value' => $optValue ] )
					->appendContent( $opt['label'] ?? $optValue );

				if ( $value === $optValue ) {
					$isValueAvailable = true;
				}
				$container->appendContent( $option );
			} else {
				$option = ( new Tag( 'optgroup' ) )
					->setAttributes( [ 'label' => $opt['optgroup'] ] );
				$this->input->appendContent( $option );
				$container = $option;
			}
			// Add disabled attribute if required (both the <option> and
			// <optgroup> elements can be disabled).
			if ( isset( $opt[ 'disabled' ] ) && $opt[ 'disabled' ] ) {
				$option->setAttributes( [ 'disabled' => 'disabled' ] );
			}

			$this->options[] = $option;
		}

		// Restore the previous value, or reset to something sensible
		if ( $isValueAvailable ) {
			// Previous value is still available
			$this->setValue( $value );
		} else {
			// No longer valid, reset
			if ( count( $options ) ) {
				$this->setValue( $options[0]['data'] );
			}
		}

		return $this;
	}

	public function getConfig( &$config ) {
		$optionsConfig = [];
		foreach ( $this->options as $option ) {
			if ( $option->getTag() !== 'optgroup' ) {
				$label = $option->content[0];
				$data = $option->getAttribute( 'value' );
				$optionConfig = [ 'data' => $data, 'label' => $label ];
			} else {
				$optgroup = $option->getAttribute( 'label' );
				$optionConfig = [ 'optgroup' => $optgroup ];
			}
			if ( $option->getAttribute( 'disabled' ) ) {
				$optionConfig[ 'disabled' ] = true;
			}
			$optionsConfig[] = $optionConfig;
		}
		$config['options'] = $optionsConfig;
		$config['$overlay'] = true;
		return parent::getConfig( $config );
	}
}
