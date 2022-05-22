<?php
if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'HeadScript' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['HeadScript'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for the HeadScript extension. ' .
		'Please use wfLoadExtension() instead, ' .
		'see https://www.mediawiki.org/wiki/Special:MyLanguage/Manual:Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the HeadScript extension requires MediaWiki 1.29+' );
}
