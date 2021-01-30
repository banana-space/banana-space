<?php

namespace Flow\Import;

interface IObjectRevision extends IImportObject {
	/**
	 * @return string Wikitext
	 */
	public function getText();

	/**
	 * @return string Timestamp compatible with wfTimestamp()
	 */
	public function getTimestamp();

	/**
	 * @return string The name of the user who created this revision.
	 */
	public function getAuthor();
}
