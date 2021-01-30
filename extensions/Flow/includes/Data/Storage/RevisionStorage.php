<?php

namespace Flow\Data\Storage;

use ExternalStore;
use Flow\Data\ObjectManager;
use Flow\Data\Utils\Merger;
use Flow\Data\Utils\ResultDuplicator;
use Flow\DbFactory;
use Flow\Exception\DataModelException;
use Flow\Model\UUID;
use MWException;
use Wikimedia\Rdbms\IDatabase;

/**
 * Abstract storage implementation for models extending from AbstractRevision
 */
abstract class RevisionStorage extends DbStorage {
	/**
	 * @inheritDoc
	 */
	protected $allowedUpdateColumns = [
		'rev_mod_state',
		'rev_mod_user_id',
		'rev_mod_user_ip',
		'rev_mod_user_wiki',
		'rev_mod_timestamp',
		'rev_mod_reason',
	];

	/**
	 * @inheritDoc
	 *
	 * @Todo - This may not be necessary anymore since we don't update historical
	 * revisions ( flow_revision ) during moderation
	 */
	protected $obsoleteUpdateColumns = [
		'tree_orig_user_text',
		'rev_user_text',
		'rev_edit_user_text',
		'rev_mod_user_text',
		'rev_type_id',
	];

	protected $externalStore;

	/**
	 * Get the table to join for the revision storage, empty string for none
	 * @return string
	 */
	protected function joinTable() {
		return '';
	}

	/**
	 * Get the column to join with flow_revision.rev_id, empty string for none
	 * @return string
	 */
	protected function joinField() {
		return '';
	}

	/**
	 * Insert to joinTable() upon revision insert
	 * @param array $row
	 * @return array
	 */
	protected function insertRelated( array $row ) {
		return $row;
	}

	/**
	 * Update to joinTable() upon revision update
	 * @param array $changes
	 * @param array $old
	 * @return array
	 */
	protected function updateRelated( array $changes, array $old ) {
		return $changes;
	}

	/**
	 * Remove from joinTable upone revision delete
	 * @param array $row
	 * @return bool
	 */
	protected function removeRelated( array $row ) {
		return true;
	}

	/**
	 * The revision type
	 * @return string
	 */
	abstract protected function getRevType();

	/**
	 * @param DbFactory $dbFactory
	 * @param array|false $externalStore List of external store servers available for insert
	 *  or false to disable. See $wgFlowExternalStore.
	 */
	public function __construct( DbFactory $dbFactory, $externalStore ) {
		parent::__construct( $dbFactory );
		$this->externalStore = $externalStore;
	}

	/**
	 * Find one by specific attributes
	 * @todo this method can probably be generalized in parent class?
	 * @param array $attributes
	 * @param array $options
	 * @return mixed
	 */
	public function find( array $attributes, array $options = [] ) {
		$multi = $this->findMulti( [ $attributes ], $options );
		return $multi ? reset( $multi ) : [];
	}

	/**
	 * @param array $attributes
	 * @param array $options
	 * @return array
	 * @throws DataModelException
	 * @throws MWException
	 */
	protected function findInternal( array $attributes, array $options = [] ) {
		$dbr = $this->dbFactory->getDB( DB_REPLICA );

		if ( !$this->validateOptions( $options ) ) {
			throw new MWException( "Validation error in database options" );
		}

		// Add rev_type if rev_type_id exists in query condition
		$attributes = $this->addRevTypeToQuery( $attributes );

		$tables = [ 'rev' => 'flow_revision' ];
		$joins = [];
		if ( $this->joinTable() ) {
			$tables[] = $this->joinTable();
			$joins = [ 'rev' => [ 'JOIN', $this->joinField() . ' = rev_id' ] ];
		}

		$res = $dbr->select(
			$tables, '*', $this->preprocessSqlArray( $attributes ), __METHOD__, $options, $joins
		);
		if ( $res === false ) {
			throw new DataModelException( __METHOD__ . ': Query failed: ' . $dbr->lastError(),
				'process-data' );
		}

		$retval = [];
		foreach ( $res as $row ) {
			$row = UUID::convertUUIDs( (array)$row, 'alphadecimal' );
			$retval[$row['rev_id']] = $row;
		}
		return $retval;
	}

	protected function addRevTypeToQuery( $query ) {
		if ( isset( $query['rev_type_id'] ) ) {
			$query['rev_type'] = $this->getRevType();
		}
		return $query;
	}

	public function findMulti( array $queries, array $options = [] ) {
		if ( count( $queries ) < 3 ) {
			$res = $this->fallbackFindMulti( $queries, $options );
		} else {
			$res = $this->findMultiInternal( $queries, $options );
		}

		return self::mergeExternalContent( $res );
	}

