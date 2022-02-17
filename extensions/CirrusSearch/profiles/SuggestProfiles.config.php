<?php

/**
 * CirrusSearch - List of profiles for search as you type suggestions
 * (Completion suggester)
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

/**
 *
 * See CirrusSearch\BuildDocument\SuggestBuilder and CirrusSearch\Searcher
 * See also: https://www.elastic.co/guide/en/elasticsearch/reference/current/search-suggesters-completion.html
 *
 * If you add new profiles you may want to add the corresponding i18n messages with the following name:
 * cirrussearch-completion-profile-profilename
 */
return [
	// Strict profile (no accent squasing)
	'strict' => [
		'i18n_msg' => 'cirrussearch-completion-profile-strict',
		'fst' => [
			'plain-strict' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'discount' => 1.0,
				'fetch_limit_factor' => 2,
			],
		],
	],
	// Accent squashing and stopwords filtering
	'normal' => [
		'i18n_msg' => 'cirrussearch-completion-profile-normal',
		'fst' => [
			'plain-normal' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'discount' => 1.0,
				'fetch_limit_factor' => 2,
			],
			'plain-stop-normal' => [
				'field' => 'suggest-stop',
				'min_query_len' => 0,
				'discount' => 0.001,
				'fetch_limit_factor' => 2,
			],
		],
	],
	// Normal with subphrases
	'normal-subphrases' => [
		'i18n_msg' => 'cirrussearch-completion-profile-normal-subphrases',
		'fst' => [
			'plain-normal' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'discount' => 1.0,
				'fetch_limit_factor' => 2,
			],
			'plain-stop-normal' => [
				'field' => 'suggest-stop',
				'min_query_len' => 0,
				'discount' => 0.001,
				'fetch_limit_factor' => 2,
			],
			'plain-subphrase' => [
				'field' => 'suggest-subphrases',
				'min_query_len' => 0,
				'discount' => 0.0001,
				'fetch_limit_factor' => 2,
			],
		],
	],
	// Default profile
	'fuzzy' => [
		'i18n_msg' => 'cirrussearch-completion-profile-fuzzy',
		'fst' => [
			// Defines the list of suggest queries to run in the same request.
			// key is the name of the suggestion request
			'plain' => [
				// Field to request
				'field' => 'suggest',
				// Fire the request only if the user query has min_query_len chars.
				// See max_query_len to limit on max.
				'min_query_len' => 0,
				// Discount result scores for this request
				// Useful to discount fuzzy request results
				'discount' => 1.0,
				// Fetch more results than the limit
				// It's possible to have the same page multiple times
				// (title and redirect suggestion).
				// Requesting more than the limit helps to display the correct number
				// of suggestions
				'fetch_limit_factor' => 2,
			],
			'plain_stop' => [
				'field' => 'suggest-stop',
				'min_query_len' => 0,
				'discount' => 0.001,
				'fetch_limit_factor' => 2,
			],
			// Fuzzy query for query length (3 to 4) with prefix len 1
			'plain_fuzzy_2' => [
				'field' => 'suggest',
				'min_query_len' => 3,
				'max_query_len' => 4,
				'discount' => 0.000001,
				'fetch_limit_factor' => 2,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			],
			'plain_stop_fuzzy_2' => [
				'field' => 'suggest-stop',
				'min_query_len' => 3,
				'max_query_len' => 4,
				'discount' => 0.0000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 2,
					'unicode_aware' => true,
				]
			],
			// Fuzzy query for query length > 5 with prefix len 0
			'plain_fuzzy_1' => [
				'field' => 'suggest',
				'min_query_len' => 5,
				'discount' => 0.000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			],
			'plain_stop_fuzzy_1' => [
				'field' => 'suggest-stop',
				'min_query_len' => 5,
				'discount' => 0.0000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			]
		],
	],
	// Experimental profile, we fire a single suggest query per field
	// problem is that a fuzzy match on plain will certainly
	// win against a non fuzzy match on plain_stop...
	'fast-fuzzy' => [
		'i18n_msg' => 'cirrussearch-completion-profile-fast-fuzzy',
		'fst' => [
			'plain' => [
				'field' => 'suggest',
				'min_query_len' => 0,
				'discount' => 1.0,
				'fetch_limit_factor' => 2,
				// Fuzzy is fired after 3 chars
				// with auto edit distance based input length
				// (see https://www.elastic.co/guide/en/elasticsearch/reference/current/common-options.html#fuzziness )
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'unicode_aware' => true,
				]
			],
			'plain_stop' => [
				'field' => 'suggest-stop',
				'min_query_len' => 0,
				'discount' => 0.01,
				'fetch_limit_factor' => 2,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'unicode_aware' => true,
				]
			],
		],
	],
	'fuzzy-subphrases' => [
		'i18n_msg' => 'cirrussearch-completion-profile-fuzzy-subphrases',
		'fst' => [
			// Defines the list of suggest queries to run in the same request.
			// key is the name of the suggestion request
			'plain' => [
				// Field to request
				'field' => 'suggest',
				// Fire the request only if the user query has min_query_len chars.
				// See max_query_len to limit on max.
				'min_query_len' => 0,
				// Discount result scores for this request
				// Useful to discount fuzzy request results
				'discount' => 1.0,
				// Fetch more results than the limit
				// It's possible to have the same page multiple times
				// (title and redirect suggestion).
				// Requesting more than the limit helps to display the correct number
				// of suggestions
				'fetch_limit_factor' => 2,
			],
			'plain_stop' => [
				'field' => 'suggest-stop',
				'min_query_len' => 0,
				'discount' => 0.001,
				'fetch_limit_factor' => 2,
			],
			'subphrases' => [
				'field' => 'suggest-subphrases',
				'min_query_len' => 0,
				'discount' => 0.0001,
				'fetch_limit_factor' => 2,
			],
			// Fuzzy query for query length (3 to 4) with prefix len 1
			'plain_fuzzy_2' => [
				'field' => 'suggest',
				'min_query_len' => 3,
				'max_query_len' => 4,
				'discount' => 0.000001,
				'fetch_limit_factor' => 2,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			],
			'plain_stop_fuzzy_2' => [
				'field' => 'suggest-stop',
				'min_query_len' => 3,
				'max_query_len' => 4,
				'discount' => 0.0000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 2,
					'unicode_aware' => true,
				]
			],
			// Fuzzy query for query length > 5 with prefix len 0
			'plain_fuzzy_1' => [
				'field' => 'suggest',
				'min_query_len' => 5,
				'discount' => 0.000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			],
			'plain_stop_fuzzy_1' => [
				'field' => 'suggest-stop',
				'min_query_len' => 5,
				'discount' => 0.0000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			],
			'subphrases_fuzzy_1' => [
				'field' => 'suggest-subphrases',
				'min_query_len' => 5,
				'discount' => 0.00000001,
				'fetch_limit_factor' => 1,
				'fuzzy' => [
					'fuzziness' => 'AUTO',
					'prefix_length' => 1,
					'unicode_aware' => true,
				]
			]
		],
	],
	// Special profile to fallback to prefix search
	'classic' => [
		'i18n_msg' => 'cirrussearch-completion-profile-classic',
		'fst' => [],
	],
];
