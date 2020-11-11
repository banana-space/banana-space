<?php
/**
 * OATHAuth extension - Support for HMAC based one time passwords
 *
 *
 * For more info see http://mediawiki.org/wiki/Extension:OATHAuth
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2012 Ryan Lane
 * @license GPL-2.0-or-later
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'OATHAuth' );

	$wgMessagesDirs['OATHAuth'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['OATHAuthAlias'] = __DIR__ . '/OATHAuth.alias.php';

	/* wfWarn(
		'Deprecated PHP entry point used for OATHAuth extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */

	return true;
} else {
	die( 'This version of the OATHAuth extension requires MediaWiki 1.25+' );
}