	protected function fallbackFindMulti( array $queries, array $options ) {
		$result = [];
		foreach ( $queries as $key => $attributes ) {
			$result[$key] = $this->findInternal( $attributes, $options );
		}
		return $result;
	}

	protected function findMultiInternal( array $queries, array $options = [] ) {
		$queriedKeys = array_keys( reset( $queries ) );
		// The findMulti doesn't map well to SQL, basically we are asking to answer a bunch
		// of queries. We can optimize those into a single query in a few select instances:
		if ( isset( $options['LIMIT'] ) && $options['LIMIT'] == 1 ) {
			// Find by primary key
			if ( $options == [ 'LIMIT' => 1 ] &&
				$queriedKeys === [ 'rev_id' ]
			) {
				return $this->findRevId( $queries );
			}

			// Find most recent revision of a number of posts
			if ( !isset( $options['OFFSET'] ) &&
				$queriedKeys == [ 'rev_type_id' ] &&
				isset( $options['ORDER BY'] ) &&
				$options['ORDER BY'] === [ 'rev_id DESC' ]
			) {
				return $this->findMostRecent( $queries );
			}
		}

		// Fetch a list of revisions for each post
		// @todo this is slow and inefficient.  Mildly better solution would be if
		// the index can ask directly for just the list of rev_id instead of whole rows,
		// but would still have the need to run a bunch of queries serially.
		if ( count( $options ) === 2 &&
			isset( $options['LIMIT'] ) && isset( $options['ORDER BY'] ) &&
			$options['ORDER BY'] === [ 'rev_id DESC' ]
		) {
			return $this->fallbackFindMulti( $queries, $options );
		// unoptimizable query
		} else {
			wfDebugLog( 'Flow', __METHOD__
				. ': Unoptimizable query for keys: '
				. implode( ',', array_keys( $queriedKeys ) )
				. ' with options '
				. \FormatJson::encode( $options )
			);
			return $this->fallbackFindMulti( $queries, $options );
		}
	}

	protected function findRevId( array $queries ) {
		$duplicator = new ResultDuplicator( [ 'rev_id' ], 1 );
		$pks = [];
		foreach ( $queries as $idx => $query ) {
			$query = UUID::convertUUIDs( (array)$query, 'alphadecimal' );
			$duplicator->add( $query, $idx );
			$id = $query['rev_id'];
			$pks[$id] = UUID::create( $id )->getBinary();
		}

		return $this->findRevIdReal( $duplicator, $pks );
	}

	protected function findMostRecent( array $queries ) {
		// SELECT MAX( rev_id ) AS rev_id
		// FROM flow_tree_revision
		// WHERE rev_type= 'post' AND rev_type_id IN (...)
		// GROUP BY rev_type_id
		$duplicator = new ResultDuplicator( [ 'rev_type_id' ], 1 );
		foreach ( $queries as $idx => $query ) {
			$query = UUID::convertUUIDs( (array)$query, 'alphadecimal' );
			$duplicator->add( $query, $idx );
		}

		$dbr = $this->dbFactory->getDB( DB_REPLICA );
		$res = $dbr->select(
			[ 'flow_revision' ],
			[ 'rev_id' => "MAX( 'rev_id' )" ],
			[ 'rev_type' => $this->getRevType() ] + $this->preprocessSqlArray(
				$this->buildCompositeInCondition( $dbr, $duplicator->getUniqueQueries() ) ),
			__METHOD__,
			[ 'GROUP BY' => 'rev_type_id' ]
		);
		if ( $res === false ) {
			throw new DataModelException( __METHOD__ . ': Query failed: ' . $dbr->lastError(),
				'process-data' );
		}

		$revisionIds = [];
		foreach ( $res as $row ) {
			$revisionIds[] = $row->rev_id;
		}

		// Due to the grouping and max, we cant reliably get a full
		// columns info in the above query, forcing the join below
		// rather than just querying flow_revision.
		return $this->findRevIdReal( $duplicator, $revisionIds );
	}

	/**
	 * @param ResultDuplicator $duplicator
	 * @param array $revisionIds Binary strings representing revision uuid's
	 * @return array
	 * @throws DataModelException
	 */
	protected function findRevIdReal( ResultDuplicator $duplicator, array $revisionIds ) {
		if ( $revisionIds ) {
			// SELECT * from flow_revision
			// JOIN flow_tree_revision ON tree_rev_id = rev_id
			// WHERE rev_id IN (...)
			$dbr = $this->dbFactory->getDB( DB_REPLICA );

			$tables = [ 'flow_revision' ];
			$joins  = [];
			if ( $this->joinTable() ) {
				$tables['rev'] = $this->joinTable();
				$joins = [ 'rev' => [ 'JOIN', "rev_id = " . $this->joinField() ] ];
			}

			$res = $dbr->select(
				$tables,
				'*',
				[ 'rev_id' => $revisionIds ],
				__METHOD__,
				[],
				$joins
			);
			if ( $res === false ) {
				throw new DataModelException( __METHOD__ . ': Query failed: ' . $dbr->lastError(),
					'process-data' );
			}

			foreach ( $res as $row ) {
				$row = UUID::convertUUIDs( (array)$row, 'alphadecimal' );
				$duplicator->merge( $row, [ $row ] );
			}
		}

		return $duplicator->getResult();
	}

