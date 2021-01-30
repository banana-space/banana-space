<?php

namespace Flow\Model;

use Flow\Exception\InvalidInputException;
use Title;

class WikiReference extends Reference {
	public const TYPE_FILE = 'file';
	public const TYPE_TEMPLATE = 'template';
	public const TYPE_CATEGORY = 'category';

	protected $target;

	/**
	 * @param UUID $id Id of the reference
	 * @param string $wiki Wiki ID of the reference source
	 * @param UUID $srcWorkflow ID of the source Workflow
	 * @param Title $srcTitle Title of the Workflow from which this reference comes.
	 * @param string $objectType Output of getRevisionType for the AbstractRevision that this
	 *   reference comes from.
	 * @param UUID $objectId Unique identifier for the revisioned object containing the reference.
	 * @param string $type Type of reference
	 * @param Title $targetTitle Title of the reference's target.
	 */
	public function __construct(
		UUID $id,
		$wiki,
		UUID $srcWorkflow,
		Title $srcTitle,
		$objectType,
		UUID $objectId,
		$type,
		Title $targetTitle
	) {
		$this->target = $targetTitle;

		$this->validTypes = array_merge( $this->validTypes,
			[
				self::TYPE_FILE,
				self::TYPE_TEMPLATE,
				self::TYPE_CATEGORY,
			]
		);

		parent::__construct( $id, $wiki, $srcWorkflow, $srcTitle, $objectType, $objectId, $type );
	}

	/**
	 * Gets the storage row for this WikiReference
	 *
	 * @return array
	 */
	public function getStorageRow() {
		return parent::getStorageRow() + [
			'ref_target_namespace' => $this->target->getNamespace(),
			'ref_target_title' => $this->target->getDBkey(),
		];
	}

	/**
	 * Instantiates a WikiReference object from a storage row.
	 *
	 * @param array $row
	 * @return WikiReference
	 */
	public static function fromStorageRow( $row ) {
		// TODO: Remove this UUID::create() call when the field is populated
		// everywhere relevant.
		$id = ( !isset( $row['ref_id'] ) || $row['ref_id'] === null )
			? UUID::create() : UUID::create( $row['ref_id'] );
		$workflow = UUID::create( $row['ref_src_workflow_id'] );
		$objectType = $row['ref_src_object_type'];
		$objectId = UUID::create( $row['ref_src_object_id'] );
		$srcTitle = self::makeTitle( $row['ref_src_namespace'], $row['ref_src_title'] );
		$targetTitle = self::makeTitle( $row['ref_target_namespace'], $row['ref_target_title'] );
		$type = $row['ref_type'];
		$wiki = $row['ref_src_wiki'];

		return new WikiReference(
			$id, $wiki, $workflow, $srcTitle, $objectType, $objectId, $type, $targetTitle
		);
	}

	/**
	 * Gets the storage row from an object.
	 * Helper for BasicObjectMapper.
	 * @param WikiReference $object
	 * @return array
	 */
	public static function toStorageRow( WikiReference $object ) {
		return $object->getStorageRow();
	}

	/**
	 * Gets a Title given a namespace number and title text
	 *
	 * Many loaded references typically point to the same Title, so we cache those
	 * instead of generating a bunch of duplicate title classes.
	 *
	 * @param int $namespace Namespace number
	 * @param string $title Title text
	 * @return Title|null
	 */
	public static function makeTitle( $namespace, $title ) {
		try {
			return Workflow::getFromTitleCache( wfWikiID(), $namespace, $title );
		} catch ( InvalidInputException $e ) {
			// duplicate Title::makeTitleSafe which returns null on failure,
			// but only for InvalidInputException

			wfDebugLog( 'Flow', __METHOD__ . ": Invalid title.  Namespace: $namespace, Title text: $title" );

			return null;
		}
	}

	public function getTitle() {
		return $this->target;
	}

	public function getTargetIdentifier() {
		return 'title:' . $this->getTitle()->getPrefixedDBkey();
	}
}
