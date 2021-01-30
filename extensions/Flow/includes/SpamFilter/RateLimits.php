<?php

namespace Flow\SpamFilter;

use Flow\Model\AbstractRevision;
use IContextSource;
use Status;
use Title;

class RateLimits implements SpamFilter {
	/**
	 * @param IContextSource $context
	 * @param AbstractRevision $newRevision
	 * @param AbstractRevision|null $oldRevision
	 * @param Title $title
	 * @param Title $ownerTitle
	 * @return Status
	 */
	public function validate(
		IContextSource $context,
		AbstractRevision $newRevision,
		?AbstractRevision $oldRevision,
		Title $title,
		Title $ownerTitle
	) {
		if ( $context->getUser()->pingLimiter( 'edit' ) ) {
			return Status::newFatal( 'actionthrottledtext' );
		}

		return Status::newGood();
	}

	/**
	 * Checks if RateLimits is enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return true;
	}
}
