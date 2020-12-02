<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Poem' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Poem'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for Poem extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the Poem extension requires MediaWiki 1.25+' );
}
