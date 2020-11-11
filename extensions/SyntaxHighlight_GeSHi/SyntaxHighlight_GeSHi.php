<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'SyntaxHighlight_GeSHi' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['SyntaxHighlight_GeSHi'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for SyntaxHighlight_GeSHi extension. '
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the SyntaxHighlight_GeSHi extension requires MediaWiki 1.25+' );
}
