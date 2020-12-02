<?php

namespace OOUI;

/**
 * Layout that expands to cover the entire area of its parent, with optional scrolling and padding.
 */
class PanelLayout extends Layout {
	/**
	 * @param array $config Configuration options
	 *      - bool $config['scrollable'] Allow vertical scrolling (default: false)
	 *      - bool $config['padded'] Pad the content from the edges (default: false)
	 *      - bool $config['expanded'] Expand size to fill the entire parent element
	 *          (default: true)
	 *      - bool $config['framed'] Wrap in a frame to visually separate from outside content
	 *          (default: false)
	 *      - bool $config['preserveContent'] Preserve DOM content when infusing (default: true)
	 */
	public function __construct( array $config = [] ) {
		// Config initialization
		$config = array_merge( [
			'scrollable' => false,
			'padded' => false,
			'expanded' => true,
			'framed' => false,
		], $config );

		$this->preserveContent = $config['preserveContent'] ?? true;

		// Parent constructor
		parent::__construct( $config );

		// Initialization
		$this->addClasses( [ 'oo-ui-panelLayout' ] );
		if ( $config['scrollable'] ) {
			$this->addClasses( [ 'oo-ui-panelLayout-scrollable' ] );
		}
		if ( $config['padded'] ) {
			$this->addClasses( [ 'oo-ui-panelLayout-padded' ] );
		}
		if ( $config['expanded'] ) {
			$this->addClasses( [ 'oo-ui-panelLayout-expanded' ] );
		}
		if ( $config['framed'] ) {
			$this->addClasses( [ 'oo-ui-panelLayout-framed' ] );
		}
	}

	public function getConfig( &$config ) {
		if ( !$this->preserveContent ) {
			$config['preserveContent'] = false;
		}
		if ( $this->hasClass( 'oo-ui-panelLayout-scrollable' ) ) {
			$config['scrollable'] = true;
		}
		if ( $this->hasClass( 'oo-ui-panelLayout-padded' ) ) {
			$config['padded'] = true;
		}
		if ( !$this->hasClass( 'oo-ui-panelLayout-expanded' ) ) {
			$config['expanded'] = false;
		}
		if ( $this->hasClass( 'oo-ui-panelLayout-framed' ) ) {
			$config['framed'] = true;
		}
		return parent::getConfig( $config );
	}
}
