<?php

namespace Flow\Data\Listener;

use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidDataException;
use Flow\LinksTableUpdater;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\Reference;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Parsoid\ReferenceExtractor;
use Flow\Repository\TreeRepository;
use SplQueue;

/**
 * Listens for new revisions to be inserted.  Calculates the difference in
 * references(URLs, images, etc) between this new version and the previous
 * revision. Uses calculated difference to update links tables to match the new revision.
 */
class ReferenceRecorder extends AbstractListener {
	/**
	 * @var ReferenceExtractor
	 */
	protected $referenceExtractor;

	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var LinksTableUpdater
	 */
	protected $linksTableUpdater;

	/**
	 * @var TreeRepository Used to query for the posts within a topic when moderation
	 *  changes the visibility of a topic.
	 */
	protected $treeRepository;

	/**
	 * @var SplQueue
	 */
	protected $deferredQueue;

	public function __construct(
		ReferenceExtractor $referenceExtractor,
		LinksTableUpdater $linksTableUpdater,
		ManagerGroup $storage,
		TreeRepository $treeRepository,
		SplQueue $deferredQueue
	) {
		$this->referenceExtractor = $referenceExtractor;
		$this->linksTableUpdater = $linksTableUpdater;
		$this->storage = $storage;
		$this->treeRepository = $treeRepository;
		$this->deferredQueue = $deferredQueue;
	}

	public function onAfterLoad( $object, array $old ) {
		// Nuthin
	}

	public function onAfterInsert( $revision, array $new, array $metadata ) {
		if ( !isset( $metadata['workflow'] ) ) {
			return;
		}
		if ( !$revision instanceof AbstractRevision ) {
			throw new InvalidDataException( 'ReferenceRecorder can only attach to AbstractRevision storage' );
		}
		/** @var Workflow $workflow */
		$workflow = $metadata['workflow'];

		if ( $revision instanceof PostRevision && $revision->isTopicTitle() ) {
			list( $added, $removed ) = $this->calculateChangesFromTopic( $workflow, $revision );
		} else {
			list( $added, $removed ) = $this->calculateChangesFromExisting( $workflow, $revision );
		}

		$this->storage->multiPut( $added );
		$this->storage->multiRemove( $removed );

		// Data has not yet been committed at this point, so let's delay
		// updating `categorylinks`, `externallinks`, etc.
		$linksTableUpdater = $this->linksTableUpdater;
		$this->deferredQueue->push( function () use ( $linksTableUpdater, $workflow ) {
			$linksTableUpdater->doUpdate( $workflow );
		} );
	}

	/**
	 * Compares the references contained within $revision against those stored for
	 * that revision.  Returns the differences.
	 *
	 * @param Workflow $workflow
	 * @param AbstractRevision $revision
	 * @param PostRevision|null $root
	 * @return array Two nested arrays, first the references that were added and
	 *  second the references that were removed.
	 */
	protected function calculateChangesFromExisting(
		Workflow $workflow,
		AbstractRevision $revision,
		PostRevision $root = null
	) {
		$prevReferences = $this->getExistingReferences(
			$revision->getRevisionType(),
			$revision->getCollectionId()
		);
		$references = $this->getReferencesFromRevisionContent( $workflow, $revision, $root );

		return $this->referencesDifference( $prevReferences, $references );
	}

	/**
	 * Topic titles themselves only support minimal wikitext, and references in the
	 * title itself are not tracked.
	 *
	 * However, moderation actions change what references are visible.  When
	 * transitioning from or to a generically visible state (unmoderated or locked) the
	 * entire topic + summary needs to be re-evaluated.
	 *
	 * @param Workflow $workflow
	 * @param PostRevision $current Topic revision object that was inserted
	 * @return array Contains two arrays, first the references to add a second
	 *  the references to remove
	 * @throws FlowException
	 */
	protected function calculateChangesFromTopic( Workflow $workflow, PostRevision $current ) {
		if ( $current->isFirstRevision() ) {
			return [ [], [] ];
		}
		$previous = $this->storage->get( 'PostRevision', $current->getPrevRevisionId() );
		if ( !$previous ) {
			throw new FlowException( 'Expected previous revision of ' . $current->getPrevRevisionId()->getAlphadecimal() );
		}

		$isHidden = self::isHidden( $current );
		$wasHidden = self::isHidden( $previous );

		if ( $isHidden === $wasHidden ) {
			return [ [], [] ];
		}

		// re-run
		$revisions = $this->collectTopicRevisions( $workflow );
		$added = [];
		$removed = [];
		foreach ( $revisions as $revision ) {
			list( $add, $remove ) = $this->calculateChangesFromExisting( $workflow, $revision, $current );
			$added = array_merge( $added, $add );
			$removed = array_merge( $removed, $remove );
		}

		return [ $added, $removed ];
	}

