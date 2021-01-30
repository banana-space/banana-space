<?php
/**
 * Thanks extension
 *
 * This extension adds 'thank' links that allow users to thank other users for
 * specific revisions. It relies on the Echo extension to send the actual thanks.
 * For more info see https://mediawiki.org/wiki/Extension:Thanks
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * This program is distributed WITHOUT ANY WARRANTY.
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Kaldari
 * @license MIT
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'Thanks' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['Thanks'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['ThanksAlias'] = __DIR__ . '/Thanks.alias.php';
	wfWarn(
		'Deprecated PHP entry point used for Thanks extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
} else {
	die( 'This version of the Thanks extension requires MediaWiki 1.25+' );
}
