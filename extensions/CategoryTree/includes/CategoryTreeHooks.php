<?php
/**
 * © 2006-2008 Daniel Kinzler and others
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

/**
 * Hooks for the CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 */
class CategoryTreeHooks {

	/**
	 * @internal For use by CategoryTreeCategoryViewer and CategoryTreePage only!
	 * @return bool
	 */
	public static function shouldForceHeaders() {
		global $wgCategoryTreeForceHeaders;
		return $wgCategoryTreeForceHeaders;
	}

	/**
	 * Adjusts config once MediaWiki is fully initialised
	 * TODO: Don't do this, lazy initialize the config
	 */
	public static function initialize() {
		global $wgRequest;
		global $wgCategoryTreeDefaultOptions, $wgCategoryTreeDefaultMode;
		global $wgCategoryTreeCategoryPageOptions, $wgCategoryTreeCategoryPageMode;
		global $wgCategoryTreeOmitNamespace;

		if ( !isset( $wgCategoryTreeDefaultOptions['mode'] )
			|| $wgCategoryTreeDefaultOptions['mode'] === null
		) {
			$wgCategoryTreeDefaultOptions['mode'] = $wgCategoryTreeDefaultMode;
		}

		if ( !isset( $wgCategoryTreeDefaultOptions['hideprefix'] )
			|| $wgCategoryTreeDefaultOptions['hideprefix'] === null
		) {
			$wgCategoryTreeDefaultOptions['hideprefix'] = $wgCategoryTreeOmitNamespace;
		}

		if ( !isset( $wgCategoryTreeCategoryPageOptions['mode'] )
			|| $wgCategoryTreeCategoryPageOptions['mode'] === null
		) {
			$mode = $wgRequest->getVal( 'mode' );
			$wgCategoryTreeCategoryPageOptions['mode'] = ( $mode )
				? CategoryTree::decodeMode( $mode ) : $wgCategoryTreeCategoryPageMode;
		}
	}

	/**
	 * @param Parser $parser
	 */
	public static function setHooks( Parser $parser ) {
		global $wgCategoryTreeAllowTag;
		if ( !$wgCategoryTreeAllowTag ) {
			return;
		}
		$parser->setHook( 'categorytree', 'CategoryTreeHooks::parserHook' );
		$parser->setFunctionHook( 'categorytree', 'CategoryTreeHooks::parserFunction' );
	}

	/**
	 * Entry point for the {{#categorytree}} tag parser function.
	 * This is a wrapper around CategoryTreeHooks::parserHook
	 * @param Parser $parser
	 * @param string ...$params
	 * @return array|string
	 */
	public static function parserFunction( Parser $parser, ...$params ) {
		// first user-supplied parameter must be category name
		if ( !$params ) {
			// no category specified, return nothing
			return '';
		}
		$cat = array_shift( $params );

		// build associative arguments from flat parameter list
		$argv = [];
		foreach ( $params as $p ) {
			if ( preg_match( '/^\s*(\S.*?)\s*=\s*(.*?)\s*$/', $p, $m ) ) {
				$k = $m[1];
				// strip any quotes enclusing the value
				$v = preg_replace( '/^"\s*(.*?)\s*"$/', '$1', $m[2] );
			} else {
				$k = trim( $p );
				$v = true;
			}

			$argv[$k] = $v;
		}

		// now handle just like a <categorytree> tag
		$html = self::parserHook( $cat, $argv, $parser );
		return [ $html, 'noparse' => true, 'isHTML' => true ];
	}

	/**
	 * Hook implementation for injecting a category tree into the sidebar.
	 * Only does anything if $wgCategoryTreeSidebarRoot is set to a category name.
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public static function onSkinBuildSidebar( Skin $skin, array &$sidebar ) {
		global $wgCategoryTreeSidebarRoot, $wgCategoryTreeSidebarOptions;

		if ( !$wgCategoryTreeSidebarRoot ) {
			return;
		}

		$html = self::parserHook( $wgCategoryTreeSidebarRoot, $wgCategoryTreeSidebarOptions );
		if ( $html ) {
			$sidebar['categorytree-portlet'] = $html;
			CategoryTree::setHeaders( $skin->getOutput() );
		}
	}

	/**
	 * Entry point for the <categorytree> tag parser hook.
	 * This loads CategoryTreeFunctions.php and calls CategoryTree::getTag()
	 * @suppress PhanUndeclaredProperty ParserOutput->mCategoryTreeTag
	 * @param string $cat
	 * @param array $argv
	 * @param Parser|null $parser
	 * @param PPFrame|null $frame
	 * @param bool $allowMissing
	 * @return bool|string
	 */
	public static function parserHook(
		$cat,
		array $argv,
		Parser $parser = null,
		PPFrame $frame = null,
		$allowMissing = false
	) {
		if ( $parser ) {
			# flag for use by CategoryTreeHooks::parserOutput
			$parser->mOutput->mCategoryTreeTag = true;
		}

		$ct = new CategoryTree( $argv );

		$attr = Sanitizer::validateTagAttributes( $argv, 'div' );

		$hideroot = isset( $argv['hideroot'] )
			? CategoryTree::decodeBoolean( $argv['hideroot'] ) : null;
		$onlyroot = isset( $argv['onlyroot'] )
			? CategoryTree::decodeBoolean( $argv['onlyroot'] ) : null;
		$depthArg = isset( $argv['depth'] ) ? (int)$argv['depth'] : null;

		$depth = CategoryTree::capDepth( $ct->getOption( 'mode' ), $depthArg );
		if ( $onlyroot ) {
			$depth = 0;
		}

		return $ct->getTag( $parser, $cat, $hideroot, $attr, $depth, $allowMissing );
	}

