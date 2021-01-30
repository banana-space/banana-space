<?php

namespace Flow;

use Flow\Model\Workflow;
use Status;
use Title;
use User;
use WikiPage;

interface OccupationController {
	/**
	 * @param WikiPage $wikipage
	 * @param Workflow $workflow
	 * @return Status
	 */
	public function ensureFlowRevision( WikiPage $wikipage, Workflow $workflow );

	/**
	 * Checks whether creation is technically possible.
	 *
	 * This considers all issues other than the user.
	 *
	 * @param Title $title Title to check
	 * @param bool $mustNotExist Whether the page is required to not exist; true means
	 *  it must not exist.
	 * @return Status Status indicating whether the creation is technically allowed
	 */
	public function checkIfCreationIsPossible( Title $title, $mustNotExist = true );

	/**
	 * Check if user has permission to create board.
	 *
	 * @param Title $title Title to check
	 * @param User $user User doing creation or move
	 * @return Status Status indicating whether the creation is technically allowed
	 */
	public function checkIfUserHasPermission( Title $title, User $user );

	/**
	 * Checks whether the given user is allowed to create a board at the given
	 * title.  If so, allows it to be created.
	 *
	 * @param Title $title Title to check
	 * @param User $user User who wants to create a board
	 * @param bool $mustNotExist Whether the page is required to not exist; defaults to
	 *   true.
	 * @return Status Returns successful status when the provided user has the rights to
	 *  convert $title from whatever it is now to a flow board; otherwise, specifies
	 *  the error.
	 */
	public function safeAllowCreation( Title $title, User $user, $mustNotExist = true );

	/**
	 * Allows creation, *WITHOUT* checks.
	 *
	 * checkIfCreationIsPossible *MUST* be called earlier, and
	 * checkIfUserHasPermission *MUST* be called earlier except when permission checks
	 * are deliberately being bypassed (very rare cases like global rename)
	 *
	 * @param Title $title
	 */
	public function forceAllowCreation( Title $title );

	/**
	 * Gives a user object used to manage talk pages
	 *
	 * @return User User to manage talkpages
	 * @throws \MWException If a user cannot be created.
	 */
	public function getTalkpageManager();
}
