<?php

namespace Flow\Data;

/**
 * Interface representing backend data stores.  Typically they
 * will be implemented in SQL with the DbStorage base class.
 */
interface ObjectStorage {

	/**
	 * Perform a single equality query.
	 *
	 * @param array $attributes Map of attributes the model must contain
	 * @param array $options Query options such as ORDER BY and LIMIT.
	 * @return array
	 */
	public function find( array $attributes, array $options = [] );

	/**
	 * Perform the equivalent of array_map against self::find for multiple
	 * equality queries with the minimum of network round trips.
	 *
	 * @param array $queries list of queries to perform
	 * @param array $options Options to use for all queries
	 * @return array[] Array of results for every query
	 */
	public function findMulti( array $queries, array $options = [] );

	/**
	 * @return array The list of columns that together uniquely identify a row
	 */
	public function getPrimaryKeyColumns();

	/**
	 * Insert the specified row into the data store.
	 *
	 * @param array $rows An array of rows, each row is a map of columns => values.
	 * Currently, the old calling convention of a simple map of columns to values is
	 * also supported.
	 * @return array|false The resulting $row including any auto-assigned ids or false on failure
	 */
	public function insert( array $rows );

	/**
	 * Perform all changes necessary to turn $old into $new in the data store.
	 *
	 * @param array $old Map of columns to values that was initially loaded.
	 * @param array $new Map of columns to values that the row should become.
	 * @return bool true when the row is successfully updated
	 */
	public function update( array $old, array $new );

	/**
	 * Remove the specified row from the data store.
	 *
	 * @param array $row Map of columns to values.  Must contain the primary key columns.
	 * @return bool true when the row is successfully removed
	 */
	public function remove( array $row );

	/**
	 * Returns a boolean true/false to indicate if the result of a particular
	 * query is valid & can be cached.
	 * In some cases, the retrieved data should not be cached. E.g. revisions
	 * with external content: revision data may be loaded, but the content could
	 * not be fetched from external storage. That shouldn't persist in cache.
	 *
	 * @param array $row
	 * @return bool
	 */
	public function validate( array $row );
}
