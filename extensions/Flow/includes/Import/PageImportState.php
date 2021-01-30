<?php

namespace Flow\Import;

use DeferredUpdates;
use Flow\Data\ManagerGroup;
use Flow\DbFactory;
use Flow\Import\Postprocessor\Postprocessor;
use Flow\Import\SourceStore\SourceStoreInterface;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use SplQueue;
use User;
use Wikimedia\IPUtils;

class PageImportState {
	/**
	 * @var LoggerInterface
	 */
	public $logger;

	/**
	 * @var Workflow
	 */
	public $boardWorkflow;

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var ReflectionProperty
	 */
	protected $workflowIdProperty;

	/**
	 * @var ReflectionProperty
	 */
	protected $postIdProperty;

	/**
	 * @var ReflectionProperty
	 */
	protected $revIdProperty;

	/**
	 * @var ReflectionProperty
	 */
	protected $lastEditIdProperty;

	/**
	 * @var bool
	 */
	protected $allowUnknownUsernames;

	/**
	 * @var Postprocessor
	 */
	public $postprocessor;

	/**
	 * @var SplQueue
	 */
	protected $deferredQueue;

	/**
	 * @var SourceStoreInterface
	 */
	private $sourceStore;

	/**
	 * @var \Wikimedia\Rdbms\IMaintainableDatabase
	 */
	private $dbw;

	public function __construct(
		Workflow $boardWorkflow,
		ManagerGroup $storage,
		SourceStoreInterface $sourceStore,
		LoggerInterface $logger,
		DbFactory $dbFactory,
		Postprocessor $postprocessor,
		SplQueue $deferredQueue,
		$allowUnknownUsernames = false
	) {
		$this->storage = $storage;
		$this->boardWorkflow = $boardWorkflow;
		$this->sourceStore = $sourceStore;
		$this->logger = $logger;
		$this->dbw = $dbFactory->getDB( DB_MASTER );
		$this->postprocessor = $postprocessor;
		$this->deferredQueue = $deferredQueue;
		$this->allowUnknownUsernames = $allowUnknownUsernames;

		// Get our workflow UUID property
		$this->workflowIdProperty = new ReflectionProperty( Workflow::class, 'id' );
		$this->workflowIdProperty->setAccessible( true );

		// Get our revision UUID properties
		$this->postIdProperty = new ReflectionProperty( PostRevision::class, 'postId' );
		$this->postIdProperty->setAccessible( true );
		$this->revIdProperty = new ReflectionProperty( AbstractRevision::class, 'revId' );
		$this->revIdProperty->setAccessible( true );
		$this->lastEditIdProperty = new ReflectionProperty( AbstractRevision::class, 'lastEditId' );
		$this->lastEditIdProperty->setAccessible( true );
	}

	/**
	 * @param object|object[] $object
	 * @param array $metadata
	 */
	public function put( $object, array $metadata ) {
		$metadata['imported'] = true;
		if ( is_array( $object ) ) {
			$this->storage->multiPut( $object, $metadata );
		} else {
			$this->storage->put( $object, $metadata );
		}
	}

	/**
	 * Gets the given object from storage
	 *
	 * WARNING: Before calling this method, ensure that you follow the rule
	 * given in clearManagerGroup.
	 *
	 * @param string $type Class name to retrieve
	 * @param UUID $id ID of the object to retrieve
	 * @return Object|false
	 */
	public function get( $type, UUID $id ) {
		return $this->storage->get( $type, $id );
	}

	/**
	 * Clears information about which objects are loaded, to avoid memory leaks.
	 * This will also:
	 * * Clear the mapper associated with each ObjectManager that has been used.
	 * * Trigger onAfterClear on any listeners.
	 *
	 * WARNING: You can *NOT* call ->get before calling clearManagerGroup, then ->put
	 * after calling clearManagerGroup, on the same object.  This will cause a
	 * duplicate object to be inserted.
	 */
	public function clearManagerGroup() {
		$this->storage->clear();
	}

