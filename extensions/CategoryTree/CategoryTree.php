<?php

/**
 * CategoryTree extension, an AJAX based gadget
 * to display the category structure of a wiki
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Kinzler, brightbyte.de
 * @copyright © 2006-2008 Daniel Kinzler and others
 * @license GPL-2.0-or-later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CategoryTree' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CategoryTree'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for CategoryTree extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the CategoryTree extension requires MediaWiki 1.25+' );
}

// To maintain compatibility with configuration we currently keep
// the defines, but there are deprecated, and we'll be removed in
// a future MediaWiki release, in addition to this file.

/**
* Constants for use with the mode,
* defining what should be shown in the tree
*/
define( 'CT_MODE_CATEGORIES', 0 );
define( 'CT_MODE_PAGES', 10 );
define( 'CT_MODE_ALL', 20 );
define( 'CT_MODE_PARENTS', 100 );

/**
* Constants for use with the hideprefix option,
* defining when the namespace prefix should be hidden
*/
define( 'CT_HIDEPREFIX_NEVER', 0 );
define( 'CT_HIDEPREFIX_ALWAYS', 10 );
define( 'CT_HIDEPREFIX_CATEGORIES', 20 );
define( 'CT_HIDEPREFIX_AUTO', 30 );
