<?php

namespace MediaWiki\Hook;

/**
 * @stable to implement
 * @ingroup Hooks
 */
interface GetLangPreferredVariantHook {
	/**
	 * This hook is called in LanguageConverter#getPreferredVariant() to
	 * allow fetching the language variant code from cookies or other such
	 * alternative storage.
	 *
	 * @since 1.35
	 *
	 * @param string|null &$req Language variant from the URL or null if no variant
	 *   was specified in the URL; the value of this variable comes from
	 *   LanguageConverter#getURLVariant()
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onGetLangPreferredVariant( &$req );
}
