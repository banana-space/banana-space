<?php

namespace Flow\Log;

use Message;
use Title;

class LqtImportFormatter extends \LogFormatter {

	public function getPreloadTitles() {
		$titles = [ $this->entry->getTarget() ];
		$params = $this->entry->getParameters() + [
			'topic' => '',
		];
		$topic = Title::newFromText( $params['topic'] );
		if ( $topic ) {
			$titles[] = $topic;
		}

		return $titles;
	}

	/**
	 * Formats an activity log entry.
	 *
	 * @return string The log entry
	 */
	protected function getActionMessage() {
		$board = $this->entry->getTarget();
		$params = $this->entry->getParameters() + [
			'topic' => '',
			'lqt_subject' => '',
		];
		$topic = Title::newFromText( $params['topic'] );

		$message = $this->msg( "logentry-import-lqt-to-flow-topic" )
			->params(
				$topic ? $topic->getPrefixedText() : '',
				Message::plaintextParam( $params['lqt_subject'] ),
				$board->getPrefixedText()
			);

		return $this->plaintext ? $message->text() : $message->parse();
	}
}
