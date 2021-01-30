<?php

namespace Flow\Block;

use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Data\Pager\HistoryPager;
use Flow\Exception\DataModelException;
use Flow\Exception\FailCommitException;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidActionException;
use Flow\Exception\InvalidDataException;
use Flow\Exception\InvalidInputException;
use Flow\Exception\PermissionException;
use Flow\Formatter\PostHistoryQuery;
use Flow\Formatter\RevisionFormatter;
use Flow\Formatter\RevisionViewQuery;
use Flow\Formatter\TopicHistoryQuery;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Notifications\Controller;
use Flow\Repository\RootPostLoader;
use MediaWiki\MediaWikiServices;
use Message;

class TopicBlock extends AbstractBlock {

	/**
	 * @var PostRevision|null
	 */
	protected $root;

	/**
	 * @var PostRevision|null
	 */
	protected $topicTitle;

	/**
	 * @var RootPostLoader|null
	 */
	protected $rootLoader;

	/**
	 * @var PostRevision|null
	 */
	protected $newRevision;

	/**
	 * @var array
	 */
	protected $requestedPost = [];

	/**
	 * @var array Map of data to be passed on as
	 *  commit metadata for event handlers
	 */
	protected $extraCommitMetadata = [];

	protected $supportedPostActions = [
		// Standard editing
		'edit-post', 'reply',
		// Moderation
		'moderate-topic',
		'moderate-post',
		// lock or unlock topic
		'lock-topic',
		// Other stuff
		'edit-title',
		'undo-edit-post',
	];

	protected $supportedGetActions = [
		'reply', 'view', 'history', 'edit-post', 'edit-title', 'compare-post-revisions', 'single-view',
		'view-topic', 'view-topic-history', 'view-post', 'view-post-history', 'undo-edit-post',
		'moderate-topic', 'moderate-post', 'lock-topic',
	];

	// @Todo - fill in the template names
	protected $templates = [
		'single-view' => 'single_view',
		'view' => '',
		'reply' => '',
		'history' => 'history',
		'edit-post' => '',
		'undo-edit-post' => 'undo_edit',
		'edit-title' => 'edit_title',
		'compare-post-revisions' => 'diff_view',
		'moderate-topic' => 'moderate_topic',
		'moderate-post' => 'moderate_post',
		'lock-topic' => 'lock',
	];

	public function __construct( Workflow $workflow, ManagerGroup $storage, $root ) {
		parent::__construct( $workflow, $storage );
		if ( $root instanceof PostRevision ) {
			$this->root = $root;
		} elseif ( $root instanceof RootPostLoader ) {
			$this->rootLoader = $root;
		} else {
			throw new DataModelException(
				'Expected PostRevision or RootPostLoader, received: ' .
					( is_object( $root ) ? get_class( $root ) : gettype( $root ) ),
				'invalid-input'
			);
		}
	}

	protected function validate() {
		$topicTitle = $this->loadTopicTitle();
		if ( !$topicTitle ) {
			// permissions issue, self::loadTopicTitle should have added appropriate
			// error messages already.
			return;
		}

		switch ( $this->action ) {
		case 'edit-title':
			$this->validateEditTitle();
			break;

		case 'reply':
			$this->validateReply();
			break;

		case 'moderate-topic':
		case 'lock-topic':
			$this->validateModerateTopic();
			break;

		case 'moderate-post':
			$this->validateModeratePost();
			break;

		case 'restore-post':
			// @todo still necessary?
			$this->validateModeratePost();
			break;

		case 'undo-edit-post':
		case 'edit-post':
			$this->validateEditPost();
			break;

		case 'edit-topic-summary':
			// pseudo-action does not do anything, only includes data in api response
			break;

		default:
			throw new InvalidActionException( "Unexpected action: {$this->action}", 'invalid-action' );
		}
	}

