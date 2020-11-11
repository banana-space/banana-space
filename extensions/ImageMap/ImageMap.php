<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ImageMap' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ImageMap'] = __DIR__ . '/i18n';
	/* wfWarn(
		'Deprecated PHP entry point used for ImageMap extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the ImageMap extension requires MediaWiki 1.25+' );
}
