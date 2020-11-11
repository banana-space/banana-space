<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ConfirmEdit/FancyCaptcha' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['FancyCaptcha'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for FancyCaptcha extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the FancyCaptcha extension requires MediaWiki 1.25+' );
}
