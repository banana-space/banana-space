<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CiteThisPage' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CiteThisPage'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CiteThisPageAliases'] = __DIR__ . '/CiteThisPage.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for CiteThisPage extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the CiteThisPage extension requires MediaWiki 1.25+' );
}
