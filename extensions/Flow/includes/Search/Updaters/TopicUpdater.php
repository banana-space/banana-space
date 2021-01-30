<?php

namespace Flow\Search\Updaters;

use Flow\Collection\PostSummaryCollection;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Repository\RootPostLoader;
use Flow\RevisionActionPermissions;
use Flow\Search\Connection;
use Flow\Search\Iterators\AbstractIterator;
use Sanitizer;

class TopicUpdater extends AbstractUpdater {
	/**
	 * @var RootPostLoader
	 */
	protected $rootPostLoader;

	/**
	 * @param AbstractIterator $iterator
	 * @param RevisionActionPermissions $permissions
	 * @param RootPostLoader $rootPostLoader
	 */
	public function __construct( AbstractIterator $iterator, RevisionActionPermissions $permissions, RootPostLoader $rootPostLoader ) {
		parent::__construct( $iterator, $permissions );
		$this->rootPostLoader = $rootPostLoader;
	}

	/**
	 * @inheritDoc
	 */
	public function getTypeName() {
		return Connection::TOPIC_TYPE_NAME;
	}

	/**
	 * @inheritDoc
	 * @suppress PhanTypeMismatchArgument Phan infers AbstractRevision
	 */
	public function buildDocument( AbstractRevision /* PostRevision */ $revision ) {
		/** @var PostRevision $revision */

		// get timestamp from the most recent revision
		$updateTimestamp = $revision->getCollection()->getWorkflow()->getLastUpdatedObj();
		// timestamp for initial topic post
		$creationTimestamp = $revision->getCollectionId()->getTimestampObj();

		// get content from all child posts in a [post id => [data]] array
		$revisions = $this->getRevisionsData( $revision );

		// find summary for this topic & add it as revision
		$summaryCollection = PostSummaryCollection::newFromId( $revision->getCollectionId() );
		try {
			/** @var PostSummary $summaryRevision */
			$summaryRevision = $summaryCollection->getLastRevision();
			$data = current( $this->getRevisionsData( $summaryRevision ) );
			if ( $data !== false ) {
				$revisions[] = $data;
			}
		} catch ( \Exception $e ) {
			// no summary - that's ok!
		}

		// get board title associated with this revision
		$title = $revision->getCollection()->getWorkflow()->getOwnerTitle();

		$doc = new \Elastica\Document(
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

		return $doc;
	}

	/**
	 * Recursively get the data for all children. This will add the revision's
	 * content to the results array, with the post ID as key.
	 *
	 * @param PostRevision|PostSummary $revision
	 * @return array
	 */
	public function getRevisionsData( /* PostRevision|PostSummary */ $revision ) {
		// store type of revision so we can also search for very specific types
		// (e.g. titles only)
		// possible values will be:
		// * title
		// * post
		// * post-summary
		$type = $revision->getRevisionType();
		if ( method_exists( $revision, 'isTopicTitle' ) && $revision->isTopicTitle() ) {
			$type = 'title';
		}

		$data = [];

		if ( $this->permissions->isAllowed( $revision, 'view' ) ) {
			$data[] = [
				'id' => $revision->getCollectionId()->getAlphadecimal(),
				'text' => trim( Sanitizer::stripAllTags( $revision->getContentInHtml() ) ),
				'source_text' => $revision->getContentInWikitext(),
				'moderation_state' => $revision->getModerationState(),
				'timestamp' => $revision->getCollectionId()->getTimestamp( TS_ISO_8601 ),
				'update_timestamp' => $revision->getRevisionId()->getTimestamp( TS_ISO_8601 ),
				'type' => $type,
			];
		}

		if ( $revision instanceof PostRevision ) {
			// get data from all child posts too
			foreach ( $revision->getChildren() as $child ) {
				$data = array_merge( $data, $this->getRevisionsData( $child ) );
			}
		}

		return $data;
	}
}
