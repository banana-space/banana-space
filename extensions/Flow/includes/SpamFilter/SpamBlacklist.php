<?php

namespace Flow\SpamFilter;

use BaseBlacklist;
use ExtensionRegistry;
use Flow\Model\AbstractRevision;
use IContextSource;
use MediaWiki\MediaWikiServices;
use Status;
use Title;

class SpamBlacklist implements SpamFilter {
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
		$spamObj = BaseBlacklist::getInstance( 'spam' );
		if ( !$spamObj instanceof \SpamBlacklist ) {
			wfWarn( __METHOD__ . ': Expected a SpamBlacklist instance but instead received: ' . get_class( $spamObj ) );
			return Status::newFatal( 'something' );
		}

		// TODO: This seems to check topic titles.  Should it?  There can't
		// actually be a link in a topic title, but http://spam.com can still look
		// spammy even if it's not a working link.
		$links = $this->getLinks( $newRevision, $title );
		$matches = $spamObj->filter( $links, $title );

		if ( $matches !== false ) {
			$status = Status::newFatal( 'spamprotectiontext' );

			foreach ( $matches as $match ) {
				$status->fatal( 'spamprotectionmatch', $match );
			}

			return $status;
		}

		return Status::newGood();
	}

	/**
	 * @param AbstractRevision $revision
	 * @param Title $title
	 * @return array
	 */
	public function getLinks( AbstractRevision $revision, Title $title ) {
		$options = new \ParserOptions;
		$output = MediaWikiServices::getInstance()->getParser()
			->parse( $revision->getContentInWikitext(), $title, $options );
		return array_keys( $output->getExternalLinks() );
	}

	/**
	 * Checks if SpamBlacklist is enabled.
	 *
	 * @return bool
	 */
	public function enabled() {
		return ExtensionRegistry::getInstance()->isLoaded( 'SpamBlacklist' );
	}
}
