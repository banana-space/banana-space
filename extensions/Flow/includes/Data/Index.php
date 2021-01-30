<?php

namespace Flow\Data;

/**
 * Indexes store one or more values bucketed by exact key/value combinations.
 */
interface Index extends LifecycleHandler {
	/**
	 * Find data models matching the provided equality condition.
	 *
	 * @param array $keys A map of k,v pairs to find via equality condition
	 * @param array $options Options to use
	 * @return array Cached subset of data model rows matching the
	 *     equality conditions provided in $keys.
	 */
	public function find( array $keys, array $options = [] );

	/**
	 * Batch together multiple calls to self::find with minimal network round trips.
	 *
	 * @param array $queries An array of arrays in the form of $keys parameter of self::find
	 * @param array $options Options to use
	 * @return array[] Array of arrays in same order as $queries representing batched result set.
	 */
	public function findMulti( array $queries, array $options = [] );

	/**
	 * Returns a boolean true/false if the find()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param array $attributes Attributes to find()
	 * @param array $options Options to find()
	 * @return bool
	 */
	public function found( array $attributes, array $options = [] );

	/**
	 * Returns a boolean true/false if the findMulti()-operation for the given
	 * attributes has already been resolves and doesn't need to query any
	 * outside cache/database.
	 * Determining if a find() has not yet been resolved may be useful so that
	 * additional data may be loaded at once.
	 *
	 * @param array $attributes Attributes to find()
	 * @param array $options Options to find()
	 * @return bool
	 */
	public function foundMulti( array $attributes, array $options = [] );

	/**
	 * @return int Maximum number of items in a single index value
	 */
	public function getLimit();

	/**
	 * Rows are first sorted based on the first term of the result, then ties
	 * are broken by evaluating the second term and so on.
	 *
	 * @todo choose a default sort instead of false?
	 * @return array|false Columns to sort on
	 */
	public function getSort();

	/**
	 * Query options are not supported at the query level, the index always
	 * returns the same value for the same key/value combination.  Depending on what
	 * the query stores it may contain the answers to various options, which will require
	 * post-processing by the caller.
	 *
	 * @param array $keys
	 * @param array $options
	 * @return bool Can the index locate a result for this keys and options pair
	 */
	public function canAnswer( array $keys, array $options );

	/**
	 * @param object $object
	 * @param array $row
	 */
	public function cachePurge( $object, array $row );
}
