<?php

namespace CirrusSearch;

/**
 * CirrusSearch - List of profiles for function score rescores.
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
 * List of rescore profiles.
 *
 * NOTE: writing a new custom profile is a complex task, you can use
 * &cirrusDumpResult&cirrusExplain query params to dump score information at
 * runtime.
 *
 */

/**
 * Transient var used to position the phrase rescore
 */
$phraseRescorePlaceHolder = [
	'window' => 512,
	'window_size_override' => 'CirrusSearchPhraseRescoreWindowSize',
	'rescore_query_weight' => 10,
	'rescore_query_weight_override' => 'CirrusSearchPhraseRescoreBoost',
	'query_weight' => 1.0,
	'type' => 'phrase',
	// defaults: 'score_mode' => 'total'
];

return [
	// Default profile which uses an all in one function score chain
	'classic' => [
		// i18n description for this profile.
		'i18n_msg' => 'cirrussearch-qi-profile-classic',
		// use 'all' if this rescore profile supports all namespaces
		// or an array of integer to limit
		'supported_namespaces' => 'all',

		// If the profile does not support all namespaces
		// you must provide a fallback profile that supports
		// all. It will be use with queries applied to namespace
		// not supported by this profile :
		// 'fallback_profile' => 'profile',

		// List of rescores
		// https://www.elastic.co/guide/en/elasticsearch/reference/current/search-request-rescore.html
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				// the rescore window size
				'window' => 8192,

				// The window size can be overridden by a config a value if set
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',

				// relative importance of the original query
				'query_weight' => 1.0,

				// relative importance of the rescore query
				'rescore_query_weight' => 1.0,

				// how to combine query and rescore scores
				// can be total, multiply, avg, max or min
				'score_mode' => 'multiply',

				// type of the rescore query
				// (only supports function_score for now)
				'type' => 'function_score',

				// name of the function score chains, must be
				// defined in $wgCirrusSearchRescoreFunctionScoreChains
				'function_chain' => 'classic_allinone_chain'
			]
		]
	],

	// Default rescore without boostlinks
	'classic_noboostlinks' => [
		'i18n_msg' => 'cirrussearch-qi-profile-classic-noboostlinks',
		'supported_namespaces' => 'all',
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'multiply',
				'type' => 'function_score',
				'function_chain' => 'optional_chain'
			],
		]
	],

	// Useful to debug primary lucene score
	'empty' => [
		'i18n_msg' => 'cirrussearch-qi-profile-empty',
		'supported_namespaces' => 'all',
		'rescore' => [],
	],

	// inclinks applied as a weighted sum
	'wsum_inclinks' => [
		'i18n_msg' => 'cirrussearch-qi-profile-wsum-inclinks',
		'supported_namespaces' => 'all',
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'total',
				'type' => 'function_score',
				'function_chain' => 'wsum_inclinks'
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
	],

	// inclinks + pageviews applied as weighted sum
	// NOTE: requires the custom field popularity_score
	'wsum_inclinks_pv' => [
		'i18n_msg' => 'cirrussearch-qi-profile-wsum-inclinks-pv',
		'supported_namespaces' => 'content',
		'fallback_profile' => 'wsum_inclinks',
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'total',
				'type' => 'function_score',
				'function_chain' => 'wsum_inclinks_pv'
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
	],

	// inclinks + pageviews applied as weighted sum with
	// a very high weight on pageviews, for returning the
	// most popular matching pages
	'popular_inclinks_pv' => [
		'supported_namespaces' => 'content',
		'fallback_profile' => 'popular_inclinks',
		'i18n_msg' => 'cirrussearch-qi-profile-popular-pv',
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'total',
				'type' => 'function_score',
				'function_chain' => 'wsum_inclinks_pv+'
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
	],

	'popular_inclinks' => [
		'supported_namespaces' => 'all',
		'i18n_msg' => 'cirrussearch-qi-profile-popular-inclinks',
		'rescore' => [
			$phraseRescorePlaceHolder,
			[
				'window' => 8192,
				'window_size_override' => 'CirrusSearchFunctionRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 100.0,
				'score_mode' => 'total',
				'type' => 'function_score',
				'function_chain' => 'wsum_inclinks'
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
	],

];
