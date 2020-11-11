<?php

namespace OOUI;

/**
 * Layout made of a field and optional label.
 *
 * Available label alignment modes include:
 *  - left: Label is before the field and aligned away from it, best for when the user will be
 *    scanning for a specific label in a form with many fields
 *  - right: Label is before the field and aligned toward it, best for forms the user is very
 *    familiar with and will tab through field checking quickly to verify which field they are in
 *  - top: Label is before the field and above it, best for when the user will need to fill out all
 *    fields from top to bottom in a form with few fields
 *  - inline: Label is after the field and aligned toward it, best for small boolean fields like
 *    checkboxes or radio buttons
 */
class FieldLayout extends Layout {
	use LabelElement;
	use TitledElement;

	/**
	 * Alignment.
	 *
	 * @var string
	 */
	protected $align;

	/**
	 * Field widget to be laid out.
	 *
	 * @var Widget
	 */
	protected $fieldWidget;

	/**
	 * Error messages.
	 *
	 * @var array
	 */
	protected $errors;

	/**
	 * Notice messages.
	 *
	 * @var array
	 */
	protected $notices;

	/**
	 * @var ButtonWidget|string
	 */
	protected $help;

	protected $field, $header, $body, $messages;

	/**
	 * @param Widget $fieldWidget Field widget
	 * @param array $config Configuration options
	 * @param string $config['align'] Alignment mode, either 'left', 'right', 'top' or 'inline'
	 *   (default: 'left')
	 * @param array $config['errors'] Error messages about the widget, as strings or HtmlSnippet
	 *   instances.
	 * @param array $config['notices'] Notices about the widget, as strings or HtmlSnippet instances.
	 * @param string|HtmlSnippet $config['help'] Explanatory text shown as a '?' icon.
	 * @throws Exception An exception is thrown if no widget is specified
	 */
	public function __construct( $fieldWidget, array $config = [] ) {
		// Allow passing positional parameters inside the config array
		if ( is_array( $fieldWidget ) && isset( $fieldWidget['fieldWidget'] ) ) {
			$config = $fieldWidget;
			$fieldWidget = $config['fieldWidget'];
		}

		// Make sure we have required constructor arguments
		if ( $fieldWidget === null ) {
			throw new Exception( 'Widget not found' );
		}

		// Config initialization
		$config = array_merge( [ 'align' => 'left' ], $config );

		// Parent constructor
		parent::__construct( $config );

		// Properties
		$this->fieldWidget = $fieldWidget;
		$this->errors = isset( $config['errors'] ) ? $config['errors'] : [];
		$this->notices = isset( $config['notices'] ) ? $config['notices'] : [];
		$this->field = $this->isFieldInline() ? new Tag( 'span' ) : new Tag( 'div' );
		$this->messages = new Tag( 'ul' );
		$this->header = new Tag( 'span' );
		$this->body = new Tag( 'div' );
		if ( isset( $config['help'] ) ) {
			$this->help = new ButtonWidget( [
				'classes' => [ 'oo-ui-fieldLayout-help' ],
				'framed' => false,
				'icon' => 'info',
				'title' => $config['help'],
			] );
		} else {
			$this->help = '';
		}

		// Traits
		$this->initializeLabelElement( array_merge( $config, [
			'labelElement' => new Tag( 'label' )
		] ) );
		$this->initializeTitledElement(
			array_merge( $config, [ 'titled' => $this->label ] ) );

		// Initialization
		if ( $this->fieldWidget->getInputId() ) {
			$this->label->setAttributes( [ 'for' => $this->fieldWidget->getInputId() ] );
		}
		$this
			->addClasses( [ 'oo-ui-fieldLayout' ] )
			->toggleClasses( [ 'oo-ui-fieldLayout-disabled' ], $this->fieldWidget->isDisabled() )
			->appendContent( $this->body );
		if ( count( $this->errors ) || count( $this->notices ) ) {
			$this->appendContent( $this->messages );
		}
		$this->body->addClasses( [ 'oo-ui-fieldLayout-body' ] );
		$this->header->addClasses( [ 'oo-ui-fieldLayout-header' ] );
		$this->messages->addClasses( [ 'oo-ui-fieldLayout-messages' ] );
		$this->field
			->addClasses( [ 'oo-ui-fieldLayout-field' ] )
			->appendContent( $this->fieldWidget );

		foreach ( $this->notices as $text ) {
			$this->messages->appendContent( $this->makeMessage( 'notice', $text ) );
		}
		foreach ( $this->errors as $text ) {
			$this->messages->appendContent( $this->makeMessage( 'error', $text ) );
		}

		$this->setAlignment( $config['align'] );
		// Call this again to take into account the widget's accessKey
		$this->updateTitle();
	}

