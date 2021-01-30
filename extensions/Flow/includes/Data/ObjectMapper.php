<?php

namespace Flow\Data;

/**
 * Interface for converting back and forth between a database row and
 * a domain model.
 */
interface ObjectMapper {
	/**
	 * Convert $object from the domain model to its db row
	 *
	 * @param object $object
	 * @return array
	 */
	public function toStorageRow( $object );

	/**
	 * Convert a db row to its domain model. Object passing is intended for
	 * updating the object to match a changed storage representation.
	 *
	 * @param array $row Assoc array representing the domain model
	 * @param object|null $object The domain model to populate, creates when null
	 * @return object The domain model populated with $row
	 * @throws \Exception When object is the wrong class for the mapper
	 */
	public function fromStorageRow( array $row, $object = null );

	/**
	 * Check internal cache for previously unserialized objects
	 *
	 * @param array $primaryKey
	 * @return object|null
	 */
	public function get( array $primaryKey );

	/**
	 * Accepts a row representing domain model & returns that same row,
	 * normalized. It'll roundtrip the row from- & toStorageRow to cleanup data.
	 * We want to make sure that data type differences cause no false positives,
	 * like $row containing strings, & new row has integers with the same value.
	 *
	 * @param array $row Assoc array representing the domain model
	 * @return array Normalized row
	 */
	public function normalizeRow( array $row );

	/**
	 * Clear any internally cached information
	 */
	public function clear();
}
