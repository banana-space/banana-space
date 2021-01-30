<?php

namespace Flow\Import\LiquidThreadsApi;

use ArrayIterator;
use Flow\Import\IImportSummary;
use Flow\Import\IImportTopic;
use Flow\Import\IObjectRevision;

/**
 * This is a bit of a weird model, acting as a revision of itself.
 */
class ImportTopic extends ImportPost implements IImportTopic, IObjectRevision {
	/**
	 * @return string
	 */
	public function getText() {
		return $this->apiResponse['subject'];
	}

	public function getAuthor() {
		return $this->apiResponse['author']['name'];
	}

	public function getRevisions() {
		// we only have access to a single revision of the topic
		return new ArrayIterator( [ $this ] );
	}

	public function getReplies() {
		$topPost = new ImportPost( $this->importSource, $this->apiResponse );

		return new ArrayIterator( [ $topPost ] );
	}

	public function getTimestamp() {
		return wfTimestamp( TS_MW, $this->apiResponse['created'] );
	}

	/**
	 * @return IImportSummary|null
	 */
	public function getTopicSummary() {
		$id = $this->getSummaryId();
		if ( $id > 0 ) {
			$data = $this->importSource->getPageData( $id );
			if ( isset( $data['revisions'][0] ) ) {
				return new ImportSummary( $data, $this->importSource );
			} else {
				return null;
			}
		} else {
			return null;
		}
	}

	/**
	 * @return int
	 */
	protected function getSummaryId() {
		return $this->apiResponse['summaryid'];
	}

	/**
	 * This needs to have a different value than the same apiResponse in an ImportPost.
	 * The ImportPost version refers to the first response to the topic.
	 * @return string
	 */
	public function getObjectKey() {
		return 'topic' . $this->importSource->getObjectKey( 'thread_id', $this->apiResponse['id'] );
	}

	public function getLogType() {
		return "lqt-to-flow-topic";
	}

	public function getLogParameters() {
		return [
			'lqt_thread_id' => $this->apiResponse['id'],
			'lqt_orig_title' => $this->getTitle()->getPrefixedText(),
			'lqt_subject' => $this->getText(),
		];
	}

	public function getLqtThreadId() {
		return $this->apiResponse['id'];
	}
}
