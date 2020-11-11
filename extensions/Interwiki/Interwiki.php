<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Interwiki' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Interwiki'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['InterwikiAlias'] = __DIR__ . '/Interwiki.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Interwiki extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the Interwiki extension requires MediaWiki 1.25+' );
}
