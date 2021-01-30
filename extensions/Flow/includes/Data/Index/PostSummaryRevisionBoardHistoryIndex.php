<?php

namespace Flow\Data\Index;

use Flow\Model\AbstractRevision;

class PostSummaryRevisionBoardHistoryIndex extends BoardHistoryIndex {
	protected function findTopicId( AbstractRevision $postSummary ) {
		return $postSummary->getCollection()->getWorkflowId();
	}
}
