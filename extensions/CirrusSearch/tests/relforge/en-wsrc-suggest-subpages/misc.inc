<?php
// 7 shards for enwiki
$wgCirrusSearchShardCount = [ 'content' => 1, 'general' => 1, 'titlesuggest' => 1 ];
$wgLanguagecode = 'en';
$wgCirrusSearchIndexBaseName = 'enwikisourcesuggestsubpages';

$wgCirrusSearchCompletionSuggesterSubphrases = [
	'use' => true,
	'build' => true,
	'type' => 'subpages',
	'limit' => 3,
];

$wgCirrusSearchCompletionSettings = 'fuzzy_subphrases';
