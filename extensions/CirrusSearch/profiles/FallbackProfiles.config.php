<?php

/**
 * CirrusSearch - List of profiles for "fallback" methods
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
	'phrase_suggest_and_language_detection' => [
		'methods' => [
			'phrase-default' => [
				'class' => \CirrusSearch\Fallbacks\PhraseSuggestFallbackMethod::class,
				'params' => [
					'profile' => 'default',
				]
			],
			'langdetect' => [
				'class' => \CirrusSearch\Fallbacks\LangDetectFallbackMethod::class,
				'params' => []
			],
		]
	],
	'phrase_suggest' => [
		'methods' => [
			'phrase-default' => [
				'class' => \CirrusSearch\Fallbacks\PhraseSuggestFallbackMethod::class,
				'params' => [
					'profile' => 'default',
				]
			],
		]
	],
	'phrase_suggest_strict' => [
		'methods' => [
			'phrase-strict' => [
				'class' => \CirrusSearch\Fallbacks\PhraseSuggestFallbackMethod::class,
				'params' => [
					'profile' => 'strict',
				]
			],
		]
	],
	'phrase_suggest_glentM0_and_langdetect' => [
		'methods' => [
			'glent-m0run' => [
				'class' => \CirrusSearch\Fallbacks\IndexLookupFallbackMethod::class,
				'params' => [
					'profile' => 'glent',
					'profile_params' => [
						'methods' => [ 'm0run' ],
					]
				]
			],
			'phrase-default' => [
				'class' => \CirrusSearch\Fallbacks\PhraseSuggestFallbackMethod::class,
				'params' => [
					'profile' => 'default',
				]
			],
			'langdetect' => [
				'class' => \CirrusSearch\Fallbacks\LangDetectFallbackMethod::class,
				'params' => []
			],
		]
	],
	'none' => []
];
