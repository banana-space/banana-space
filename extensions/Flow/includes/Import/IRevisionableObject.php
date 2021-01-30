<?php

namespace Flow\Import;

use Iterator;

interface IRevisionableObject extends IImportObject {
	/**
	 * @return Iterator<IObjectRevision>
	 */
	public function getRevisions();
}
