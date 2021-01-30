<?php

namespace Flow\Search\Updaters;

use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Search\Connection;
use Sanitizer;

class HeaderUpdater extends AbstractUpdater {
	/**
	 * @inheritDoc
	 */
	public function getTypeName() {
		return Connection::HEADER_TYPE_NAME;
	}

	/**
	 * @inheritDoc
	 */
	public function buildDocument( AbstractRevision /* Header */ $revision ) {
		/** @var Header $revision */

		// get article title associated with this revision
		$title = $revision->getCollection()->getWorkflow()->getArticleTitle();

		$format = 'html';

		$creationTimestamp = $revision->getCollectionId()->getTimestampObj();
		$updateTimestamp = $revision->getRevisionId()->getTimestampObj();

		$revisions = [];
		if ( $this->permissions->isAllowed( $revision, 'view' ) ) {
			$revisions[] = [
				'id' => $revision->getCollectionId()->getAlphadecimal(),
				'text' => trim( Sanitizer::stripAllTags( $revision->getContent( $format ) ) ),
				'source_text' => $revision->getContent( 'wikitext' ), // for insource: searches
				// headers can't (currently) be moderated, so should always be MODERATED_NONE
				'moderation_state' => $revision->getModerationState(),
				'timestamp' => $creationTimestamp->getTimestamp( TS_ISO_8601 ),
				'update_timestamp' => $updateTimestamp->getTimestamp( TS_ISO_8601 ),
				'type' => $revision->getRevisionType(),
			];
		}

		// for consistency with topics, headers will also get "revisions",
		// although there's always only 1 revision per document (unlike topics,
		// which may have multiple sub-posts)
		return new \Elastica\Document(
			$revision->getCollectionId()->getAlphadecimal(),
			[
				'namespace' => $title->getNamespace(),
				'namespace_text' => $title->getPageLanguage()->getFormattedNsText( $title->getNamespace() ),
				'pageid' => $title->getArticleID(),
				'title' => $title->getText(),
				'timestamp' => $creationTimestamp->getTimestamp( TS_ISO_8601 ),
				'update_timestamp' => $updateTimestamp->getTimestamp( TS_ISO_8601 ),
				'revisions' => $revisions,
			]
		);
	}
}
