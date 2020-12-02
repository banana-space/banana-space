<?php

namespace MediaWiki\Hook;

use Skin;
use Title;

/**
 * @stable to implement
 * @ingroup Hooks
 */
interface WatchlistEditorBuildRemoveLineHook {
	/**
	 * This hook is called when building remove lines in Special:Watchlist/edit.
	 *
	 * @since 1.35
	 *
	 * @param string[] &$tools Array of extra HTML links
	 * @param Title $title
	 * @param bool $redirect whether the page is a redirect
	 * @param Skin $skin
	 * @param string &$link HTML link to title
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onWatchlistEditorBuildRemoveLine( &$tools, $title, $redirect,
		$skin, &$link
	);
}
