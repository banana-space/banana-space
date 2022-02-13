<?php

/** Fast mode excludes MW from analysis. May miss some stuff with hooks. */

$baseCfg = require __DIR__ . '/base-config.php';

$IP = getenv( 'MW_INSTALL_PATH' ) !== false
	// Replace \\ by / for windows users to let exclude work correctly
	? str_replace( '\\', '/', getenv( 'MW_INSTALL_PATH' ) )
	: '../../';

/**
 * This is based on MW's phan config.php.
 */
$MWExtConfig = [
	/**
	 * A list of individual files to include in analysis
	 * with a path relative to the root directory of the
	 * project. directory_list won't find .inc files so
	 * we augment it here.
	 */
	'file_list' => array_merge(
		function_exists( 'wikidiff2_do_diff' ) ? [] : [ "$IP.phan/stubs/wikidiff.php" ],
		class_exists( PEAR::class ) ? [] : [ "$IP.phan/stubs/mail.php" ],
		defined( 'PASSWORD_ARGON2I' ) ? [] : [ "$IP.phan/stubs/password.php" ],
		class_exists( ProfilerExcimer::class ) ? [] : [ "$IP.phan/stubs/excimer.php" ]
	),

	'exclude_file_list' => [
		// This file has invalid PHP syntax
		$IP . 'vendor/squizlabs/php_codesniffer/src/Standards/PSR2/Tests/Methods/' .
			'MethodDeclarationUnitTest.inc',
	],

	'directory_list' => [
		$IP . 'includes/',
		$IP . 'languages/',
		$IP . 'maintenance/',
		$IP . 'mw-config/',
		$IP . 'resources/',
		$IP . 'skins/',
		$IP . 'vendor/',
		'.'
	],

	/**
	 * A list of directories holding code that we want
	 * to parse, but not analyze. Also works for individual
	 * files.
	 */
	"exclude_analysis_directory_list" => [
		'vendor',
		'tests',
		$IP
	],
];
unset( $IP );

$cfg = $MWExtConfig + $baseCfg;
$cfg['plugins'][] = __DIR__ . '/../MediaWikiSecurityCheckPlugin.php';

return $cfg;
