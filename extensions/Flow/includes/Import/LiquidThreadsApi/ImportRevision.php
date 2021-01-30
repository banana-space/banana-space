<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\IImportObject;
use Flow\Import\IObjectRevision;
use User;

class ImportRevision implements IObjectRevision {
	/** @var IImportObject */
	protected $parent;

	/** @var array */
	protected $apiResponse;

	/**
	 * @var User Account used when the imported revision is by a suppressed user
	 */
	protected $scriptUser;

	/**
	 * Creates an ImportRevision based on a MW page revision
	 *
	 * @param array $apiResponse An element from api.query.revisions
	 * @param IImportObject $parentObject
	 * @param User $scriptUser Account used when the imported revision is by a suppressed user
	 */
	public function __construct( array $apiResponse, IImportObject $parentObject, User $scriptUser ) {
		$this->apiResponse = $apiResponse;
		$this->parent = $parentObject;
		$this->scriptUser = $scriptUser;
	}

	/**
	 * @return string
	 */
	public function getText() {
		$contentKey = 'content';

		$content = $this->apiResponse[$contentKey];

		if ( isset( $this->apiResponse['userhidden'] ) ) {
			$template = wfMessage(
				'flow-importer-lqt-suppressed-user-template'
			)->inContentLanguage()->plain();

			$content .= "\n\n{{{$template}}}";
		}

		return $content;
	}

	public function getTimestamp() {
		return wfTimestamp( TS_MW, $this->apiResponse['timestamp'] );
	}

	public function getAuthor() {
		if ( isset( $this->apiResponse['userhidden'] ) ) {
			return $this->scriptUser->getName();
		} else {
			return $this->apiResponse['user'];
		}
	}

	public function getObjectKey() {
		return $this->parent->getObjectKey() . ':rev:' . $this->apiResponse['revid'];
	}
}
