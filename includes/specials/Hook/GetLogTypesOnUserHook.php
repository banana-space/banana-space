<?php

namespace MediaWiki\Hook;

/**
 * @stable to implement
 * @ingroup Hooks
 */
interface GetLogTypesOnUserHook {
	/**
	 * Use this hook to add log types where the target is a user page.
	 *
	 * @since 1.35
	 *
	 * @param string[] &$types Array of log types
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetLogTypesOnUser( &$types );
}
