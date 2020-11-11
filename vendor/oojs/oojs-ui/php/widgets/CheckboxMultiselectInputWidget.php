<?php

namespace OOUI;

/**
 * Multiple checkbox input widget. Intended to be used within a OO.ui.FormLayout.
 */
class CheckboxMultiselectInputWidget extends InputWidget {

	/* Properties */

	/**
	 * @var string|null
	 */
	protected $name = null;

	/**
	 * Input value.
	 *
	 * @var string[]
	 */
	protected $value = [];

	/**
	 * Layouts for this input, as FieldLayouts.
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * @param array $config Configuration options
	 * @param array[] $config['options'] Array of menu options in the format
	 *   `[ 'data' => …, 'label' => …, 'disabled' => … ]`
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		if ( isset( $config['name'] ) ) {
			$this->name = $config['name'];
		}

		// Initialization
		$this->setOptions( isset( $config['options'] ) ? $config['options'] : [] );
		// Have to repeat this from parent, as we need options to be set up for this to make sense
		$this->setValue( isset( $config['value'] ) ? $config['value'] : null );
		$this->addClasses( [ 'oo-ui-checkboxMultiselectInputWidget' ] );
	}

	protected function getInputElement( $config ) {
		// Actually unused
		return new Tag( 'unused' );
	}

	/**
	 * Set the value of the input.
	 *
	 * @param string[] $value New value
	 * @return $this
	 */
	public function setValue( $value ) {
		$this->value = $this->cleanUpValue( $value );
		// Deselect all options
		foreach ( $this->fields as $field ) {
			$field->getField()->setSelected( false );
		}
		// Select the requested ones
		foreach ( $this->value as $key ) {
			$this->fields[ $key ]->getField()->setSelected( true );
		}
		return $this;
	}

	/**
	 * Clean up incoming value.
	 *
	 * @param string[] $value Original value
	 * @return string[] Cleaned up value
	 */
	protected function cleanUpValue( $value ) {
		$cleanValue = [];
		if ( !is_array( $value ) ) {
			return $cleanValue;
		}
		foreach ( $value as $singleValue ) {
			$singleValue = parent::cleanUpValue( $singleValue );
			// Remove options that we don't have here
			if ( !isset( $this->fields[ $singleValue ] ) ) {
				continue;
			}
			$cleanValue[] = $singleValue;
		}
		return $cleanValue;
	}

	/**
	 * Set the options available for this input.
	 *
	 * @param array[] $options Array of menu options in the format
	 *   `[ 'data' => …, 'label' => …, 'disabled' => … ]`
	 * @return $this
	 */
	public function setOptions( $options ) {
		$this->fields = [];

		// Rebuild the checkboxes
		$this->clearContent();
		$name = $this->name;
		foreach ( $options as $opt ) {
			$optValue = parent::cleanUpValue( $opt['data'] );
			$optDisabled = isset( $opt['disabled'] ) ? $opt['disabled'] : false;
			$field = new FieldLayout(
				new CheckboxInputWidget( [
					'name' => $name,
					'value' => $optValue,
					'disabled' => $this->isDisabled() || $optDisabled,
				] ),
				[
					'label' => isset( $opt['label'] ) ? $opt['label'] : $optValue,
					'align' => 'inline',
				]
			);

			$this->fields[ $optValue ] = $field;
			$this->appendContent( $field );
		}

		// Re-set the value, checking the checkboxes as needed.
		// This will also get rid of any stale options that we just removed.
		$this->setValue( $this->getValue() );

		return $this;
	}

	public function setDisabled( $state ) {
		parent::setDisabled( $state );
		foreach ( $this->fields as $field ) {
			$field->getField()->setDisabled( $this->isDisabled() );
		}
		return $this;
	}

	public function getConfig( &$config ) {
		$o = [];
		foreach ( $this->fields as $field ) {
			$label = $field->getLabel();
			$data = $field->getField()->getValue();
			$disabled = $field->getField()->isDisabled();
			$o[] = [ 'data' => $data, 'label' => $label, 'disabled' => $disabled ];
		}
		$config['options'] = $o;
		return parent::getConfig( $config );
	}
}
