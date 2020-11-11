<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'ParserFunctions' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['ParserFunctions'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['ParserFunctionsMagic'] = __DIR__ . '/ParserFunctions.i18n.magic.php';
	/* wfWarn(
		'Deprecated PHP entry point used for ParserFunctions extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return true;
} else {
	die( 'This version of the ParserFunctions extension requires MediaWiki 1.25+' );
}
