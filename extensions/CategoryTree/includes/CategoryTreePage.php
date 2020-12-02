<?php
/**
 * © 2006 Daniel Kinzler
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 */

use MediaWiki\MediaWikiServices;

/**
 * Special page for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 */
class CategoryTreePage extends SpecialPage {
	public $target = '';

	/**
	 * @var CategoryTree
	 */
	public $tree = null;

	public function __construct() {
		parent::__construct( 'CategoryTree' );
	}

	/**
	 * @param string $name
	 * @return mixed
	 */
	private function getOption( $name ) {
		global $wgCategoryTreeDefaultOptions;

		if ( $this->tree ) {
			return $this->tree->getOption( $name );
		} else {
			return $wgCategoryTreeDefaultOptions[$name];
		}
	}

	/**
	 * Main execution function
	 * @param string|null $par Parameters passed to the page
	 */
	public function execute( $par ) {
		global $wgCategoryTreeDefaultOptions, $wgCategoryTreeSpecialPageOptions;

		$this->setHeaders();
		$this->addHelpLink( 'Extension:CategoryTree' );
		$request = $this->getRequest();
		if ( $par ) {
			$this->target = $par;
		} else {
			$this->target = $request->getVal( 'target' );
			if ( $this->target === null ) {
				$rootcategory = $this->msg( 'rootcategory' );
				if ( $rootcategory->exists() ) {
					$this->target = $rootcategory->text();
				}
			}
		}

		$this->target = trim( $this->target );

		$options = [];

		# grab all known options from the request. Normalization is done by the CategoryTree class
		foreach ( $wgCategoryTreeDefaultOptions as $option => $default ) {
			if ( isset( $wgCategoryTreeSpecialPageOptions[$option] ) ) {
				$default = $wgCategoryTreeSpecialPageOptions[$option];
			}

			$options[$option] = $request->getVal( $option, $default );
		}

		$this->tree = new CategoryTree( $options );

		$output = $this->getOutput();
		$output->addWikiMsg( 'categorytree-header' );

		$this->executeInputForm();

		if ( $this->target !== '' && $this->target !== null ) {
			if ( !CategoryTreeHooks::shouldForceHeaders() ) {
				CategoryTree::setHeaders( $output );
			}

			$title = CategoryTree::makeTitle( $this->target );

			if ( $title && $title->getArticleID() ) {
				$output->addHTML( Xml::openElement( 'div', [ 'class' => 'CategoryTreeParents' ] ) );
				$output->addHTML( $this->msg( 'categorytree-parents' )->parse() );
				$output->addHTML( $this->msg( 'colon-separator' )->escaped() );

				$parents = $this->tree->renderParents( $title );

				if ( $parents == '' ) {
					$output->addHTML( $this->msg( 'categorytree-no-parent-categories' )->parse() );
				} else {
					$output->addHTML( $parents );
				}

				$output->addHTML( Xml::closeElement( 'div' ) );

				$output->addHTML( Xml::openElement( 'div', [ 'class' => 'CategoryTreeResult' ] ) );
				$output->addHTML( $this->tree->renderNode( $title, 1 ) );
				$output->addHTML( Xml::closeElement( 'div' ) );
			} else {
				$output->addHTML( Xml::openElement( 'div', [ 'class' => 'CategoryTreeNotice' ] ) );
				$output->addHTML( $this->msg( 'categorytree-not-found', $this->target )->parse() );
				$output->addHTML( Xml::closeElement( 'div' ) );
			}
		}
	}

	/**
	 * Input form for entering a category
	 */
	private function executeInputForm() {
		$namespaces = $this->getRequest()->getVal( 'namespaces', '' );
		// mode may be overriden by namespaces option
		$mode = ( $namespaces == '' ? $this->getOption( 'mode' ) : CategoryTreeMode::ALL );
		if ( $mode == CategoryTreeMode::CATEGORIES ) {
			$modeDefault = 'categories';
		} elseif ( $mode == CategoryTreeMode::PAGES ) {
			$modeDefault = 'pages';
		} else {
			$modeDefault = 'all';
		}

		$formDescriptor = [
			'category' => [
				'type' => 'title',
				'name' => 'target',
				'label-message' => 'categorytree-category',
				'namespace' => NS_CATEGORY,
			],

			'mode' => [
				'type' => 'select',
				'name' => 'mode',
				'label-message' => 'categorytree-mode-label',
				'options-messages' => [
					'categorytree-mode-categories' => 'categories',
					'categorytree-mode-pages' => 'pages',
					'categorytree-mode-all' => 'all',
				],
				'default' => $modeDefault,
				'nodata' => true,
			],

			'namespace' => [
				'type' => 'namespaceselect',
				'name' => 'namespaces',
				'label-message' => 'namespace',
				'all' => '',
			],
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->addHiddenFields( [ 'title' => $this->getPageTitle()->getPrefixedDbKey() ] )
			->setWrapperLegendMsg( 'categorytree-legend' )
			->setSubmitTextMsg( 'categorytree-go' )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		$title = Title::newFromText( $search, NS_CATEGORY );
		if ( $title && $title->getNamespace() !== NS_CATEGORY ) {
			// Someone searching for something like "Wikipedia:Foo"
			$title = Title::makeTitleSafe( NS_CATEGORY, $search );
		}
		if ( !$title ) {
			// No prefix suggestion outside of category namespace
			return [];
		}
		$searchEngine = MediaWikiServices::getInstance()->newSearchEngine();
		$searchEngine->setLimitOffset( $limit, $offset );
		// Autocomplete subpage the same as a normal search, but just for categories
		$searchEngine->setNamespaces( [ NS_CATEGORY ] );
		$result = $searchEngine->defaultPrefixSearch( $search );

		return array_map( function ( Title $t ) {
			// Remove namespace in search suggestion
			return $t->getText();
		}, $result );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'pages';
	}

}
