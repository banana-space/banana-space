<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'LocalisationUpdate' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$GLOBALS['wgMessagesDirs']['LocalisationUpdate'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for LocalisationUpdate extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the LocalisationUpdate extension requires MediaWiki 1.25+' );
}
