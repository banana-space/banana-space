<?php

$wgCirrusSearchRescoreProfiles['relforge_a'] = [
	'supported_namespaces' => 'all',
	'fallback_profile' => 'classic_noboostlinks',
	'rescore' => [
		[
			'window' => 512,
			'window_size_override' => 'CirrusSearchPhraseRescoreWindowSize',
			'rescore_query_weight' => 10,
			'rescore_query_weight_override' => 'CirrusSearchPhraseRescoreBoost',
			'query_weight' => 1.0,
			'type' => 'phrase',
		],
		[
			'window' => 8192,
			'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
			'query_weight' => 1.0,
			'rescore_query_weight' => 1.0,
			'score_mode' => 'total',
			'type' => 'function_score',
			'function_chain' => 'img_qual'
		],
		[
			'window' => 8192,
			'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
			'query_weight' => 1.0,
			'rescore_query_weight' => 1.0,
			'score_mode' => 'multiply',
			'type' => 'function_score',
			'function_chain' => 'optional_chain'
		],
	],
];

$wgCirrusSearchRescoreProfiles['relforge'] = [
	'supported_namespaces' => 'all',
	'fallback_profile' => 'classic_noboostlinks',
	'rescore' => [
		[
			'window' => 512,
			'window_size_override' => 'CirrusSearchPhraseRescoreWindowSize',
			'rescore_query_weight' => 10,
			'rescore_query_weight_override' => 'CirrusSearchPhraseRescoreBoost',
			'query_weight' => 1.0,
			'type' => 'phrase',
		],
		[
			'window' => 8192,
			'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
			'query_weight' => 1.0,
			'rescore_query_weight' => 1.0,
			'score_mode' => 'multiply',
			'type' => 'function_score',
			'function_chain' => 'img_qual_m'
		],
		[
			'window' => 8192,
			'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
			'query_weight' => 1.0,
			'rescore_query_weight' => 1.0,
			'score_mode' => 'multiply',
			'type' => 'function_score',
			'function_chain' => 'optional_chain'
		],
	],
];

$wgCirrusSearchRescoreFunctionScoreChains['img_qual'] = [
	'functions' => [
		[
			'type' => 'custom_field',
			'weight' => [
				'value' => 0.6,
				'uri_param_override' => 'cirrusQualW',
			],
			'params' => [
				'field' => 'image_quality',
				'missing' => 0.01
			],
		],
	],
];

$weight = $_GET['cirrusQualW'] ?? 1;

$wgCirrusSearchRescoreFunctionScoreChains['img_qual_m'] = [
	'functions' => [
		[
			'type' => 'script',
			'script' => "pow((doc['image_quality'].empty ? 0.0 : doc['image_quality']) + 1, $weight)"
		],
	],
];