	/**
	 * Handle the injection of externalstore data into a revision
	 * row.  All rows exiting this method will have rev_content_url
	 * set to either null or the external url.  The rev_content
	 * field will be the final content (possibly compressed still)
	 *
	 * @param array $cacheResult 2d array of rows
	 * @return array 2d array of rows with content merged and rev_content_url populated
	 */
	public static function mergeExternalContent( array $cacheResult ) {
		foreach ( $cacheResult as &$source ) {
			if ( $source === null ) {
				// unanswered queries return null
				continue;
			}
			foreach ( $source as &$row ) {
				$flags = explode( ',', $row['rev_flags'] );
				if ( in_array( 'external', $flags ) ) {
					$row['rev_content_url'] = $row['rev_content'];
					$row['rev_content'] = '';
				} else {
					$row['rev_content_url'] = null;
				}
			}
		}

		return Merger::mergeMulti(
			$cacheResult,
			/* fromKey = */ 'rev_content_url',
			/* callable = */ [ 'ExternalStore', 'batchFetchFromURLs' ],
			/* name = */ 'rev_content',
			/* default = */ ''
		);
	}

	protected function buildCompositeInCondition( IDatabase $dbr, array $queries ) {
		$keys = array_keys( reset( $queries ) );
		$conditions = [];
		if ( count( $keys ) === 1 ) {
			// standard in condition: tree_rev_descendant_id IN (1,2...)
			$key = reset( $keys );
			foreach ( $queries as $query ) {
				$conditions[$key][] = reset( $query );
			}
			return $conditions;
		} else {
			// composite in condition: ( foo = 1 AND bar = 2 ) OR ( foo = 1 AND bar = 3 )...
			// Could be more efficient if composed as a range scan, but seems more complex than
			// its benefit.
			foreach ( $queries as $query ) {
				$conditions[] = $dbr->makeList( $query, LIST_AND );
			}
			return $dbr->makeList( $conditions, LIST_OR );
		}
	}

	public function insert( array $rows ) {
		if ( !is_array( reset( $rows ) ) ) {
			$rows = [ $rows ];
		}

		// Holds the subset of the row to go into the revision table
		$revisions = [];

		foreach ( $rows as $key => $row ) {
			$row = $this->processExternalStore( $row );
			$revisions[$key] = $this->splitUpdate( $row, 'rev' );
		}

		$dbw = $this->dbFactory->getDB( DB_MASTER );
		$dbw->insert(
			'flow_revision',
			$this->preprocessNestedSqlArray( $revisions ),
			__METHOD__
		);

		return $this->insertRelated( $rows );
	}

	/**
	 * Checks whether updating content for an existing revision is allowed.
	 * This is only needed for rare actions like fixing XSS.  Normally a new revision
	 * is made.
	 *
	 * Will throw if column configuration is not consistent
	 *
	 * @return bool True if and only if updating existing content is allowed
	 * @throws DataModelException
	 */
	public function isUpdatingExistingRevisionContentAllowed() {
		// All of these are required to do a consistent mechanical update.
		$requiredColumnNames = [
			'rev_content',
			'rev_content_length',
			'rev_flags',
			'rev_previous_content_length',
		];

		// compare required column names against allowedUpdateColumns
		$diff = array_diff( $requiredColumnNames, $this->allowedUpdateColumns );

		// we're able to update all columns we need: go ahead!
		if ( empty( $diff ) ) {
			return true;
		}

		// we're only able to update part of the columns required to update content
		// @phan-suppress-next-line PhanImpossibleTypeComparison
		if ( $diff !== $requiredColumnNames ) {
			throw new DataModelException( "Allowed update column configuration is inconsistent",
				'allowed-update-inconsistent' );
		}

		// content changes aren't allowed
		return false;
	}

	/**
	 * If this is a new row (new rows should always have content) or part of an update
	 * involving a content change, inserts into external store.
	 * @param array $row
	 * @return array
	 */
	protected function processExternalStore( array $row ) {
		// Check if we need to insert new content
		if (
			$this->externalStore &&
			isset( $row['rev_content'] )
		) {
			$row = $this->insertExternalStore( $row );
		}

		// If a content url is available store that in the db
		// instead of real content.
		if ( isset( $row['rev_content_url'] ) ) {
			$row['rev_content'] = $row['rev_content_url'];
		}
		unset( $row['rev_content_url'] );

		return $row;
	}

