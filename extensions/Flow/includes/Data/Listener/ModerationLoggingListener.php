<?php

namespace Flow\Data\Listener;

use Flow\Log\ModerationLogger;
use Flow\Model\PostRevision;
use Flow\Model\Workflow;

class ModerationLoggingListener extends AbstractListener {

	/**
	 * @var ModerationLogger
	 */
	protected $moderationLogger;

	public function __construct( ModerationLogger $moderationLogger ) {
		$this->moderationLogger = $moderationLogger;
	}

	/**
	 * @param PostRevision $object
	 * @param array $row
	 * @param array $metadata (must contain 'workflow' key with a Workflow object)
	 */
	public function onAfterInsert( $object, array $row, array $metadata ) {
		if ( $object instanceof PostRevision ) {
			$this->log( $object, $metadata['workflow'] );
		}
	}

	protected function log( PostRevision $post, Workflow $workflow ) {
		if ( !$post->isModerationChange() ) {
			// Do nothing for non-moderation actions
			return;
		}

		if ( $this->moderationLogger->canLog( $post, $post->getChangeType() ) ) {
			$workflowId = $workflow->getId();

			$this->moderationLogger->log(
				$post,
				$post->getChangeType(),
				$post->getModeratedReason(),
				$workflowId
			);
		}
	}
}
