<?php

namespace CirrusSearch\MetaStore;

/**
 * A component of the metastore index.
 */
interface MetaStore {

	/**
	 * @return array the mapping
	 */
	public function buildIndexProperties();
}
