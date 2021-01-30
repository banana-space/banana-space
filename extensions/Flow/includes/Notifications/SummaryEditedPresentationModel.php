<?php

namespace Flow\Notifications;

use Flow\Container;
use Flow\UrlGenerator;
use Title;

class SummaryEditedPresentationModel extends FlowPresentationModel {
	public function getIconType() {
		return 'flow-topic-renamed';
	}

	public function canRender() {
		return $this->hasTitle()
			&& $this->hasValidTopicWorkflowId()
			&& $this->event->getExtraParam( 'revision-id' ) !== null;
	}

	public function getPrimaryLink() {
		return $this->getViewTopicLink();
	}

	public function getSecondaryLinks() {
		if ( $this->isBundled() ) {
			$links = [ $this->getBoardLink() ];
		} else {
			$links = [ $this->getAgentLink(), $this->getBoardLink() ];
			if ( !$this->isFirstRevision() ) {
				$links[] = $this->getDiffLink( false );
			}
		}

		$links[] = $this->getFlowUnwatchDynamicActionLink( true );

		return $links;
	}

	protected function getHeaderMessageKey() {
		if ( $this->isBundled() ) {
			$key = "notification-bundle-header-flow-summary-edited";
		} elseif ( $this->isFirstRevision() ) {
			$key = 'notification-header-flow-summary-edited-first';
		} else {
			$key = 'notification-header-flow-summary-edited';
		}

		if ( $this->isUserTalkPage() ) {
			$key .= '-user-talk';
		}

		return $key;
	}

	protected function isFirstRevision() {
		return $this->event->getExtraParam( 'prev-revision-id' ) === null;
	}

	public function getHeaderMessage() {
		$msg = $this->msg( $this->getHeaderMessageKey() );
		$msg->plaintextParams( $this->getTopicTitle() );
		$msg->params( $this->getViewingUserForGender() );
		return $msg;
	}

	public function getBodyMessage() {
		$key = 'notification-body-flow-summary-edited';
		if ( $this->isUserTalkPage() ) {
			$key .= '-user-talk';
		}

		return $this->msg( $key )->plaintextParams( $this->getContentSnippet() );
	}

	protected function getDiffLink( $prioritized = true ) {
		/** @var UrlGenerator $urlGenerator */
		$urlGenerator = Container::get( 'url_generator' );
		$anchor = $urlGenerator->diffSummaryLink(
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
