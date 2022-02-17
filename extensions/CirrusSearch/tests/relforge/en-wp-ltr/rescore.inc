<?php

$wgCirrusSearchRescoreProfiles['relforge'] = [
	'supported_namespaces' => 'content',
	'unsupported_syntax' => [ 'full_text_querystring', 'query_string', 'filter_only' ],
	'fallback_profile' => 'wsum_inclinks',
	'rescore' => [
		[
			'window' => isset( $_GET['cirrusSearchRescoreWindow'] )
				? intval( $_GET['cirrusSearchRescoreWindow'] )
				: 1024,
			'query_weight' => 1.0,
			'rescore_query_weight' => 10000.0,
			'score_mode' => 'total',
			'type' => 'ltr',
			'model' => 'enwiki_100t_v1',
		]
	],
];
