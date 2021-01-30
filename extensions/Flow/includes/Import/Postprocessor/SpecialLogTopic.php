<?php

namespace Flow\Import\Postprocessor;

use Flow\Import\IImportHeader;
use Flow\Import\IImportPost;
use Flow\Import\IImportTopic;
use Flow\Import\PageImportState;
use Flow\Import\TopicImportState;
use Flow\Model\PostRevision;
use ManualLogEntry;
use User;

/**
 * Records topic imports to Special:Log.
 */
class SpecialLogTopic implements Postprocessor {
	/**
	 * @var bool Indicates if new posts have been seen since the last commit operation
	 */
	protected $newPosts = false;

	/**
	 * @var User The user to attribute logs to
	 */
	protected $user;

	public function __construct( User $user ) {
		$this->user = $user;
	}

	public function afterHeaderImported( PageImportState $state, IImportHeader $topic ) {
		// nothing to do
	}

	public function afterPostImported( TopicImportState $state, IImportPost $post, PostRevision $newPost ) {
		$this->newPosts = true;
	}

	public function afterTopicImported( TopicImportState $state, IImportTopic $topic ) {
		if ( !$this->newPosts ) {
			return;
		}
		$logEntry = new ManualLogEntry( 'import', $topic->getLogType() );
		$logEntry->setTarget( $state->topicWorkflow->getOwnerTitle() );
		$logEntry->setPerformer( $this->user );
		$logEntry->setParameters( [
			'topic' => $state->topicWorkflow->getArticleTitle()->getPrefixedText(),
		] + $topic->getLogParameters() );
		$logEntry->insert();

		$this->newPosts = false;
	}

	public function importAborted() {
		$this->newPosts = false;
	}
}
