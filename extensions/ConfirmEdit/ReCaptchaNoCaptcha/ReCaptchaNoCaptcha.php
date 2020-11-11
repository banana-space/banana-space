<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ConfirmEdit/ReCaptchaNoCaptcha' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ReCaptchaNoCaptcha'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for ReCaptchaNoCaptcha extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the ReCaptchaNoCaptcha extension requires MediaWiki 1.25+' );
}
