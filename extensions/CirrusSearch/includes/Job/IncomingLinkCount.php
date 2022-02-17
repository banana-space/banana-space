<?php

namespace CirrusSearch\Job;

use CirrusSearch\Updater;
use Title;

/**
 * Updates link counts to page when it is newly linked or unlinked.
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
class IncomingLinkCount extends CirrusTitleJob {
	public function __construct( Title $title, array $params ) {
		parent::__construct( $title, $params );
	}

	/**
	 * @return bool
	 */
	protected function doJob() {
		// Load the titles and filter out any that no longer exist.
		$updater = Updater::build( $this->getSearchConfig(), $this->params['cluster'] ?? null );
		// We're intentionally throwing out whether or not this job succeeds.
		// We're logging it but we're not retrying.
		$updater->updateLinkedArticles( [ $this->getTitle() ] );
		return true;
	}
}
