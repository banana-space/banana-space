<?php

class EchoForeignPresentationModel extends EchoEventPresentationModel {
	public function getIconType() {
		return 'global';
	}

	public function getPrimaryLink() {
		return false;
	}

	protected function getHeaderMessageKey() {
		$data = $this->event->getExtra();
		$section = $data['section'] == 'message' ? 'notice' : $data['section'];

		// notification-header-foreign-alert
		// notification-header-foreign-notice
		// notification-header-foreign-all
		return "notification-header-foreign-{$section}";
	}

	public function getHeaderMessage() {
		$msg = parent::getHeaderMessage();

		$data = $this->event->getExtra();
		$firstWiki = reset( $data['wikis'] );
		$names = $this->getWikiNames( [ $firstWiki ] );
		$msg->params( $names[0] );
		$msg->numParams( count( $data['wikis'] ) - 1 );
		$msg->numParams( count( $data['wikis'] ) );

		return $msg;
	}

	public function getBodyMessage() {
		$data = $this->event->getExtra();
		$msg = wfMessage( 'notification-body-foreign' );
		$msg->params( $this->language->listToText( $this->getWikiNames( $data['wikis'] ) ) );
		return $msg;
	}

	protected function getWikiNames( array $wikis ) {
		$data = EchoForeignNotifications::getApiEndpoints( $wikis );
		$names = [];
		foreach ( $wikis as $wiki ) {
			$names[] = $data[$wiki]['title'];
		}
		return $names;
	}
}
