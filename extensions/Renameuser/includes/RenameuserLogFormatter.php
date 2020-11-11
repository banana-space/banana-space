<?php

/**
 * LogFormatter for renameuser/renameuser logs
 */
class RenameuserLogFormatter extends LogFormatter {

	protected function getMessageParameters() {
		$params = parent::getMessageParameters();
		/* Current format:
		 * 1,2,3: normal logformatter params
		 * 4: old username (linked)
		 *    (legaciest doesn't have this at all, all in comment)
		 *    (legacier uses this as new name and stores old name in target)
		 * 5: new username (linked)
		 * 6: number of edits the user had at the time
		 *    (not available except in newest log entries)
		 * 7: new username (raw format for GENDER)
		 * Note that the arrays are zero-indexed, while message parameters
		 * start from 1, so substract one to get array entries below.
		 */

		if ( !isset( $params[3] ) ) {
			// The oldest format
			return $params;
		} elseif ( !isset( $params[4] ) ) {
			// See comments above
			$params[4] = $params[3];
			$params[3] = $this->entry->getTarget()->getText();
		}

		// Nice link to old user page
		$title = Title::makeTitleSafe( NS_USER, $params[3] );
		$link = $this->myPageLink( $title, $params[3] );
		$params[3] = Message::rawParam( $link );

		// Nice link to new user page
		$title = Title::makeTitleSafe( NS_USER, $params[4] );
		$link = $this->myPageLink( $title, $params[4] );
		$params[4] = Message::rawParam( $link );
		// GENDER support (using new user page)
		$params[6] = $title->getText();

		return $params;
	}

	protected function myPageLink( Title $title = null, $text ) {
		if ( !$this->plaintext ) {
			if ( !$title instanceof Title ) {
				$link = htmlspecialchars( $text );
			} else {
				$link = $this->getLinkRenderer()->makeLink( $title, $text );
			}
		} else {
			if ( !$title instanceof Title ) {
				$link = "[[User:$text]]";
			} else {
				$link = '[[' . $title->getPrefixedText() . ']]';
			}
		}

		return $link;
	}

	public function getMessageKey() {
		$key = parent::getMessageKey();
		$params = $this->extractParameters();

		// Very old log format, everything in comment
		if ( !isset( $params[3] ) ) {
			return "$key-legaciest";
		} elseif ( !isset( $params[5] ) ) {
			return "$key-legacier";
		}

		return $key;
	}

	public function getPreloadTitles() {
		$params = $this->extractParameters();
		if ( !isset( $params[3] ) ) {
			// Very old log format, everything in comment - legaciest
			return [];
		}
		if ( !isset( $params[4] ) ) {
			// Old log format - legacier
			$newUserName = $params[3];
		} else {
			$newUserName = $params[4];
		}

		$title = Title::makeTitleSafe( NS_USER, $newUserName );
		if ( $title ) {
			return [ $title ];
		}

		return [];
	}
}
