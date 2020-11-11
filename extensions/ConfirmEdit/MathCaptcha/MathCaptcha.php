<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ConfirmEdit/MathCaptcha' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['MathCaptcha'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for MathCaptcha extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the MathCaptcha extension requires MediaWiki 1.25+' );
}
