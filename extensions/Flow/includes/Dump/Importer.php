<?php

namespace Flow\Dump;

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\DbFactory;
use Flow\Import\HistoricalUIDGenerator;
use Flow\Import\ImportException;
use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\TopicListEntry;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\OccupationController;
use MWException;
use WikiImporter;
use WikiPage;
use XMLReader;

class Importer {
	/**
	 * @var WikiImporter
	 */
	protected $importer;

	/**
	 * @var ManagerGroup|null
	 */
	protected $storage;

	/**
	 * The most recently imported board workflow (if any).
	 *
	 * @var Workflow|null
	 */
	protected $boardWorkflow;

	/**
	 * The most recently imported topic workflow (if any).
	 *
	 * @var Workflow|null
	 */
	protected $topicWorkflow;

	/**
	 * @var array Map of old to new IDs
	 */
	protected $idMap = [];

	/**
	 * To convert between global and local user ids
	 *
	 * @var \CentralIdLookup
	 */
	protected $lookup;

	/**
	 * Whether the current board is being imported in trans-wiki mode
	 *
	 * @var bool
	 */
	protected $transWikiMode = false;

	/**
	 * @param WikiImporter $importer
	 */
	public function __construct( WikiImporter $importer ) {
		$this->importer = $importer;
		$this->lookup = \CentralIdLookup::factory( 'CentralAuth' );
	}

	/**
	 * @param ManagerGroup $storage
	 */
	public function setStorage( ManagerGroup $storage ) {
		$this->storage = $storage;
	}

	/**
	 * @param object $object
	 * @param array $metadata
	 */
	protected function put( $object, array $metadata = [] ) {
		if ( $this->storage ) {
			$this->storage->put( $object, [ 'imported' => true ] + $metadata );

			// prevent memory from being filled up
			$this->storage->clear();

			// keep workflow objects around, so follow-up `put`s (e.g. to update
			// last_update_timestamp) don't confuse it for a new object
			foreach ( [ $this->boardWorkflow, $this->topicWorkflow ] as $object ) {
				if ( $object ) {
					$this->storage->getStorage( get_class( $object ) )->merge( $object );
				}
			}
		}
	}

	public function handleBoard() {
		$this->checkTransWikiMode(
			$this->importer->nodeAttribute( 'id' ),
			$this->importer->nodeAttribute( 'title' )
		);

		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter board handler for ' . $id );

		$uuid = UUID::create( $id );
		$title = \Title::newFromDBkey( $this->importer->nodeAttribute( 'title' ) );

		$this->boardWorkflow = Workflow::fromStorageRow( [
			'workflow_id' => $uuid->getAlphadecimal(),
			'workflow_type' => 'discussion',
			'workflow_wiki' => wfWikiID(),
			'workflow_page_id' => $title->getArticleID(),
			'workflow_namespace' => $title->getNamespace(),
			'workflow_title_text' => $title->getDBkey(),
			'workflow_last_update_timestamp' => $uuid->getTimestamp( TS_MW ),
		] );

		// create page if it does not yet exist
		/** @var OccupationController $occupationController */
		$occupationController = Container::get( 'occupation_controller' );
		$creationStatus = $occupationController->safeAllowCreation( $title, $occupationController->getTalkpageManager() );
		if ( !$creationStatus->isOK() ) {
			throw new MWException( $creationStatus->getWikiText() );
		}

		$ensureStatus = $occupationController->ensureFlowRevision(
			WikiPage::factory( $title ),
			$this->boardWorkflow
		);
		if ( !$ensureStatus->isOK() ) {
			throw new MWException( $ensureStatus->getWikiText() );
		}

		$this->put( $this->boardWorkflow, [] );
	}

	public function handleHeader() {
		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter description handler for ' . $id );

		$metadata = [ 'workflow' => $this->boardWorkflow ];

		$revisions = $this->getRevisions( [ Header::class, 'fromStorageRow' ] );
		foreach ( $revisions as $revision ) {
			$this->put( $revision, $metadata );
		}

		/** @var Header $revision */
		$revision = end( $revisions );
		$this->boardWorkflow->updateLastUpdated( $revision->getRevisionId() );
		$this->put( $this->boardWorkflow, [] );
	}

