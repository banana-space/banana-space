<?php
/**
 * Special handling for category description pages.
 * Modelled after ImagePage.php.
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
 */

/**
 * Special handling for category description pages, showing pages,
 * subcategories and file that belong to the category
 *
 * @method WikiCategoryPage getPage() Set by overwritten newPage() in this class
 */
class CategoryPage extends Article {
	# Subclasses can change this to override the viewer class.
	protected $mCategoryViewerClass = CategoryViewer::class;

	/**
	 * @param Title $title
	 * @return WikiCategoryPage
	 */
	protected function newPage( Title $title ) {
		// Overload mPage with a category-specific page
		return new WikiCategoryPage( $title );
	}

	public function view() {
		$request = $this->getContext()->getRequest();
		$diff = $request->getVal( 'diff' );
		$diffOnly = $request->getBool( 'diffonly',
			$this->getContext()->getUser()->getOption( 'diffonly' ) );

		if ( $diff !== null && $diffOnly ) {
			parent::view();
			return;
		}

		if ( !$this->getHookRunner()->onCategoryPageView( $this ) ) {
			return;
		}

		$title = $this->getTitle();
		if ( $title->inNamespace( NS_CATEGORY ) ) {
			$this->openShowCategory();
		}

		parent::view();

		if ( $title->inNamespace( NS_CATEGORY ) ) {
			$this->closeShowCategory();
		}

		# Use adaptive TTLs for CDN so delayed/failed purges are noticed less often
		$outputPage = $this->getContext()->getOutput();
		$outputPage->adaptCdnTTL(
			$this->getPage()->getTouched(),
			IExpiringStore::TTL_MINUTE
		);
	}

	public function openShowCategory() {
		# For overloading
	}

	public function closeShowCategory() {
		// Use these as defaults for back compat --catrope
		$request = $this->getContext()->getRequest();
		$oldFrom = $request->getVal( 'from' );
		$oldUntil = $request->getVal( 'until' );

		$reqArray = $request->getValues();

		$from = $until = [];
		foreach ( [ 'page', 'subcat', 'file' ] as $type ) {
			$from[$type] = $request->getVal( "{$type}from", $oldFrom );
			$until[$type] = $request->getVal( "{$type}until", $oldUntil );

			// Do not want old-style from/until propagating in nav links.
			if ( !isset( $reqArray["{$type}from"] ) && isset( $reqArray["from"] ) ) {
				$reqArray["{$type}from"] = $reqArray["from"];
			}
			if ( !isset( $reqArray["{$type}to"] ) && isset( $reqArray["to"] ) ) {
				$reqArray["{$type}to"] = $reqArray["to"];
			}
		}

		unset( $reqArray["from"] );
		unset( $reqArray["to"] );

		$viewer = new $this->mCategoryViewerClass(
			$this->getContext()->getTitle(),
			$this->getContext(),
			$from,
			$until,
			$reqArray
		);
		$out = $this->getContext()->getOutput();
		$out->addHTML( $viewer->getHTML() );
		$this->addHelpLink( 'Help:Categories' );
	}

	/**
	 * @deprecated since 1.35
	 */
	public function getCategoryViewerClass() {
		wfDeprecated( __METHOD__, '1.35' );
		return $this->mCategoryViewerClass;
	}

	/**
	 * @deprecated since 1.35
	 */
	public function setCategoryViewerClass( $class ) {
		wfDeprecated( __METHOD__, '1.35' );
		$this->mCategoryViewerClass = $class;
	}
}
