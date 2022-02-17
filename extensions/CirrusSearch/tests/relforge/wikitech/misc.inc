<?php
// 7 shards for enwiki
$wgCirrusSearchShardCount = [ 'content' => 1, 'general' => 1, 'titlesuggest' => 1 ];
$wgLanguagecode = 'en';
$wgCirrusSearchIndexBaseName = 'labswiki';

$wgNamespacesToBeSearchedDefault = [ 12 => 1, 116 => 1, 498 => 1 ];
$wgNamespacesWithSubpages = [ 0 => 1, 110 => 1, 498 => 1, 666 => 1 ];
$wgContentNamespaces = [ 12, 116, 0, 498 ];

$wgExtraNamespaces = [
	110 => "Obsolete",
	111 => "Obsolete_talk",
	112 => "OfficeIT",
	113 => "OfficeIT_talk",
	116 => "Tool",
	117 => "Tool_talk",
	666 => "Hiera",
	667 => "Hiera_Talk",
	498 => "Nova_Resource",
	499 => "Nova_Resource_Talk",
];

$wgCirrusSearchDefaultNamespaceWeight = 0.2;
$wgCirrusSearchNamespaceWeights = [
	116 => 1.0,
	498 => 1.0,
] + $wgCirrusSearchNamespaceWeights;
$wgCirrusSearchCompletionSettings = 'fuzzy_subphrases';
$wgCirrusSearchExtraIndexes = [];