	/**
	 * Hook callback that injects messages and things into the <head> tag,
	 * if needed in the current page.
	 * Does nothing if $parserOutput->mCategoryTreeTag is not set
	 * @suppress PhanUndeclaredProperty ParserOutput->mCategoryTreeTag
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public static function parserOutput( OutputPage $outputPage, ParserOutput $parserOutput ) {
		if ( self::shouldForceHeaders() ) {
			// Skip, we've already set the headers unconditionally
			return;
		}
		if ( !empty( $parserOutput->mCategoryTreeTag ) ) {
			CategoryTree::setHeaders( $outputPage );
		}
	}

	/**
	 * BeforePageDisplay and BeforePageDisplayMobile hooks.
	 * These hooks are used when $wgCategoryTreeForceHeaders is set.
	 * Otherwise similar to CategoryTreeHooks::parserOutput.
	 * @param OutputPage $out
	 */
	public static function addHeaders( OutputPage $out ) {
		if ( !self::shouldForceHeaders() ) {
			return;
		}
		CategoryTree::setHeaders( $out );
	}

	/**
	 * ArticleFromTitle hook, override category page handling
	 *
	 * @param Title $title
	 * @param Article|null &$article Article (object) that will be returned
	 */
	public static function articleFromTitle( Title $title, Article &$article = null ) {
		if ( $title->getNamespace() == NS_CATEGORY ) {
			$article = new CategoryTreeCategoryPage( $title );
		}
	}

	/**
	 * OutputPageMakeCategoryLinks hook, override category links
	 * @param OutputPage &$out
	 * @param array $categories
	 * @param array &$links
	 * @return bool
	 */
	public static function outputPageMakeCategoryLinks(
		OutputPage &$out,
		array $categories,
		array &$links
	) {
		global $wgCategoryTreePageCategoryOptions, $wgCategoryTreeHijackPageCategories;

		if ( !$wgCategoryTreeHijackPageCategories ) {
			// Not enabled, don't do anything
			return true;
		}

		foreach ( $categories as $category => $type ) {
			$links[$type][] = self::parserHook( $category, $wgCategoryTreePageCategoryOptions, null, null, true );
			CategoryTree::setHeaders( $out );
		}

		return false;
	}

	/**
	 * Get exported data for the "ext.categoryTree" ResourceLoader module.
	 *
	 * @internal For use in extension.json only.
	 * @return array Data to be serialised as data.json
	 */
	public static function getDataForJs() {
		global $wgCategoryTreeCategoryPageOptions;

		// Look, this is pretty bad but CategoryTree is just whacky, it needs to be rewritten
		$ct = new CategoryTree( $wgCategoryTreeCategoryPageOptions );

		return [
			'defaultCtOptions' => $ct->getOptionsAsJsStructure(),
		];
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::preprocess hook
	 * @suppress PhanUndeclaredProperty SpecialPage->categoryTreeCategories
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param array $trackingCategories [ 'msg' => Title, 'cats' => Title[] ]
	 * @phan-param array<string,array{msg:Title,cats:Title[]}> $trackingCategories
	 */
	public static function onSpecialTrackingCategoriesPreprocess(
		SpecialPage $specialPage, array $trackingCategories
	) {
		$categoryDbKeys = [];
		foreach ( $trackingCategories as $catMsg => $data ) {
			foreach ( $data['cats'] as $catTitle ) {
				$categoryDbKeys[] = $catTitle->getDbKey();
			}
		}
		$categories = [];
		if ( $categoryDbKeys ) {
			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select(
				'category',
				[ 'cat_id', 'cat_title', 'cat_pages', 'cat_subcats', 'cat_files' ],
				[ 'cat_title' => array_unique( $categoryDbKeys ) ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$categories[$row->cat_title] = Category::newFromRow( $row );
			}
		}
		$specialPage->categoryTreeCategories = $categories;
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::generateCatLink hook
	 * @suppress PhanUndeclaredProperty SpecialPage->categoryTreeCategories
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param Title $catTitle Title object of the linked category
	 * @param string &$html Result html
	 */
	public static function onSpecialTrackingCategoriesGenerateCatLink(
		SpecialPage $specialPage, Title $catTitle, &$html
	) {
		if ( !isset( $specialPage->categoryTreeCategories ) ) {
			return;
		}

		$cat = null;
		if ( isset( $specialPage->categoryTreeCategories[$catTitle->getDbKey()] ) ) {
			$cat = $specialPage->categoryTreeCategories[$catTitle->getDbKey()];
		}

		$html .= CategoryTree::createCountString( $specialPage->getContext(), $cat, 0 );
	}
}
