<?php

namespace Flow\Search\Iterators;

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\DbFactory;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;
use Iterator;
use stdClass;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

abstract class AbstractIterator implements Iterator {
	/**
	 * @var IDatabase
	 */
	protected $dbr;

	/**
	 * @var array
	 */
	protected $conditions = [];

	/**
	 * @var IResultWrapper|null
	 */
	protected $results;

	/**
	 * Depending on where we are in the iteration, this can be null (object
	 * constructed but not yet being iterated over), AbstractRevision (being
	 * iterated) or false (end of iteration, no more revisions)
	 *
	 * @var AbstractRevision|null|false
	 */
	protected $current;

	/**
	 * Depending on where we are in the iteration, this can be integer (object
	 * being iterated over) or null (iteration not yet started, or completed)
	 *
	 * @var int|null
	 */
	protected $key;

	/**
	 * @param DbFactory $dbFactory
	 */
	public function __construct( DbFactory $dbFactory ) {
		$this->dbr = $dbFactory->getDB( DB_REPLICA );
		$this->conditions = [ 'workflow_wiki' => wfWikiID() ];
	}

	/**
	 * @return bool|IResultWrapper
	 */
	abstract protected function query();

	/**
	 * @param array|int|null $pageId
	 */
	public function setPage( $pageId = null ) {
		$this->results = null;

		unset( $this->conditions['workflow_page_id'] );
		if ( $pageId !== null ) {
			$this->conditions['workflow_page_id'] = $pageId;
		}
	}

	/**
	 * @param int|null $namespace
	 */
	public function setNamespace( $namespace = null ) {
		$this->results = null;

		unset( $this->conditions['workflow_namespace'] );
		if ( $namespace !== null ) {
			$this->conditions['workflow_namespace'] = $namespace;
		}
	}

	/**
	 * Define where to start iterating (inclusive)
	 *
	 * @param UUID|null $revId
	 */
	public function setFrom( UUID $revId = null ) {
		$this->results = null;

		unset( $this->conditions[0] );
		if ( $revId !== null ) {
			$this->conditions[0] = 'rev_id >= ' . $this->dbr->addQuotes( $revId->getBinary() );
		}
	}

	/**
	 * Define where to stop iterating (exclusive)
	 *
	 * @param UUID|null $revId
	 */
	public function setTo( UUID $revId = null ) {
		$this->results = null;

		unset( $this->conditions[1] );
		if ( $revId !== null ) {
			$this->conditions[1] = 'rev_id < ' . $this->dbr->addQuotes( $revId->getBinary() );
		}
	}

	/**
	 * @return AbstractRevision|null The most recently fetched revision object
	 */
	public function current() {
		return $this->current;
	}

	/**
	 * @return int 0-indexed count of the page number fetched
	 */
	public function key() {
		return $this->key;
	}

	/**
	 * Reset the iterator to the beginning of the table.
	 */
	public function rewind() {
		$this->results = null;
		$this->key = -1; // self::next() will turn this into 0
		$this->current = null;
		$this->next();
	}

	/**
	 * @return bool True when the iterator is in a valid state
	 */
	public function valid() {
		return (bool)$this->current;
	}

	/**
	 * Fetch the next set of rows from the database.
	 */
	public function next() {
		if ( $this->results === null ) {
			$this->results = $this->query();
		}

		$current = $this->results->fetchObject();
		if ( $current !== false ) {
			$this->current = $this->transform( $current );
			$this->key++;
		} else {
			// end of iteration reached
			$this->current = false;
			$this->key = null;
		}
	}

	/**
	 * Transforms the DB row into a revision object.
	 *
	 * $row will be one of the results of static::query(). In this method, $row
	 * is expected to have at least properties `rev_id` & `rev_type`, which will
	 * be used to fetch this specific row's data from storage.
	 *
	 * This will need to do some DB/cache requests. Ideally, those would be
	 * bundled instead of being done on a per-row record. These iterators
	 * are only meant to be run in maintenance scripts, however, so it
	 * doesn't really matter that much ;)
	 *
	 * @param stdClass $row
	 * @return AbstractRevision
	 */
	protected function transform( stdClass $row ) {
		$uuid = UUID::create( $row->rev_id );

		/** @var ManagerGroup $storage */
		$storage = Container::get( 'storage' );

		// prevent memory from being filled up
		$storage->clear();

		return $storage->getStorage( $row->rev_type )->get( $uuid );
	}
}
