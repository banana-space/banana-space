<?php

namespace Flow\Notifications;

use Flow\Container;
use Flow\UrlGenerator;
use Title;

class PostEditedPresentationModel extends FlowPresentationModel {

	public function getIconType() {
		return 'flow-post-edited';
	}

	public function canRender() {
		return $this->hasTitle()
			&& $this->hasValidTopicWorkflowId()
			&& $this->hasValidPostId();
	}

	public function getPrimaryLink() {
		return [
			'url' => $this->getPostLinkUrl(),
			'label' => $this->msg( 'flow-notification-link-text-view-post' )->text()
		];
	}

	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			$links = [ $this->getBoardLink() ];
		} else {
			if ( $this->isUserTalkPage() ) {
				$links = [
					$this->getAgentLink(),
					$this->getDiffLink(),
				];
			} else {
				$links = [
					$this->getAgentLink(),
					$this->getBoardLink(),
					$this->getDiffLink( false ),
				];
			}
		}

		$links[] = $this->getFlowUnwatchDynamicActionLink( true );

		return $links;
	}

	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			if ( $this->isUserTalkPage() ) {
				return "notification-bundle-header-flow-post-edited-user-talk";
			} else {
				return "notification-bundle-header-flow-post-edited-v2";
			}
		} else {
			if ( $this->isUserTalkPage() ) {
				return 'notification-header-flow-post-edited-user-talk';
			} else {
				return 'notification-header-flow-post-edited-v2';
			}
		}
	}

	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->plaintextParams( $this->getTopicTitle() );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	public function getBodyMessage() {
		if ( $this->isUserTalkPage() ) {
			$msg = $this->msg( 'notification-body-flow-post-edited-user-talk' );
		} else {
			$msg = $this->msg( 'notification-body-flow-post-edited-v2' );
		}

		$msg->plaintextParams( $this->getContentSnippet() );
		return $msg;
	}

	protected function getDiffLink( $prioritized = true ) {
		/** @var UrlGenerator $urlGenerator */
		$urlGenerator = Container::get( 'url_generator' );
		$anchor = $urlGenerator->diffPostLink(
			Title::newFromText( $this->event->getExtraParam( 'topic-workflow' )->getAlphadecimal(), NS_TOPIC ),
			$this->event->getExtraParam( 'post-id' ),
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
