<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	[
		'container.php',
		'defines.php',
		'FlowActions.php',
	]
);

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/Echo',
		'../../extensions/BetaFeatures',
		'../../extensions/CentralAuth',
		'../../extensions/LiquidThreads',
		'../../extensions/Elastica',
		'../../extensions/CirrusSearch',
		'../../extensions/AbuseFilter',
		'../../extensions/ConfirmEdit',
		'../../extensions/SpamBlacklist',
		'../../extensions/GuidedTour',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Echo',
		'../../extensions/BetaFeatures',
		'../../extensions/CentralAuth',
		'../../extensions/LiquidThreads',
		'../../extensions/Elastica',
		'../../extensions/CirrusSearch',
		'../../extensions/AbuseFilter',
		'../../extensions/ConfirmEdit',
		'../../extensions/SpamBlacklist',
		'../../extensions/GuidedTour',
	]
);

return $cfg;
