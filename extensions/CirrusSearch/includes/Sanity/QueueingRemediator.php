<?php

namespace CirrusSearch\Sanity;

use CirrusSearch\Job\DeletePages;
use CirrusSearch\Job\LinksUpdate;
use JobQueueGroup;
use Title;
use WikiPage;

/**
 * Remediator implementation that queues jobs to fix the index.
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

class QueueingRemediator implements Remediator {
	/**
	 * @var string|null
	 */
	protected $cluster;

	/**
	 * @var JobQueueGroup
	 */
	private $jobQueue;

	/**
	 * @param string|null $cluster The name of the cluster to update,
	 *  or null to update all clusters.
	 * @param JobQueueGroup|null $jobQueueGroup
	 */
	public function __construct( $cluster, JobQueueGroup $jobQueueGroup = null ) {
		$this->cluster = $cluster;
		$this->jobQueue = $jobQueueGroup ?: JobQueueGroup::singleton();
	}

	/**
	 * @inheritDoc
	 */
	public function redirectInIndex( WikiPage $page ) {
		$this->pushLinksUpdateJob( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function pageNotInIndex( WikiPage $page ) {
		$this->pushLinksUpdateJob( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function ghostPageInIndex( $docId, Title $title ) {
		$this->jobQueue->push(
			new DeletePages( $title, [
				'docId' => $docId,
				'cluster' => $this->cluster,
			] )
		);
	}

	/**
	 * @inheritDoc
	 */
	public function pageInWrongIndex( $docId, WikiPage $page, $wrongIndex ) {
		$this->jobQueue->push(
			new DeletePages( $page->getTitle(), [
				'indexType' => $wrongIndex,
				'docId' => $docId,
				'cluster' => $this->cluster,
			] )
		);
		$this->pushLinksUpdateJob( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function oldVersionInIndex( $docId, WikiPage $page, $index ) {
		$this->pushLinksUpdateJob( $page );
	}

	/**
	 * @inheritDoc
	 */
	public function oldDocument( WikiPage $page ) {
		$this->pushLinksUpdateJob( $page );
	}

	private function pushLinksUpdateJob( WikiPage $page ) {
		$this->jobQueue->push(
			new LinksUpdate( $page->getTitle(), [
				'addedLinks' => [],
				'removedLinks' => [],
				'cluster' => $this->cluster,
			] )
		);
	}
}
