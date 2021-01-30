<?php

namespace Flow\Collection;

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Data\ObjectManager;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidDataException;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Title;

abstract class AbstractCollection {
	/**
	 * Id of the collection object.
	 *
	 * @var UUID
	 */
	protected $uuid;

	/**
	 * Array of revisions for this object.
	 *
	 * @var AbstractRevision[]
	 */
	protected $revisions = [];

	/**
	 * @var Workflow
	 */
	protected $workflow;

	/**
	 * Returns the revision class name for this specific object (e.g. Header,
	 * PostRevision)
	 *
	 * @return string
	 */
	public static function getRevisionClass() {
		// Workaround to allow static inheritance without strict standards warning from this being abstract
		throw new FlowException( 'getRevisionClass() must be implemented in the subclass', 'not-implemented' );
	}

	/**
	 * Returns the id of the workflow this collection is associated with.
	 *
	 * @return UUID
	 */
	abstract public function getWorkflowId();

	/**
	 * Returns the id of the workflow of the board this collection is on.
	 *
	 * @return UUID
	 */
	abstract public function getBoardWorkflowId();

	/**
	 * Use the static methods to load an object from a given revision.
	 *
	 * @see AbstractCollection::newFromId
	 * @see AbstractCollection::newFromRevision
	 * @see AbstractCollection::newFromRevisionId
	 *
	 * @param UUID $uuid
	 */
	protected function __construct( UUID $uuid ) {
		$this->uuid = $uuid;
	}

	/**
	 * Instantiate a new object based on its type id
	 * (post ID, header ID, etc.)
	 *
	 * @param UUID $uuid
	 * @return static
	 * @suppress PhanTypeInstantiateAbstractStatic Phan is right, though
	 */
	public static function newFromId( UUID $uuid ) {
		return new static( $uuid );
	}

	/**
	 * Instantiate a new collection based on a revision ID
	 *
	 * @param UUID $revId Revision ID
	 * @return AbstractCollection
	 */
	public static function newFromRevisionId( UUID $revId ) {
		$revision = static::getStorage()->get( $revId );

		if ( $revision === null ) {
			throw new InvalidDataException(
				'Revisions for ' . $revId->getAlphadecimal() . ' could not be found',
				'invalid-revision-id'
			);
		}

		return static::newFromRevision( $revision );
	}

	/**
	 * Instantiate a new object based off of an AbstractRevision object.
	 *
	 * @param AbstractRevision $revision
	 * @return AbstractCollection
	 */
	public static function newFromRevision( AbstractRevision $revision ) {
		return static::newFromId( $revision->getCollectionId() );
	}

	/**
	 * @return UUID
	 */
	public function getId() {
		return $this->uuid;
	}

	/**
	 * @param string|null $class Storage class - defaults to getRevisionClass()
	 * @return ObjectManager
	 */
	public static function getStorage( $class = null ) {
		if ( !$class ) {
			$class = static::getRevisionClass();
		}

		/** @var ManagerGroup $storage */
		$storage = Container::get( 'storage' );
		return $storage->getStorage( $class );
	}

	/**
	 * Returns all revisions.
	 *
	 * @return AbstractRevision[] Array of AbstractRevision
	 * @throws InvalidDataException When no revisions can be found
	 */
	public function getAllRevisions() {
		if ( !$this->revisions ) {
			/** @var AbstractRevision[] $revisions */
			$revisions = self::getStorage()->find(
				[ 'rev_type_id' => $this->uuid ],
				[ 'sort' => 'rev_id', 'order' => 'DESC' ]
			);

			if ( !$revisions ) {
				throw new InvalidDataException(
					'Revisions for ' . $this->uuid->getAlphadecimal() . ' could not be found',
					'invalid-type-id'
				);
			}

			foreach ( $revisions as $revision ) {
				$this->revisions[$revision->getRevisionId()->getAlphadecimal()] = $revision;
			}
		}

		return $this->revisions;
	}

	/**
	 * Returns the revision with the given id.
	 *
	 * @param UUID $uuid
	 * @return AbstractRevision|null null if there is no such revision in the collection
	 */
	public function getRevision( UUID $uuid ) {
		// make sure all revisions have been loaded
		$this->getAllRevisions();

		if ( !isset( $this->revisions[$uuid->getAlphadecimal()] ) ) {
			return null;
		}

		// find requested id, based on given revision
		return $this->revisions[$uuid->getAlphadecimal()];
	}

	/**
	 * Returns the oldest revision.
	 *
	 * @return AbstractRevision
	 */
	public function getFirstRevision() {
		$revisions = $this->getAllRevisions();
		return array_pop( $revisions );
	}

	/**
	 * Returns the most recent revision.
	 *
	 * @return AbstractRevision
	 */
	public function getLastRevision() {
		$revisions = $this->getAllRevisions();
		return array_shift( $revisions );
	}

	/**
	 * Given a certain revision, returns the previous revision.
	 *
	 * @param AbstractRevision $revision
	 * @return AbstractRevision|null null if there is no previous revision
	 */
	public function getPrevRevision( AbstractRevision $revision ) {
		$previousRevisionId = $revision->getPrevRevisionId();
		if ( !$previousRevisionId ) {
			return null;
		}

		return $this->getRevision( $previousRevisionId );
	}

	/**
	 * Given a certain revision, returns the next revision.
	 *
	 * @param AbstractRevision $revision
	 * @return AbstractRevision|null null if there is no next revision
	 */
	public function getNextRevision( AbstractRevision $revision ) {
		// make sure all revisions have been loaded
		$this->getAllRevisions();

		// find requested id, based on given revision
		$ids = array_keys( $this->revisions );
		$current = array_search( $revision->getRevisionId()->getAlphadecimal(), $ids );
		$next = $current - 1;

		if ( $next < 0 ) {
			return null;
		}

		return $this->getRevision( UUID::create( $ids[$next] ) );
	}

	/**
	 * Returns the Title object this revision is associated with.
	 *
	 * @return Title
	 */
	public function getTitle() {
		return $this->getWorkflow()->getArticleTitle();
	}

	/**
	 * Returns the workflow object this collection is associated with.
	 *
	 * @return Workflow
	 * @throws InvalidDataException
	 */
	public function getWorkflow() {
		if ( !$this->workflow ) {
			$uuid = $this->getWorkflowId();

			$this->workflow = self::getStorage( Workflow::class )->get( $uuid );
			if ( !$this->workflow ) {
				throw new InvalidDataException( 'Invalid workflow: ' . $uuid->getAlphadecimal(), 'invalid-workflow' );
			}
		}

		return $this->workflow;
	}

	public function getBoardWorkflow() {
		return self::getStorage( Workflow::class )->get( $this->getBoardWorkflowId() );
	}
}
