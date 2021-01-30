<?php

namespace Flow\Model;

use Flow\Collection\HeaderCollection;
use User;

/**
 * @Todo - Header is just a summary to the discussion workflow, it could be just
 * migrated to Summary revision with rev_change_type: create-header-summary,
 * edit-header-summary
 */
class Header extends AbstractRevision {

	/**
	 * @var UUID
	 */
	protected $workflowId;

	/**
	 * @param Workflow $workflow
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html
	 * @param string $changeType
	 * @return Header
	 */
	public static function create( Workflow $workflow, User $user, $content, $format, $changeType = 'create-header' ) {
		$obj = new self;
		$obj->revId = UUID::create();
		$obj->workflowId = $workflow->getId();
		$obj->user = UserTuple::newFromUser( $user );
		$obj->prevRevision = null; // no prior revision
		$obj->setContent( $content, $format, $workflow->getArticleTitle() );
		$obj->changeType = $changeType;
		return $obj;
	}

	/**
	 * @param string[] $row
	 * @param Header|null $obj
	 * @return Header
	 */
	public static function fromStorageRow( array $row, $obj = null ) {
		/** @var $obj Header */
		$obj = parent::fromStorageRow( $row, $obj );
		// @phan-suppress-next-line PhanUndeclaredProperty Type not inferred
		$obj->workflowId = UUID::create( $row['rev_type_id'] );
		return $obj;
	}

	/**
	 * @return string
	 */
	public function getRevisionType() {
		return 'header';
	}

	/**
	 * @return UUID
	 */
	public function getWorkflowId() {
		return $this->workflowId;
	}

	/**
	 * @return UUID
	 */
	public function getCollectionId() {
		return $this->getWorkflowId();
	}

	/**
	 * @return HeaderCollection
	 */
	public function getCollection() {
		return HeaderCollection::newFromRevision( $this );
	}

	public function getObjectId() {
		return $this->getWorkflowId();
	}
}
