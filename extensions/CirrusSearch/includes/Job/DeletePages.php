<?php

namespace CirrusSearch\Job;

use CirrusSearch\Updater;
use Title;

/**
 * Job wrapper around Updater::deletePages.  If indexType parameter is
 * specified then only deletes from that type of index.
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
class DeletePages extends CirrusTitleJob {
	public function __construct( Title $title, array $params ) {
		parent::__construct( $title, $params );

		// This is one of the cheapest jobs we have. Plus I'm reasonably
		// paranoid about deletions so I'd rather delete things extra times
		// if something actually requested it.
		$this->removeDuplicates = false;
	}

	/**
	 * @return bool
	 */
	protected function doJob() {
		$updater = Updater::build( $this->getSearchConfig(), $this->params['cluster'] ?? null );
		$indexType = $this->params[ 'indexType' ] ?? null;
		$updater->deletePages( [ $this->title ], [ $this->params['docId'] ], $indexType );

		if ( $this->getSearchConfig()->get( 'CirrusSearchIndexDeletes' ) ) {
			$updater->archivePages( [
				[
					'title' => $this->title,
					'page' => $this->params['docId'],
				],
			] );
		}

		return true;
	}
}
