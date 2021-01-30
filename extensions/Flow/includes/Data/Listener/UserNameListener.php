<?php
/**
 * Provide usernames filtered by per-wiki ipblocks. Batches together
 * database requests for multiple usernames when possible.
 */
namespace Flow\Data\Listener;

use Flow\Repository\UserNameBatch;

/**
 * Listen for loaded objects and pre-load their user id fields into
 * a batch username loader.
 */
class UserNameListener extends AbstractListener {
	protected $batch;
	protected $keys;
	protected $wikiKey;
	protected $wiki;

	/**
	 * @param UserNameBatch $batch
	 * @param array $keys key - a list of keys from storage that contain user ids, value - the wiki for the
	 *   user id lookup, default to $wiki if null
	 * @param string|null $wiki The wikiid to use when $wikiKey is null. If both are null wfWikiID() is used
	 */
	public function __construct( UserNameBatch $batch, array $keys, $wiki = null ) {
		$this->batch = $batch;
		$this->keys = $keys;

		if ( $wiki === null ) {
			$this->wiki = wfWikiID();
		} else {
			$this->wiki = $wiki;
		}
	}

	public function onAfterClear() {
		$this->batch->clear();
	}

	/**
	 * Load any user ids in $row into the username batch
	 * @param object $object
	 * @param array $row
	 */
	public function onAfterLoad( $object, array $row ) {
		foreach ( $this->keys as $userKey => $wikiKey ) {
			// check if the user id key exists in the data array and
			// make sure it has a non-zero value
			if ( isset( $row[$userKey] ) && $row[$userKey] != 0 ) {
				// the wiki for the user id lookup is specified,
				// check if it exists in the data array
				if ( $wikiKey ) {
					if ( !isset( $row[$wikiKey] ) ) {
						wfDebugLog( 'Flow', __METHOD__ . ": could not detect wiki with " . $wikiKey );
						continue;
					}
					$wiki = $row[$wikiKey];
				// no wiki lookup is specified, default to $this->wiki
				} else {
					$wiki = $this->wiki;
				}
				$this->batch->add( $wiki, $row[$userKey] );
			}
		}
	}
}
