<?php

namespace OOUI;

/**
 * MenuLayouts combine a menu and a content PanelLayout panel.
 *
 * The menu is positioned relative to the content (after, before, top, or bottom)
 */
class MenuLayout extends Layout {

	/**
	 * @var Tag
	 */
	protected $menuWrapper;
	/**
	 * @var Tag
	 */
	protected $contentWrapper;
	/**
	 * @var ?PanelLayout
	 */
	protected $menuPanel;
	/**
	 * @var ?PanelLayout
	 */
	protected $contentPanel;
	/**
	 * @var string
	 */
	protected $menuPosition;
	/**
	 * @var bool
	 */
	protected $expanded;

	/**
	 * @param array $config Configuration options
	 *      - PanelLayout $config['menuPanel'] Menu panel
	 *      - PanelLayout $config['contentPanel'] Content panel
	 *      - bool $config['expanded'] Expand content to fill the parent element (default: true)
	 *      - bool $config['showMenu'] Show menu (default: true)
	 *      - string $config['menuPosition'] top, after, bottom, before (default: before)
	 * @codingStandardsIgnoreStart
	 * @phan-param array{menuPanel:PanelLayout,contentPanel:PanelLayout,expanded?:bool,showMenu?:bool,menuPosition?:string} $config
	 * @codingStandardsIgnoreEnd
	 */
	public function __construct( array $config = [] ) {
		$config = array_merge( [
			'expanded' => true,
			'showMenu' => true,
			'menuPosition' => 'before',
		], $config );

		parent::__construct( $config );

		$this->menuPanel = null;
		$this->contentPanel = null;
		$this->expanded = $config['expanded'];
		$this->menuWrapper = new Tag( 'div' );
		$this->contentWrapper = new Tag( 'div' );

		$this->menuWrapper->addClasses( [ 'oo-ui-menuLayout-menu' ] );
		$this->contentWrapper->addClasses( [ 'oo-ui-menuLayout-content' ] );
		$this->addClasses( [ 'oo-ui-menuLayout' ] );
		if ( $this->expanded ) {
			$this->addClasses( [ 'oo-ui-menuLayout-expanded' ] );
		} else {
			$this->addClasses( [ 'oo-ui-menuLayout-static' ] );
		}
		if ( !empty( $config['menuPanel'] ) ) {
			$this->setMenuPanel( $config['menuPanel'] );
		}
		if ( !empty( $config['contentPanel'] ) ) {
			$this->setContentPanel( $config['contentPanel'] );
		}
		$this->setMenuPosition( $config['menuPosition'] );
		$this->toggleMenu( (bool)$config['showMenu' ] );
	}

	public function getConfig( &$config ) {
		$config = parent::getConfig( $config );
		if ( $this->menuPosition !== 'before' ) {
			$config['menuPosition'] = $this->menuPosition;
		}
		if ( !$this->expanded ) {
			$config['expanded'] = $this->expanded;
		}
		$showMenu = $this->hasClass( 'oo-ui-menuLayout-showMenu' );
		if ( !$showMenu ) {
			$config['showMenu'] = $showMenu;
		}
		if ( $this->menuPanel ) {
			$config['menuPanel'] = $this->menuPanel;
		}
		if ( $this->contentPanel ) {
			$config['contentPanel'] = $this->contentPanel;
		}
		return $config;
	}

	public function toggleMenu( $showMenu ) {
		$this->toggleClasses( [ 'oo-ui-menuLayout-showMenu' ], $showMenu );
		$this->toggleClasses( [ 'oo-ui-menuLayout-hideMenu' ], !$showMenu );
		$this->menuWrapper->setAttributes( [
			'aria-hidden' => $showMenu ? 'false' : 'true'
		] );
	}

	public function setMenuPosition( $position ) {
		if ( !in_array( $position, [ 'top', 'bottom', 'before', 'after' ], true ) ) {
			$position = 'before';
		}

		$this->removeClasses( [ 'oo-ui-menuLayout-' . $this->menuPosition ] );
		$this->menuPosition = $position;
		$this->clearContent();
		if ( $this->menuPosition == 'top' || $this->menuPosition == 'before' ) {
			$this->appendContent( $this->menuWrapper, $this->contentWrapper );
		} else {
			$this->appendContent( $this->contentWrapper, $this->menuWrapper );
		}
		$this->addClasses( [ 'oo-ui-menuLayout-' . $position ] );
	}

	public function setMenuPanel( PanelLayout $menuPanel ) {
		$this->menuPanel = $menuPanel;
		$this->menuWrapper->appendContent( $this->menuPanel );
	}

	public function setContentPanel( PanelLayout $contentPanel ) {
		$this->contentPanel = $contentPanel;
		$this->contentWrapper->appendContent( $this->contentPanel );
	}
}
