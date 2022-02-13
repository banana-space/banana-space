<?php

$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../../';

$MWExtConfig = require __DIR__ . '/mwext-fast-config.php';
$MWExtConfig['exclude_analysis_directory_list'] = [
	$IP . 'vendor/',
	$IP . '.phan/stubs/',
	$IP . 'includes/composer/',
	$IP . 'maintenance/language/',
	$IP . 'includes/libs/jsminplus.php',
	'vendor'
];

unset( $IP );
return $MWExtConfig;
