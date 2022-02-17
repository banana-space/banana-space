<?php

namespace CirrusSearch;

use FormlessAction;
use MediaWiki\MediaWikiServices;

/**
 * action=cirrusDump handler.  Dumps contents of Elasticsearch indexes for the
 * page.
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
class Dump extends FormlessAction {
	public function onView() {
		// Disable regular results
		$this->getOutput()->disable();

		$response = $this->getRequest()->response();
		$response->header( 'Content-type: application/json; charset=UTF-8' );

		$config = MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'CirrusSearch' );
		/** @phan-suppress-next-line PhanTypeMismatchArgument $config is actually a SearchConfig */
		$conn = new Connection( $config );
		/** @phan-suppress-next-line PhanTypeMismatchArgument $config is actually a SearchConfig */
		$searcher = new Searcher( $conn, 0, 0, $config, [], $this->getUser() );

		/** @phan-suppress-next-line PhanUndeclaredMethod Phan doesn't know $config is a SearchConfig */
		$docId = $config->makeId( $this->getTitle()->getArticleID() );
		$esSources = $searcher->get( [ $docId ], true );
		if ( !$esSources->isOK() ) {
			// Exception has been logged
			echo '{}';
			return null;
		}
		$esSources = $esSources->getValue();

		$result = [];
		foreach ( $esSources as $esSource ) {
			$result[] = [
				'_index' => $esSource->getIndex(),
				'_type' => $esSource->getType(),
				'_id' => $esSource->getId(),
				'_version' => $esSource->getVersion(),
				'_source' => $esSource->getData(),
			];
		}
		echo json_encode( $result );
		return null;
	}

	/**
	 * @return string
	 */
	public function getName() {
		return 'cirrusdump';
	}

	/**
	 * @return bool
	 */
	public function requiresWrite() {
		return false;
	}

	/**
	 * @return bool
	 */
	public function requiresUnblock() {
		return false;
	}
}
