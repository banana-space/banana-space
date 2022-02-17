<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\SearchConfig;
use MediaWiki\MediaWikiServices;

/**
 * Builds elasticsearch mapping configuration arrays for the suggester index.
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
class SuggesterMappingConfigBuilder {
	/**
	 * Version number for the core analysis. Increment the major
	 * version when the analysis changes in an incompatible way,
	 * and change the minor version when it changes but isn't
	 * incompatible
	 */
	const VERSION = '3.0';

	/** @var SearchConfig */
	private $config;

	/**
	 * @param SearchConfig|null $config
	 */
	public function __construct( SearchConfig $config = null ) {
		if ( $config === null ) {
			$config =
				MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'CirrusSearch' );
		}
		$this->config = $config;
	}

	/**
	 * @return array[]
	 */
	public function buildConfig() {
		$suggest = [
			'dynamic' => false,
			'_source' => [ 'enabled' => true ],
			'properties' => [
				'batch_id' => [ 'type' => 'long' ],
				'source_doc_id' => [ 'type' => 'keyword' ],
				// Sadly we can't reuse the same input
				// into multiple fields, it would help
				// us to save space since we now have
				// to store the source.
				'suggest' => [
					'type' => 'completion',
					'analyzer' => 'plain',
					'search_analyzer' => 'plain_search',
					'max_input_length' => 255,
				],
				'suggest-stop' => [
					'type' => 'completion',
					'analyzer' => 'stop_analyzer',
					'search_analyzer' => 'stop_analyzer_search',
					'preserve_separators' => false,
					'preserve_position_increments' => false,
					'max_input_length' => 255,
				],
			]
		];
		if ( $this->config->getElement( 'CirrusSearchCompletionSuggesterSubphrases', 'build' ) ) {
			$suggest['properties']['suggest-subphrases'] = [
				'type' => 'completion',
				'analyzer' => 'subphrases',
				'search_analyzer' => 'subphrases_search',
				'max_input_length' => 255,
			];

		}
		return [ \CirrusSearch\Connection::TITLE_SUGGEST_TYPE_NAME => $suggest ];
	}

}
