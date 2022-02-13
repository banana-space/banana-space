<?php

$baseCfg = require __DIR__ . '/base-config.php';

$thisCfg = [
	/**
	 * A list of individual files to include in analysis
	 * with a path relative to the root directory of the
	 * project. directory_list won't find .inc files so
	 * we augment it here.
	 */
	'file_list' => [],
	'directory_list' => [
		'.'
	],

	/**
	 * A file list that defines files that will be excluded
	 * from parsing and analysis and will not be read at all.
	 *
	 * This is useful for excluding hopelessly unanalyzable
	 * files that can't be removed for whatever reason.
	 */
	'exclude_file_list' => [],

	/**
	 * A list of directories holding code that we want
	 * to parse, but not analyze. Also works for individual
	 * files.
	 */
	"exclude_analysis_directory_list" => [
		'vendor'
	],
];

$cfg = $thisCfg + $baseCfg;
$cfg['plugins'][] = __DIR__ . '/../GenericSecurityCheckPlugin.php';

return $cfg;
