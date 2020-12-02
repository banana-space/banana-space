<?php

namespace Wikimedia\Rdbms;

use InvalidArgumentException;

/**
 * Helper class used for automatically marking an IDatabase connection as reusable (once it no
 * longer matters which DB domain is selected) and for deferring the actual network connection
 *
 * This uses an RAII-style pattern where calling code is expected to keep the returned reference
 * handle as a function variable that falls out of scope when no longer needed. This avoids the
 * need for matching reuseConnection() calls for every "return" statement as well as the tedious
 * use of try/finally.
 *
 * @par Example:
 * @code
 *     function getRowData() {
 *         $conn = $this->lb->getConnectedRef( DB_REPLICA );
 *         $row = $conn->select( ... );
 *         return $row ? (array)$row : false;
 *         // $conn falls out of scope and $this->lb->reuseConnection() gets called
 *     }
 * @endcode
 *
 * @ingroup Database
 * @since 1.22
 */
class DBConnRef implements IDatabase {
	/** @var ILoadBalancer */
	private $lb;
	/** @var Database|null Live connection handle */
	private $conn;
	/** @var array N-tuple of (server index, group, DatabaseDomain|string) */
	private $params;
	/** @var int One of DB_MASTER/DB_REPLICA */
	private $role;

	private const FLD_INDEX = 0;
	private const FLD_GROUP = 1;
	private const FLD_DOMAIN = 2;
	private const FLD_FLAGS = 3;

	/**
	 * @param ILoadBalancer $lb Connection manager for $conn
	 * @param IDatabase|array $conn Database or (server index, query groups, domain, flags)
	 * @param int $role The type of connection asked for; one of DB_MASTER/DB_REPLICA
	 * @internal This method should not be called outside of LoadBalancer
	 */
	public function __construct( ILoadBalancer $lb, $conn, $role ) {
		$this->lb = $lb;
		$this->role = $role;
		if ( $conn instanceof IDatabase && !( $conn instanceof DBConnRef ) ) {
			$this->conn = $conn; // live handle
		} elseif ( is_array( $conn ) && count( $conn ) >= 4 && $conn[self::FLD_DOMAIN] !== false ) {
			$this->params = $conn;
		} else {
			throw new InvalidArgumentException( "Missing lazy connection arguments." );
		}
	}

	public function __call( $name, array $arguments ) {
		if ( $this->conn === null ) {
			list( $index, $groups, $wiki, $flags ) = $this->params;
			$this->conn = $this->lb->getConnection( $index, $groups, $wiki, $flags );
		}

		return $this->conn->$name( ...$arguments );
	}

	/**
	 * @return int DB_MASTER when this *requires* the master DB, otherwise DB_REPLICA
	 * @since 1.33
	 */
	public function getReferenceRole() {
		return $this->role;
	}

	public function getServerInfo() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getTopologyRole() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getTopologyRootMaster() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function trxLevel() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function trxTimestamp() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function explicitTrxActive() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function assertNoOpenTransactions() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function tablePrefix( $prefix = null ) {
		if ( $this->conn === null && $prefix === null ) {
			$domain = DatabaseDomain::newFromId( $this->params[self::FLD_DOMAIN] );
			// Avoid triggering a database connection
			return $domain->getTablePrefix();
		} elseif ( $this->conn !== null && $prefix === null ) {
			// This will just return the prefix
			return $this->__call( __FUNCTION__, func_get_args() );
		}
		// Disallow things that might confuse the LoadBalancer tracking
		throw $this->getDomainChangeException();
	}

	public function dbSchema( $schema = null ) {
		if ( $this->conn === null && $schema === null ) {
			$domain = DatabaseDomain::newFromId( $this->params[self::FLD_DOMAIN] );
			// Avoid triggering a database connection
			return $domain->getSchema();
		} elseif ( $this->conn !== null && $schema === null ) {
			// This will just return the schema
			return $this->__call( __FUNCTION__, func_get_args() );
		}
		// Disallow things that might confuse the LoadBalancer tracking
		throw $this->getDomainChangeException();
	}

