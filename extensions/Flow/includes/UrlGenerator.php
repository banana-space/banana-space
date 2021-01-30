<?php

namespace Flow;

use Flow\Collection\PostCollection;
use Flow\Data\Mapper\CachingObjectMapper;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidInputException;
use Flow\Model\AbstractRevision;
use Flow\Model\Anchor;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\UUID;
use RecentChange;
use RequestContext;
use SpecialPage;
use Title;

/**
 * Provides url generation capabilities for Flow. Ties together an
 * i18n message with a specific Title, query parameters and fragment.
 *
 * URL generation methods mostly accept either a Title or a UUID
 * representing the Workflow. URL generation methods all return
 * Anchor instances..
 */
class UrlGenerator {
	/**
	 * @var CachingObjectMapper
	 */
	private $workflowMapper;

	public function __construct( CachingObjectMapper $workflowMapper ) {
		$this->workflowMapper = $workflowMapper;
	}

	/**
	 * @param Title|null $title
	 * @param UUID|null $workflowId
	 * @return Title
	 * @throws FlowException
	 */
	protected function resolveTitle( Title $title = null, UUID $workflowId = null ) {
		if ( $title !== null ) {
			return $title;
		}
		if ( $workflowId === null ) {
			throw new FlowException( 'No title or workflow given' );
		}

		$alpha = $workflowId->getAlphadecimal();
		$workflow = $this->workflowMapper->get( [
			'workflow_id' => $alpha,
		] );
		if ( $workflow === null ) {
			throw new InvalidInputException( 'Unloaded workflow:' . $alpha, 'invalid-workflow' );
		}
		return $workflow->getArticleTitle();
	}

