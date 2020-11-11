<?php

class SpamBlacklistLogFormatter extends LogFormatter {

	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		$params[3] = Message::rawParam( htmlspecialchars( $params[3] ) );
		return $params;
	}

}