	public function handleTopic() {
		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter topic handler for ' . $id );

		$uuid = UUID::create( $id );
		$title = $this->boardWorkflow->getArticleTitle();

		$this->topicWorkflow = Workflow::fromStorageRow( [
			'workflow_id' => $uuid->getAlphadecimal(),
			'workflow_type' => 'topic',
			'workflow_wiki' => wfWikiID(),
			'workflow_page_id' => $title->getArticleID(),
			'workflow_namespace' => $title->getNamespace(),
			'workflow_title_text' => $title->getDBkey(),
			'workflow_last_update_timestamp' => $uuid->getTimestamp( TS_MW ),
		] );
		$topicListEntry = TopicListEntry::create( $this->boardWorkflow, $this->topicWorkflow );

		$metadata = [
			'board-workflow' => $this->boardWorkflow,
			'workflow' => $this->topicWorkflow,
			// @todo: topic-title & first-post? (used only in NotificationListener)
		];

		// create page if it does not yet exist
		/** @var OccupationController $occupationController */
		$occupationController = Container::get( 'occupation_controller' );
		$creationStatus = $occupationController->safeAllowCreation(
			$this->topicWorkflow->getArticleTitle(),
			$occupationController->getTalkpageManager()
		);
		if ( !$creationStatus->isOK() ) {
			throw new MWException( $creationStatus->getWikiText() );
		}

		$ensureStatus = $occupationController->ensureFlowRevision(
			WikiPage::factory( $this->topicWorkflow->getArticleTitle() ),
			$this->topicWorkflow
		);
		if ( !$ensureStatus->isOK() ) {
			throw new MWException( $ensureStatus->getWikiText() );
		}

		$this->put( $this->topicWorkflow, $metadata );
		$this->put( $topicListEntry, $metadata );
	}

	public function handlePost() {
		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter post handler for ' . $id );

		$metadata = [
			'workflow' => $this->topicWorkflow
			// @todo: topic-title? (used only in NotificationListener)
		];

		$revisions = $this->getRevisions( [ PostRevision::class, 'fromStorageRow' ] );
		foreach ( $revisions as $revision ) {
			$this->put( $revision, $metadata );
		}

		/** @var PostRevision $revision */
		$revision = end( $revisions );
		$this->topicWorkflow->updateLastUpdated( $revision->getRevisionId() );
		$this->put( $this->topicWorkflow, $metadata );
	}

	public function handleSummary() {
		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter summary handler for ' . $id );

		$metadata = [ 'workflow' => $this->topicWorkflow ];

		$revisions = $this->getRevisions( [ PostSummary::class, 'fromStorageRow' ] );
		foreach ( $revisions as $revision ) {
			$this->put( $revision, $metadata );
		}

		/** @var PostSummary $revision */
		$revision = end( $revisions );
		$this->topicWorkflow->updateLastUpdated( $revision->getRevisionId() );
		$this->put( $this->topicWorkflow, $metadata );
	}

	/**
	 * @param callable $callback The relevant fromStorageRow callback
	 * @return AbstractRevision[]
	 */
	protected function getRevisions( $callback ) {
		$revisions = [];

		// keep processing <revision> nodes until </revisions>
		while ( $this->importer->getReader()->localName !== 'revisions' ||
			$this->importer->getReader()->nodeType !== XMLReader::END_ELEMENT
		) {
			if ( $this->importer->getReader()->localName === 'revision' ) {
				$revisions[] = $this->getRevision( $callback );
			}
			$this->importer->getReader()->read();
		}

		return $revisions;
	}

