<?php

namespace Flow\Search\Iterators;

use Flow\DbFactory;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Repository\RootPostLoader;
use stdClass;

class TopicIterator extends AbstractIterator {
	/**
	 * @var PostRevision
	 */
	protected $previous;

	/**
	 * @var RootPostLoader
	 */
	protected $rootPostLoader;

	/**
	 * @var bool
	 */
	public $orderByUUID = false;

	/**
	 * @param DbFactory $dbFactory
	 * @param RootPostLoader $rootPostLoader
	 */
	public function __construct( DbFactory $dbFactory, RootPostLoader $rootPostLoader ) {
		parent::__construct( $dbFactory );
		$this->rootPostLoader = $rootPostLoader;
	}

	/**
	 * Define where to start iterating (inclusive)
	 *
	 * We'll be querying the workflow table instead of the revisions table.
	 * Because it's possible to request only a couple of revisions (in between
	 * certain ids), we'll need to override the parent buildQueryConditions
	 * method to also work on the workflow table.
	 * A topic workflow is updated with a workflow_last_update_timestamp for
	 * every change made in the topic. Our UUIDs are sequential & time-based,
	 * so we can just query for workflows with a timestamp higher than the
	 * timestamp derived from the starting UUID and lower than the end UUID.
	 *
	 * @param UUID|null $revId
	 */
	public function setFrom( UUID $revId = null ) {
		$this->results = null;

		unset( $this->conditions[0] );
		if ( $revId !== null ) {
			$this->conditions[0] = 'workflow_last_update_timestamp >= ' . $this->dbr->addQuotes( $revId->getBinary() );
		}
	}

	/**
	 * Define where to stop iterating (exclusive)
	 *
	 * We'll be querying the workflow table instead of the revisions table.
	 * Because it's possible to request only a couple of revisions (in between
	 * certain ids), we'll need to override the parent buildQueryConditions
	 * method to also work on the workflow table.
	 * A topic workflow is updated with a workflow_last_update_timestamp for
	 * every change made in the topic. Our UUIDs are sequential & time-based,
	 * so we can just query for workflows with a timestamp higher than the
	 * timestamp derived from the starting UUID and lower than the end UUID.
	 *
	 * @param UUID|null $revId
	 */
	public function setTo( UUID $revId = null ) {
		$this->results = null;

		unset( $this->conditions[1] );
		if ( $revId !== null ) {
			$this->conditions[1] = 'workflow_last_update_timestamp < ' . $this->dbr->addQuotes( $revId->getBinary() );
		}
	}

	/**
	 * Instead of querying for revisions (which is what we actually need), we'll
	 * just query the workflow table, which will save us some complicated joins.
	 * The workflow_id for a topic title (aka root post) is the same as its
	 * collection id, so we can pass that to the root post loader and *poof*, we
	 * have our revisions!
	 *
	 * @inheritDoc
	 */
	protected function query() {
		if ( $this->orderByUUID ) {
			$order = 'workflow_id ASC';
		} else {
			$order = 'workflow_last_update_timestamp ASC';
		}
		return $this->dbr->select(
			[ 'flow_workflow' ],
			// for root post (topic title), workflow_id is the same as its rev_type_id
			[ 'workflow_id', 'workflow_last_update_timestamp' ],
			[
				'workflow_type' => 'topic'
			] + $this->conditions,
			__METHOD__,
			[
				'ORDER BY' => $order,
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function transform( stdClass $row ) {
		$root = UUID::create( $row->workflow_id );

		// we need to fetch all data via rootloader because we'll want children
		// to be populated
		return $this->rootPostLoader->get( $root );
	}
}
