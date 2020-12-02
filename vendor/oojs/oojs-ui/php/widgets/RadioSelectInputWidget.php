<?php

namespace OOUI;

/**
 * Multiple radio buttons input widget. Intended to be used within a OO.ui.FormLayout.
 */
class RadioSelectInputWidget extends InputWidget {

	/* Properties */

	/**
	 * @var string|null
	 */
	protected $name = null;

	/**
	 * Layouts for this input, as FieldLayouts.
	 *
	 * @var array
	 */
	protected $fields = [];

	/**
	 * @param array $config Configuration options
	 *      - array[] $config['options'] Array of menu options in the format
	 *          `[ 'data' => …, 'label' => … ]`
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		if ( isset( $config['name'] ) ) {
			$this->name = $config['name'];
		}

		// Initialization
		$this->setOptions( $config['options'] ?? [] );
		$this->addClasses( [ 'oo-ui-radioSelectInputWidget' ] );
	}

	protected function getInputElement( $config ) {
		// Actually unused
		return new Tag( 'unused' );
	}

	public function setValue( $value ) {
		$this->value = $this->cleanUpValue( $value );
		foreach ( $this->fields as &$field ) {
			$field->getField()->setSelected( $field->getField()->getValue() === $this->value );
		}
		return $this;
	}

	/**
	 * Set the options available for this input.
	 *
	 * @param array[] $options Array of menu options in the format
	 *   `[ 'data' => …, 'label' => … ]`
	 * @return $this
	 */
	public function setOptions( $options ) {
		$value = $this->getValue();
		$isValueAvailable = false;
		$this->fields = [];

		// Rebuild the radio buttons
		$this->clearContent();
		// Need a unique name, otherwise more than one radio will be selectable
		// Note: This is not going in the ID attribute, not that it matters
		$name = $this->name ?: Tag::generateElementId();
		foreach ( $options as $opt ) {
			$optValue = $this->cleanUpValue( $opt['data'] );
			$field = new FieldLayout(
				new RadioInputWidget( [
					'name' => $name,
					'value' => $optValue,
					'disabled' => $this->isDisabled(),
				] ),
				[
					'label' => $opt['label'] ?? $optValue,
					'align' => 'inline',
				]
			);

			if ( $value === $optValue ) {
				$isValueAvailable = true;
			}

			$this->fields[] = $field;
			$this->appendContent( $field );
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
			$o[] = [ 'data' => $data, 'label' => $label ];
		}
		$config['options'] = $o;
		return parent::getConfig( $config );
	}
}
