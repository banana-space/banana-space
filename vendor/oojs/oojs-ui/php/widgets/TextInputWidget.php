<?php

namespace OOUI;

/**
 * Input widget with a text field.
 */
class TextInputWidget extends InputWidget {
	use IconElement;
	use IndicatorElement;

	/* Properties */

	/**
	 * Input field type.
	 *
	 * @var string
	 */
	protected $type = null;

	/**
	 * Prevent changes.
	 *
	 * @var boolean
	 */
	protected $readOnly = false;

	/**
	 * Mark as required.
	 *
	 * @var boolean
	 */
	protected $required = false;

	/**
	 * Allow multiple lines of text.
	 *
	 * @var boolean
	 */
	protected $multiline = false;

	/**
	 * @param array $config Configuration options
	 * @param string $config['type'] HTML tag `type` attribute: 'text', 'password', 'email',
	 *   'url' or 'number'. Ignored if `multiline` is true. (default: 'text')
	 * @param string $config['placeholder'] Placeholder text
	 * @param bool $config['autofocus'] Ask the browser to focus this widget, using the 'autofocus'
	 *   HTML attribute (default: false)
	 * @param bool $config['readOnly'] Prevent changes (default: false)
	 * @param number $config['maxLength'] Maximum allowed number of characters to input
	 *
	 *   For unfortunate historical reasons, this counts the number of UTF-16 code units rather than
	 *   Unicode codepoints, which means that codepoints outside the Basic Multilingual Plane (e.g.
	 *   many emojis) count as 2 characters each.
	 * @param bool $config['multiline'] Allow multiple lines of text (default: false)
	 * @param bool $config['required'] Mark the field as required.
	 *   Implies `indicator: 'required'`. Note that `false` & setting `indicator: 'required'
	 *   will result in no indicator shown. (default: false)
	 * @param bool $config['autocomplete'] If the field should support autocomplete
	 *   or not (default: true)
	 * @param bool $config['spellcheck'] If the field should support spellcheck
	 *   or not (default: browser-dependent)
	 */
	public function __construct( array $config = [] ) {
		// Config initialization
		$config = array_merge( [
			'type' => 'text',
			'readOnly' => false,
			'autofocus' => false,
			'required' => false,
			'autocomplete' => true,
		], $config );

		// Parent constructor
		parent::__construct( $config );

		// Properties
		$this->type = $this->getSaneType( $config );
		$this->multiline = isset( $config['multiline'] ) ? (bool)$config['multiline'] : false;

		if ( $this->multiline && !( $this instanceof MultilineTextInputWidget ) ) {
			Element::warnDeprecation(
				'The TextInputWidget "multiline" option is deprecated as of OOUI v0.22.2. ' .
				'Use MultilineTextInputWidget instead.'
			);
		}

		// Traits
		$this->initializeIconElement( $config );
		$this->initializeIndicatorElement( $config );

		// Initialization
		$this
			->addClasses( [
				'oo-ui-textInputWidget',
				'oo-ui-textInputWidget-type-' . $this->type,
				'oo-ui-textInputWidget-php',
			] )
			->appendContent( $this->icon, $this->indicator );
		$this->setReadOnly( $config['readOnly'] );
		$this->setRequired( $config['required'] );
		if ( isset( $config['placeholder'] ) ) {
			$this->input->setAttributes( [ 'placeholder' => $config['placeholder'] ] );
		}
		if ( isset( $config['maxLength'] ) ) {
			$this->input->setAttributes( [ 'maxlength' => $config['maxLength'] ] );
		}
		if ( $config['autofocus'] ) {
			$this->input->setAttributes( [ 'autofocus' => 'autofocus' ] );
		}
		if ( !$config['autocomplete'] ) {
			$this->input->setAttributes( [ 'autocomplete' => 'off' ] );
		}
		if ( isset( $config['spellcheck'] ) ) {
			$this->input->setAttributes( [ 'spellcheck' => $config['spellcheck'] ? 'true' : 'false' ] );
		}
		if ( $this->multiline && isset( $config['rows'] ) && $config['rows'] ) {
			$this->input->setAttributes( [ 'rows' => $config['rows'] ] );
		}
	}

