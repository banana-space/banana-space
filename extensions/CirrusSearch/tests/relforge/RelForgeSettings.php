<?php
/**
 * Settings for relforge wikis (wmf wikis)
 * Must be included from /vagrant/settings.d/00-Relforge.php
 * after habing set $wgCirrusSearchRelforgeProfile
 */

// Load it now so it won't override our settings
require_once __DIR__ . "/../jenkins/Jenkins.php";

// pop score is global
$wgHooks['CirrusSearchMappingConfig'][] = function ( array &$config, $mappingConfigBuilder ) {
	$config['page']['properties']['popularity_score'] = [
		'type' => 'double',
	];
};

// No need for replicas in relforge
$wgCirrusSearchReplicas = '0-0';
// Allow more than one shard per node
$wgCirrusSearchMaxShardsPerNode = [ 'content' => -1, 'general' => -1, 'titlesuggest' => -1 ];
$wgCirrusSearchRefreshInterval = 30;

// We don't use DFS in prod
$wgCirrusSearchMoreAccurateScoringMode = false;

// subphrase is not enabled by default on prod
$wgCirrusSearchCompletionSuggesterSubphrases = [
	'use' => false,
	'build' => false,
	'type' => 'subpages',
	'limit' => 3,
];

$wgCirrusSearchWikimediaExtraPlugin['token_count_router'] = true;
// Reset this value in case it was set to do some testing
// in FullyFeaturedConfig.php
$wgCirrusSearchICUFoldingUnicodeSetFilter = null;

// Move to prod like defaults now
$wgCirrusSearchSimilarityProfile = 'wmf_defaults';
$wgCirrusSearchRescoreProfile = 'wsum_inclinks';
$wgCirrusSearchFullTextQueryBuilderProfile = 'perfield_builder';

// Activate devel options useful for relforge
$wgCirrusSearchDevelOptions = [
	'morelike_collect_titles_from_elastic' => true,
	'ignore_missing_rev' => true,
];

// Specific settings
if ( file_exists( __DIR__ . "/$wgCirrusSearchRelforgeProfile/misc.inc" ) ) {
	require_once __DIR__ . "/$wgCirrusSearchRelforgeProfile/misc.inc";
}
if ( file_exists( __DIR__ . "/$wgCirrusSearchRelforgeProfile/similarity.inc" ) ) {
	require_once __DIR__ . "/$wgCirrusSearchRelforgeProfile/similarity.inc";
	$wgCirrusSearchSimilarityProfile = 'relforge';
}
if ( file_exists( __DIR__ . "/$wgCirrusSearchRelforgeProfile/ftbuilder.inc" ) ) {
	require_once __DIR__ . "/$wgCirrusSearchRelforgeProfile/ftbuilder.inc";
	$wgCirrusSearchFullTextQueryBuilderProfile = 'relforge';
}
if ( file_exists( __DIR__ . "/$wgCirrusSearchRelforgeProfile/rescore.inc" ) ) {
	require_once __DIR__ . "/$wgCirrusSearchRelforgeProfile/rescore.inc";
	$wgCirrusSearchRescoreProfile = 'relforge';
}

if ( file_exists( __DIR__ . "/$wgCirrusSearchRelforgeProfile/templates.inc" ) ) {
	require_once __DIR__ . "/$wgCirrusSearchRelforgeProfile/templates.inc";
}
