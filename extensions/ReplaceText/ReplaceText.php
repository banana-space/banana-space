<?php

wfLoadExtension( 'ReplaceText' );
// Keep i18n globals so mergeMessageFileList.php doesn't break
$wgMessagesDirs['ReplaceText'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['ReplaceTextAlias'] = __DIR__ . '/ReplaceText.i18n.alias.php';
wfWarn(
	'Deprecated PHP entry point used for Replace Text extension. ' .
	'Please use wfLoadExtension instead, ' .
	'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
);
