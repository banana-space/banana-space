<?php
/**
 * Special:Gadgets, provides a preview of MediaWiki:Gadgets.
 *
 * @file
 * @ingroup SpecialPage
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2007 Daniel Kinzler
 * @license GPL-2.0-or-later
 */

class SpecialGadgets extends SpecialPage {
	public function __construct() {
		parent::__construct( 'Gadgets', '', true );
	}

	/**
	 * Main execution function
	 * @param string $par Parameters passed to the page
	 */
	public function execute( $par ) {
		$parts = explode( '/', $par );

		if ( count( $parts ) == 2 && $parts[0] == 'export' ) {
			$this->showExportForm( $parts[1] );
		} else {
			$this->showMainForm();
		}
	}

	private function makeAnchor( $gadgetName ) {
		return 'gadget-' . Sanitizer::escapeId( $gadgetName, [ 'noninitial' ] );
	}

	/**
	 * Displays form showing the list of installed gadgets
	 */
	public function showMainForm() {
		global $wgContLang;

		$output = $this->getOutput();
		$this->setHeaders();
		$output->setPageTitle( $this->msg( 'gadgets-title' ) );
		$output->addWikiMsg( 'gadgets-pagetext' );

		$gadgets = GadgetRepo::singleton()->getStructuredList();
		if ( !$gadgets ) {
			return;
		}

		$output->disallowUserJs();
		$lang = $this->getLanguage();
		$langSuffix = "";
		if ( $lang->getCode() != $wgContLang->getCode() ) {
			$langSuffix = "/" . $lang->getCode();
		}

		$listOpen = false;

		$editInterfaceMessage = $this->getUser()->isAllowed( 'editinterface' )
			? 'edit'
			: 'viewsource';

		$linkRenderer = $this->getLinkRenderer();
		foreach ( $gadgets as $section => $entries ) {
			if ( $section !== false && $section !== '' ) {
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-section-$section$langSuffix" );
				$lnkTarget = $t
					? $linkRenderer->makeLink( $t, $this->msg( $editInterfaceMessage )->text(),
						[], [ 'action' => 'edit' ] )
					: htmlspecialchars( $section );
				$lnk = "&#160; &#160; [$lnkTarget]";

				$ttext = $this->msg( "gadget-section-$section" )->parse();

				if ( $listOpen ) {
					$output->addHTML( Xml::closeElement( 'ul' ) . "\n" );
					$listOpen = false;
				}

				$output->addHTML( Html::rawElement( 'h2', [], $ttext . $lnk ) . "\n" );
			}

			/**
			 * @var $gadget Gadget
			 */
			foreach ( $entries as $gadget ) {
				$name = $gadget->getName();
				$t = Title::makeTitleSafe( NS_MEDIAWIKI, "Gadget-{$name}$langSuffix" );
				if ( !$t ) {
					continue;
				}

				$links = [];
				$links[] = $linkRenderer->makeLink(
					$t,
					$this->msg( $editInterfaceMessage )->text(),
					[],
					[ 'action' => 'edit' ]
				);
				$links[] = $linkRenderer->makeLink(
					$this->getPageTitle( "export/{$name}" ),
					$this->msg( 'gadgets-export' )->text()
				);

				$ttext = $this->msg( "gadget-{$name}" )->parse();

				if ( !$listOpen ) {
					$listOpen = true;
					$output->addHTML( Xml::openElement( 'ul' ) );
				}

				$actions = '&#160;&#160;' .
					$this->msg( 'parentheses' )->rawParams( $lang->pipeList( $links ) )->escaped();
				$output->addHTML(
					Xml::openElement( 'li', [ 'id' => $this->makeAnchor( $name ) ] ) .
						$ttext . $actions . "<br />" .
						$this->msg( 'gadgets-uses' )->escaped() .
						$this->msg( 'colon-separator' )->escaped()
				);

				$lnk = [];
				foreach ( $gadget->getPeers() as $peer ) {
					$lnk[] = Html::element(
						'a',
						[ 'href' => '#' . $this->makeAnchor( $peer ) ],
						$peer
					);
				}
				foreach ( $gadget->getScriptsAndStyles() as $codePage ) {
					$t = Title::newFromText( $codePage );

					if ( !$t ) {
						continue;
					}

					$lnk[] = $linkRenderer->makeLink( $t, $t->getText() );
				}
				$output->addHTML( $lang->commaList( $lnk ) );
				if ( $gadget->getLegacyScripts() ) {
					$output->addHTML( '<br />' . Html::rawElement(
						'span',
						[ 'class' => 'mw-gadget-legacy errorbox' ],
						$this->msg( 'gadgets-legacy' )->parse()
					) );
				}

				$rights = [];
				foreach ( $gadget->getRequiredRights() as $right ) {
					$rights[] = '* ' . $this->msg( "right-$right" )->plain();
				}
				if ( count( $rights ) ) {
					$output->addHTML( '<br />' .
							$this->msg( 'gadgets-required-rights', implode( "\n", $rights ), count( $rights ) )->parse()
					);
				}

				$requiredSkins = $gadget->getRequiredSkins();
				// $requiredSkins can be an array or true (if all skins are supported)
				if ( is_array( $requiredSkins ) ) {
					$skins = [];
					$validskins = Skin::getSkinNames();
					foreach ( $requiredSkins as $skinid ) {
						if ( isset( $validskins[$skinid] ) ) {
							$skins[] = $this->msg( "skinname-$skinid" )->plain();
						} else {
							$skins[] = $skinid;
						}
					}
					if ( count( $skins ) ) {
						$output->addHTML(
							'<br />' .
							$this->msg( 'gadgets-required-skins', $lang->commaList( $skins ) )
								->numParams( count( $skins ) )->parse()
						);
					}
				}

				if ( $gadget->isOnByDefault() ) {
					$output->addHTML( '<br />' . $this->msg( 'gadgets-default' )->parse() );
				}

				$output->addHTML( Xml::closeElement( 'li' ) . "\n" );
			}
		}

		if ( $listOpen ) {
			$output->addHTML( Xml::closeElement( 'ul' ) . "\n" );
		}
	}

	/**
	 * Exports a gadget with its dependencies in a serialized form
	 * @param string $gadget Name of gadget to export
	 */
	public function showExportForm( $gadget ) {
		global $wgScript;

		$output = $this->getOutput();
		try {
			$g = GadgetRepo::singleton()->getGadget( $gadget );
		} catch ( InvalidArgumentException $e ) {
			$output->showErrorPage( 'error', 'gadgets-not-found', [ $gadget ] );
			return;
		}

		$this->setHeaders();
		$output->setPageTitle( $this->msg( 'gadgets-export-title' ) );
		$output->addWikiMsg( 'gadgets-export-text', $gadget, $g->getDefinition() );

		$exportList = "MediaWiki:gadget-$gadget\n";
		foreach ( $g->getScriptsAndStyles() as $page ) {
			$exportList .= "$page\n";
		}

		$htmlForm = HTMLForm::factory( 'ooui', [], $this->getContext() );
		$htmlForm
			->addHiddenField( 'title', SpecialPage::getTitleFor( 'Export' )->getPrefixedDBKey() )
			->addHiddenField( 'pages', $exportList )
			->addHiddenField( 'wpDownload', '1' )
			->addHiddenField( 'templates', '1' )
			->setAction( $wgScript )
			->setMethod( 'get' )
			->setSubmitText( $this->msg( 'gadgets-export-download' )->text() )
			->prepareForm()
			->displayForm( false );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
