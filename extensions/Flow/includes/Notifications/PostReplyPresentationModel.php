<?php

namespace Flow\Notifications;

use Flow\Container;

class PostReplyPresentationModel extends FlowPresentationModel {

	public function getIconType() {
		return $this->isUserTalkPage() ? 'edit-user-talk' : 'chat';
	}

	public function canRender() {
		return $this->hasTitle()
			&& $this->hasValidTopicWorkflowId()
			&& $this->hasValidPostId();
	}

	public function getPrimaryLink() {
		$topmostPostID = null;

		if ( $this->isBundled() ) {
			// "Strict standards: Only variables should be passed by reference" in older PHP versions
			$bundledEvents = $this->getBundledEvents();

			/** @var Controller $notificationController */
			$notificationController = Container::get( 'controller.notification' );
			$firstChronologicallyEvent = end( $bundledEvents );
			$firstChronologicallyPostId = $firstChronologicallyEvent->getExtraParam( 'post-id' );
			$bundledEventsIncludingThis = array_merge( [ $this->event ], $bundledEvents );
			$topmostPostID = $notificationController->getTopmostPostId( $bundledEventsIncludingThis ) ?:
				$firstChronologicallyPostId;

		} else {
			$event = $this->event;
			$firstChronologicallyPostId = $event->getExtraParam( 'post-id' );
		}
		return [
			'url' => $this->getPostLinkUrl( $firstChronologicallyPostId, $topmostPostID ),
			'label' => $this->msg( 'flow-notification-link-text-view-post' )->text(),
		];
	}

	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			$links = [ $this->getBoardLink() ];
		} else {
			$links = [ $this->getAgentLink(), $this->getBoardLink() ];
		}

		$links[] = $this->getFlowUnwatchDynamicActionLink( true );

		return $links;
	}

	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			if ( $this->isUserTalkPage() ) {
				return 'notification-bundle-header-flow-post-reply-user-talk';
			} else {
				return 'notification-bundle-header-flow-post-reply-v2';
			}
		} else {
			if ( $this->isUserTalkPage() ) {
				return 'notification-header-flow-post-reply-user-talk';
			} else {
				return 'notification-header-flow-post-reply';
			}
		}
	}

	public function getHeaderMessage() {
		if ( $this->isBundled() ) {
			$count = $this->getNotificationCountForOutput();
			$msg = $this->msg( $this->getHeaderMessageKey() );

			// Repeat is B/C until unused parameter is removed from translations
			$msg->numParams( $count, $count );
			$msg->plaintextParams( $this->getTopicTitle() );
			return $msg;
		} else {
			$msg = parent::getHeaderMessage();
			$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
			$msg->plaintextParams( $this->getTopicTitle() );
			return $msg;
		}
	}

	public function getCompactHeaderMessage() {
		$msg = $this->getMessageWithAgent( 'notification-compact-header-flow-post-reply' );
		$msg->plaintextParams( $this->getContentSnippet() );
		return $msg;
	}

	public function getBodyMessage() {
		if ( !$this->isBundled() ) {
			if ( $this->isUserTalkPage() ) {
				$msg = $this->msg( "notification-body-flow-post-reply-v2" );
			} else {
				$msg = $this->msg( "notification-body-flow-post-reply-user-talk" );
			}
			$msg->plaintextParams( $this->getContentSnippet() );
			return $msg;
		}
	}

}