	/**
	 * Check if the widget is read-only.
	 *
	 * @return bool
	 */
	public function isReadOnly() {
		return $this->readOnly;
	}

	/**
	 * Set the read-only state of the widget. This should probably change the widget's appearance and
	 * prevent it from being used.
	 *
	 * @param bool $state Make input read-only
	 * @return $this
	 */
	public function setReadOnly( $state ) {
		$this->readOnly = (bool)$state;
		if ( $this->readOnly ) {
			$this->input->setAttributes( [ 'readonly' => 'readonly' ] );
		} else {
			$this->input->removeAttributes( [ 'readonly' ] );
		}
		return $this;
	}

	/**
	 * Check if the widget is required.
	 *
	 * @return bool
	 */
	public function isRequired() {
		return $this->required;
	}

	/**
	 * Set the required state of the widget.
	 *
	 * @param bool $state Make input required
	 * @return $this
	 */
	public function setRequired( $state ) {
		$this->required = (bool)$state;
		if ( $this->required ) {
			$this->input->setAttributes( [ 'required' => 'required', 'aria-required' => 'true' ] );
			if ( $this->getIndicator() === null ) {
				$this->setIndicator( 'required' );
			}
		} else {
			$this->input->removeAttributes( [ 'required', 'aria-required' ] );
			if ( $this->getIndicator() === 'required' ) {
				$this->setIndicator( null );
			}
		}
		return $this;
	}

	protected function getInputElement( $config ) {
		if ( isset( $config['multiline'] ) && $config['multiline'] ) {
			return new Tag( 'textarea' );
		} elseif ( $this->getSaneType( $config ) === 'number' ) {
			return ( new Tag( 'input' ) )->setAttributes( [
				'step' => 'any',
				'type' => 'number',
			] );
		} else {
			return ( new Tag( 'input' ) )->setAttributes( [ 'type' => $this->getSaneType( $config ) ] );
		}
	}

	protected function getSaneType( $config ) {
		$allowedTypes = [
			'text',
			'password',
			'email',
			'url',
			'number'
		];
		return in_array( $config['type'], $allowedTypes ) ? $config['type'] : 'text';
	}

	/**
	 * Check if input supports multiple lines.
	 *
	 * @return bool
	 */
	public function isMultiline() {
		return (bool)$this->multiline;
	}

	public function getConfig( &$config ) {
		if ( $this->isMultiline() ) {
			$config['multiline'] = true;
			$rows = $this->input->getAttribute( 'rows' );
			if ( $rows !== null ) {
				$config['rows'] = $rows;
			}
		}
		if ( $this->type !== 'text' ) {
			$config['type'] = $this->type;
		}
		if ( $this->isReadOnly() ) {
			$config['readOnly'] = true;
		}
		$placeholder = $this->input->getAttribute( 'placeholder' );
		if ( $placeholder !== null ) {
			$config['placeholder'] = $placeholder;
		}
		$maxlength = $this->input->getAttribute( 'maxlength' );
		if ( $maxlength !== null ) {
			$config['maxLength'] = $maxlength;
		}
		$autofocus = $this->input->getAttribute( 'autofocus' );
		if ( $autofocus !== null ) {
			$config['autofocus'] = true;
		}
		$required = $this->input->getAttribute( 'required' );
		$ariarequired = $this->input->getAttribute( 'aria-required' );
		if ( ( $required !== null ) || ( $ariarequired !== null ) ) {
			$config['required'] = true;
		}
		$autocomplete = $this->input->getAttribute( 'autocomplete' );
		if ( $autocomplete !== null ) {
			$config['autocomplete'] = false;
		}
		return parent::getConfig( $config );
	}
}
