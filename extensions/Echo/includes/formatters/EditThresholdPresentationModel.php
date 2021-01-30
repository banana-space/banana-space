<?php

class EchoEditThresholdPresentationModel extends EchoEventPresentationModel {

	public function getIconType() {
		return 'edit';
	}

	public function getHeaderMessageKey() {
		return 'notification-header-thank-you-' . $this->event->getExtraParam( 'editCount' ) . '-edit';
	}

	public function getPrimaryLink() {
		if ( !$this->event->getTitle() ) {
			return false;
		}
		return [
			'url' => $this->event->getTitle()->getLocalURL(),
			'label' => $this->msg( 'notification-link-thank-you-edit', $this->getViewingUserForGender() )->text()
		];
	}
}
