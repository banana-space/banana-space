<?php

namespace Flow\Tests\Mock;

use Flow\Import\IImportSummary;
use Flow\Import\IImportTopic;
use Flow\Import\IObjectRevision;

class MockImportTopic extends MockImportPost implements IImportTopic {
	/**
	 * @var IImportSummary
	 */
	protected $summary;

	/**
	 * @param IImportSummary|null $summary
	 * @param IObjectRevision[] $revisions
	 * @param IImportPost[] $replies
	 */
	public function __construct( ?IImportSummary $summary, array $revisions, array $replies ) {
		parent::__construct( $revisions, $replies );
		$this->summary = $summary;
	}

	/**
	 * @inheritDoc
	 */
	public function getTopicSummary() {
		return $this->summary;
	}

	/**
	 * @inheritDoc
	 */
	public function getLogType() {
		"mock-flow-topic-import";
	}

	/**
	 * @inheritDoc
	 */
	public function getLogParameters() {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectKey() {
		return 'mock-topic:1';
	}
}
