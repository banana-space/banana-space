<?php

namespace Flow\Notifications;

class MentionPresentationModel extends FlowPresentationModel {

	public function getIconType() {
		return 'mention';
	}

	public function canRender() {
		return $this->hasTitle();
	}

	public function getPrimaryLink() {
		$link = [
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-mention' )->text()
		];

		// override url, link straight to that specific post/topic
		if ( $this->getRevisionType() === 'post' ) {
			$link['url'] = $this->getPostLinkUrl();
		} elseif ( $this->getRevisionType() === 'post-summary' ) {
			$link['url'] = $this->getTopicLinkUrl();
		}

		return $link;
	}

	public function getSecondaryLinks() {
		return [
			$this->getAgentLink(),
			$this->getBoardByNewestLink(),
		];
	}

	public function getHeaderMessageKey() {
		return parent::getHeaderMessageKey() . '-' . $this->getRevisionType();
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		$msg->params( $this->getViewingUserForGender() );

		if ( in_array( $this->getRevisionType(), [ 'post', 'post-summary' ] ) ) {
			$msg->plaintextParams( $this->getTopicTitle() );
		}

		return $msg;
	}

	public function getBodyMessage() {
		$msg = $this->msg( "notification-body-{$this->type}" );
		$msg->plaintextParams( $this->getContentSnippet() );
		return $msg;
	}

	protected function getRevisionType() {
		// we didn't use to include the type to differentiate messages, but
		// then we only supported posts
		return $this->event->getExtraParam( 'revision-type', 'post' );
	}
}