	protected function insertExternalStore( array $row ) {
		if ( $row['rev_content'] === null ) {
			throw new DataModelException( "Must have data to write to external storage", 'process-data' );
		}
		$url = ExternalStore::insertWithFallback( $this->externalStore, $row['rev_content'] );
		if ( !$url ) {
			throw new DataModelException( "Unable to store text to external storage", 'process-data' );
		}
		$row['rev_content_url'] = $url;
		if ( isset( $row['rev_flags'] ) && $row['rev_flags'] ) {
			$row['rev_flags'] .= ',external';
		} else {
			$row['rev_flags'] = 'external';
		}

		return $row;
	}

	/**
	 * Gets the required updates.  Any changes to External Store will be reflected in
	 * the returned array.
	 *
	 * @param array $old Associative array mapping prior columns to old values
	 * @param array $new Associative array mapping updated columns to new values
	 *
	 * @return array Validated change set as associative array, mapping columns to
	 *   change to their new values
	 */
	public function calcUpdates( array $old, array $new ) {
		// First, see if there are any changes to content at all.
		// If not, processExternalStore will know not to insert a useless row for
		// unchanged content (if updating content is allowed).
		$unvalidatedChangeset = ObjectManager::calcUpdatesWithoutValidation( $old, $new );

		// We check here so if it's not allowed, we don't insert a wasted External
		// Store entry, then throw an exception in the parent calcUpdates.
		if ( $this->isUpdatingExistingRevisionContentAllowed() ) {
			$unvalidatedChangeset = $this->processExternalStore( $unvalidatedChangeset );
		}

		// The parent calcUpdates does the validation that we're not changing a non-allowed
		// field, regardless of whether explicitly passed in, or done by processExternalStore.
		$validatedChangeset = parent::calcUpdates( [], $unvalidatedChangeset );
		return $validatedChangeset;
	}

	/**
	 * This is to *UPDATE* a revision.  It should hardly ever be used.
	 * For the most part should insert a new revision.  This should only be called
	 * by maintenance scripts and (future) suppression features.
	 * It supports updating content, which is only intended for required mechanical
	 * transformations, such as XSS fixes.  However, since this is only intended for
	 * maintenance scripts, these columns must first be temporarily added to
	 * allowedUpdateColumns.
	 * @param array $old
	 * @param array $new
	 * @return bool
	 */
	public function update( array $old, array $new ) {
		$changeSet = $this->calcUpdates( $old, $new );

		$rev = $this->splitUpdate( $changeSet, 'rev' );

		if ( $rev ) {
			$dbw = $this->dbFactory->getDB( DB_MASTER );
			$dbw->update(
				'flow_revision',
				$this->preprocessSqlArray( $rev ),
				$this->preprocessSqlArray( [ 'rev_id' => $old['rev_id'] ] ),
				__METHOD__
			);
			if ( !$dbw->affectedRows() ) {
				return false;
			}
		}
		return (bool)$this->updateRelated( $changeSet, $old );
	}

	/**
	 * Revisions can only be removed for LIMITED circumstances,  in almost all cases
	 * the offending revision should be updated with appropriate suppression.
	 * Also note this doesnt delete the whole post, it just deletes the revision.
	 * The post will *always* exist in the tree structure, it will just show up as
	 * [deleted] or something
	 * @param array $row
	 * @return bool
	 */
	public function remove( array $row ) {
		$res = $this->dbFactory->getDB( DB_MASTER )->delete(
			'flow_revision',
			$this->preprocessSqlArray( [ 'rev_id' => $row['rev_id'] ] ),
			__METHOD__
		);
		if ( !$res ) {
			return false;
		}
		return $this->removeRelated( $row );
	}

	/**
	 * Used to locate the index for a query by ObjectLocator::get()
	 * @return string[]
	 */
	public function getPrimaryKeyColumns() {
		return [ 'rev_id' ];
	}

	/**
	 * When retrieving revisions from DB, self::mergeExternalContent will be
	 * called to fetch the content. This could fail, resulting in the content
	 * being a 'false' value.
	 *
	 * @inheritDoc
	 */
	public function validate( array $row ) {
		return !isset( $row['rev_content'] ) || $row['rev_content'] !== false;
	}

	/**
	 * Gets all columns from $row that start with a given prefix and omits other
	 * columns.
	 *
	 * @param array $row Rows to split
	 * @param string $prefix
	 * @return array Remaining rows
	 */
	protected function splitUpdate( array $row, $prefix = 'rev' ) {
		$rev = [];
		foreach ( $row as $key => $value ) {
			$keyPrefix = strstr( $key, '_', true );
			if ( $keyPrefix === $prefix ) {
				$rev[$key] = $value;
			}
		}
		return $rev;
	}
}
