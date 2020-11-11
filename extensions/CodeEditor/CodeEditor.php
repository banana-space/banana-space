<?php

/**
 * Helper to load syntax-highlighting editor for JavaScript and CSS pages
 * on-wiki.
 *
 * Extends and requires WikiEditor extension.
 *
 * Extension code is GPLv2 following MediaWiki base.
 * Ace editor JS code follows its own license, see in the 'ace' subdir.
 */

/**
 * This PHP entry point is deprecated.
 * Please use wfLoadExtension() and the extension.json file instead.
 * See https://www.mediawiki.org/wiki/Manual:Extension_registration for more details.
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CodeEditor' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CodeEditor'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for CodeEditor extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the CodeEditor extension requires MediaWiki 1.25+' );
}
