<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Renameuser' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Renameuser'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['RenameuserAliases'] = __DIR__ . '/Renameuser.alias.php';

	/* wfWarn(
		'Deprecated PHP entry point used for Renameuser extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */

	return true;
} else {
	die( 'This version of the Renameuser extension requires MediaWiki 1.30+' );
}