	/**
	 * Link to create new topic on a topiclist.
	 *
	 * @param Title|null $title
	 * @param UUID|null $workflowId
	 * @return Anchor
	 */
	public function newTopicLink( Title $title = null, UUID $workflowId = null ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-new' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'new-topic' ]
		);
	}

	/**
	 * Edit the header at the specified workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function editHeaderLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-edit-header' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'edit-header' ]
		);
	}

	/**
	 * View a specific revision of a header workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function headerRevisionLink( ?Title $title, UUID $workflowId, UUID $revId ) {
		return new Anchor(
			wfMessage( 'flow-link-header-revision' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'header_revId' => $revId->getAlphadecimal(),
				'action' => 'view-header'
			]
		);
	}

	/**
	 * View a specific revision of a topic title
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function topicRevisionLink( ?Title $title, UUID $workflowId, UUID $revId ) {
		return new Anchor(
			wfMessage( 'flow-link-topic-revision' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'topic_revId' => $revId->getAlphadecimal(),
				'action' => 'single-view'
			]
		);
	}

	/**
	 * View a specific revision of a post within a topic workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function postRevisionLink(
		?Title $title,
		UUID $workflowId,
		UUID $postId,
		UUID $revId
	) {
		return new Anchor(
			wfMessage( 'flow-link-post-revision' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'topic_postId' => $postId->getAlphadecimal(),
				'topic_revId' => $revId->getAlphadecimal(),
				'action' => 'single-view'
			]
		);
	}

	/**
	 * View a specific revision of topic summary.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function summaryRevisionLink( ?Title $title, UUID $workflowId, UUID $revId ) {
		return new Anchor(
			wfMessage( 'flow-link-summary-revision' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'topicsummary_revId' => $revId->getAlphadecimal(),
				'action' => 'view-topic-summary'
			]
		);
	}

	/**
	 * View the topic at the specified workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function topicLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-link-topic' ),
			$this->resolveTitle( $title, $workflowId )
		);
	}

	/**
	 * View a topic scrolled down to the provided post at the
	 * specified workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @return Anchor
	 */
	public function postLink( ?Title $title, UUID $workflowId, UUID $postId ) {
		return new Anchor(
			wfMessage( 'flow-link-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				// If the post is moderated this will flag the backend to still
				// include the content in the html response.
				'topic_showPostId' => $postId->getAlphadecimal()
			],
			'#flow-post-' . $postId->getAlphadecimal()
		);
	}

	/**
	 * Show the history of a specific post within a topic workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @return Anchor
	 */
	public function postHistoryLink( ?Title $title, UUID $workflowId, UUID $postId ) {
		return new Anchor(
			wfMessage( 'flow-post-action-post-history' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'history',
				'topic_postId' => $postId->getAlphadecimal(),
			]
		);
	}

	/**
	 * Show the history of a workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function workflowHistoryLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-history' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'history' ]
		);
	}

	/**
	 * Show the history of a flow board.
	 *
	 * @param Title $title
	 * @return Anchor
	 */
	public function boardHistoryLink( Title $title ) {
		return new Anchor(
			wfMessage( 'hist' ),
			$title,
			[ 'action' => 'history' ]
		);
	}

	/**
	 * Generate a link to undo the specified revision.  Note that this will only work if
	 * that is the most recent content edit against the revision type.
	 *
	 * @param AbstractRevision $revision The revision to undo.
	 * @param Title|null $title The title the revision belongs to
	 * @param UUID $workflowId The workflow id the revision belongs to
	 * @return Anchor
	 * @throws FlowException When the provided revision is not known
	 */
	public function undoAction( AbstractRevision $revision, ?Title $title, UUID $workflowId ) {
		$startId = $revision->getPrevRevisionId();
		$endId = $revision->getRevisionId();
		if ( $revision instanceof PostRevision ) {
			return $this->undoEditPostAction( $title, $workflowId, $startId, $endId );
		} elseif ( $revision instanceof Header ) {
			return $this->undoEditHeaderAction( $title, $workflowId, $startId, $endId );
		} elseif ( $revision instanceof PostSummary ) {
			return $this->undoEditSummaryAction( $title, $workflowId, $startId, $endId );
		} else {
			throw new FlowException( 'Unknown revision type: ' . get_class( $revision ) );
		}
	}

	/**
	 * @param Title|null $title The title the post belongs to, or null
	 * @param UUID $workflowId The workflowId the post belongs to
	 * @param UUID $startId The revision to start undo from.
	 * @param UUID $endId The revision to stop undoing at
	 * @return Anchor
	 */
	public function undoEditPostAction(
		?Title $title,
		UUID $workflowId,
		UUID $startId,
		UUID $endId
	) {
		return new Anchor(
			wfMessage( 'flow-undo' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'undo-edit-post',
				'topic_startId' => $startId->getAlphadecimal(),
				'topic_endId' => $endId->getAlphadecimal(),
			]
		);
	}

	/**
	 * @param Title|null $title The title the header belongs to, or null
	 * @param UUID $workflowId The workflowId the header belongs to
	 * @param UUID $startId The revision to start undo from.
	 * @param UUID $endId The revision to stop undoing at
	 * @return Anchor
	 */
	public function undoEditHeaderAction(
		?Title $title,
		UUID $workflowId,
		UUID $startId,
		UUID $endId
	) {
		return new Anchor(
			wfMessage( 'flow-undo' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'undo-edit-header',
				'header_startId' => $startId->getAlphadecimal(),
				'header_endId' => $endId->getAlphadecimal(),
			]
		);
	}

	/**
	 * @param Title|null $title The title the summary belongs to, or null
	 * @param UUID $workflowId The workflowId the summary belongs to
	 * @param UUID $startId The revision to start undo from.
	 * @param UUID $endId The revision to stop undoing at
	 * @return Anchor
	 */
	public function undoEditSummaryAction(
		?Title $title,
		UUID $workflowId,
		UUID $startId,
		UUID $endId
	) {
		return new Anchor(
			wfMessage( 'flow-undo' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'undo-edit-topic-summary',
				'topicsummary_startId' => $startId->getAlphadecimal(),
				'topicsummary_endId' => $endId->getAlphadecimal(),
			]
		);
	}

	/**
	 * @param AbstractRevision $revision
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID|null $oldRevId
	 * @return Anchor
	 * @throws FlowException When $revision is not PostRevision, Header or PostSummary
	 */
	public function diffLink(
		AbstractRevision $revision,
		?Title $title,
		UUID $workflowId,
		UUID $oldRevId = null
	) {
		if ( $revision instanceof PostRevision ) {
			return $this->diffPostLink( $title, $workflowId, $revision->getRevisionId(), $oldRevId );
		} elseif ( $revision instanceof Header ) {
			return $this->diffHeaderLink( $title, $workflowId, $revision->getRevisionId(), $oldRevId );
		} elseif ( $revision instanceof PostSummary ) {
			return $this->diffSummaryLink( $title, $workflowId, $revision->getRevisionId(), $oldRevId );
		} else {
			throw new FlowException( 'Unknown revision type: ' . get_class( $revision ) );
		}
	}

	/**
	 * Show the differences between two revisions of a header.
	 *
	 * When $oldRevId is null shows the differences between $revId and the revision
	 * immediately prior.  If $oldRevId is provided shows the differences between
	 * $oldRevId and $revId.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @param UUID|null $oldRevId
	 * @return Anchor
	 */
	public function diffHeaderLink(
		?Title $title,
		UUID $workflowId,
		UUID $revId,
		UUID $oldRevId = null
	) {
		return new Anchor(
			wfMessage( 'diff' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'compare-header-revisions',
				'header_newRevision' => $revId->getAlphadecimal(),
			] + ( $oldRevId === null ? [] : [
				'header_oldRevision' => $oldRevId->getAlphadecimal(),
			] )
		);
	}

	/**
	 * Show the differences between two revisions of a post.
	 *
	 * When $oldRevId is null shows the differences between $revId and the revision
	 * immediately prior.  If $oldRevId is provided shows the differences between
	 * $oldRevId and $revId.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @param UUID|null $oldRevId
	 * @return Anchor
	 */
	public function diffPostLink(
		?Title $title,
		UUID $workflowId,
		UUID $revId,
		UUID $oldRevId = null
	) {
		return new Anchor(
			wfMessage( 'diff' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'compare-post-revisions',
				'topic_newRevision' => $revId->getAlphadecimal(),
			] + ( $oldRevId === null ? [] : [
				'topic_oldRevision' => $oldRevId->getAlphadecimal(),
			] )
		);
	}

	/**
	 * Show the differences between two revisions of a summary.
	 *
	 * When $oldRevId is null shows the differences between $revId and the revision
	 * immediately prior.  If $oldRevId is provided shows the differences between
	 * $oldRevId and $revId.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @param UUID|null $oldRevId
	 * @return Anchor
	 */
	public function diffSummaryLink(
		?Title $title,
		UUID $workflowId,
		UUID $revId,
		UUID $oldRevId = null
	) {
		return new Anchor(
			wfMessage( 'diff' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'compare-postsummary-revisions',
				'topicsummary_newRevision' => $revId->getAlphadecimal(),
			] + ( $oldRevId === null ? [] : [
				'topicsummary_oldRevision' => $oldRevId->getAlphadecimal(),
			] )
		);
	}

	/**
	 * View the specified workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function workflowLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-workflow' ),
			$this->resolveTitle( $title, $workflowId )
		);
	}

	/**
	 * Watch topic link
	 * @todo - replace title with a flow topic namespace topic
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function watchTopicLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'watch' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'watch' ]
		);
	}

	/**
	 * Unwatch topic link
	 * @todo - replace title with a flow topic namespace topic
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function unwatchTopicLink( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'unwatch' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'unwatch' ]
		);
	}

	/**
	 * View the flow board at the specified title
	 *
	 * Makes the assumption the title is flow-enabled.
	 *
	 * @param Title $title
	 * @param string|null $sortBy
	 * @param bool $saveSortBy
	 * @return Anchor
	 */
	public function boardLink( Title $title, $sortBy = null, $saveSortBy = false ) {
		$options = [];

		if ( $sortBy !== null ) {
			$options['topiclist_sortby'] = $sortBy;
			if ( $saveSortBy ) {
				$options['topiclist_savesortby'] = '1';
			}
		}

		return new Anchor(
			$title->getPrefixedText(),
			$title,
			$options
		);
	}

	/**
	 * Reply to an individual post in a topic workflow.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @param bool $isTopLevelReply
	 * @return Anchor
	 */
	public function replyAction(
		?Title $title,
		UUID $workflowId,
		UUID $postId,
		$isTopLevelReply
	) {
		$hash = "#flow-post-{$postId->getAlphadecimal()}";
		if ( $isTopLevelReply ) {
			$hash .= "-form-content";
		}
		return new Anchor(
			wfMessage( 'flow-reply-link' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'reply',
				'topic_postId' => $postId->getAlphadecimal(),
			],
			$hash
		);
	}

	/**
	 * Edit the specified topic summary
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function editTopicSummaryAction( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-summarize-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'edit-topic-summary' ]
		);
	}

	/**
	 * Lock the specified topic
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function lockTopicAction( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-lock-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'lock-topic',
				'flow_moderationState' => AbstractRevision::MODERATED_LOCKED,
			]
		);
	}

	/**
	 * Restore the specified topic to unmoderated status.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param string $moderationAction
	 * @param string $flowAction
	 * @return Anchor
	 */
	public function restoreTopicAction(
		?Title $title,
		UUID $workflowId,
		$moderationAction,
		$flowAction = 'moderate-topic'
	) {
		return new Anchor(
			wfMessage( 'flow-topic-action-' . $moderationAction . '-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => $flowAction,
				'flow_moderationState' => $moderationAction,
			]
		);
	}

	/**
	 * Restore the specified post to unmoderated status.
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @param string $moderationAction
	 * @param string $flowAction
	 * @return Anchor
	 */
	public function restorePostAction(
		?Title $title,
		UUID $workflowId,
		UUID $postId,
		$moderationAction,
		$flowAction = 'moderate-post'
	) {
		return new Anchor(
			wfMessage( 'flow-post-action-' . $moderationAction . '-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => $flowAction,
				'topic_moderationState' => $moderationAction,
				'topic_postId' => $postId->getAlphadecimal(),
			]
		);
	}

	/**
	 * Create a header for the specified page
	 *
	 * @param Title $title
	 * @return Anchor
	 */
	public function createHeaderAction( Title $title ) {
		return new Anchor(
			wfMessage( 'flow-edit-header-link' ),
			$title,
			[ 'action' => 'edit-header' ]
		);
	}

	/**
	 * Edit the specified header
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function editHeaderAction( ?Title $title, UUID $workflowId, UUID $revId ) {
		return new Anchor(
			wfMessage( 'flow-edit-header-link' ),
			$this->resolveTitle( $title, $workflowId ),
			[ 'action' => 'edit-header' ]
		);
	}

	/**
	 * Edit the specified topic title
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function editTitleAction(
		?Title $title,
		UUID $workflowId,
		UUID $postId,
		UUID $revId
	) {
		return new Anchor(
			wfMessage( 'flow-topic-action-edit-title' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'edit-title',
				'topic_postId' => $postId->getAlphadecimal(),
				'topic_format' => 'wikitext',
			]
		);
	}

	/**
	 * Edit the specified post within the specified workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @param UUID $revId
	 * @return Anchor
	 */
	public function editPostAction(
		?Title $title,
		UUID $workflowId,
		UUID $postId,
		UUID $revId
	) {
		return new Anchor(
			wfMessage( 'flow-post-action-edit-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'edit-post',
				'topic_postId' => $postId->getAlphadecimal(),
				// @todo not necessary?
				'topic_revId' => $revId->getAlphadecimal(),
			],
			'#flow-post-' . $postId->getAlphadecimal()

		);
	}

	/**
	 * Hide the specified topic
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function hideTopicAction( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-hide-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-topic',
				'topic_moderationState' => AbstractRevision::MODERATED_HIDDEN,
			]
		);
	}

	/**
	 * Hide the specified post within the specified workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @return Anchor
	 */
	public function hidePostAction( ?Title $title, UUID $workflowId, UUID $postId ) {
		return new Anchor(
			wfMessage( 'flow-post-action-hide-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-post',
				'topic_postId' => $postId->getAlphadecimal(),
				'topic_moderationState' => AbstractRevision::MODERATED_HIDDEN,
			]
		);
	}

	/**
	 * Delete the specified topic workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function deleteTopicAction( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-delete-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-topic',
				'topic_moderationState' => AbstractRevision::MODERATED_DELETED,
			]
		);
	}

	/**
	 * Delete the specified post within the specified workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @return Anchor
	 */
	public function deletePostAction( ?Title $title, UUID $workflowId, UUID $postId ) {
		return new Anchor(
			wfMessage( 'flow-post-action-delete-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-post',
				'topic_postId' => $postId->getAlphadecimal(),
				'topic_moderationState' => AbstractRevision::MODERATED_DELETED,
			]
		);
	}

	/**
	 * Suppress the specified topic workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @return Anchor
	 */
	public function suppressTopicAction( ?Title $title, UUID $workflowId ) {
		return new Anchor(
			wfMessage( 'flow-topic-action-suppress-topic' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-topic',
				'topic_moderationState' => AbstractRevision::MODERATED_SUPPRESSED,
			]
		);
	}

	/**
	 * Suppress the specified post within the specified workflow
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param UUID $postId
	 * @return Anchor
	 */
	public function suppressPostAction( ?Title $title, UUID $workflowId, UUID $postId ) {
		return new Anchor(
			wfMessage( 'flow-post-action-suppress-post' ),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'moderate-post',
				'topic_postId' => $postId->getAlphadecimal(),
				'topic_moderationState' => AbstractRevision::MODERATED_SUPPRESSED,
			]
		);
	}

	public function newTopicAction( Title $title = null, UUID $workflowId = null ) {
		return new Anchor(
			wfMessage( 'flow-newtopic-start-placeholder' ),
			// resolveTitle doesn't accept null uuid
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'new-topic'
			]
		);
	}

	public function thankAction( UUID $postId ) {
		$sender = RequestContext::getMain()->getUser();
		$recipient = $sender; // Default to current user's gender if we can't find the recipient
		$postCollection = PostCollection::newFromId( $postId );
		$postRevision = $postCollection->getLastRevision();
		$recipient = $postRevision->getCreatorTuple()->createUser();

		return new Anchor(
			wfMessage( 'flow-thank-link', $sender, $recipient )->text(),
			SpecialPage::getTitleFor( 'Thanks', 'Flow/' . $postId->getAlphadecimal() ),
			[],
			null,
			wfMessage( 'flow-thank-link-title', $sender, $recipient )->text()
		);
	}

	/**
	 * Mark a revision as patrolled
	 *
	 * @param Title|null $title
	 * @param UUID $workflowId
	 * @param RecentChange $rc
	 * @param string $token
	 * @return Anchor
	 * @throws FlowException
	 * @throws InvalidInputException
	 */
	public function markRevisionPatrolledAction(
		?Title $title,
		UUID $workflowId,
		RecentChange $rc,
		$token
	) {
		return new Anchor(
			wfMessage( 'flow-mark-revision-patrolled-link-text' )->text(),
			$this->resolveTitle( $title, $workflowId ),
			[
				'action' => 'markpatrolled',
				'rcid' => $rc->getAttribute( 'rc_id' ),
				'token' => $token,
			]
		);
	}
}
