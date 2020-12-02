<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use stdClass;

class DataBag {
	/**
	 * @var array A map of node data-object-id ids to data objects.
	 * This map is used during DOM processing to avoid having to repeatedly
	 * json-parse/json-serialize data-parsoid and data-mw attributes.
	 * This map is initialized when a DOM is created/parsed/refreshed.
	 */
	private $dataObject;

	/** @var int An id counter for this document used for the dataObject map */
	private $docId;

	/** @var stdClass the page bundle object into which all data-parsoid and data-mw
	 * attributes will be extracted to for pagebundle API requests.
	 */
	private $pageBundle;

	public function __construct() {
		$this->dataObject = [];
		$this->docId = 0;
		$this->pageBundle = (object)[
			"parsoid" => PHPUtils::arrayToObject( [ "counter" => -1, "ids" => [] ] ),
			"mw" => PHPUtils::arrayToObject( [ "ids" => [] ] )
		];
	}

	/**
	 * Return this document's pagebundle object
	 * @return stdClass
	 */
	public function getPageBundle(): stdClass {
		return $this->pageBundle;
	}

	/**
	 * Get the data object for the node with data-object-id 'docId'.
	 * This will return null if a non-existent docId is provided.
	 *
	 * @param int $docId
	 * @return stdClass|null
	 */
	public function getObject( int $docId ): ?stdClass {
		return $this->dataObject[$docId] ?? null;
	}

	/**
	 * Stash the data and return an id for retrieving it later
	 * @param stdClass $data
	 * @return int
	 */
	public function stashObject( stdClass $data ): int {
		$docId = $this->docId++;
		$this->dataObject[$docId] = $data;
		return $docId;
	}
}
