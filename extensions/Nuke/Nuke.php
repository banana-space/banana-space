<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Nuke' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Nuke'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['NukeAlias'] = __DIR__ . '/Nuke.alias.php';

	wfWarn(
		'Deprecated PHP entry point used for Nuke extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);

	return true;
} else {
	die( 'This version of the Nuke extension requires MediaWiki 1.28.1+' );
}
