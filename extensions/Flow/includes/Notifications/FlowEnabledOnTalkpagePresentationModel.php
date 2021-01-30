<?php

namespace Flow\Notifications;

class FlowEnabledOnTalkpagePresentationModel extends FlowPresentationModel {

	public function getIconType() {
		return 'chat';
	}

	public function canRender() {
		return $this->hasTitle();
	}

	public function getPrimaryLink() {
		return [
			'url' => $this->event->getTitle()->getFullURL(),
			'label' => $this->msg( 'flow-notification-link-text-enabled-on-talkpage' )->text()
		];
	}

	/**
	 * All Flow notifications have the 'Agent' link except this one.
	 *
	 * @return array Empty array
	 */
	public function getSecondaryLinks() {
		$userTalkLink = $this->getPageLink(
			$this->event->getTitle(), '', true
		);
		return [ $userTalkLink ];
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();
		$msg->params( $this->getTruncatedTitleText( $this->event->getTitle(), true ) );
		return $msg;
	}

}
