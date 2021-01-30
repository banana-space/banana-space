<?php

namespace Flow\Data\Storage;

use Flow\Data\ObjectManager;
use Flow\Data\ObjectStorage;
use Flow\Data\Utils\RawSql;
use Flow\DbFactory;
use Flow\Exception\DataModelException;
use Flow\Model\UUID;

/**
 * Base class for all ObjectStorage implementers
 * which use a database as the backing store.
 *
 * Includes some utility methods for database management and
 * SQL security.
 */
abstract class DbStorage implements ObjectStorage {
	/**
	 * @var DbFactory
	 */
	protected $dbFactory;

	/**
	 * The revision columns allowed to be updated
	 *
	 * @var string[]|true Allow of selective columns to allow, or true to allow
	 *   everything
	 */
	protected $allowedUpdateColumns = true;

	/**
	 * This is to prevent 'Update not allowed on xxx' error during moderation when
	 * * old cache is not purged and still holds obsolete deleted column
	 * * old cache is not purged and doesn't have the newly added column
	 *
	 * @var string[] Array of columns to ignore
	 */
	protected $obsoleteUpdateColumns = [];

	/**
	 * @param DbFactory $dbFactory
	 */
	public function __construct( DbFactory $dbFactory ) {
		$this->dbFactory = $dbFactory;
	}

	/**
	 * Runs preprocessSqlArray on each element of an array.
	 *
	 * @param array $outer The array to check
	 * @return array Preprocessed SQL array.
	 * @throws DataModelException
	 */
	protected function preprocessNestedSqlArray( array $outer ) {
		foreach ( $outer as $i => $data ) {
			if ( !is_array( $data ) ) {
				throw new DataModelException( "Unexpected non-array in nested SQL array" );
			}
			$outer[$i] = $this->preprocessSqlArray( $data );
		}
		return $outer;
	}

	/**
	 * At the moment, does three things:
	 * 1. Finds UUID objects and returns their database representation.
	 * 2. Checks for unarmoured raw SQL and errors out if it exists.
	 * 3. Finds armoured raw SQL and expands it out.
	 *
	 * @param array $data Query conditions for IDatabase::select
	 * @return array query conditions escaped for use
	 * @throws DataModelException
	 */
	protected function preprocessSqlArray( array $data ) {
		// Assuming that all databases have the same escaping settings.
		$db = $this->dbFactory->getDB( DB_REPLICA );

		$data = UUID::convertUUIDs( $data, 'binary' );

		foreach ( $data as $key => $value ) {
			if ( $value instanceof RawSql ) {
				$data[$key] = $value->getSQL( $db );
			} elseif ( is_numeric( $key ) ) {
				throw new DataModelException( "Unescaped raw SQL found in " . __METHOD__, 'process-data' );
			} elseif ( !preg_match( '/^[A-Za-z0-9\._]+$/', $key ) ) {
				throw new DataModelException( "Dangerous SQL field name '$key' found in " . __METHOD__, 'process-data' );
			}
		}

		return $data;
	}

	/**
	 * Internal security function which checks a row object
	 * (for inclusion as a condition or a row for insert/update)
	 * for any numeric keys (= raw SQL), or field names with
	 * potentially unsafe characters.
	 *
	 * @param array $row The row to check.
	 * @return bool True if raw SQL is found
	 */
	protected function hasUnescapedSQL( array $row ) {
		foreach ( $row as $key => $value ) {
			if ( $value instanceof RawSql ) {
				// Specifically allowed SQL
				continue;
			}

			if ( is_numeric( $key ) ) {
				return true;
			}

			if ( !preg_match( '/^' . $this->getFieldRegexFragment() . '$/', $key ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns a regular expression fragment suitable for matching a valid
	 * SQL field name, and hopefully no injection attacks
	 * @return string Regular expression fragment
	 */
	protected function getFieldRegexFragment() {
		return '\s*[A-Za-z0-9\._]+\s*';
	}

	/**
	 * Internal security function to check an options array for
	 * SQL injection and other funkiness
	 * @todo Currently only supports LIMIT, OFFSET and ORDER BY
	 * @param array $options An options array passed to a query.
	 * @return bool
	 */
	protected function validateOptions( $options ) {
		static $validUnaryOptions = [
			'UNIQUE',
			'EXPLAIN',
		];

		$fieldRegex = $this->getFieldRegexFragment();

		foreach ( $options as $key => $value ) {
			if ( is_numeric( $key ) && in_array( strtoupper( $value ), $validUnaryOptions ) ) {
				continue;
			} elseif ( is_numeric( $key ) ) {
				wfDebug( __METHOD__ . ": Unrecognised unary operator $value\n" );
				return false;
			}

			if ( $key === 'LIMIT' ) {
				// LIMIT is one or two integers, separated by a comma.
				if ( !preg_match( '/^\d+(,\d+)?$/', $value ) ) {
					wfDebug( __METHOD__ . ": Invalid LIMIT $value\n" );
					return false;
				}
			} elseif ( $key === 'ORDER BY' ) {
				// ORDER BY is a list of field names with ASC / DESC afterwards
				if ( is_string( $value ) ) {
					$value = explode( ',', $value );
				}
				$orderByRegex = "/^\s*$fieldRegex\s*(ASC|DESC)?\s*$/i";

				foreach ( $value as $orderByField ) {
					if ( !preg_match( $orderByRegex, $orderByField ) ) {
						wfDebug( __METHOD__ . ": invalid ORDER BY field $orderByField\n" );
						return false;
					}
				}
			} elseif ( $key === 'OFFSET' ) {
				// OFFSET is just an integer
				if ( !is_numeric( $value ) ) {
					wfDebug( __METHOD__ . ": non-numeric offset $value\n" );
					return false;
				}
			} elseif ( $key === 'GROUP BY' ) {
				if ( !preg_match( "/^{$fieldRegex}(,{$fieldRegex})+$/", $value ) ) {
					wfDebug( __METHOD__ . ": invalid GROUP BY field\n" );
				}
			} else {
				wfDebug( __METHOD__ . ": Unknown option $key\n" );
				return false;
			}
		}

		// Everything passes
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function validate( array $row ) {
		return true;
	}

	/**
	 * Calculates the DB updates to be performed to update data from $old to
	 * $new.
	 *
	 * @param array $old
	 * @param array $new
	 * @return array
	 * @throws DataModelException
	 */
	public function calcUpdates( array $old, array $new ) {
		$changeSet = ObjectManager::calcUpdatesWithoutValidation( $old, $new );

		foreach ( $this->obsoleteUpdateColumns as $val ) {
			// Need to use array_key_exists to check null value
			if ( array_key_exists( $val, $changeSet ) ) {
				unset( $changeSet[$val] );
			}
		}

		if ( is_array( $this->allowedUpdateColumns ) ) {
			$extra = array_diff( array_keys( $changeSet ), $this->allowedUpdateColumns );
			if ( $extra ) {
				throw new DataModelException( 'Update not allowed on: ' . implode( ', ', $extra ), 'process-data' );
			}
		}

		return $changeSet;
	}
}