	/**
	 * Gets the top revision of an item by ID
	 *
	 * @param string $type The type of the object to return (e.g. PostRevision).
	 * @param UUID $id The ID (e.g. post ID, topic ID, etc)
	 * @return object|false The top revision of the requested object, or false if not found.
	 */
	public function getTopRevision( $type, UUID $id ) {
		$result = $this->storage->find(
			$type,
			[ 'rev_type_id' => $id ],
			[
				'sort' => 'rev_id',
				'order' => 'DESC',
				'limit' => 1
			]
		);

		if ( is_array( $result ) && count( $result ) ) {
			return reset( $result );
		} else {
			return false;
		}
	}

	/**
	 * Creates a UUID object representing a given timestamp.
	 *
	 * @param string $timestamp The timestamp to represent, in a wfTimestamp compatible format.
	 * @return UUID
	 */
	public function getTimestampId( $timestamp ) {
		return UUID::create( HistoricalUIDGenerator::historicalTimestampedUID88( $timestamp ) );
	}

	/**
	 * Update the id of the workflow to match the provided timestamp
	 *
	 * @param Workflow $workflow
	 * @param string $timestamp
	 */
	public function setWorkflowTimestamp( Workflow $workflow, $timestamp ) {
		$uid = $this->getTimestampId( $timestamp );
		$this->workflowIdProperty->setValue( $workflow, $uid );
	}

	/**
	 * @param AbstractRevision $revision
	 * @param string $timestamp
	 */
	public function setRevisionTimestamp( AbstractRevision $revision, $timestamp ) {
		$uid = $this->getTimestampId( $timestamp );

		// We don't set the topic title postId as it was inherited from the workflow.  We only set the
		// postId for first revisions because further revisions inherit it from the parent which was
		// set appropriately.
		if ( $revision instanceof PostRevision && $revision->isFirstRevision(
			) && !$revision->isTopicTitle()
		) {
			$this->postIdProperty->setValue( $revision, $uid );
		}

		if ( $revision->getRevisionId()->equals( $revision->getLastContentEditId() ) ) {
			$this->lastEditIdProperty->setValue( $revision, $uid );
		}
		$this->revIdProperty->setValue( $revision, $uid );
	}

	/**
	 * Records an association between a created object and its source.
	 *
	 * @param UUID $objectId UUID representing the object that was created.
	 * @param IImportObject $object Output from getObjectKey
	 */
	public function recordAssociation( UUID $objectId, IImportObject $object ) {
		$this->sourceStore->setAssociation( $objectId, $object->getObjectKey() );
	}

	/**
	 * Gets the imported ID for a given object, if any.
	 *
	 * @param IImportObject $object
	 * @return UUID|false
	 */
	public function getImportedId( IImportObject $object ) {
		return $this->sourceStore->getImportedId( $object );
	}

	public function createUser( $name ) {
		if ( IPUtils::isIPAddress( $name ) ) {
			return User::newFromName( $name, false );
		}
		$user = User::newFromName( $name );
		if ( !$user ) {
			throw new ImportException( 'Unable to create user: ' . $name );
		}
		if ( $user->getId() == 0 && !$this->allowUnknownUsernames ) {
			throw new ImportException( 'User does not exist: ' . $name );
		}

		return $user;
	}

	public function begin() {
		$this->flushDeferredQueue();
		$this->dbw->begin( __METHOD__ );
	}

	public function commit() {
		$this->dbw->commit( __METHOD__ );
		$this->sourceStore->save();
		$this->flushDeferredQueue();
	}

	public function rollback() {
		$this->dbw->rollback( __METHOD__ );
		$this->sourceStore->rollback();
		$this->clearDeferredQueue();
		$this->postprocessor->importAborted();
	}

	protected function flushDeferredQueue() {
		while ( !$this->deferredQueue->isEmpty() ) {
			DeferredUpdates::addCallableUpdate(
				$this->deferredQueue->dequeue(),
				DeferredUpdates::PRESEND
			);
			DeferredUpdates::tryOpportunisticExecute();
		}
	}

	protected function clearDeferredQueue() {
		while ( !$this->deferredQueue->isEmpty() ) {
			$this->deferredQueue->dequeue();
		}
	}
}
