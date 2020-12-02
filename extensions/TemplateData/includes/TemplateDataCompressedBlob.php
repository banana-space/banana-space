<?php
/**
 * @file
 * @ingroup Extensions
 */

/**
 * Represents the information about a template,
 * coming from the JSON blob in the <templatedata> tags
 * on wiki pages.
 * This implementation stores the information as a compressed gzip blob
 * in the database.
 *
 * @class
 */
class TemplateDataCompressedBlob extends TemplateDataBlob {
	// Size of MySQL 'blob' field; page_props table where the data is stored uses one.
	private const MAX_LENGTH = 65535;

	/**
	 * @var string|null In-object cache for getJSONForDatabase()
	 */
	protected $jsonDB = null;

	/**
	 * Parse the data, normalise it and validate it.
	 *
	 * See Specification.md for the expected format of the JSON object.
	 * @return Status
	 */
	protected function parse() {
		$status = parent::parse();
		if ( $status->isOK() ) {
			$length = strlen( $this->getJSONForDatabase() );
			if ( $length > self::MAX_LENGTH ) {
				return Status::newFatal( 'templatedata-invalid-length', $length, self::MAX_LENGTH );
			}
		}
		return $status;
	}

	/**
	 * @return string JSON (gzip compressed)
	 */
	public function getJSONForDatabase() {
		if ( $this->jsonDB === null ) {
			// Cache for repeat calls
			$this->jsonDB = gzencode( $this->getJSON() );
		}
		return $this->jsonDB;
	}

	/**
	 * Just initialize the data, compression to be done later.
	 *
	 * @param stdClass|null $data Template data
	 */
	protected function __construct( $data = null ) {
		$this->data = $data;
		$this->jsonDB = null;
	}
}
