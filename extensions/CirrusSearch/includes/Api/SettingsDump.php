<?php

namespace CirrusSearch\Api;

use CirrusSearch\Connection;
use CirrusSearch\SearchConfig;

/**
 * Dumps CirrusSearch mappings for easy viewing.
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
class SettingsDump extends \ApiBase {
	use ApiTrait;

	public function execute() {
		$conn = $this->getCirrusConnection();
		$indexPrefix = $this->getSearchConfig()->get( SearchConfig::INDEX_BASE_NAME );
		foreach ( $conn->getAllIndexTypes() as $index ) {
			$this->getResult()->addValue(
				[ $index, 'page' ],
				'index',
				$conn->getIndex( $indexPrefix, $index )->getSettings()->get()
			);
		}
		if ( $this->getSearchConfig()->isCompletionSuggesterEnabled() ) {
			$index = $conn->getIndex( $indexPrefix, Connection::TITLE_SUGGEST_TYPE );
			if ( $index->exists() ) {
				$mapping = $index->getSettings()->get();
				$this->getResult()->addValue( [ Connection::TITLE_SUGGEST_TYPE, Connection::TITLE_SUGGEST_TYPE ], 'index', $mapping );
			}
		}
	}

	public function getAllowedParams() {
		return [];
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=cirrus-settings-dump' =>
				'apihelp-cirrus-settings-dump-example'
		];
	}

}
