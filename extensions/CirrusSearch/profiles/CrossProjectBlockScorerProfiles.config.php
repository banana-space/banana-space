<?php

/**
 * CirrusSearch - List of FullTextQueryBuilderProfiles used to generate an elasticsearch
 * query by parsing user input.
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
 * Profiles to control ordering of blocks of CrossProject searchresults.
 *
 * key is the profile name used in wgCirrusSearchCrossProjectOrder
 * value is array where
 * - 'type' the scorer to use (static, recall, random)
 * - settings is scorer specific config
 */
return [
	// static ordering, scores are provided in the 'settings' key
	// with a score (value) per 'wiki prefix (key)
	'static' => [
		'type' => 'static',
	],

	// ordered by recall (total hits)
	'recall' => [
		'type' => 'recall',
	],

	// randomly ordered
	'random' => [
		'type' => 'random',
	],

	// Example profile for WMF english wikipedia
	// - wiktionary always first
	// - wikibooks always last
	// - others are ordered by recall
	// wikt will be : (1 * 1) + (0.01 * log(total_hits + 2))
	// wikibooks : (1 * 0.01) + (0.01 * log(total_hits + 2))
	// others : (1 * 0.1) + (0.01 * log(total_hits + 2))
	'wmf_enwiki' => [
		'type' => 'composite',
		'settings' => [
			'recall' => [
				'weight' => 0.01,
			],
			'static' => [
				'weight' => 1,
				'settings' => [
					'__default__' => 0.1,
					'wikt' => 1,
					'b' => 0.01,
				],
			],
		],
	],
];
