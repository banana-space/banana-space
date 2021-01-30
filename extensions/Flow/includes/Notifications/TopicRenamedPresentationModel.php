<?php

namespace Flow\Notifications;

use Flow\Container;
use Flow\UrlGenerator;
use Title;

class TopicRenamedPresentationModel extends FlowPresentationModel {

	public function getIconType() {
		return 'flow-topic-renamed';
	}

	public function canRender() {
		return $this->hasTitle()
			&& $this->hasValidTopicWorkflowId();
	}

	public function getPrimaryLink() {
		return $this->getViewTopicLink();
	}

	public function getSecondaryLinks() {
		if ( $this->isUserTalkPage() ) {
			$links = [
				$this->getAgentLink(),
				$this->getDiffLink(),
			];
		} else {
			$links = [
				$this->getAgentLink(),
				$this->getBoardByNewestLink(),
				$this->getDiffLink( false ),
			];
		}

		$links[] = $this->getFlowUnwatchDynamicActionLink( true );

		return $links;
	}

	protected function getHeaderMessageKey() {
		if ( $this->isUserTalkPage() ) {
			return 'notification-header-flow-topic-renamed-user-talk';
		} else {
			return 'notification-header-flow-topic-renamed-v2';
		}
	}

	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->plaintextParams( $this->getTopicTitle( 'old-subject' ) );
		$msg->plaintextParams( $this->getTopicTitle( 'new-subject' ) );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	protected function getDiffLink( $prioritized = true ) {
		/** @var UrlGenerator $urlGenerator */
		$urlGenerator = Container::get( 'url_generator' );
		$anchor = $urlGenerator->diffPostLink(
			Title::newFromText( $this->event->getExtraParam( 'topic-workflow' )->getAlphadecimal(), NS_TOPIC ),
			$this->event->getExtraParam( 'topic-workflow' ),
			$this->event->getExtraParam( 'revision-id' )
		);

		return [
			'url' => $anchor->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-changes' )->params( $this->getViewingUserForGender() )->text(),
			'description' => '',
			'icon' => 'changes',
			'prioritized' => $prioritized,
		];
	}
}
