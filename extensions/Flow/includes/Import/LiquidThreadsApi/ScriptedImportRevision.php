<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\IImportObject;
use Flow\Import\IObjectRevision;
use User;

/*
 * Represents a revision the script makes on its own behalf, using a script user
 */
class ScriptedImportRevision implements IObjectRevision {
	/** @var IImportObject */
	protected $parent;

	/** @var User */
	protected $destinationScriptUser;

	/** @var string */
	protected $revisionText;

	/** @var string */
	protected $timestamp;

	/**
	 * Creates a ScriptedImportRevision with the given timestamp, given a script user
	 * and arbitrary text.
	 *
	 * @param IImportObject $parentObject Object this is a revision of
	 * @param User $destinationScriptUser User that performed this scripted edit
	 * @param string $revisionText Text of revision
	 * @param IObjectRevision $baseRevision Base revision, used only for timestamp generation
	 */
	public function __construct( IImportObject $parentObject, User $destinationScriptUser, $revisionText, $baseRevision ) {
		$this->parent = $parentObject;
		$this->destinationScriptUser = $destinationScriptUser;
		$this->revisionText = $revisionText;

		$baseTimestamp = (int)wfTimestamp( TS_UNIX, $baseRevision->getTimestamp() );

		// Set a minute after.  If it uses $baseTimestamp again, there can be time
		// collisions.
		$this->timestamp = wfTimestamp( TS_UNIX, $baseTimestamp + 60 );
	}

	public function getText() {
		return $this->revisionText;
	}

	public function getTimestamp() {
		return $this->timestamp;
	}

	public function getAuthor() {
		return $this->destinationScriptUser->getName();
	}

	// XXX: This is called but never used, but if it were, including getText and getAuthor in
	// the key might not be desirable, because we don't necessarily want to re-import
	// the revision when these change.
	public function getObjectKey() {
		return $this->parent->getObjectKey() . ':rev:scripted:' . md5(
				$this->getText() . $this->getAuthor()
			);
	}
}
