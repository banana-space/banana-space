<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'TemplateData' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['TemplateData'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for TemplateData extension. Please use wfLoadExtension ' .
		'instead, see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return true;
} else {
	die( 'This version of the TemplateData extension requires MediaWiki 1.32+' );
}