	protected function validateEditTitle() {
		if ( $this->workflow->isNew() ) {
			$this->addError( 'content', $this->context->msg( 'flow-error-no-existing-workflow' ) );
			return;
		}
		if ( !isset( $this->submitted['content'] ) || !is_string( $this->submitted['content'] ) ) {
			$this->addError( 'content', $this->context->msg( 'flow-error-missing-title' ) );
			return;
		}
		$this->submitted['content'] = trim( $this->submitted['content'] );
		$len = mb_strlen( $this->submitted['content'] );
		if ( $len === 0 ) {
			$this->addError( 'content', $this->context->msg( 'flow-error-missing-title' ) );
			return;
		}
		if ( $len > PostRevision::MAX_TOPIC_LENGTH ) {
			$this->addError( 'content', $this->context->msg(
				'flow-error-title-too-long', PostRevision::MAX_TOPIC_LENGTH ) );
			return;
		}
		if ( empty( $this->submitted['prev_revision'] ) ) {
			$this->addError( 'prev_revision', $this->context->msg(
				'flow-error-missing-prev-revision-identifier' ) );
			return;
		}
		$topicTitle = $this->loadTopicTitle();
		if ( !$topicTitle ) {
			return;
		}
		if ( !$this->permissions->isAllowed( $topicTitle, 'edit-title' ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $topicTitle ) );
			return;
		}
		if ( $topicTitle->getRevisionId()->getAlphadecimal() !== $this->submitted['prev_revision'] ) {
			// This is a reasonably effective way to ensure prev revision matches, but for guarantees
			// against race conditions there also exists a unique index on rev_prev_revision in mysql,
			// meaning if someone else inserts against the parent we and the submitter think is the
			// latest, our insert will fail.
			// TODO: Catch whatever exception happens there, make sure the most recent revision is the
			// one in the cache before handing user back to specific dialog indicating race condition
			$this->addError(
				'prev_revision',
				$this->context->msg( 'flow-error-prev-revision-mismatch' )->params(
					$this->submitted['prev_revision'],
					$topicTitle->getRevisionId()->getAlphadecimal(),
					$this->context->getUser()->getName()
				),
				[ 'revision_id' => $topicTitle->getRevisionId()->getAlphadecimal() ] // save current revision ID
			);
			return;
		}