	protected static function isHidden( AbstractRevision $revision ) {
		return $revision->isModerated() && $revision->getModerationState() !== $revision::MODERATED_LOCKED;
	}

	/**
	 * Gets all the 'top' revisions within the topic, namely the posts and the
	 * summary. These are used when a topic changes is visibility via moderation
	 * to add or remove the relevant references.
	 *
	 * @param Workflow $workflow
	 * @return AbstractRevision[]
	 */
	protected function collectTopicRevisions( Workflow $workflow ) {
		$found = $this->treeRepository->fetchSubtreeNodeList( [ $workflow->getId() ] );
		$queries = [];
		foreach ( reset( $found ) as $uuid ) {
			$queries[] = [ 'rev_type_id' => $uuid ];
		}

		$posts = $this->storage->findMulti(
			'PostRevision',
			$queries,
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);

		// we also need the most recent topic summary if it exists
		$summaries = $this->storage->find(
			'PostSummary',
			[ 'rev_type_id' => $workflow->getId() ],
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);

		$result = $summaries;
		// we have to unwrap the posts since we used findMulti, it returns
		// a separate result set for each query
		foreach ( $posts as $found ) {
			$result[] = reset( $found );
		}
		return $result;
	}

	/**
	 * Pulls references from a revision's content
	 *
	 * @param Workflow $workflow The Workflow that the revision is attached to.
	 * @param AbstractRevision $revision The Revision to pull references from.
	 * @param PostRevision|null $root
	 * @return Reference[] Array of References.
	 */
	public function getReferencesFromRevisionContent(
		Workflow $workflow,
		AbstractRevision $revision,
		PostRevision $root = null
	) {
		// Locked is the only moderated state we still collect references for.
		if ( self::isHidden( $revision ) ) {
			return [];
		}

		// We also do not track references in topic titles.
		if ( $revision instanceof PostRevision && $revision->isTopicTitle() ) {
			return [];
		}

		// If this is attached to a topic we also need to check its permissions
		if ( $root === null ) {
			try {
				if ( $revision instanceof PostRevision && !$revision->isTopicTitle() ) {
					$root = $revision->getCollection()->getRoot()->getLastRevision();
				} elseif ( $revision instanceof PostSummary ) {
					$root = $revision->getCollection()->getPost()->getRoot()->getLastRevision();
				}
			} catch ( FlowException $e ) {
				// Do nothing - we're likely in a unit test where no root can
				// be resolved because the revision is created on the fly
			}
		}

		if ( $root && ( self::isHidden( $root ) ) ) {
			return [];
		}

		return $this->referenceExtractor->getReferences(
			$workflow,
			$revision->getRevisionType(),
			$revision->getCollectionId(),
			$revision->getContent( 'html' )
		);
	}

	/**
	 * Retrieves references that are already stored in the database for a given revision
	 *
	 * @param string $revType The value returned from Revision::getRevisionType() for the revision.
	 * @param UUID $objectId The revision's Object ID.
	 * @return Reference[] Array of References.
	 */
	public function getExistingReferences( $revType, UUID $objectId ) {
		$prevWikiReferences = $this->storage->find( 'WikiReference', [
			'ref_src_wiki' => wfWikiID(),
			'ref_src_object_type' => $revType,
			'ref_src_object_id' => $objectId,
		] );

		$prevUrlReferences = $this->storage->find( 'URLReference', [
			'ref_src_wiki' => wfWikiID(),
			'ref_src_object_type' => $revType,
			'ref_src_object_id' => $objectId,
		] );

		return array_merge( (array)$prevWikiReferences, (array)$prevUrlReferences );
	}

	/**
	 * Compares two arrays of references
	 *
	 * Would be protected if not for testing.
	 *
	 * @param Reference[] $old The old references.
	 * @param Reference[] $new The new references.
	 * @return array Array with two elements: added and removed references.
	 */
	public function referencesDifference( array $old, array $new ) {
		$newReferences = [];

		foreach ( $new as $ref ) {
			$newReferences[$ref->getIdentifier()] = $ref;
		}

		$oldReferences = [];

		foreach ( $old as $ref ) {
			$oldReferences[$ref->getIdentifier()] = $ref;
		}

		$addReferences = [];

		foreach ( $newReferences as $identifier => $ref ) {
			if ( !isset( $oldReferences[$identifier] ) ) {
				$addReferences[] = $ref;
			}
		}

		$removeReferences = [];

		foreach ( $oldReferences as $identifier => $ref ) {
			if ( !isset( $newReferences[$identifier] ) ) {
				$removeReferences[] = $ref;
			}
		}

		return [ $addReferences, $removeReferences ];
	}
}
