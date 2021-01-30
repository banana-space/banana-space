<?php

namespace Flow\Import;

use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use ReflectionProperty;

class TopicImportState {
	/**
	 * @var PageImportState
	 */
	public $parent;

	/**
	 * @var Workflow
	 */
	public $topicWorkflow;

	/**
	 * @var PostRevision
	 */
	public $topicTitle;

	/**
	 * @var string
	 */
	protected $lastUpdated;

	/**
	 * @var ReflectionProperty
	 */
	private $workflowUpdatedProperty;

	public function __construct( PageImportState $parent, Workflow $topicWorkflow, PostRevision $topicTitle ) {
		$this->parent = $parent;
		$this->topicWorkflow = $topicWorkflow;
		$this->topicTitle = $topicTitle;

		$this->workflowUpdatedProperty = new ReflectionProperty( Workflow::class, 'lastUpdated' );
		$this->workflowUpdatedProperty->setAccessible( true );

		$this->lastUpdated = '';
		$this->recordUpdateTime( $topicWorkflow->getId() );
	}

	public function getMetadata() {
		return [
			'workflow' => $this->topicWorkflow,
			'board-workflow' => $this->parent->boardWorkflow,
			'topic-title' => $this->topicTitle,
		];
	}

	/**
	 * Notify the state about a modification action at a given time.
	 *
	 * @param UUID $uuid UUID of the modification revision.
	 */
	public function recordUpdateTime( UUID $uuid ) {
		$timestamp = $uuid->getTimestamp();
		$timestamp = wfTimestamp( TS_MW, $timestamp );

		if ( $timestamp > $this->lastUpdated ) {
			$this->lastUpdated = $timestamp;
		}
	}

	/**
	 * Saves the last updated timestamp based on calls to recordUpdateTime
	 * XXX: Kind of icky; reaching through the parent and doing a second put().
	 */
	public function commitLastUpdated() {
		$this->workflowUpdatedProperty->setValue(
			$this->topicWorkflow,
			$this->lastUpdated
		);

		$this->parent->put( $this->topicWorkflow, $this->getMetadata() );
	}
}
