<?php

namespace Flow\Data;

/**
 * Compact rows before writing to cache, expand when receiving back
 * Still returns arrays, just removes unnecessary values.
 */
interface Compactor {
	/**
	 * @param array $row A data model row to strip unnecessary data from
	 * @return array Only the values in $row that will be written to the cache
	 */
	public function compactRow( array $row );

	/**
	 * @param array $rows Multiple data model rows to strip unnecesssary data from
	 * @return array The provided rows now containing only the values the will be written to cache
	 */
	public function compactRows( array $rows );

	/**
	 * Repopulate BagOStuff::multiGet results with any values removed in self::compactRow
	 *
	 * @param array $cached The multi-dimensional array results of BagOStuff::multiGet
	 * @param array $keyToQuery An array mapping memcache-key to the values used to generate that cache key
	 * @return array The cached content from memcache along with any data stripped in self::compactRow
	 */
	public function expandCacheResult( array $cached, array $keyToQuery );
}
