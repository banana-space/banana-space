<?php

namespace CirrusSearch\Job;

use CirrusSearch\Updater;
use JobQueueGroup;
use Title;

/**
 * Performs the appropriate updates to Elasticsearch after a LinksUpdate is
 * completed.  The page itself is updated first then a second copy of this job
 * is queued to update linked articles if any links change.  The job can be
 * 'prioritized' via the 'prioritize' parameter which will switch it to a
 * different queue then the non-prioritized jobs.  Prioritized jobs will never
 * be deduplicated with non-prioritized jobs which is good because we can't
 * control which job is removed during deduplication.  In our case it'd only be
 * ok to remove the non-prioritized version.
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
class LinksUpdate extends CirrusTitleJob {
	public function __construct( Title $title, array $params ) {
		parent::__construct( $title, $params );

		if ( $this->isPrioritized() ) {
			$this->command .= 'Prioritized';
		}
		// Note that we have to keep the prioritized param or else when the job
		// is loaded it'll load under a different name/command/type which would
		// be confusing.
	}

	/**
	 * @return bool
	 */
	protected function doJob() {
		$updater = Updater::build( $this->getSearchConfig(), $this->params['cluster'] ?? null );
		$res = $updater->updateFromTitle( $this->title );
		if ( $res === false ) {
			// Couldn't update. Bail early and retry rather than adding an
			// IncomingLinkCount job that will produce the wrong answer.
			return $res;
		}

		// Queue IncomingLinkCount jobs when pages are newly linked or unlinked
		$titleKeys = array_merge( $this->params[ 'addedLinks' ],
			$this->params[ 'removedLinks' ] );
		$refreshInterval = $this->getSearchConfig()->get( 'CirrusSearchRefreshInterval' );
		foreach ( $titleKeys as $titleKey ) {
			$title = Title::newFromDBkey( $titleKey );
			if ( !$title ) {
				continue;
			}
			// If possible, delay the job execution by a few seconds so Elasticsearch
			// can refresh to contain what we just sent it.  The delay should be long
			// enough for Elasticsearch to complete the refresh cycle, which normally
			// takes wgCirrusSearchRefreshInterval seconds but we double it and add
			// one just in case.
			$delay = 2 * $refreshInterval + 1;
			$linkCount = new IncomingLinkCount( $title, [
				'cluster' => $this->params['cluster'],
			] + self::buildJobDelayOptions( IncomingLinkCount::class, $delay ) );
			JobQueueGroup::singleton()->push( $linkCount );
		}

		// All done
		return $res;
	}

	/**
	 * @return bool Is this job prioritized?
	 */
	public function isPrioritized() {
		return isset( $this->params[ 'prioritize' ] ) && $this->params[ 'prioritize' ];
	}
}
