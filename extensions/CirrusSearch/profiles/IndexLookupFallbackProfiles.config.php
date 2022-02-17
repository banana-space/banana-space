<?php

return [
	'glent' => [
		'suggestion_field' => 'dym',
		'metric_fields' => [ 'method' ],
		'index' => 'glent_production',
		'params' => [
			'function_score.query.bool.filter.0.match.query' => 'query',
			'function_score.query.bool.filter.1.match.wiki' => 'wiki',
			'function_score.query.bool.filter.2.terms.method' => 'params:methods',
		],
		'query' => [
			'function_score' => [
				'query' => [
					'bool' => [
						'filter' => [
							[
								'match' => [
									'query' => 'PLACEHOLDER',
								]
							],
							[
								'match' => [
									'wiki' => 'PLACEHOLDER',
								]
							],
							[
								'terms' => [
									'method' => 'PLACEHOLDER'
								]
							]
						],
					]
				],
				"functions" => [
					[
						'field_value_factor' => [
							'field' => 'suggestion_score',
							'missing' => 0,
						]
					]
				],
				'boost_mode' => 'replace',
			]
		],
		'index_template' => [
			'template_name' => 'glent',
			'language_code' => 'int',
			'index_patterns' => [ 'glent_*' ],
			'version' => 1,
			'settings' => [
				'number_of_shards' => 1,
				'auto_expand_replicas' => '0-5',
			],
			'mappings' => [
				'_doc' => [
					'_source' => [
						'enabled' => false,
					],
					'properties' => [
						'suggestion_score' => [
							'type' => 'float',
							'index' => false,
						],
						'wiki' => [
							'type' => 'keyword',
						],
						'method' => [
							'type' => 'keyword',
							'store' => true,
						],
						'dym' => [
							'type' => 'text',
							'index' => false,
							'store' => true,
						],
						'query' => [
							'type' => 'text',
							'index' => true,
							'analyzer' => 'near_match',
							'search_analyzer' => 'near_match',
							'index_options' => 'docs'
						]
					]
				]
			]
		]
	]
];
