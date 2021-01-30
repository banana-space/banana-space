<?php

namespace Flow\Formatter;

use Flow\Data\ManagerGroup;
use Flow\Exception\InvalidInputException;
use Flow\Exception\InvalidParameterException;
use Flow\Exception\PermissionException;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;
use Flow\Repository\TreeRepository;
use Flow\RevisionActionPermissions;

abstract class RevisionViewQuery extends AbstractQuery {

	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @param ManagerGroup $storage
	 * @param TreeRepository $treeRepository
	 * @param RevisionActionPermissions $permissions
	 */
	public function __construct(
		ManagerGroup $storage,
		TreeRepository $treeRepository,
		RevisionActionPermissions $permissions
	) {
		parent::__construct( $storage, $treeRepository );
		$this->permissions = $permissions;
	}

	/**
	 * Create a revision based on revisionId
	 * @param UUID|string $revId
	 * @return AbstractRevision
	 */
	abstract protected function createRevision( $revId );

	/**
	 * Get the data for rendering single revision view
	 * @param string $revId
	 * @return FormatterRow|null
	 * @throws InvalidInputException
	 */
	public function getSingleViewResult( $revId ) {
		if ( !$revId ) {
			throw new InvalidParameterException( 'Missing revision' );
		}
		$rev = $this->createRevision( $revId );
		if ( !$rev ) {
			throw new InvalidInputException( 'Could not find revision: ' . $revId, 'missing-revision' );
		}
		$this->loadMetadataBatch( [ $rev ] );
		return $this->buildResult( $rev, null );
	}

	/**
	 * Get the data for rendering revisions diff view
	 * @param UUID $curId
	 * @param UUID|null $prevId
	 * @return FormatterRow[]
	 * @throws InvalidInputException
	 * @throws PermissionException
	 */
	public function getDiffViewResult( UUID $curId, UUID $prevId = null ) {
		$cur = $this->createRevision( $curId );
		if ( !$cur ) {
			throw new InvalidInputException( 'Could not find revision: ' . $curId, 'missing-revision' );
		}
		if ( !$prevId ) {
			$prevId = $cur->getPrevRevisionId();
		}
		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		$prev = $this->createRevision( $prevId );
		if ( !$prev ) {
			throw new InvalidInputException(
				'Could not find revision to compare against: ' . $curId->getAlphadecimal(),
				'missing-revision'
			);
		}
		if ( !$this->isComparable( $cur, $prev ) ) {
			throw new InvalidInputException( 'Attempt to compare revisions of different types', 'revision-comparison' );
		}

		// Re-position old and new revisions if necessary
		if (
			$cur->getRevisionId()->getTimestamp() >
			$prev->getRevisionId()->getTimestamp()
		) {
			$oldRev = $prev;
			$newRev = $cur;
		} else {
			$oldRev = $cur;
			$newRev = $prev;
		}

		if (
			!$this->permissions->isAllowed( $oldRev, 'view' ) ||
			!$this->permissions->isAllowed( $newRev, 'view' )
		) {
			throw new PermissionException( 'Insufficient permission to compare revisions', 'insufficient-permission' );
		}

		$this->loadMetadataBatch( [ $oldRev, $newRev ] );

		return [
			$this->buildResult( $newRev, null ),
			$this->buildResult( $oldRev, null ),
		];
	}

	public function getUndoDiffResult( $startUndoId, $endUndoId ) {
		$start = $this->createRevision( $startUndoId );
		if ( !$start ) {
			throw new InvalidInputException( 'Could not find revision: ' . $startUndoId, 'missing-revision' );
		}
		$end = $this->createRevision( $endUndoId );
		if ( !$end ) {
			throw new InvalidInputException( 'Could not find revision: ' . $endUndoId, 'missing-revision' );
		}

		// the two revision must have the same revision type id
		if ( !$start->getCollectionId()->equals( $end->getCollectionId() ) ) {
			throw new InvalidInputException( 'start and end are not from the same set' );
		}

		$current = $start->getCollection()->getLastRevision();

		if (
			!$this->permissions->isAllowed( $start, 'view' ) ||
			!$this->permissions->isAllowed( $end, 'view' ) ||
			!$this->permissions->isAllowed( $current, 'view' )
		) {
			throw new PermissionException( 'Insufficient permission to undo revisions', 'insufficient-permission' );
		}

		$this->loadMetadataBatch( [ $start, $end, $current ] );

		return [
			$this->buildResult( $start, null ),
			$this->buildResult( $end, null ),
			$this->buildResult( $current, null ),
		];
	}

	public function isComparable( AbstractRevision $cur, AbstractRevision $prev ) {
		if ( $cur->getRevisionType() == $prev->getRevisionType() ) {
			return $cur->getCollectionId()->equals( $prev->getCollectionId() );
		} else {
			return false;
		}
	}
}
