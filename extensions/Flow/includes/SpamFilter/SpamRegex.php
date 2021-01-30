<?php

namespace Flow\SpamFilter;

use Flow\Model\AbstractRevision;
use IContextSource;
use Status;
use Title;

class SpamRegex implements SpamFilter {
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
		global $wgSpamRegex;

		/*
		 * This should not cause an extra conversion; SpamRegex checks will be
		 * performed upon submitting new content.  Content is always either
		 * submitted in (topic-title-)wikitext, or submitted in HTML, but
		 * immediately converted to wikitext and then treated as such. It will only
		 * be transformed once it's being saved to DB.
		 */
		$text = $newRevision->getContentInWikitext();

		// back compat, $wgSpamRegex may be a single string or an array of regexes
		$regexes = (array)$wgSpamRegex;

		foreach ( $regexes as $regex ) {
			if ( preg_match( $regex, $text, $matches ) ) {
				return Status::newFatal( 'spamprotectionmatch', $matches[0] );
			}
		}

		return Status::newGood();
	}

	/**
	 * Checks if SpamRegex is enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		global $wgSpamRegex;
		return (bool)$wgSpamRegex;
	}
}
