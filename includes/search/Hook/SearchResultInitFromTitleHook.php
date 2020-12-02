<?php

namespace MediaWiki\Search\Hook;

use Title;

/**
 * @stable to implement
 * @ingroup Hooks
 */
interface SearchResultInitFromTitleHook {
	/**
	 * Use this hook to set the revision used when displaying a page in
	 * search results.
	 *
	 * @since 1.35
	 *
	 * @param Title $title Current title being displayed in search results
	 * @param int|bool &$id Revision ID (default is false, for latest)
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSearchResultInitFromTitle( $title, &$id );
}
