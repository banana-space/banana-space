<?php

$wgCirrusSearchSimilarityProfiles['relforge'] = [
	'similarity' => [
		'title' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'title_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'opening_text' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'opening_text_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'auxiliary_text' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'auxiliary_text_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'category' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'category_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'redirect' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'redirect_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'heading' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'heading_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'suggest' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.3,
		],
		'text' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'text_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'all' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
		'all_plain' => [
			'type' => 'BM25',
			'k1' => 1.2,
			'b' => 0.75,
		],
	],
	'fields' => [
		'__default__' => 'text',
		// Field specific config
		'opening_text' => 'opening_text',
		'opening_text.plain' => 'opening_text_plain',
		'category' => 'category',
		'category.plain' => 'category_plain',
		'title' => 'title',
		'title.plain' => 'title_plain',
		'redirect' => 'redirect',
		'redirect.plain' => 'redirect_plain',
		'text' => 'text',
		'text.plain' => 'text_plain',
		'all' => 'all',
		'all.plain' => 'all_plain',
		'heading' => 'heading',
		'heading.plain' => 'heading_plain',
		'suggest' => 'suggest',
	],
];
