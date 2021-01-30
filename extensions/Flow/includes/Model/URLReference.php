<?php

namespace Flow\Model;

use Flow\Exception\InvalidReferenceException;
use Title;

class URLReference extends Reference {
	protected $url;

	/**
	 * @param UUID $id Id of the reference
	 * @param string $wiki Wiki ID of the reference source
	 * @param UUID $srcWorkflow ID of the source Workflow
	 * @param Title $srcTitle Title of the page that the Workflow exists on
	 * @param string $objectType Output of getRevisionType for the AbstractRevision that this reference comes from.
	 * @param UUID $objectId Unique identifier for the revisioned object containing the reference.
	 * @param string $type Type of reference
	 * @param string $url URL of the reference's target.
	 * @throws InvalidReferenceException
	 */
	public function __construct( UUID $id, $wiki, UUID $srcWorkflow, Title $srcTitle, $objectType, UUID $objectId, $type, $url ) {
		$this->url = $url;

		if ( !is_array( wfParseUrl( $url ) ) ) {
			throw new InvalidReferenceException(
				"Invalid URL $url specified for reference " . get_class( $this )
			);
		}

		parent::__construct( $id, $wiki, $srcWorkflow, $srcTitle, $objectType, $objectId, $type );
	}

	/**
	 * Gets the storage row for this URLReference
	 *
	 * @return array
	 */
	public function getStorageRow() {
		return parent::getStorageRow() + [
			'ref_target' => $this->url,
		];
	}

	/**
	 * Instantiates a URLReference object from a storage row.
	 *
	 * @param array $row
	 * @return URLReference
	 */
	public static function fromStorageRow( $row ) {
		// TODO: Remove this UUID::create() call when the field is populated
		// everywhere relevant.
		$id = ( !isset( $row['ref_id'] ) || $row['ref_id'] === null ) ? UUID::create() : UUID::create( $row['ref_id'] );
		$workflow = UUID::create( $row['ref_src_workflow_id'] );
		$objectType = $row['ref_src_object_type'];
		$objectId = UUID::create( $row['ref_src_object_id'] );
		$url = $row['ref_target'];
		$type = $row['ref_type'];
		$srcTitle = Title::makeTitle( $row['ref_src_namespace'], $row['ref_src_title'] );
		$wiki = $row['ref_src_wiki'];

		return new URLReference( $id, $wiki, $workflow, $srcTitle, $objectType, $objectId, $type, $url );
	}

	/**
	 * Gets the storage row from an object.
	 * Helper for BasicObjectMapper.
	 * @param URLReference $object
	 * @return array
	 */
	public static function toStorageRow( URLReference $object ) {
		return $object->getStorageRow();
	}

	public function getUrl() {
		return $this->url;
	}

	public function getTargetIdentifier() {
		return 'url:' . $this->getUrl();
	}
}
