<?php

/* Slow config. Include vendor in the analysis. */
$MWExtConfig = require __DIR__ . '/mwext-fast-config.php';
$MWExtConfig['exclude_analysis_directory_list'] = [
	'vendor/mediawiki/phan-taint-check-plugin'
];

return $MWExtConfig;
