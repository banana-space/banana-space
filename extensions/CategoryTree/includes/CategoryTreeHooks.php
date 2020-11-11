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

	public static function shouldForceHeaders() {
		global $wgCategoryTreeSidebarRoot, $wgCategoryTreeHijackPageCategories,
			$wgCategoryTreeForceHeaders;
		return $wgCategoryTreeForceHeaders || $wgCategoryTreeSidebarRoot
			|| $wgCategoryTreeHijackPageCategories;
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
			|| is_null( $wgCategoryTreeDefaultOptions['mode'] )
		) {
			$wgCategoryTreeDefaultOptions['mode'] = $wgCategoryTreeDefaultMode;
		}

		if ( !isset( $wgCategoryTreeDefaultOptions['hideprefix'] )
			|| is_null( $wgCategoryTreeDefaultOptions['hideprefix'] )
		) {
			$wgCategoryTreeDefaultOptions['hideprefix'] = $wgCategoryTreeOmitNamespace;
		}

		if ( !isset( $wgCategoryTreeCategoryPageOptions['mode'] )
			|| is_null( $wgCategoryTreeCategoryPageOptions['mode'] )
		) {
			$mode = $wgRequest->getVal( 'mode' );
			$wgCategoryTreeCategoryPageOptions['mode'] = ( $mode )
				? CategoryTree::decodeMode( $mode ) : $wgCategoryTreeCategoryPageMode;
		}
	}

	/**
	 * @param Parser $parser
	 */
	public static function setHooks( $parser ) {
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
	 * @return array|string
	 */
	public static function parserFunction( $parser ) {
		$params = func_get_args();
		array_shift( $params ); // first is $parser, strip it

		// first user-supplied parameter must be category name
		if ( !$params ) {
			return ''; // no category specified, return nothing
		}
		$cat = array_shift( $params );

		// build associative arguments from flat parameter list
		$argv = [];
		foreach ( $params as $p ) {
			if ( preg_match( '/^\s*(\S.*?)\s*=\s*(.*?)\s*$/', $p, $m ) ) {
				$k = $m[1];
				$v = preg_replace( '/^"\s*(.*?)\s*"$/', '$1', $m[2] ); // strip any quotes enclusing the value
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
	 * @param SkinTemplate $tpl
	 */
	public static function skinTemplateOutputPageBeforeExec( $skin, $tpl ) {
		global $wgCategoryTreeSidebarRoot, $wgCategoryTreeSidebarOptions;

		if ( !$wgCategoryTreeSidebarRoot ) {
			return;
		}

		$html = self::parserHook( $wgCategoryTreeSidebarRoot, $wgCategoryTreeSidebarOptions );
		if ( $html ) {
			$tpl->data['sidebar']['categorytree-portlet'] = $html;
		}
	}

	/**
	 * Entry point for the <categorytree> tag parser hook.
	 * This loads CategoryTreeFunctions.php and calls CategoryTree::getTag()
	 * @param string $cat
	 * @param array $argv
	 * @param Parser $parser
	 * @param bool $allowMissing
	 * @return bool|string
	 */
	public static function parserHook( $cat, $argv, $parser = null, $allowMissing = false ) {
		global $wgOut;

		if ( $parser ) {
			$parser->mOutput->mCategoryTreeTag = true; # flag for use by CategoryTreeHooks::parserOutput
		} else {
			CategoryTree::setHeaders( $wgOut );
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
	 * @param OutputPage $outputPage
	 * @param ParserOutput $parserOutput
	 */
	public static function parserOutput( $outputPage, $parserOutput ) {
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
	 * @param Article &$article
	 * @return bool
	 */
	public static function articleFromTitle( $title, &$article ) {
		if ( $title->getNamespace() == NS_CATEGORY ) {
			$article = new CategoryTreeCategoryPage( $title );
		}
		return true;
	}

	/**
	 * OutputPageMakeCategoryLinks hook, override category links
	 * @param OutputPage &$out
	 * @param array $categories
	 * @param array &$links
	 * @return bool
	 */
	public static function outputPageMakeCategoryLinks( &$out, $categories, &$links ) {
		global $wgCategoryTreePageCategoryOptions, $wgCategoryTreeHijackPageCategories;

		if ( !$wgCategoryTreeHijackPageCategories ) {
			// Not enabled, don't do anything
			return true;
		}

		foreach ( $categories as $category => $type ) {
			$links[$type][] = self::parserHook( $category, $wgCategoryTreePageCategoryOptions, null, true );
		}

		return false;
	}

	/**
	 * @param Skin $skin
	 * @param array &$links
	 * @param string &$result
	 * @return bool
	 */
	public static function skinJoinCategoryLinks( $skin, &$links, &$result ) {
		global $wgCategoryTreeHijackPageCategories;
		if ( !$wgCategoryTreeHijackPageCategories ) {
			// Not enabled, don't do anything.
			return true;
		}
		$embed = '<div class="CategoryTreeCategoryBarItem">';
		$pop = '</div>';
		$sep = ' ';

		$result = $embed . implode( "{$pop} {$sep} {$embed}", $links ) . $pop;

		return false;
	}

	/**
	 * @param array &$vars
	 * @return bool
	 */
	public static function getConfigVars( &$vars ) {
		global $wgCategoryTreeCategoryPageOptions;

		// Look this is pretty bad but Category tree is just whacky, it needs to be rewritten
		$ct = new CategoryTree( $wgCategoryTreeCategoryPageOptions );
		$vars['wgCategoryTreePageCategoryOptions'] = $ct->getOptionsAsJsStructure();
		return true;
	}

	/**
	 * Hook handler for the SpecialTrackingCategories::preprocess hook
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param array $trackingCategories [ 'msg' => Title, 'cats' => Title[] ]
	 */
	public static function onSpecialTrackingCategoriesPreprocess(
		$specialPage, $trackingCategories
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
	 * @param SpecialPage $specialPage SpecialTrackingCategories object
	 * @param Title $catTitle Title object of the linked category
	 * @param string &$html Result html
	 */
	public static function onSpecialTrackingCategoriesGenerateCatLink(
		$specialPage, $catTitle, &$html
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
