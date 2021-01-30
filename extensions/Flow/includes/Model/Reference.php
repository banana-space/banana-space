<?php

namespace Flow\Model;

use Flow\Exception\InvalidParameterException;
use Title;

abstract class Reference {
	public const TYPE_LINK = 'link';

	/**
	 * @var UUID
	 */
	protected $id;

	/**
	 * @var UUID
	 */
	protected $workflowId;

	/**
	 * @var Title
	 */
	protected $srcTitle;

	/**
	 * @var String
	 */
	protected $objectType;

	/**
	 * @var UUID
	 */
	protected $objectId;

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var string
	 */
	protected $wikiId;

	protected $validTypes = [ self::TYPE_LINK ];

	/**
	 * Standard constructor. Called from subclasses only
	 *
	 * @param UUID $id Id of the reference
	 * @param string $wiki Wiki ID of the reference source
	 * @param UUID $srcWorkflow Source Workflow's ID
	 * @param Title $srcTitle Title of the Workflow from which this reference comes.
	 * @param string $objectType Output of getRevisionType for the AbstractRevision that this reference comes from.
	 * @param UUID $objectId Unique identifier for the revisioned object containing the reference.
	 * @param string $type The type of reference
	 * @throws InvalidParameterException
	 */
	protected function __construct( UUID $id, $wiki, UUID $srcWorkflow, Title $srcTitle, $objectType, UUID $objectId, $type ) {
		$this->id = $id;
		$this->wikiId = $wiki;
		$this->workflowId = $srcWorkflow;
		$this->objectType = $objectType;
		$this->objectId = $objectId;
		$this->type = $type;
		$this->srcTitle = $srcTitle;

		if ( !in_array( $type, $this->validTypes ) ) {
			throw new InvalidParameterException(
				"Invalid type $type specified for reference " . get_class( $this )
			);
		}
	}

	/**
	 * Returns the wiki ID of the wiki on which the reference appears
	 * @return string Wiki ID
	 */
	public function getSrcWiki() {
		return $this->wikiId;
	}

	/**
	 * Gives the UUID of the source Workflow
	 *
	 * @return UUID
	 */
	public function getWorkflowId() {
		return $this->workflowId;
	}

	/**
	 * Gives the Title from which this Reference comes.
	 *
	 * @return Title
	 */
	public function getSrcTitle() {
		return $this->srcTitle;
	}

	/**
	 * Gives the object type of the source object.
	 * @return string
	 */
	public function getObjectType() {
		return $this->objectType;
	}

	/**
	 * Gives the UUID of the source object
	 *
	 * @return UUID
	 */
	public function getObjectId() {
		return $this->objectId;
	}

	/**
	 * Gives the type of Reference
	 *
	 * @return string
	 */
	public function getType() {
		return $this->type;
	}

	/**
	 * Returns the storage row for this Reference.
	 * For this abstract reference, only partial.
	 *
	 * @return array
	 */
	public function getStorageRow() {
		return [
			'ref_id' => $this->id->getAlphadecimal(),
			'ref_src_wiki' => $this->wikiId,
			'ref_src_workflow_id' => $this->workflowId->getAlphadecimal(),
			'ref_src_namespace' => $this->srcTitle->getNamespace(),
			'ref_src_title' => $this->srcTitle->getDBkey(),
			'ref_src_object_type' => $this->objectType,
			'ref_src_object_id' => $this->objectId->getAlphadecimal(),
			'ref_type' => $this->type,
		];
	}

	/**
	 * @return string Unique string identifier for the target of this reference.
	 */
	abstract public function getTargetIdentifier();

	public function getIdentifier() {
		return $this->getType() . ':' . $this->getTargetIdentifier();
	}

	public function getUniqueIdentifier() {
		return $this->getSrcTitle() . '|' .
			$this->getObjectType() . '|' .
			$this->getObjectId()->getAlphadecimal() . '|' .
			$this->getIdentifier();
	}

	/**
	 * We don't have a real PK (see comment in
	 * ReferenceClarifier::loadReferencesForPage) but I'll do a array_unique on
	 * multiple Reference objects, just to make sure we have no duplicates.
	 * But to be able to do an array_unique, the objects will be compared as
	 * strings.
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->getUniqueIdentifier();
	}
}
