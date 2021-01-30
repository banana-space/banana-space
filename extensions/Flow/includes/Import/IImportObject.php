<?php

namespace Flow\Import;

interface IImportObject {
	/**
	 * Returns an opaque string that uniquely identifies this object.
	 * Should uniquely identify this particular object every time it is imported.
	 *
	 * @return string
	 */
	public function getObjectKey();
}