	/**
	 * @param string $kind 'error' or 'notice'
	 * @param string|HtmlSnippet $text
	 * @return Tag
	 */
	private function makeMessage( $kind, $text ) {
		$listItem = new Tag( 'li' );
		if ( $kind === 'error' ) {
			$icon = new IconWidget( [ 'icon' => 'alert', 'flags' => [ 'warning' ] ] );
			$listItem->setAttributes( [ 'role' => 'alert' ] );
		} elseif ( $kind === 'notice' ) {
			$icon = new IconWidget( [ 'icon' => 'notice' ] );
		} else {
			$icon = null;
		}
		$message = new LabelWidget( [ 'label' => $text ] );
		$listItem
			->appendContent( $icon, $message )
			->addClasses( [ "oo-ui-fieldLayout-messages-$kind" ] );
		return $listItem;
	}

	/**
	 * Get the field.
	 *
	 * @return Widget Field widget
	 */
	public function getField() {
		return $this->fieldWidget;
	}

	/**
	 * Return `true` if the given field widget can be used with `'inline'` alignment (see
	 * setAlignment()). Return `false` if it can't or if this can't be determined.
	 *
	 * @return bool
	 */
	public function isFieldInline() {
		// This is very simplistic, but should be good enough. It's important to avoid false positives,
		// as that will cause the generated HTML to be invalid and go all out of whack when parsed.
		return strtolower( $this->getField()->getTag() ) === 'span';
	}

	/**
	 * Set the field alignment mode.
	 *
	 * @param string $value Alignment mode, either 'left', 'right', 'top' or 'inline'
	 * @return $this
	 */
	protected function setAlignment( $value ) {
		if ( $value !== $this->align ) {
			// Default to 'left'
			if ( !in_array( $value, [ 'left', 'right', 'top', 'inline' ] ) ) {
				$value = 'left';
			}
			// Validate
			if ( $value === 'inline' && !$this->isFieldInline() ) {
				$value = 'top';
			}
			// Reorder elements
			$this->body->clearContent();
			if ( $value === 'top' ) {
				$this->header->appendContent( $this->help, $this->label );
				$this->body->appendContent( $this->header, $this->field );
			} elseif ( $value === 'inline' ) {
				$this->header->appendContent( $this->help, $this->label );
				$this->body->appendContent( $this->field, $this->header );
			} else {
				$this->header->appendContent( $this->label );
				$this->body->appendContent( $this->header, $this->help, $this->field );
			}
			// Set classes. The following classes can be used here:
			// * oo-ui-fieldLayout-align-left
			// * oo-ui-fieldLayout-align-right
			// * oo-ui-fieldLayout-align-top
			// * oo-ui-fieldLayout-align-inline
			if ( $this->align ) {
				$this->removeClasses( [ 'oo-ui-fieldLayout-align-' . $this->align ] );
			}
			$this->addClasses( [ 'oo-ui-fieldLayout-align-' . $value ] );
			$this->align = $value;
		}

		return $this;
	}

	/**
	 * Include information about the widget's accessKey in our title. TitledElement calls this method.
	 * (This is a bit of a hack.)
	 *
	 * @param string $title Tooltip label for 'title' attribute
	 * @return string
	 */
	protected function formatTitleWithAccessKey( $title ) {
		if ( $this->fieldWidget && method_exists( $this->fieldWidget, 'formatTitleWithAccessKey' ) ) {
			return $this->fieldWidget->formatTitleWithAccessKey( $title );
		}
		return $title;
	}

	public function getConfig( &$config ) {
		$config['fieldWidget'] = $this->fieldWidget;
		$config['align'] = $this->align;
		$config['errors'] = $this->errors;
		$config['notices'] = $this->notices;
		if ( $this->help !== '' ) {
			$config['help'] = $this->help->getTitle();
		}
		$config['$overlay'] = true;
		return parent::getConfig( $config );
	}
}
