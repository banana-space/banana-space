<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'InputBox' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['InputBox'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for InputBox extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the InputBox extension requires MediaWiki 1.30+' );
}
