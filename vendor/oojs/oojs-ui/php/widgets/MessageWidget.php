<?php

namespace OOUI;

/**
 * Notice widget.
 */
class MessageWidget extends Widget {
	use IconElement;
	use LabelElement;
	use TitledElement;
	use FlaggedElement;

	/**
	 * Defines whether the widget is inline
	 *
	 * @var bool
	 */
	protected $inline;

	/**
	 * Defines the displayed message type
	 *
	 * @var string
	 */
	protected $type;

	/**
	 * Map legal types to their OOUI icon
	 *
	 * @var array
	 */
	protected $iconMap = [
		'notice' => 'infoFilled',
		'error' => 'error',
		'warning' => 'alert',
		'success' => 'check',
	];

	/**
	 * Default type for the widget
	 *
	 * @var string
	 */
	protected $defaultType = 'notice';

	/**
	 * @param array $config Configuration options
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeLabelElement( $config );
		$this->initializeIconElement( $config );
		$this->initializeTitledElement( $config );
		$this->initializeFlaggedElement( $config );

		$this->setType( $config['type'] ?? $this->defaultType );
		$this->setInline( isset( $config['inline'] ) && (bool)$config['inline'] );

		$this->addClasses( [ 'oo-ui-messageWidget' ] );
		$this->appendContent( [ $this->icon, $this->label ] );
	}

	public function getConfig( &$config ) {
		$config['type'] = $this->type;
		$config['inline'] = $this->inline;

		return parent::getConfig( $config );
	}

	/**
	 * Set the inline state of the widget
	 *
	 * @param bool $inline Widget is inline
	 */
	public function setInline( $inline ) {
		$this->inline = (bool)$inline;
		$this->toggleClasses( [ 'oo-ui-messageWidget-block' ], !$this->inline );
	}

	/**
	 * Set the widget type. The given type must belong to the list of
	 * legal types set by $this->iconMap
	 *
	 * @param string $type Given type
	 */
	public function setType( $type ) {
		if ( !array_key_exists( $type, $this->iconMap ) ) {
			$type = $this->defaultType;
		}

		// Set flag
		$this->clearFlags();
		$this->setFlags( [ $type ] );

		// Set icon
		$this->setIcon( $this->iconMap[ $type ] );
		$this->icon->removeClasses( [ 'oo-ui-image-' . $this->type ] );
		$this->icon->addClasses( [ 'oo-ui-image-' . $type ] );

		// Initialization
		if ( $type === 'error' ) {
			$this->setAttributes( [ 'role' => 'alert' ] );
			$this->removeAttributes( [ 'aria-live' ] );
		} else {
			$this->setAttributes( [ 'aria-live' => 'polite' ] );
			$this->removeAttributes( [ 'role' ] );
		}

		$this->type = $type;
	}
}
