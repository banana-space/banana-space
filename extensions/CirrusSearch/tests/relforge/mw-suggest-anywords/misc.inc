<?php
// 7 shards for enwiki
$wgCirrusSearchShardCount = [ 'content' => 1, 'general' => 1, 'titlesuggest' => 1 ];
$wgLanguagecode = 'en';
$wgCirrusSearchIndexBaseName = 'mediawikiwikisuganywords';

$wgCirrusSearchCompletionSuggesterSubphrases = [
	'use' => true,
	'build' => true,
	'type' => 'anywords',
	'limit' => 10,
];

$wgCirrusSearchCompletionSettings = 'fuzzy_subphrases';
