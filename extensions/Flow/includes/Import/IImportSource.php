<?php

namespace Flow\Import;

use Iterator;

interface IImportSource {
	/**
	 * @return Iterator<IImportTopic>
	 */
	public function getTopics();

	/**
	 * @return IImportHeader
	 * @throws ImportException
	 */
	public function getHeader();
}
