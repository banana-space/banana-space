<?php

$baseCfg = require __DIR__ . '/base-config.php';

/**
 * This is based on MW's phan config.php.
 */
$coreCfg = [
	/**
	 * A list of individual files to include in analysis
	 * with a path relative to the root directory of the
	 * project. directory_list won't find .inc files so
	 * we augment it here.
	 */
	'file_list' => array_merge(
		function_exists( 'wikidiff2_do_diff' ) ? [] : [ '.phan/stubs/wikidiff.php' ],
		class_exists( PEAR::class ) ? [] : [ '.phan/stubs/mail.php' ],
		defined( 'PASSWORD_ARGON2I' ) ? [] : [ '.phan/stubs/password.php' ],
		class_exists( ProfilerExcimer::class ) ? [] : [ '.phan/stubs/excimer.php' ]
	),

	'exclude_file_list' => [
		// This file has invalid PHP syntax
		'vendor/squizlabs/php_codesniffer/src/Standards/PSR2/Tests/Methods/MethodDeclarationUnitTest.inc',
	],

	'directory_list' => [
		'includes/',
		'languages/',
		'maintenance/',
		'mw-config/',
		'resources/',
		'vendor/',
		'.phan/stubs/',
	],

	/**
	 * A list of directories holding code that we want
	 * to parse, but not analyze. Also works for individual
	 * files.
	 */
	"exclude_analysis_directory_list" => [
		'vendor/',
		'.phan/',
		'includes/composer/',
		'maintenance/language/',
		'includes/libs/jsminplus.php',
		'includes/libs/objectcache/utils/MemcachedClient.php',
	],
];

$cfg = $coreCfg + $baseCfg;
$cfg['plugins'][] = __DIR__ . '/../MediaWikiSecurityCheckPlugin.php';

return $cfg;
