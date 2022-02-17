<?php

$wgCirrusSearchFullTextQueryBuilderProfiles['relforge'] = [
	'builder_class' => \CirrusSearch\Query\FullTextSimpleMatchQueryBuilder::class,
	'settings' => [
		'default_min_should_match' => '1',
		'default_query_type' => 'most_fields',
		'default_stem_weight' => 3.0,
		'fields' => [
			'title' => 0.3,
			'redirect.title' => [
				'boost' => 0.27,
				'in_dismax' => 'redirects_or_shingles'
			],
			'suggest' => [
				'is_plain' => true,
				'boost' => 0.20,
				'in_dismax' => 'redirects_or_shingles',
			],
			'category' => 0.05,
			'heading' => 0.05,
			'text' => [
				'boost' => 0.6,
				'in_dismax' => 'text_and_opening_text',
			],
			'opening_text' => [
				'boost' => 0.5,
				'in_dismax' => 'text_and_opening_text',
			],
			'auxiliary_text' => 0.05,
			'file_text' => 0.5,
		],
		'phrase_rescore_fields' => [
			// very low (don't forget it's multiplied by 10 by default)
			'all' => 0.03,
			'all.plain' => 0.05,
		],
	]
];
