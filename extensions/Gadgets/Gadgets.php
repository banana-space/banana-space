<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Gadgets' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Gadgets'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['GadgetsAlias'] = __DIR__ . '/Gadgets.alias.php';
	$wgExtensionMessagesFiles['GadgetsNamespaces'] = __DIR__ . '/Gadgets.namespaces.php';
	/* wfWarn(
		'Deprecated PHP entry point used for Gadgets extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the Gadgets extension requires MediaWiki 1.28+' );
}