		$this->newRevision = $topicTitle->newNextRevision(
			$this->context->getUser(),
			$this->submitted['content'],
			'topic-title-wikitext',
			'edit-title',
			$this->workflow->getArticleTitle()
		);
		if ( !$this->checkSpamFilters( $topicTitle, $this->newRevision ) ) {
			return;
		}
	}

	protected function validateReply() {
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( trim( $this->submitted['content'] ) === '' ) {
			$this->addError( 'content', $this->context->msg( 'flow-error-missing-content' ) );
			return;
		}
		if ( !isset( $this->submitted['replyTo'] ) ) {
			$this->addError( 'replyTo', $this->context->msg( 'flow-error-missing-replyto' ) );
			return;
		}

		$post = $this->loadRequestedPost( $this->submitted['replyTo'] );
		if ( !$post ) {
			return; // loadRequestedPost adds its own errors
		}
		if ( !$this->permissions->isAllowed( $post, 'reply' ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $post ) );
			return;
		}
		$this->newRevision = $post->reply(
			$this->workflow,
			$this->context->getUser(),
			$this->submitted['content'],
			// default to wikitext when not specified, for old API requests
			$this->submitted['format'] ?? 'wikitext'
		);
		if ( !$this->checkSpamFilters( null, $this->newRevision ) ) {
			return;
		}

		$this->extraCommitMetadata['reply-to'] = $post;
	}

	protected function validateModerateTopic() {
		$root = $this->loadRootPost();
		if ( !$root ) {
			return;
		}

		$this->doModerate( $root );
	}

	protected function validateModeratePost() {
		if ( empty( $this->submitted['postId'] ) ) {
			$this->addError( 'post', $this->context->msg( 'flow-error-missing-postId' ) );
			return;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$post = $this->loadRequestedPost( $this->submitted['postId'] );
		if ( !$post ) {
			// loadRequestedPost added its own messages to $this->errors;
			return;
		}
		if ( $post->isTopicTitle() ) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-not-a-post' ) );
			return;
		}
		$this->doModerate( $post );
	}

	protected function doModerate( PostRevision $post ) {
		if (
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$this->submitted['moderationState'] === AbstractRevision::MODERATED_LOCKED
			&& $post->isModerated()
		) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-lock-moderated-post' ) );
			return;
		}

		// Moderation state supplied in request parameters
		$moderationState = $this->submitted['moderationState'] ?? null;

		// $moderationState should be a string like 'restore', 'suppress', etc.  The exact strings allowed
		// are checked below with $post->isValidModerationState(), but this is checked first otherwise
		// a blank string would restore a post(due to AbstractRevision::MODERATED_NONE === '').
		if ( !$moderationState ) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-invalid-moderation-state' ) );
			return;
		}

		/*
		 * BC: 'suppress' used to be called 'censor', 'lock' was 'close' &
		 * 'unlock' was 'reopen'
		 */
		$bc = [
			'censor' => AbstractRevision::MODERATED_SUPPRESSED,
			'close' => AbstractRevision::MODERATED_LOCKED,
			'reopen' => 'un' . AbstractRevision::MODERATED_LOCKED
		];
		$moderationState = str_replace( array_keys( $bc ), array_values( $bc ), $moderationState );

		// these all just mean set to no moderation, it returns a post to unmoderated status
		$allowedRestoreAliases = [ 'unlock', 'unhide', 'undelete', 'unsuppress', /* BC for unlock: */ 'reopen' ];
		if ( in_array( $moderationState, $allowedRestoreAliases ) ) {
			$moderationState = 'restore';
		}
		// By allowing the moderationState to be sourced from $this->submitted['moderationState']
		// we no longer have a unique action name for use with the permissions system.  This rebuilds
		// an action name. e.x. restore-post, restore-topic, suppress-topic, etc.
		$action = $moderationState . ( $post->isTopicTitle() ? "-topic" : "-post" );

		if ( $moderationState === 'restore' ) {
			$newState = AbstractRevision::MODERATED_NONE;
		} else {
			$newState = $moderationState;
		}

		if ( !$post->isValidModerationState( $newState ) ) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-invalid-moderation-state' ) );
			return;
		}
		if ( !$this->permissions->isAllowed( $post, $action ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $post ) );
			return;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( trim( $this->submitted['reason'] ) === '' ) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-invalid-moderation-reason' ) );
			return;
		}

		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$reason = $this->submitted['reason'];

		$this->newRevision = $post->moderate( $this->context->getUser(), $newState, $action, $reason );
		if ( !$this->newRevision ) {
			$this->addError( 'moderate', $this->context->msg( 'flow-error-not-allowed' ) );
			return;
		}
	}

	protected function validateEditPost() {
		if ( empty( $this->submitted['postId'] ) ) {
			$this->addError( 'post', $this->context->msg( 'flow-error-missing-postId' ) );
			return;
		}
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( trim( $this->submitted['content'] ) === '' ) {
			$this->addError( 'content', $this->context->msg( 'flow-error-missing-content' ) );
			return;
		}
		if ( empty( $this->submitted['prev_revision'] ) ) {
			$this->addError( 'prev_revision', $this->context->msg( 'flow-error-missing-prev-revision-identifier' ) );
			return;
		}
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$post = $this->loadRequestedPost( $this->submitted['postId'] );
		if ( !$post ) {
			return;
		}
		if ( !$this->permissions->isAllowed( $post, 'edit-post' ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $post ) );
			return;
		}
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( $post->getRevisionId()->getAlphadecimal() !== $this->submitted['prev_revision'] ) {
			// This is a reasonably effective way to ensure prev revision
			// matches, but for guarantees against race conditions there
			// also exists a unique index on rev_prev_revision in mysql,
			// meaning if someone else inserts against the parent we and
			// the submitter think is the latest, our insert will fail.
			// TODO: Catch whatever exception happens there, make sure the
			// most recent revision is the one in the cache before handing
			// user back to specific dialog indicating race condition
			$this->addError(
				'prev_revision',
				$this->context->msg( 'flow-error-prev-revision-mismatch' )->params(
					// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
					$this->submitted['prev_revision'],
					$post->getRevisionId()->getAlphadecimal(),
					$this->context->getUser()->getName()
				),
				[ 'revision_id' => $post->getRevisionId()->getAlphadecimal() ] // save current revision ID
			);
			return;
		}

		$this->newRevision = $post->newNextRevision(
			$this->context->getUser(),
			// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
			$this->submitted['content'],
			// default to wikitext when not specified, for old API requests
			$this->submitted['format'] ?? 'wikitext',
			'edit-post',
			$this->workflow->getArticleTitle()
		);

		if ( $this->newRevision->getRevisionId()->equals( $post->getRevisionId() ) ) {
			$this->extraCommitMetadata['null-edit'] = true;
		} elseif ( !$this->checkSpamFilters( $post, $this->newRevision ) ) {
			return;
		}
	}

	public function commit() {
		switch ( $this->action ) {
		case 'edit-topic-summary':
			// pseudo-action does not do anything, only includes data in api response
			return [];

		case 'reply':
		case 'moderate-topic':
		case 'lock-topic':
		case 'restore-post':
		case 'moderate-post':
		case 'edit-title':
		case 'undo-edit-post':
		case 'edit-post':
			if ( $this->newRevision === null ) {
				throw new FailCommitException( 'Attempt to save null revision', 'fail-commit' );
			}

			$metadata = $this->extraCommitMetadata + [
				'workflow' => $this->workflow,
				'topic-title' => $this->loadTopicTitle(),
			];
			if ( !$metadata['topic-title'] instanceof PostRevision ) {
				// permissions failure, should never have gotten this far
				throw new PermissionException( 'Not Allowed', 'insufficient-permission' );
			}
			if ( $this->newRevision->getPostId()->equals( $metadata['topic-title']->getPostId() ) ) {
				// When performing actions against the topic-title self::loadTopicTitle
				// returns the previous revision.
				$metadata['topic-title'] = $this->newRevision;
			}

			// store data, unless we're dealing with a null-edit (in which case
			// is storing the same thing not only pointless, it can even be
			// incorrect, since listeners will run & generate notifications etc)
			if ( !isset( $this->extraCommitMetadata['null-edit'] ) ) {
				$this->storage->put( $this->newRevision, $metadata );
				$this->workflow->updateLastUpdated( $this->newRevision->getRevisionId() );
				$this->storage->put( $this->workflow, $metadata );

				if ( strpos( $this->action, 'moderate-' ) === 0 ) {
					$topicId = $this->newRevision->getCollection()->getRoot()->getId();

					$moderate = $this->newRevision->isModerated()
						&& ( $this->newRevision->getModerationState() === PostRevision::MODERATED_DELETED
							|| $this->newRevision->getModerationState() === PostRevision::MODERATED_SUPPRESSED );

					/** @var Controller $controller */
					$controller = Container::get( 'controller.notification' );
					if ( $this->action === 'moderate-topic' ) {
						$controller->moderateTopicNotifications( $topicId, $moderate );
					} elseif ( $this->action === 'moderate-post' ) {
						$postId = $this->newRevision->getPostId();
						$controller->moderatePostNotifications( $topicId, $postId, $moderate );
					}
				}
			}

			$newRevision = $this->newRevision;

			// If no context was loaded render the post in isolation
			// @todo make more explicit
			try {
				$newRevision->getChildren();
			} catch ( \MWException $e ) {
				$newRevision->setChildren( [] );
			}

			$returnMetadata = [
				'post-id' => $this->newRevision->getPostId(),
				'post-revision-id' => $this->newRevision->getRevisionId(),
			];

			return $returnMetadata;

		default:
			throw new InvalidActionException( "Unknown commit action: {$this->action}", 'invalid-action' );
		}
	}

	public function renderApi( array $options ) {
		$output = [ 'type' => $this->getName() ];

		$topic = $this->loadTopicTitle();
		if ( !$topic ) {
			return $output + $this->finalizeApiOutput( $options );
		}

		// there's probably some OO way to turn this stack of if/else into
		// something nicer. Consider better ways before extending this with
		// more conditionals
		switch ( $this->action ) {
			case 'history':
				// single post history or full topic?
				if ( isset( $options['postId'] ) ) {
					// singular post history
					$output += $this->renderPostHistoryApi( $options, UUID::create( $options['postId'] ) );
				} else {
					// post history for full topic
					$output += $this->renderTopicHistoryApi( $options );
				}
				break;

			case 'single-view':
				if ( isset( $options['revId'] ) ) {
					$revId = $options['revId'];
				} else {
					throw new InvalidInputException( 'A revision must be provided', 'invalid-input' );
				}
				$output += $this->renderSingleViewApi( $revId );
				break;

			case 'lock-topic':
				// Treat topic as a post, only the post + summary are needed
				$result = $this->renderPostApi( $options, $this->workflow->getId() );
				if ( $result !== null ) {
					$topicId = $result['roots'][0];
					$revisionId = $result['posts'][$topicId][0];
					$output += $result['revisions'][$revisionId];
				}
				break;

			case 'compare-post-revisions':
				$output += $this->renderDiffViewApi( $options );
				break;

			case 'undo-edit-post':
				$output += $this->renderUndoApi( $options );
				break;

			case 'view-post-history':
				// View entire history of single post
				$output += $this->renderPostHistoryApi( $options, UUID::create( $options['postId'] ), false );
				break;

			case 'view-topic-history':
				// View entire history of a topic's posts
				$output += $this->renderTopicHistoryApi( $options, false );
				break;

			// Any actions require (re)rendering the whole topic
			case 'edit-post':
			case 'moderate-post':
			case 'restore-post':
			case 'reply':
			case 'moderate-topic':
			case 'view-topic':
			case 'view' && !isset( $options['postId'] ) && !isset( $options['revId'] );
				// view full topic
				$output += $this->renderTopicApi( $options );
				break;

			case 'edit-title':
			case 'view-post':
			case 'view':
			default:
				// view single post, possibly specific revision
				$result = $this->renderPostApi( $options );
				if ( $result !== null ) {
					$output += $result;
				}
				break;
		}

		return $output + $this->finalizeApiOutput( $options );
	}

	/**
	 * @param array $options
	 * @return array
	 */
	protected function finalizeApiOutput( $options ) {
		if ( $this->wasSubmitted() ) {
			// Failed actions, like reply, end up here
			return [
				'submitted' => $this->submitted,
				'errors' => $this->errors,
			];
		} else {
			return [
				'submitted' => $options,
				'errors' => $this->errors,
			];
		}
	}

	// @Todo - duplicated logic in other diff view block
	protected function renderDiffViewApi( array $options ) {
		if ( !isset( $options['newRevision'] ) ) {
			throw new InvalidInputException( 'A revision must be provided for comparison',
				'revision-comparison' );
		}
		$oldRevision = null;
		if ( isset( $options['oldRevision'] ) ) {
			$oldRevision = $options['oldRevision'];
		}
		list( $new, $old ) = Container::get( 'query.post.view' )
			->getDiffViewResult( UUID::create( $options['newRevision'] ), UUID::create( $oldRevision ) );

		return [
			'revision' => Container::get( 'formatter.revision.diff.view' )
				->formatApi( $new, $old, $this->context )
		];
	}

	// @Todo - duplicated logic in other single view block
	protected function renderSingleViewApi( $revId ) {
		$row = Container::get( 'query.post.view' )->getSingleViewResult( $revId );

		if ( !$this->permissions->isAllowed( $row->revision, 'view' ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $row->revision ) );
			return [];
		}

		return [
			'revision' => Container::get( 'formatter.revisionview' )->formatApi( $row, $this->context )
		];
	}

	protected function renderTopicApi( array $options, $workflowId = '' ) {
		$serializer = Container::get( 'formatter.topic' );
		$format = $options['format'] ?? 'fixed-html';
		$serializer->setContentFormat( $format );

		if ( !$workflowId ) {
			if ( $this->workflow->isNew() ) {
				return $serializer->buildEmptyResult( $this->workflow );
			}
			$workflowId = $this->workflow->getId();
		}

		if ( $this->submitted !== null ) {
			$options += $this->submitted;
		}

		// In the topic level responses we only want to force a single revision
		// to wikitext (the one we're editing), not the entire thing.
		if ( $this->action === 'edit-post' && !empty( $options['revId'] ) ) {
			$uuid = UUID::create( $options['revId'] );
			if ( $uuid ) {
				$serializer->setContentFormat( 'wikitext', $uuid );
			}
		}

		return $serializer->formatApi(
			$this->workflow,
			Container::get( 'query.topiclist' )->getResults( [ $workflowId ] ),
			$this->context
		);
	}

	/**
	 * @todo Any failed action performed against a single revisions ends up here.
	 * To generate forms with validation errors in the non-javascript renders we
	 * need to add something to this output, but not sure what yet
	 * @param array $options
	 * @param string $postId
	 * @return null|array[]
	 * @throws FlowException
	 */
	protected function renderPostApi( array $options, $postId = '' ) {
		if ( $this->workflow->isNew() ) {
			throw new FlowException( 'No posts can exist for non-existent topic' );
		}

		$format = $options['format'] ?? 'fixed-html';
		$serializer = $this->getRevisionFormatter( $format );

		if ( !$postId ) {
			if ( isset( $options['postId'] ) ) {
				$postId = $options['postId'];
			} elseif ( $this->newRevision ) {
				// API results after a reply will have no $postId (ID is not yet
				// known when the reply is submitted) so we'll grab it from the
				// newly added revision
				$postId = $this->newRevision->getPostId();
			} else {
				throw new FlowException( 'No post id specified' );
			}
		} else {
			// $postId is only set for lock-topic, which should default to
			// wikitext instead of html
			$format = $options['format'] ?? 'wikitext';
			$serializer->setContentFormat( $format, UUID::create( $postId ) );
		}

		$row = Container::get( 'query.singlepost' )->getResult( UUID::create( $postId ) );
		$serialized = $serializer->formatApi( $row, $this->context );
		if ( !$serialized ) {
			return null;
		}

		return [
			'roots' => [ $serialized['postId'] ],
			'posts' => [
				$serialized['postId'] => [ $serialized['revisionId'] ],
			],
			'revisions' => [
				$serialized['revisionId'] => $serialized,
			]
		];
	}

	protected function renderUndoApi( array $options ) {
		if ( $this->workflow->isNew() ) {
			throw new FlowException( 'No posts can exist for non-existent topic' );
		}

		if ( !isset( $options['startId'] ) || !isset( $options['endId'] ) ) {
			throw new InvalidInputException( 'Both startId and endId must be provided' );
		}

		/** @var RevisionViewQuery */
		$query = Container::get( 'query.post.view' );
		$rows = $query->getUndoDiffResult( $options['startId'], $options['endId'] );
		if ( !$rows ) {
			throw new InvalidInputException( 'Could not load revision to undo' );
		}

		$serializer = Container::get( 'formatter.undoedit' );
		return $serializer->formatApi( $rows[0], $rows[1], $rows[2], $this->context );
	}

	/**
	 * @param string $format Content format (html|wikitext|fixed-html|topic-title-html|topic-title-wikitext)
	 * @return RevisionFormatter
	 */
	protected function getRevisionFormatter( $format ) {
		$serializer = Container::get( 'formatter.revision.factory' )->create();
		$serializer->setContentFormat( $format );

		return $serializer;
	}

	protected function renderTopicHistoryApi( array $options, $navbar = true ) {
		if ( $this->workflow->isNew() ) {
			throw new FlowException( 'No topic history can exist for non-existent topic' );
		}
		return $this->processHistoryResult( Container::get( 'query.topic.history' ),
			$this->workflow->getId(), $options, $navbar );
	}

	protected function renderPostHistoryApi( array $options, UUID $postId, $navbar = true ) {
		if ( $this->workflow->isNew() ) {
			throw new FlowException( 'No post history can exist for non-existent topic' );
		}
		return $this->processHistoryResult( Container::get( 'query.post.history' ),
			$postId, $options, $navbar );
	}

	/**
	 * Process the history result for either topic or post
	 *
	 * @param TopicHistoryQuery|PostHistoryQuery $query
	 * @param UUID $uuid
	 * @param array $options
	 * @param bool $navbar Whether to include the page navbar
	 * @return array
	 */
	protected function processHistoryResult(
		/* TopicHistoryQuery|PostHistoryQuery */ $query,
		UUID $uuid,
		$options,
		$navbar = true
	) {
		global $wgRequest;

		$format = $options['format'] ?? 'fixed-html';
		$serializer = $this->getRevisionFormatter( $format );
		$serializer->setIncludeHistoryProperties( true );

		list( $limit, /* $offset */ ) = $wgRequest->getLimitOffsetForUser(
			$this->context->getUser()
		);
		// don't use offset from getLimitOffset - that assumes an int, which our
		// UUIDs are not
		$offset = $wgRequest->getText( 'offset' );
		$offset = $offset ? UUID::create( $offset ) : null;

		$pager = new HistoryPager( $query, $uuid );
		$pager->setLimit( $limit );
		$pager->setOffset( $offset );
		$pager->doQuery();
		$history = $pager->getResult();

		$revisions = [];
		foreach ( $history as $row ) {
			// @phan-suppress-next-line PhanTypeMismatchArgument
			$serialized = $serializer->formatApi( $row, $this->context, 'history' );
			// if the user is not allowed to see this row it will return empty
			if ( $serialized ) {
				$revisions[] = $serialized;
			}
		}

		$response = [ 'revisions' => $revisions ];
		if ( $navbar ) {
			$response['navbar'] = $pager->getNavigationBar();
		}
		return $response;
	}

	/**
	 * @return PostRevision|null
	 */
	public function loadRootPost() {
		if ( $this->root !== null ) {
			return $this->root;
		}

		$rootPost = $this->rootLoader->get( $this->workflow->getId() );

		if ( $this->permissions->isAllowed( $rootPost, 'view' ) ) {
			// topicTitle is same as root, difference is root has children populated to full depth
			$this->topicTitle = $rootPost;
			$this->root = $rootPost;
			return $rootPost;
		}

		$this->addError( 'moderation', $this->context->msg( 'flow-error-not-allowed' ) );

		return null;
	}

	/**
	 * @param string $action Permissions action to require to return revision
	 * @return AbstractRevision|null
	 * @throws InvalidDataException
	 */
	public function loadTopicTitle( $action = 'view' ) {
		if ( $this->workflow->isNew() ) {
			throw new InvalidDataException( 'New workflows do not have any related content',
				'missing-topic-title' );
		}

		if ( $this->topicTitle === null ) {
			$found = $this->storage->find(
				'PostRevision',
				[ 'rev_type_id' => $this->workflow->getId() ],
				[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
			);
			if ( !$found ) {
				throw new InvalidDataException( 'Every workflow must have an associated topic title',
					'missing-topic-title' );
			}
			$this->topicTitle = reset( $found );

			// this method loads only title, nothing else; otherwise, you're
			// looking for loadRootPost
			$this->topicTitle->setChildren( [] );
			$this->topicTitle->setDepth( 0 );
			$this->topicTitle->setRootPost( $this->topicTitle );
		}

		if ( !$this->permissions->isAllowed( $this->topicTitle, $action ) ) {
			$this->addError( 'permissions', $this->getDisallowedErrorMessage( $this->topicTitle ) );
			return null;
		}

		return $this->topicTitle;
	}

	/**
	 * @todo Move this to AbstractBlock and use for summary/header/etc.
	 * @param AbstractRevision $revision
	 * @return Message
	 */
	protected function getDisallowedErrorMessage( AbstractRevision $revision ) {
		if ( in_array( $this->action, [ 'moderate-topic', 'moderate-post' ] ) ) {
			/*
			 * When failing to moderate an already moderated action (like
			 * undo), show the more general "you have insufficient
			 * permissions for this action" message, rather than the
			 * specialized "this topic is <hidden|deleted|suppressed>" msg.
			 */
			return $this->context->msg( 'flow-error-not-allowed' );
		}

		$state = $revision->getModerationState();

		// display simple message
		// i18n messages:
		// flow-error-not-allowed-hide,
		// flow-error-not-allowed-reply-to-hide-topic
		// flow-error-not-allowed-delete
		// flow-error-not-allowed-reply-to-delete-topic
		// flow-error-not-allowed-suppress
		// flow-error-not-allowed-reply-to-suppress-topic
		if ( $revision instanceof PostRevision ) {
			$type = $revision->isTopicTitle() ? 'topic' : 'post';
		} else {
			$type = $revision->getRevisionType();
		}

		// Show a snippet of the relevant log entry if available.
		if ( \LogPage::isLogType( $state ) ) {
			// check if user has sufficient permissions to see log
			$logPage = new \LogPage( $state );
			if ( MediaWikiServices::getInstance()->getPermissionManager()
					->userHasRight( $this->context->getUser(), $logPage->getRestriction() )
			) {
				// LogEventsList::showLogExtract will write to OutputPage, but we
				// actually just want that text, to write it ourselves wherever we want,
				// so let's create an OutputPage object to then get the content from.
				$rc = new \RequestContext();
				$output = $rc->getOutput();

				// get log extract
				$entries = \LogEventsList::showLogExtract(
					$output,
					[ $state ],
					$this->workflow->getArticleTitle()->getPrefixedText(),
					'',
					[
						'lim' => 10,
						'showIfEmpty' => false,
						// i18n messages:
						// flow-error-not-allowed-hide-extract
						// flow-error-not-allowed-reply-to-hide-topic-extract
						// flow-error-not-allowed-delete-extract
						// flow-error-not-allowed-reply-to-delete-topic-extract
						// flow-error-not-allowed-suppress-extract
						// flow-error-not-allowed-reply-to-suppress-topic-extract
						'msgKey' => [
							[
								"flow-error-not-allowed-{$this->action}-to-$state-$type",
								"flow-error-not-allowed-$state-extract",
							],
						]
					]
				);

				// check if there were any log extracts
				if ( $entries ) {
					$message = new \RawMessage( '$1' );
					return $message->rawParams( $output->getHTML() );
				}
			}
		}

		return $this->context->msg( [
			// set of keys to try in order
			"flow-error-not-allowed-{$this->action}-to-$state-$type",
			"flow-error-not-allowed-$state",
			"flow-error-not-allowed"
		] );
	}

	/**
	 * Loads the post referenced by $postId. Returns null when:
	 *    $postId does not belong to the workflow
	 *    The user does not have view access to the topic title
	 *    The user does not have view access to the referenced post
	 * All these conditions add a relevant error message to $this->errors when returning null
	 *
	 * @param UUID|string $postId The post being requested
	 * @return PostRevision|null
	 */
	protected function loadRequestedPost( $postId ) {
		if ( !$postId instanceof UUID ) {
			$postId = UUID::create( $postId );
		}
		'@phan-var UUID $postId';

		if ( $this->rootLoader === null ) {
			// Since there is no root loader the full tree is already loaded
			$topicTitle = $root = $this->loadRootPost();
			if ( !$topicTitle ) {
				return null;
			}
			$post = $root->getDescendant( $postId );
			if ( $post === null ) {
				// The requested postId is not a member of the current workflow
				$this->addError( 'post', $this->context->msg(
					'flow-error-invalid-postId', $postId->getAlphadecimal() ) );
				return null;
			}
		} else {
			// Load the post and its root
			$found = $this->rootLoader->getWithRoot( $postId );
			if ( !$found['post'] || !$found['root'] ||
				!$found['root']->getPostId()->equals( $this->workflow->getId() )
			) {
				$this->addError( 'post', $this->context->msg(
					'flow-error-invalid-postId', $postId->getAlphadecimal() ) );
				return null;
			}
			$this->topicTitle = $topicTitle = $found['root'];
			$post = $found['post'];

			// using the path to the root post, we can know the post's depth
			$rootPath = $this->rootLoader->getTreeRepo()->findRootPath( $postId );
			$post->setDepth( count( $rootPath ) - 1 );
			$post->setRootPost( $found['root'] );
		}

		// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
		if ( $this->permissions->isAllowed( $topicTitle, 'view' )
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable
			&& $this->permissions->isAllowed( $post, 'view' ) ) {
			return $post;
		}

		$this->addError( 'moderation', $this->context->msg( 'flow-error-not-allowed' ) );
		return null;
	}

	/**
	 * The prefix used for form data$pos
	 * @return string
	 */
	public function getName() {
		return 'topic';
	}

	/**
	 * @param \OutputPage $out
	 *
	 * @todo Provide more informative page title for actions other than view,
	 *       e.g. "Hide post in <TITLE>", "Unlock <TITLE>", etc.
	 */
	public function setPageTitle( \OutputPage $out ) {
		$topic = $this->loadTopicTitle( $this->action === 'history' ? 'history' : 'view' );
		if ( !$topic ) {
			return;
		}

		$title = $this->workflow->getOwnerTitle();
		$out->setPageTitle( $out->msg( 'flow-topic-first-heading', $title->getPrefixedText() ) );
		if ( $this->permissions->isAllowed( $topic, 'view' ) ) {
			if ( $this->action === 'undo-edit-post' ) {
				$key = 'flow-undo-edit-post';
			} else {
				$key = 'flow-topic-html-title';
			}
			$out->setHTMLTitle( $out->msg( $key,
				// This must be a rawParam to not expand {{foo}} in the title, it must
				// not be htmlspecialchar'd because OutputPage::setHtmlTitle handles that.
				Message::rawParam( $topic->getContent( 'topic-title-plaintext' ) ),
				$title->getPrefixedText()
			) );
		} else {
			$out->setHTMLTitle( $title->getPrefixedText() );
		}
		$out->setSubtitle( '&lt; ' .
			MediaWikiServices::getInstance()->getLinkRenderer()->makeLink( $title ) );
	}
}