	public function getLBInfo( $name = null ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setLBInfo( $nameOrArray, $value = null ) {
		// Disallow things that might confuse the LoadBalancer tracking
		throw $this->getDomainChangeException();
	}

	public function implicitOrderby() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lastQuery() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lastDoneWrites() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function writesPending() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function preCommitCallbacksPending() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function writesOrCallbacksPending() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function pendingWriteQueryDuration( $type = self::ESTIMATE_TOTAL ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function pendingWriteCallers() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function pendingWriteRowsAffected() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function isOpen() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setFlag( $flag, $remember = self::REMEMBER_NOTHING ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function clearFlag( $flag, $remember = self::REMEMBER_NOTHING ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function restoreFlags( $state = self::RESTORE_PRIOR ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getFlag( $flag ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getProperty( $name ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getDomainID() {
		if ( $this->conn === null ) {
			$domain = $this->params[self::FLD_DOMAIN];
			// Avoid triggering a database connection
			return $domain instanceof DatabaseDomain ? $domain->getId() : $domain;
		}

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getType() {
		if ( $this->conn === null ) {
			// Avoid triggering a database connection
			if ( $this->params[self::FLD_INDEX] === ILoadBalancer::DB_MASTER ) {
				$index = $this->lb->getWriterIndex();
			} else {
				$index = $this->params[self::FLD_INDEX];
			}
			if ( $index >= 0 ) {
				// In theory, if $index is DB_REPLICA, the type could vary
				return $this->lb->getServerType( $index );
			}
		}

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function fetchObject( $res ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function fetchRow( $res ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function numRows( $res ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function numFields( $res ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function fieldName( $res, $n ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function insertId() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function dataSeek( $res, $row ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lastErrno() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lastError() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function affectedRows() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getSoftwareLink() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getServerVersion() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function close( $fname = __METHOD__, $owner = null ) {
		throw new DBUnexpectedError( $this->conn, 'Cannot close shared connection.' );
	}

	public function query( $sql, $fname = __METHOD__, $flags = 0 ) {
		if ( $this->role !== ILoadBalancer::DB_MASTER ) {
			$flags |= IDatabase::QUERY_REPLICA_ROLE;
		}

		return $this->__call( __FUNCTION__, [ $sql, $fname, $flags ] );
	}

	public function freeResult( $res ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function newSelectQueryBuilder() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectField(
		$table, $var, $cond = '', $fname = __METHOD__, $options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectFieldValues(
		$table, $var, $cond = '', $fname = __METHOD__, $options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function select(
		$table, $vars, $conds = '', $fname = __METHOD__,
		$options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectSQLText(
		$table, $vars, $conds = '', $fname = __METHOD__,
		$options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function limitResult( $sql, $limit, $offset = false ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectRow(
		$table, $vars, $conds, $fname = __METHOD__,
		$options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function estimateRowCount(
		$tables, $vars = '*', $conds = '', $fname = __METHOD__, $options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectRowCount(
		$tables, $vars = '*', $conds = '', $fname = __METHOD__, $options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lockForUpdate(
		$table, $conds = '', $fname = __METHOD__, $options = [], $join_conds = []
	) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function fieldExists( $table, $field, $fname = __METHOD__ ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function indexExists( $table, $index, $fname = __METHOD__ ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function tableExists( $table, $fname = __METHOD__ ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function insert( $table, $rows, $fname = __METHOD__, $options = [] ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function update( $table, $set, $conds, $fname = __METHOD__, $options = [] ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function makeList( array $a, $mode = self::LIST_COMMA ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function makeWhereFrom2d( $data, $baseKey, $subKey ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function aggregateValue( $valuedata, $valuename = 'value' ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function bitNot( $field ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function bitAnd( $fieldLeft, $fieldRight ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function bitOr( $fieldLeft, $fieldRight ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildConcat( $stringList ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildGroupConcatField(
		$delim, $table, $field, $conds = '', $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildGreatest( $fields, $values ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildLeast( $fields, $values ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildSubstring( $input, $startPosition, $length = null ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildStringCast( $field ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildIntegerCast( $field ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildSelectSubquery(
		$table, $vars, $conds = '', $fname = __METHOD__,
		$options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function databasesAreIndependent() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function selectDB( $db ) {
		// Disallow things that might confuse the LoadBalancer tracking
		throw $this->getDomainChangeException();
	}

	public function selectDomain( $domain ) {
		// Disallow things that might confuse the LoadBalancer tracking
		throw $this->getDomainChangeException();
	}

	public function getDBname() {
		if ( $this->conn === null ) {
			$domain = DatabaseDomain::newFromId( $this->params[self::FLD_DOMAIN] );
			// Avoid triggering a database connection
			return $domain->getDatabase();
		}

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getServer() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function addQuotes( $s ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function addIdentifierQuotes( $s ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function buildLike( $param, ...$params ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function anyChar() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function anyString() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function nextSequenceValue( $seqName ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function replace( $table, $uniqueKeys, $rows, $fname = __METHOD__ ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function upsert(
		$table, array $rows, $uniqueKeys, array $set, $fname = __METHOD__
	) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function deleteJoin(
		$delTable, $joinTable, $delVar, $joinVar, $conds, $fname = __METHOD__
	) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function delete( $table, $conds, $fname = __METHOD__ ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function insertSelect(
		$destTable, $srcTable, $varMap, $conds,
		$fname = __METHOD__, $insertOptions = [], $selectOptions = [], $selectJoinConds = []
	) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function unionSupportsOrderAndLimit() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function unionQueries( $sqls, $all ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function unionConditionPermutations(
		$table, $vars, array $permute_conds, $extra_conds = '', $fname = __METHOD__,
		$options = [], $join_conds = []
	) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function conditional( $cond, $trueVal, $falseVal ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function strreplace( $orig, $old, $new ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getServerUptime() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function wasDeadlock() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function wasLockTimeout() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function wasConnectionLoss() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function wasReadOnlyError() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function wasErrorReissuable() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function masterPosWait( DBMasterPos $pos, $timeout ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getReplicaPos() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getMasterPos() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function serverIsReadOnly() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function onTransactionResolution( callable $callback, $fname = __METHOD__ ) {
		// DB_REPLICA role: caller might want to refresh cache after a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function onTransactionCommitOrIdle( callable $callback, $fname = __METHOD__ ) {
		// DB_REPLICA role: caller might want to refresh cache after a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function onTransactionIdle( callable $callback, $fname = __METHOD__ ) {
		return $this->onTransactionCommitOrIdle( $callback, $fname );
	}

	public function onTransactionPreCommitOrIdle( callable $callback, $fname = __METHOD__ ) {
		// DB_REPLICA role: caller might want to refresh cache after a cache mutex is released
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function onAtomicSectionCancel( callable $callback, $fname = __METHOD__ ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setTransactionListener( $name, callable $callback = null ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function startAtomic(
		$fname = __METHOD__, $cancelable = IDatabase::ATOMIC_NOT_CANCELABLE
	) {
		// Don't call assertRoleAllowsWrites(); caller might want a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function endAtomic( $fname = __METHOD__ ) {
		// Don't call assertRoleAllowsWrites(); caller might want a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function cancelAtomic( $fname = __METHOD__, AtomicSectionIdentifier $sectionId = null ) {
		// Don't call assertRoleAllowsWrites(); caller might want a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function doAtomicSection(
		$fname, callable $callback, $cancelable = self::ATOMIC_NOT_CANCELABLE
	) {
		// Don't call assertRoleAllowsWrites(); caller might want a REPEATABLE-READ snapshot
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function begin( $fname = __METHOD__, $mode = IDatabase::TRANSACTION_EXPLICIT ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function commit( $fname = __METHOD__, $flush = self::FLUSHING_ONE ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function rollback( $fname = __METHOD__, $flush = self::FLUSHING_ONE ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function flushSnapshot( $fname = __METHOD__, $flush = self::FLUSHING_ONE ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function timestamp( $ts = 0 ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function timestampOrNull( $ts = null ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function ping( &$rtt = null ) {
		return func_num_args()
			? $this->__call( __FUNCTION__, [ &$rtt ] )
			: $this->__call( __FUNCTION__, [] ); // method cares about null vs missing
	}

	public function getLag() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getSessionLagStatus() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function maxListLen() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function encodeBlob( $b ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function decodeBlob( $b ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setSessionOptions( array $options ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setSchemaVars( $vars ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lockIsFree( $lockName, $method ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function lock( $lockName, $method, $timeout = 5 ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function unlock( $lockName, $method ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getScopedLockAndFlush( $lockKey, $fname, $timeout ) {
		$this->assertRoleAllowsWrites();

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function namedLocksEnqueue() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function getInfinity() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function encodeExpiry( $expiry ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function decodeExpiry( $expiry, $format = TS_MW ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setBigSelects( $value = true ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function isReadOnly() {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setTableAliases( array $aliases ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function setIndexAliases( array $aliases ) {
		return $this->__call( __FUNCTION__, func_get_args() );
	}

	public function __toString() {
		if ( $this->conn === null ) {
			return $this->getType() . ' object #' . spl_object_id( $this );
		}

		return $this->__call( __FUNCTION__, func_get_args() );
	}

	/**
	 * Error out if the role is not DB_MASTER
	 *
	 * Note that the underlying connection may or may not itself be read-only.
	 * It could even be to a writable master (both server-side and to the application).
	 * This error is meant for the case when a DB_REPLICA handle was requested but a
	 * a write was attempted on that handle regardless.
	 *
	 * In configurations where the master DB has some generic read load or is the only server,
	 * DB_MASTER/DB_REPLICA will sometimes (or always) use the same connection to the master DB.
	 * This does not effect the role of DBConnRef instances.
	 * @throws DBReadOnlyRoleError
	 */
	protected function assertRoleAllowsWrites() {
		// DB_MASTER is "prima facie" writable
		if ( $this->role !== ILoadBalancer::DB_MASTER ) {
			throw new DBReadOnlyRoleError( $this->conn, "Cannot write with role DB_REPLICA" );
		}
	}

	/**
	 * @return DBUnexpectedError
	 */
	protected function getDomainChangeException() {
		return new DBUnexpectedError(
			$this,
			"Cannot directly change the selected DB domain; any underlying connection handle " .
			"is owned by a LoadBalancer instance and possibly shared with other callers. " .
			"LoadBalancer automatically manages DB domain re-selection of unused handles."
		);
	}

	/**
	 * Clean up the connection when out of scope
	 */
	public function __destruct() {
		if ( $this->conn ) {
			$this->lb->reuseConnection( $this->conn );
		}
	}
}

/**
 * @since 1.22
 * @deprecated since 1.29
 */
class_alias( DBConnRef::class, 'DBConnRef' );
