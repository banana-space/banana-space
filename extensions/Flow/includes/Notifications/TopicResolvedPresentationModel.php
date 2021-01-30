<?php

namespace Flow\Notifications;

class TopicResolvedPresentationModel extends FlowPresentationModel {
	public function getIconType() {
		// flow-topic-resolved or flow-topic-reopened
		return $this->event->getExtraParam( 'type' );
	}

	public function canRender() {
		$type = $this->event->getExtraParam( 'type' );

		return $this->hasTitle()
			&& $this->hasValidTopicWorkflowId()
			&& in_array( $type, [ 'flow-topic-resolved', 'flow-topic-reopened' ] );
	}

	public function getPrimaryLink() {
		return $this->getViewTopicLink();
	}

	public function getSecondaryLinks() {
		return [
			$this->getAgentLink(),
			$this->getBoardLink(),
			$this->getFlowUnwatchDynamicActionLink( true ),
		];
	}

	protected function getHeaderMessageKey() {
		// notification-header-flow-topic-resolved,
		// notification-header-flow-topic-reopened,
		// notification-header-flow-topic-resolved-user-talk or
		// notification-header-flow-topic-reopened-user-talk
		$key = "notification-header-" . $this->event->getExtraParam( 'type' );
		if ( $this->isUserTalkPage() ) {
			$key .= '-user-talk';
		}

		return $key;
	}

	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->plaintextParams( $this->getTopicTitle() );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}
}