	/**
	 * @param callable $callback The relevant fromStorageRow callback
	 * @return AbstractRevision
	 */
	protected function getRevision( $callback ) {
		$id = $this->mapId( $this->importer->nodeAttribute( 'id' ) );
		$this->importer->debug( 'Enter revision handler for ' . $id );

		// isEmptyElement will no longer be valid after we've started iterating
		// the attributes
		$empty = $this->importer->getReader()->isEmptyElement;

		$attribs = [];

		$this->importer->getReader()->moveToFirstAttribute();
		do {
			$attribs[$this->importer->getReader()->name] = $this->importer->getReader()->value;
		} while ( $this->importer->getReader()->moveToNextAttribute() );

		$idFields = [ 'id', 'typeid', 'treedescendantid', 'treerevid', 'parentid', 'treeparentid', 'lasteditid' ];
		foreach ( $idFields as $idField ) {
			if ( isset( $attribs[ $idField ] ) ) {
				$attribs[ $idField ] = $this->mapId( $attribs[ $idField ] );
			}
		}

		if ( $this->transWikiMode && $this->lookup ) {
			$userFields = [ 'user', 'treeoriguser', 'moduser', 'edituser' ];
			foreach ( $userFields as $userField ) {
				$globalUserIdField = 'global' . $userField . 'id';
				if ( isset( $attribs[ $globalUserIdField ] ) ) {
					$localUser = $this->lookup->localUserFromCentralId(
						(int)$attribs[ $globalUserIdField ],
						\CentralIdLookup::AUDIENCE_RAW
					);
					if ( !$localUser ) {
						$localUser = $this->createLocalUser( (int)$attribs[ $globalUserIdField ] );
					}
					$attribs[ $userField . 'id' ] = $localUser->getId();
					$attribs[ $userField . 'wiki' ] = wfWikiID();
				} elseif ( isset( $attribs[ $userField . 'ip' ] ) ) {
					// make anons local users
					$attribs[ $userField . 'wiki' ] = wfWikiID();
				}
			}
		}

		// now that we've moved inside the node (to fetch attributes),
		// nodeContents() is no longer reliable: is uses isEmptyContent (which
		// will now no longer respond with 'true') to see if the node should be
		// skipped - use the value we've fetched earlier!
		$attribs['content'] = $empty ? '' : $this->importer->nodeContents();

		// make sure there are no leftover key columns (unknown to $attribs)
		$keys = array_intersect_key( array_flip( Exporter::$map ), $attribs );
		// now make sure $values columns are in the same order as $keys are
		// (array_merge) and there are no leftover columns (array_intersect_key)
		$values = array_intersect_key( array_merge( $keys, $attribs ), $keys );
		// combine them
		$attribs = array_combine( $keys, $values );

		// now fill in missing attributes
		$keys = array_fill_keys( array_keys( Exporter::$map ), null );
		$attribs += $keys;

		return $callback( $attribs );
	}

	/**
	 * When in trans-wiki mode, return a new id based on the same timestamp
	 *
	 * @param string $id
	 * @return string
	 */
	private function mapId( $id ) {
		if ( !$this->transWikiMode ) {
			return $id;
		}

		if ( !isset( $this->idMap[ $id ] ) ) {
			$this->idMap[ $id ] = UUID::create( HistoricalUIDGenerator::historicalTimestampedUID88(
				UUID::hex2timestamp( UUID::create( $id )->getHex() )
			) )->getAlphadecimal();
		}
		return $this->idMap[ $id ];
	}

	/**
	 * Check if a board already exist and should be imported in trans-wiki mode
	 *
	 * @param string $boardWorkflowId
	 * @param string $title
	 */
	private function checkTransWikiMode( $boardWorkflowId, $title ) {
		/** @var DbFactory $dbFactory */
		$dbFactory = Container::get( 'db.factory' );
		$workflowExist = (bool)$dbFactory->getDB( DB_MASTER )->selectField(
			'flow_workflow',
			'workflow_id',
			[ 'workflow_id' => UUID::create( $boardWorkflowId )->getBinary() ],
			__METHOD__
		);

		if ( $workflowExist ) {
			$this->importer->debug( "$title will be imported in trans-wiki mode" );
		}
		$this->transWikiMode = $workflowExist;
	}

	/**
	 * Create a local user corresponding to a global id
	 *
	 * @param int $globalUserId
	 * @return \User Local user
	 * @throws ImportException
	 */
	private function createLocalUser( $globalUserId ) {
		if ( !( $this->lookup instanceof \CentralAuthIdLookup ) ) {
			throw new ImportException( 'Creating local users is not supported with central id provider: ' .
				get_class( $this->lookup ) );
		}

		$globalUser = \CentralAuthUser::newFromId( $globalUserId );
		$localUser = \User::newFromName( $globalUser->getName() );

		if ( $localUser->getId() ) {
			throw new ImportException( "User '{$localUser->getName()}' already exists" );
		}

		$status = \CentralAuthUtils::autoCreateUser( $localUser );
		if ( !$status->isGood() ) {
			throw new ImportException(
				"autoCreateUser failed for {$localUser->getName()}: " . print_r( $status->getErrors(), true )
			);
		}

		# Update user count
		$ssUpdate = \SiteStatsUpdate::factory( [ 'users' => 1 ] );
		$ssUpdate->doUpdate();

		return $localUser;
	}
}
