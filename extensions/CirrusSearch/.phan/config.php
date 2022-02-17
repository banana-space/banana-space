<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'profiles/',
		'../../extensions/Elastica',
		'../../extensions/BetaFeatures',
		'../../extensions/SiteMatrix',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Elastica',
		'../../extensions/BetaFeatures',
		'../../extensions/SiteMatrix',
	]
);

$cfg['enable_class_alias_support'] = true;

return $cfg;
