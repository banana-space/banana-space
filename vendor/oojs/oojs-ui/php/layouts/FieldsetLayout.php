<?php

namespace OOUI;

/**
 * Layout made of a fieldset and optional legend.
 *
 * Just add FieldLayout items.
 */
class FieldsetLayout extends Layout {
	use IconElement;
	use LabelElement;
	use GroupElement;

	/* Static Properties */

	public static $tagName = 'fieldset';

	protected $header;

	/**
	 * @param array $config Configuration options
	 * @param FieldLayout[] $config['items'] Items to add
	 */
	public function __construct( array $config = [] ) {
		// Parent constructor
		parent::__construct( $config );

		// Traits
		$this->initializeIconElement( $config );
		$this->initializeLabelElement( $config );
		$this->initializeGroupElement( $config );

		// Properties
		$this->header = new Tag( 'legend' );

		// Initialization
		$this->header
			->addClasses( [ 'oo-ui-fieldsetLayout-header' ] )
			->appendContent( $this->icon, $this->label );
		$this->group->addClasses( [ 'oo-ui-fieldsetLayout-group' ] );
		$this
			->addClasses( [ 'oo-ui-fieldsetLayout' ] )
			->prependContent( $this->header, $this->group );
		if ( isset( $config['items'] ) ) {
			$this->addItems( $config['items'] );
		}
	}

	public function getConfig( &$config ) {
		$config['$overlay'] = true;
		return parent::getConfig( $config );
	}
}
