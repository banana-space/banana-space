<?php

namespace OOUI;

/**
 * Element containing a label.
 *
 * @abstract
 */
trait LabelElement {
	/**
	 * Label value.
	 *
	 * @var string|HtmlSnippet|null
	 */
	protected $labelValue = null;

	/**
	 * @var Tag
	 */
	protected $label;

	/**
	 * @param array $config Configuration options
	 * @param string|HtmlSnippet $config['label'] Label text
	 */
	public function initializeLabelElement( array $config = [] ) {
		// Properties
		// FIXME 'labelElement' is a very stupid way to call '$label'
		$this->label = isset( $config['labelElement'] ) ? $config['labelElement'] : new Tag( 'span' );

		// Initialization
		$this->label->addClasses( [ 'oo-ui-labelElement-label' ] );
		$this->setLabel( isset( $config['label'] ) ? $config['label'] : null );

		$this->registerConfigCallback( function ( &$config ) {
			if ( $this->labelValue !== null ) {
				$config['label'] = $this->labelValue;
			}
		} );
	}

	/**
	 * Set the label.
	 *
	 * An empty string will result in the label being hidden. A string containing only whitespace will
	 * be converted to a single `&nbsp;`.
	 *
	 * @param string|HtmlSnippet|null $label Label text
	 * @return $this
	 */
	public function setLabel( $label ) {
		$this->labelValue = (string)$label ? $label : null;

		$this->label->clearContent();
		if ( $this->labelValue !== null ) {
			if ( is_string( $this->labelValue ) && $this->labelValue !== ''
				&& trim( $this->labelValue ) === ''
			) {
				$this->label->appendContent( new HtmlSnippet( '&nbsp;' ) );
			} else {
				$this->label->appendContent( $label );
			}
		}

		$this->toggleClasses( [ 'oo-ui-labelElement' ], !!$this->labelValue );

		return $this;
	}

	/**
	 * Get the label.
	 *
	 * @return string|HtmlSnippet|null Label text
	 */
	public function getLabel() {
		return $this->labelValue;
	}
}
