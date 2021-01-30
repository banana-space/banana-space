<?php

namespace Flow\Notifications;

use Flow\Container;
use Flow\UrlGenerator;

class HeaderEditedPresentationModel extends FlowPresentationModel {
	public function getIconType() {
		return 'flow-topic-renamed';
	}

	public function canRender() {
		return $this->hasTitle()
			&& $this->event->getExtraParam( 'revision-id' ) !== null
			&& $this->event->getExtraParam( 'collection-id' ) !== null;
	}

	public function getPrimaryLink() {
		$boardLink = $this->getBoardLink();
		$boardLink['label'] = $this->msg( "notification-links-flow-description-edited-view-page" )
			->params( $this->getViewingUserForGender() )->text();
		return $boardLink;
	}

	public function getSecondaryLinks() {
		return [
			$this->getAgentLink(),
			$this->getDiffLink(),
			$this->getFlowUnwatchDynamicActionLink()
		];
	}

	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			$key = 'notification-bundle-header-flow-description-edited';
		} else {
			$key = 'notification-header-flow-description-edited';
		}

		if ( $this->isUserTalkPage() ) {
			$key .= '-user-talk';
		}

		return $key;
	}

	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	public function getBodyMessage() {
		$key = "notification-body-flow-description-edited";
		if ( $this->isUserTalkPage() ) {
			$key .= '-user-talk';
		}

		return $this->msg( $key )->plaintextParams( $this->getContentSnippet() );
	}

	protected function getDiffLink() {
		/** @var UrlGenerator $urlGenerator */
		$urlGenerator = Container::get( 'url_generator' );
		$anchor = $urlGenerator->diffHeaderLink(
			$this->event->getTitle(),
			$this->event->getExtraParam( 'collection-id' ),
			$this->event->getExtraParam( 'revision-id' )
		);

		return [
			'url' => $anchor->getFullURL(),
			'label' => $this->msg( 'notification-link-text-view-changes' )
				->params( $this->getViewingUserForGender() )->text(),
			'description' => '',
			'icon' => 'changes',
			'prioritized' => true,
		];
	}
}
