<?php

namespace MediaWiki\Hook;

use Title;

/**
 * @stable to implement
 * @ingroup Hooks
 */
interface SpecialRandomGetRandomTitleHook {
	/**
	 * This hook is called during the execution of Special:Random,
	 *
	 * Use this to change some selection criteria or substitute a different title.
	 *
	 * @since 1.35
	 *
	 * @param string &$randstr The random number from wfRandom()
	 * @param bool &$isRedir Boolean, whether to select a redirect or non-redirect
	 * @param int[] &$namespaces An array of namespace indexes to get the title from
	 * @param array &$extra An array of extra SQL statements
	 * @param Title &$title If the hook returns false, a Title object to use instead of the
	 *   result from the normal query
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSpecialRandomGetRandomTitle( &$randstr, &$isRedir,
		&$namespaces, &$extra, &$title
	);
}
