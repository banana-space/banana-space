<?php

namespace Flow\Model;

/**
 * We share uuid for different entities in some cases. If both entities happen
 * to have a summary, it's not easy to distinguish them with the same rev_type
 */
abstract class AbstractSummary extends AbstractRevision {

	/**
	 * The id of the entity to be summarized
	 * @var UUID
	 */
	protected $summaryTargetId;

	public static function fromStorageRow( array $row, $obj = null ) {
		/** @var $obj AbstractSummary */
		$obj = parent::fromStorageRow( $row, $obj );
		// @phan-suppress-next-line PhanUndeclaredProperty Type not inferred
		$obj->summaryTargetId = UUID::create( $row['rev_type_id'] );
		return $obj;
	}

	/**
	 * @return UUID
	 */
	public function getSummaryTargetId() {
		return $this->summaryTargetId;
	}

	/**
	 * @return UUID
	 */
	public function getCollectionId() {
		return $this->getSummaryTargetId();
	}

}
