<?php

/**
 * CirrusSearch - List of Similarity profiles
 * Configure the Similarity function used for scoring
 * see https://www.elastic.co/guide/en/elasticsearch/reference/current/index-modules-similarity.html
 *
 * A reindex is required when changes are made to the similarity configuration.
 * NOTE the parameters that do not affect indexed values can be tuned manually
 * in the index settings but it is possible only on a closed index.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */

return [
	// BM25 with default values for k and a for all fields
	'bm25_with_defaults' => [
		'similarity' => [
			'bm25' => [
				'type' => 'BM25'
			]
		],
		'fields' => [
			'__default__' => 'bm25',
		]
	],
	// Example with "randomly" tuned values
	// (do not use)
	'bm25_tuned' => [
		'similarity' => [
			'title' => [
				'type' => 'BM25',
				'k1' => 1.23,
				'b' => 0.75,
			],
			'opening' => [
				'type' => 'BM25',
				'k1' => 1.22,
				'b' => 0.75,
			],
			'arrays' => [
				'type' => 'BM25',
				'k1' => 1.1,
				'b' => 0.3,
			],
			'text' => [
				'type' => 'BM25',
				'k1' => 1.3,
				'b' => 0.80,
			],
		],
		'fields' => [
			'__default__' => 'text',
			// Field specific config
			'opening_text' => 'opening',
			'category' => 'arrays',
			'title' => 'title',
			'heading' => 'arrays',
			'suggest' => 'title',
		],
	],
	'bm25_browser_tests' => [
		'similarity' => [
			// Lower norms impact, cirrustestwiki is not well
			// balanced with many small docs without opening nor
			// heading resulting in very low avg field length
			// on such fields
			'lower_norms' => [
				'type' => 'BM25',
				'k1' => 1.2,
				'b' => 0.3,
			],
			'with_defaults' => [
				'type' => 'BM25',
			]
		],
		'fields' => [
			'__default__' => 'lower_norms',
		],
	],
	// Default BM25 settings used by wmf sites
	'wmf_defaults' => [
		'similarity' => [
			'default' => [
				// Although not referenced, this is necessary
				// to disable coord
				'type' => 'BM25',
			],
			'arrays' => [
				'type' => 'BM25',
				'k1' => 1.2,
				'b' => 0.3,
			],
		],
		'fields' => [
			'__default__' => 'BM25',
			'category' => 'arrays',
			'heading' => 'arrays',
			'redirect.title' => 'arrays',
			'suggest' => 'arrays',
		],
	],
];
