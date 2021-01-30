<?php

namespace Flow\Formatter;

use ApiResult;
use ExtensionRegistry;
use Flow\Collection\PostCollection;
use Flow\Conversion\Utils;
use Flow\Exception\FlowException;
use Flow\Exception\InvalidInputException;
use Flow\Exception\PermissionException;
use Flow\Model\AbstractRevision;
use Flow\Model\Anchor;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\UUID;
use Flow\Repository\UserNameBatch;
use Flow\RevisionActionPermissions;
use Flow\Templating;
use Flow\UrlGenerator;
use GenderCache;
use IContextSource;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use Message;
use User;
use Wikimedia\Timestamp\TimestampException;

/**
 * This implements a serializer for converting revision objects
 * into an array of localized and sanitized data ready for user
 * consumption.
 *
 * The formatApi method is the primary method of interacting with
 * this serializer. The results of formatApi can be passed on to
 * html formatting or emitted directly as an api response.
 *
 * For performance considerations of special purpose formatters like
 * CheckUser methods that build pieces of the api response are also
 * public.
 *
 * @todo can't output as api yet, Message instances are returned
 *  for the various strings.
 *
 * @todo this needs a better name, RevisionSerializer? not sure yet
 */
class RevisionFormatter {

	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @var Templating
	 */
	protected $templating;

	/**
	 * @var UrlGenerator
	 */
	protected $urlGenerator;

	/**
	 * @var bool
	 */
	protected $includeProperties = false;

	/**
	 * @var bool
	 */
	protected $includeContent = true;

	/**
	 * @var string[] Allowed content formats
	 *
	 *  See setContentFormat.
	 */
	protected $allowedContentFormats = [ 'html', 'wikitext', 'fixed-html',
		'topic-title-html', 'topic-title-wikitext' ];

	/**
	 * @var string Default content format for revision output
	 */
	protected $contentFormat = 'fixed-html';

	/**
	 * @var array Map from alphadecimal revision id to content format override
	 */
	protected $revisionContentFormat = [];

	/**
	 * @var int
	 */
	protected $maxThreadingDepth;

	/**
	 * @var Message[]
	 */
	protected $messages = [];

	/**
	 * @var array
	 */
	protected $userLinks = [];

	/**
	 * @var UserNameBatch
	 */
	protected $usernames;

	/**
	 * @var GenderCache
	 */
	protected $genderCache;

	/**
	 * @param RevisionActionPermissions $permissions
	 * @param Templating $templating
	 * @param UserNameBatch $usernames
	 * @param int $maxThreadingDepth
	 */
	public function __construct(
		RevisionActionPermissions $permissions,
		Templating $templating,
		UserNameBatch $usernames,
		$maxThreadingDepth
	) {
		$this->permissions = $permissions;
		$this->templating = $templating;
		$this->urlGenerator = $this->templating->getUrlGenerator();
		$this->usernames = $usernames;
		$this->genderCache = MediaWikiServices::getInstance()->getGenderCache();
		$this->maxThreadingDepth = $maxThreadingDepth;
	}

	/**
	 * The self::buildProperties method is fairly expensive and only used for rendering
	 * history entries.  As such it is optimistically disabled unless requested
	 * here
	 *
	 * @param bool $shouldInclude
	 */
	public function setIncludeHistoryProperties( $shouldInclude ) {
		$this->includeProperties = (bool)$shouldInclude;
	}

	/**
	 * Outputing content can be somehwat expensive, as most of the content is loaded
	 * into DOMDocuemnts for processing of relidlinks and badimages.  Set this to false
	 * if the content will not be used such as for recent changes.
	 * @param bool $shouldInclude
	 */
	public function setIncludeContent( $shouldInclude ) {
		$this->includeContent = (bool)$shouldInclude;
	}

	/**
	 * Sets the content format for all revisions formatted by this formatter, or a
	 * particular revision.
	 *
	 * @param string $format Format to use for revision content.  If no revision ID is
	 *  given, this is a default format, and the allowed formats are 'html', 'wikitext',
	 *  and 'fixed-html'.
	 *
	 *  For the default format, 'fixed-html' will be converted to 'topic-title-html'
	 *  when formatting a topic title.  'html' and 'wikitext' will be converted to
	 *  'topic-title-wikitext' for topic titles (because 'html' and 'wikitext' are
	 *  editable, and 'topic-title-html' is not editable).
	 *
	 *  If a revision ID is given, the allowed formats are 'html', 'wikitext',
	 *  'fixed-html', 'topic-title-html', and 'topic-title-wikitext'.  However, the
	 *  format will not be converted, and must be valid for the given revision ('html',
	 *  'wikitext', and 'fixed-html' are valid only for non-topic titles.
	 *  'topic-title-html' and 'topic-title-wikitext' are only valid for topic titles.
	 *  Otherwise, an exception will be thrown later.
	 * @param UUID|null $revisionId Revision ID this format applies for.
	 * @throws FlowException
	 * @throws InvalidInputException
	 */
	public function setContentFormat( $format, UUID $revisionId = null ) {
		if ( false === array_search( $format, $this->allowedContentFormats ) ) {
			throw new InvalidInputException( "Unknown content format: $format" );
		}
		if ( $revisionId === null ) {
			// set default content format
			$this->contentFormat = $format;
		} else {
			// set per-revision content format
			$this->revisionContentFormat[$revisionId->getAlphadecimal()] = $format;
		}
	}

	/**
	 * @param FormatterRow $row
	 * @param IContextSource $ctx
	 * @param string $action action from FlowActions
	 * @return array|bool
	 * @throws FlowException
	 * @throws PermissionException
	 * @throws \Exception
	 * @throws \Flow\Exception\InvalidInputException
	 * @throws TimestampException
	 * @suppress PhanUndeclaredMethod Phan doesn't infer types from the instanceofs
	 */
	public function formatApi( FormatterRow $row, IContextSource $ctx, $action = 'view' ) {
		$this->permissions->setUser( $ctx->getUser() );

		if ( !$this->permissions->isAllowed( $row->revision, $action ) ) {
			LoggerFactory::getInstance( 'Flow' )->debug(
				__METHOD__ . ': Permission denied for user on action {action}',
				[
					'action' => $action,
					'revision_id' => $row->revision->getRevisionId(),
					'user_id' => $ctx->getUser()->getId(),
				]
			);
			return false;
		}

		$moderatedRevision = $this->templating->getModeratedRevision( $row->revision );
		$ts = $row->revision->getRevisionId()->getTimestampObj();
		$res = [
			ApiResult::META_BC_BOOLS => [
				'isOriginalContent',
				'isModerated',
				'isLocked',
				'isModeratedNotLocked',
			],
			'workflowId' => $row->workflow->getId()->getAlphadecimal(),
			'articleTitle' => $row->workflow->getArticleTitle()->getPrefixedText(),
			'revisionId' => $row->revision->getRevisionId()->getAlphadecimal(),
			'timestamp' => $ts->getTimestamp( TS_MW ),
			'changeType' => $row->revision->getChangeType(),
			// @todo push all date formatting to the render side?
			'dateFormats' => $this->getDateFormats( $row->revision, $ctx ),
			'properties' => $this->buildProperties( $row->workflow->getId(), $row->revision, $ctx, $row ),
			'isOriginalContent' => $row->revision->isOriginalContent(),
			'isModerated' => $moderatedRevision->isModerated(),
			// These are read urls
			'links' => $this->buildLinks( $row ),
			// These are write urls
			'actions' => $this->buildActions( $row ),
			'size' => [
				'old' => $row->revision->getPreviousContentLength(),
				'new' => $row->revision->getContentLength(),
			],
			'author' => $this->serializeUser(
				$row->revision->getUserWiki(),
				$row->revision->getUserId(),
				$row->revision->getUserIp()
			),
			'lastEditUser' => $this->serializeUser(
				$row->revision->getLastContentEditUserWiki(),
				$row->revision->getLastContentEditUserId(),
				$row->revision->getLastContentEditUserIp()
			),
			'lastEditId' => $row->revision->isOriginalContent()
				? null : $row->revision->getLastContentEditId()->getAlphadecimal(),
			'previousRevisionId' => $row->revision->isFirstRevision()
				? null
				: $row->revision->getPrevRevisionId()->getAlphadecimal(),
		];

		if ( $res['isModerated'] ) {
			$res['moderator'] = $this->serializeUser(
				$moderatedRevision->getModeratedByUserWiki(),
				$moderatedRevision->getModeratedByUserId(),
				$moderatedRevision->getModeratedByUserIp()
			);
			// @todo why moderate instead of moderated or something else?
			$res['moderateState'] = $moderatedRevision->getModerationState();
			$res['moderateReason'] = [
				'content' => $moderatedRevision->getModeratedReason(),
				'format' => 'plaintext',
			];
			$res['isLocked'] = $moderatedRevision->isLocked();
		} else {
			$res['isLocked'] = false;
		}
		// to avoid doing this check in handlebars
		$res['isModeratedNotLocked'] = $moderatedRevision->isModerated() && !$moderatedRevision->isLocked();

		if ( $this->includeContent ) {
			$contentFormat = $this->decideContentFormat( $row->revision );

			// @todo better name?
			$res['content'] = [
				'content' => $this->templating->getContent( $row->revision, $contentFormat ),
				'format' => $contentFormat
			];
		}

		if ( $row instanceof TopicRow ) {
			$res[ApiResult::META_BC_BOOLS] = array_merge(
				$res[ApiResult::META_BC_BOOLS],
				[
					'isWatched',
					'watchable',
				]
			);
			if ( $row->summary ) {
				$summary = $this->formatApi( $row->summary, $ctx, $action );
				if ( $summary ) {
					$res['summary'] = [
						'revision' => $summary,
					];
				}
			}

			// Only non-anon users can watch/unwatch a flow topic
			// isWatched - the topic is watched by current user
			// watchable - the user could watch the topic, eg, anon-user can't watch a topic
			if ( !$ctx->getUser()->isAnon() ) {
				// default topic is not watched and topic is not always watched
				$res['isWatched'] = (bool)$row->isWatched;
				$res['watchable'] = true;
			} else {
				$res['watchable'] = false;
			}
		}

		if ( $row->revision instanceof PostRevision ) {
			$res[ApiResult::META_BC_BOOLS] = array_merge(
				$res[ApiResult::META_BC_BOOLS],
				[
					'isMaxThreadingDepth',
					'isNewPage',
				]
			);

			$replyTo = $row->revision->getReplyToId();
			$res['replyToId'] = $replyTo ? $replyTo->getAlphadecimal() : null;
			$res['postId'] = $row->revision->getPostId()->getAlphadecimal();
			$res['isMaxThreadingDepth'] = $row->revision->getDepth() >= $this->maxThreadingDepth;
			$res['creator'] = $this->serializeUser(
				$row->revision->getCreatorWiki(),
				$row->revision->getCreatorId(),
				$row->revision->getCreatorIp()
			);

			// Always output this along with topic titles so they
			// have a safe parameter to use within l10n for content
			// output.
			if ( $row->revision->isTopicTitle() && !isset( $res['properties']['topic-of-post'] ) ) {
				$res['properties']['topic-of-post'] = $this->processParam(
					'topic-of-post',
					$row->revision,
					$row->workflow->getId(),
					$ctx,
					$row
				);

				$res['properties']['topic-of-post-text-from-html'] = $this->processParam(
					'topic-of-post-text-from-html',
					$row->revision,
					$row->workflow->getId(),
					$ctx,
					$row
				);

				// moderated posts won't have that property
				if ( isset( $res['properties']['topic-of-post-text-from-html']['plaintext'] ) ) {
					$res['content']['plaintext'] =
						$res['properties']['topic-of-post-text-from-html']['plaintext'];
				}
			}

			$res['isNewPage'] = $row->isFirstReply && $row->revision->isFirstRevision();

		} elseif ( $row->revision instanceof PostSummary ) {
			$res['creator'] = $this->serializeUser(
				$row->revision->getCreatorWiki(),
				$row->revision->getCreatorId(),
				$row->revision->getCreatorIp()
			);
		}

		return $res;
	}

	/**
	 * @param array $userData Contains `name`, `wiki`, and `gender` keys
	 * @return array
	 */
	public function serializeUserLinks( $userData ) {
		$name = $userData['name'];
		if ( isset( $this->userLinks[$name] ) ) {
			return $this->userLinks[$name];
		}

		$talkPageTitle = null;
		$userTitle = \Title::newFromText( $name, NS_USER );
		if ( $userTitle ) {
			$talkPageTitle = $userTitle->getTalkPage();
		}

		$blockTitle = \SpecialPage::getTitleFor( 'Block', $name );

		$userContribsTitle = \SpecialPage::getTitleFor( 'Contributions', $name );
		$userLinksBCBools = [
			'_BC_bools' => [
				'exists',
			],
		];
		$links = [
			'contribs' => [
				'url' => $userContribsTitle->getLinkURL(),
				'title' => $userContribsTitle->getText(),
				'exists' => true,
			] + $userLinksBCBools,
			'userpage' => [
				'url' => $userTitle->getLinkURL(),
				'title' => $userTitle->getText(),
				'exists' => $userTitle->isKnown(),
			] + $userLinksBCBools,
		];

		if ( $talkPageTitle ) {
			$links['talk'] = [
				'url' => $talkPageTitle->getLinkURL(),
				'title' => $talkPageTitle->getPrefixedText(),
				'exists' => $talkPageTitle->isKnown()
			] + $userLinksBCBools;
		}
		// is this right permissions? typically this would
		// be sourced from Linker::userToolLinks, but that
		// only undertands html strings.
		if ( MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $this->permissions->getUser(), 'block' )
		) {
			// only is the user has blocking rights
			$links += [
				"block" => [
					'url' => $blockTitle->getLinkURL(),
					'title' => wfMessage( 'blocklink' ),
					'exists' => true
				] + $userLinksBCBools,
			];
		}

		$this->userLinks[$name] = $links;
		return $this->userLinks[$name];
	}

	public function serializeUser( $userWiki, $userId, $userIp ) {
		$res = [
			'name' => $this->usernames->get( $userWiki, $userId, $userIp ),
			'wiki' => $userWiki,
			'gender' => 'unknown',
			'links' => [],
			'id' => $userId
		];
		// Only works for the local wiki
		if ( wfWikiID() === $userWiki ) {
			$res['gender'] = $this->genderCache->getGenderOf( $res['name'], __METHOD__ );
		}
		if ( $res['name'] ) {
			$res['links'] = $this->serializeUserLinks( $res );
		}

		return $res;
	}

	/**
	 * @param AbstractRevision $revision
	 * @param IContextSource $ctx
	 * @return array Contains [timeAndDate, date, time]
	 */
	public function getDateFormats( AbstractRevision $revision, IContextSource $ctx ) {
		// also restricted to history
		if ( $this->includeProperties === false ) {
			return [];
		}

		$timestamp = $revision->getRevisionId()->getTimestampObj()->getTimestamp( TS_MW );
		$user = $ctx->getUser();
		$lang = $ctx->getLanguage();

		return [
			'timeAndDate' => $lang->userTimeAndDate( $timestamp, $user ),
			'date' => $lang->userDate( $timestamp, $user ),
			'time' => $lang->userTime( $timestamp, $user ),
		];
	}

	/**
	 * @param FormatterRow $row
	 * @return array
	 * @throws FlowException
	 */
	public function buildActions( FormatterRow $row ) {
		global $wgThanksSendToBots;

		$user = $this->permissions->getUser();
		$workflow = $row->workflow;
		$title = $workflow->getArticleTitle();

		// If a user does not have rights to perform actions on this page return
		// an empty array of actions.
		if ( !$workflow->userCan( 'edit', $user ) ) {
			return [];
		}

		$revision = $row->revision;
		$action = $revision->getChangeType();
		$workflowId = $workflow->getId();
		$revId = $revision->getRevisionId();
		// @phan-suppress-next-line PhanUndeclaredMethod Checks method_exists
		$postId = method_exists( $revision, 'getPostId' ) ? $revision->getPostId() : null;
		$actionTypes = $this->permissions->getActions()->getValue( $action, 'actions' );
		if ( $actionTypes === null ) {
			wfDebugLog( 'Flow', __METHOD__ . ": No actions defined for action: $action" );
			return [];
		}

		// actions primarily vary by revision type...
		$links = [];
		foreach ( $actionTypes as $type ) {
			if ( !$this->permissions->isAllowed( $revision, $type ) ) {
				continue;
			}
			switch ( $type ) {
			case 'thank':
				$targetedUser = User::newFromId( $revision->getCreatorId() );
				if (
					// thanks extension must be available
					ExtensionRegistry::getInstance()->isLoaded( 'Thanks' ) &&
					// anons can't give a thank
					!$user->isAnon() &&
					// can only thank for PostRevisions
					// (other revision objects have no getCreator* methods)
					$revision instanceof PostRevision &&
					// only thank a logged in user
					!$targetedUser->isAnon() &&
					// can't thank self
					$user->getId() !== $revision->getCreatorId() &&
					// can't thank bots
					!( !$wgThanksSendToBots && in_array( 'bot', $targetedUser->getGroups() ) )
				) {
					$links['thank'] = $this->urlGenerator->thankAction( $postId );
				}
				break;

			case 'reply':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				} elseif ( !$revision instanceof PostRevision ) {
					throw new FlowException( "$type called without PostRevision object" );
				}

				/*
				 * If the post being replied to is the most recent post
				 * of its depth, the reply link should point to parent
				 */
				$replyToId = $postId;
				$replyToRevision = $revision;
				if ( $row->isLastReply ) {
					$replyToId = $replyToRevision->getReplyToId();
					$replyToRevision = PostCollection::newFromId( $replyToId )->getLastRevision();
				}

				/*
				 * If the post being replied to is at or exceeds the max
				 * threading depth, the reply link should point to parent.
				 */
				while ( $replyToRevision->getDepth() >= $this->maxThreadingDepth ) {
					$replyToId = $replyToRevision->getReplyToId();
					$replyToRevision = PostCollection::newFromId( $replyToId )->getLastRevision();
				}

				$links['reply'] = $this->urlGenerator->replyAction(
					$title,
					$workflowId,
					$replyToId,
					$revision->isTopicTitle()
				);
				break;

			case 'edit-header':
				$links['edit'] = $this->urlGenerator->editHeaderAction( $title, $workflowId, $revId );
				break;

			case 'edit-title':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$links['edit'] = $this->urlGenerator
					->editTitleAction( $title, $workflowId, $postId, $revId );
				break;

			case 'edit-post':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$links['edit'] = $this->urlGenerator
					->editPostAction( $title, $workflowId, $postId, $revId );
				break;

			case 'undo-edit-header':
			case 'undo-edit-post':
			case 'undo-edit-topic-summary':
				if ( !$revision->isFirstRevision() ) {
					$links['undo'] = $this->urlGenerator->undoAction( $revision, $title, $workflowId );
				}
				break;

			case 'hide-post':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$links['hide'] = $this->urlGenerator->hidePostAction( $title, $workflowId, $postId );
				break;

			case 'delete-topic':
				$links['delete'] = $this->urlGenerator->deleteTopicAction( $title, $workflowId );
				break;

			case 'delete-post':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$links['delete'] = $this->urlGenerator->deletePostAction( $title, $workflowId, $postId );
				break;

			case 'suppress-topic':
				$links['suppress'] = $this->urlGenerator->suppressTopicAction( $title, $workflowId );
				break;

			case 'suppress-post':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$links['suppress'] = $this->urlGenerator->suppressPostAction( $title, $workflowId, $postId );
				break;

			case 'lock-topic':
				// lock topic link is only available to topics
				if ( !$revision instanceof PostRevision || !$revision->isTopicTitle() ) {
					break;
				}

				$links['lock'] = $this->urlGenerator->lockTopicAction( $title, $workflowId );
				break;

			case 'restore-topic':
				$moderateAction = $flowAction = null;
				switch ( $revision->getModerationState() ) {
				case AbstractRevision::MODERATED_LOCKED:
					$moderateAction = 'unlock';
					$flowAction = 'lock-topic';
					break;
				case AbstractRevision::MODERATED_HIDDEN:
				case AbstractRevision::MODERATED_DELETED:
				case AbstractRevision::MODERATED_SUPPRESSED:
					$moderateAction = 'un' . $revision->getModerationState();
					$flowAction = 'moderate-topic';
					break;
				}
				if ( $moderateAction && $flowAction ) {
					$links[$moderateAction] = $this->urlGenerator->restoreTopicAction(
						$title, $workflowId, $moderateAction, $flowAction );
				}
				break;

			case 'restore-post':
				if ( !$postId ) {
					throw new FlowException( "$type called without \$postId" );
				}
				$moderateAction = $flowAction = null;
				switch ( $revision->getModerationState() ) {
				case AbstractRevision::MODERATED_HIDDEN:
				case AbstractRevision::MODERATED_DELETED:
				case AbstractRevision::MODERATED_SUPPRESSED:
					$moderateAction = 'un' . $revision->getModerationState();
					$flowAction = 'moderate-post';
					break;
				}
				if ( $moderateAction && $flowAction ) {
					$links[$moderateAction] = $this->urlGenerator->restorePostAction(
						$title, $workflowId, $postId, $moderateAction, $flowAction );
				}
				break;

			case 'hide-topic':
				$links['hide'] = $this->urlGenerator->hideTopicAction( $title, $workflowId );
				break;

			// Need to use 'edit-topic-summary' to match FlowActions
			case 'edit-topic-summary':
				// summarize link is only available to topic workflow
				if ( !in_array( $workflow->getType(), [ 'topic', 'topicsummary' ] ) ) {
					break;
				}
				$links['summarize'] = $this->urlGenerator->editTopicSummaryAction( $title, $workflowId );
				break;

			default:
				wfDebugLog( 'Flow', __METHOD__ . ': unkown action link type: ' . $type );
				break;
			}
		}

		return $links;
	}

	/**
	 * @param FormatterRow $row
	 * @return Anchor[]
	 * @throws FlowException
	 */
	public function buildLinks( FormatterRow $row ) {
		$workflow = $row->workflow;
		$revision = $row->revision;
		$title = $workflow->getArticleTitle();
		$action = $revision->getChangeType();
		$workflowId = $workflow->getId();
		$revId = $revision->getRevisionId();
		// @phan-suppress-next-line PhanUndeclaredMethod Checks method_exists
		$postId = method_exists( $revision, 'getPostId' ) ? $revision->getPostId() : null;

		$linkTypes = $this->permissions->getActions()->getValue( $action, 'links' );
		if ( $linkTypes === null ) {
			wfDebugLog( 'Flow', __METHOD__ . ": No links defined for action: $action" );
			return [];
		}

		$links = [];
		$diffCallback = null;
		foreach ( $linkTypes as $type ) {
			switch ( $type ) {
			case 'watch-topic':
				$links['watch-topic'] = $this->urlGenerator->watchTopicLink( $title, $workflowId );
				break;

			case 'unwatch-topic':
				$links['unwatch-topic'] = $this->urlGenerator->unwatchTopicLink( $title, $workflowId );
				break;

			case 'topic':
				$links['topic'] = $this->urlGenerator->topicLink( $title, $workflowId );
				break;

			case 'post':
				if ( !$postId ) {
					wfDebugLog( 'Flow', __METHOD__ . ': No postId available to render post link' );
					break;
				}
				$links['post'] = $this->urlGenerator->postLink( $title, $workflowId, $postId );
				break;

			case 'header-revision':
				$links['header-revision'] = $this->urlGenerator
					->headerRevisionLink( $title, $workflowId, $revId );
				break;

			case 'topic-revision':
				if ( !$postId ) {
					wfDebugLog( 'Flow', __METHOD__ . ': No postId available to render revision link' );
					break;
				}

				$links['topic-revision'] = $this->urlGenerator
					->topicRevisionLink( $title, $workflowId, $revId );
				break;

			case 'post-revision':
				if ( !$postId ) {
					wfDebugLog( 'Flow', __METHOD__ . ': No postId available to render revision link' );
					break;
				}

				$links['post-revision'] = $this->urlGenerator
					->postRevisionLink( $title, $workflowId, $postId, $revId );
				break;

			case 'summary-revision':
				$links['summary-revision'] = $this->urlGenerator
					->summaryRevisionLink( $title, $workflowId, $revId );
				break;

			case 'post-history':
				if ( !$postId ) {
					wfDebugLog( 'Flow', __METHOD__ . ': No postId available to render post-history link' );
					break;
				}
				$links['post-history'] = $this->urlGenerator->postHistoryLink( $title, $workflowId, $postId );
				break;

			case 'topic-history':
				$links['topic-history'] = $this->urlGenerator->workflowHistoryLink( $title, $workflowId );
				break;

			case 'board-history':
				$links['board-history'] = $this->urlGenerator->boardHistoryLink( $title );
				break;

			/** @noinspection PhpMissingBreakStatementInspection */
			case 'diff-header':
				$diffCallback = $diffCallback ?? [ $this->urlGenerator, 'diffHeaderLink' ];
				// don't break, diff links are rendered below
			/** @noinspection PhpMissingBreakStatementInspection */
			case 'diff-post':
				$diffCallback = $diffCallback ?? [ $this->urlGenerator, 'diffPostLink' ];
				// don't break, diff links are rendered below
			case 'diff-post-summary':
				$diffCallback = $diffCallback ?? [ $this->urlGenerator, 'diffSummaryLink' ];

				/*
				 * To diff against previous revision, we don't really need that
				 * revision id; if no particular diff id is specified, it will
				 * assume a diff against previous revision. However, we do want
				 * to make sure that a previous revision actually exists to diff
				 * against. This could result in a network request (fetching the
				 * current revision), but it's likely being loaded anyways.
				 */
				if ( $revision->getPrevRevisionId() !== null ) {
					$links['diff'] = $diffCallback( $title, $workflowId, $revId );

					/*
					 * Different formatters have different terminology for the link
					 * that diffs a certain revision to the previous revision.
					 *
					 * E.g.: Special:Contributions has "diff" ($links['diff']),
					 * ?action=history has "prev" ($links['prev']).
					 */
					$links['diff-prev'] = clone $links['diff'];
					$lastMsg = new Message( 'last' );
					$links['diff-prev']->setTitleMessage( $lastMsg );
					$links['diff-prev']->setMessage( $lastMsg );
				}

				/*
				 * To diff against the current revision, we need to know the id
				 * of this last revision. This could be an additional network
				 * request, though anything using formatter likely already needs
				 * to request the most current revision (e.g. to check
				 * permissions) so we should be able to get it from local cache.
				 */
				$cur = $row->currentRevision;
				if ( !$revId->equals( $cur->getRevisionId() ) ) {
					$links['diff-cur'] = $diffCallback( $title, $workflowId, $cur->getRevisionId(), $revId );
					$curMsg = new Message( 'cur' );
					$links['diff-cur']->setTitleMessage( $curMsg );
					$links['diff-cur']->setMessage( $curMsg );
				}
				break;

			case 'workflow':
				$links['workflow'] = $this->urlGenerator->workflowLink( $title, $workflowId );
				break;

			default:
				wfDebugLog( 'Flow', __METHOD__ . ': unkown action link type: ' . $type );
				break;
			}
		}

		return $links;
	}

	/**
	 * Build api properties defined in FlowActions for this change type
	 *
	 * This is a fairly expensive function(compared to the other methods in this class).
	 * As such its only output when specifically requested
	 *
	 * @param UUID $workflowId
	 * @param AbstractRevision $revision
	 * @param IContextSource $ctx
	 * @param FormatterRow|null $row
	 * @return array
	 */
	public function buildProperties(
		UUID $workflowId,
		AbstractRevision $revision,
		IContextSource $ctx,
		FormatterRow $row = null
	) {
		if ( $this->includeProperties === false ) {
			return [];
		}

		$changeType = $revision->getChangeType();
		$actions = $this->permissions->getActions();
		$params = $actions->getValue( $changeType, 'history', 'i18n-params' );
		if ( !$params ) {
			// should we have a sigil for i18n with no parameters?
			wfDebugLog( 'Flow', __METHOD__ . ": No i18n params for changeType $changeType on " .
				$revision->getRevisionId()->getAlphadecimal() );
			return [];
		}

		$res = [ '_key' => $actions->getValue( $changeType, 'history', 'i18n-message' ) ];
		foreach ( $params as $param ) {
			$res[$param] = $this->processParam( $param, $revision, $workflowId, $ctx, $row );
		}

		return $res;
	}

	/**
	 * Mimic Echo parameter formatting
	 *
	 * @param string $param The requested i18n parameter
	 * @param AbstractRevision|AbstractRevision[] $revision The revision or
	 *  revisions to format or an array of revisions
	 * @param UUID $workflowId The UUID of the workflow $revision belongs tow
	 * @param IContextSource $ctx
	 * @param FormatterRow|null $row
	 * @return mixed A valid parameter for a core Message instance. These
	 *  parameters will be used with Message::parse
	 * @throws FlowException
	 */
	public function processParam(
		$param,
		$revision,
		UUID $workflowId,
		IContextSource $ctx,
		FormatterRow $row = null
	) {
		switch ( $param ) {
		case 'creator-text':
			if ( $revision instanceof PostRevision ) {
				return $this->usernames->getFromTuple( $revision->getCreatorTuple() );
			} else {
				return '';
			}

		case 'user-text':
			return $this->usernames->getFromTuple( $revision->getUserTuple() );

		case 'user-links':
			return Message::rawParam( $this->templating->getUserLinks( $revision ) );

		case 'summary':
			if ( !$this->permissions->isAllowed( $revision, 'view' ) ) {
				return '';
			}

			/*
			 * Fetch in HTML; unparsed wikitext in summary is pointless.
			 * Larger-scale wikis will likely also store content in html, so no
			 * Parsoid roundtrip is needed then (and if it *is*, it'll already
			 * be needed to render Flow discussions, so this is manageable)
			 */
			$content = $this->templating->getContent( $revision, 'fixed-html' );
			// strip html tags and decode to plaintext
			$content = Utils::htmlToPlaintext( $content, 140, $ctx->getLanguage() );
			return Message::plaintextParam( $content );

		case 'wikitext':
			if ( !$this->permissions->isAllowed( $revision, 'view' ) ) {
				return '';
			}

			$format = $revision->getWikitextFormat();

			$content = $this->templating->getContent( $revision, $format );
			// This must be escaped and marked raw to prevent special chars in
			// content, like $1, from changing the i18n result
			return Message::plaintextParam( $content );

		// This is potentially two networked round trips, much too expensive for
		// the rendering loop
		case 'prev-wikitext':
			if ( $revision->isFirstRevision() ) {
				return '';
			}
			if ( $row === null ) {
				$previousRevision = $revision->getCollection()->getPrevRevision( $revision );
			} else {
				$previousRevision = $row->previousRevision;
			}
			if ( !$previousRevision ) {
				return '';
			}
			if ( !$this->permissions->isAllowed( $previousRevision, 'view' ) ) {
				return '';
			}

			$format = $revision->getWikitextFormat();

			$content = $this->templating->getContent( $previousRevision, $format );
			return Message::plaintextParam( $content );
		case 'plaintext':
			if ( !$this->permissions->isAllowed( $revision, 'view' ) ) {
				return '';
			}

			$format = $revision->getHtmlFormat();

			$content = Utils::htmlToPlaintext( $this->templating->getContent( $revision, $format ) );
			return Message::plaintextParam( $content );

		// This is potentially two networked round trips, much too expensive for
		// the rendering loop
		case 'prev-plaintext':
			if ( $revision->isFirstRevision() ) {
				return '';
			}
			if ( $row === null ) {
				$previousRevision = $revision->getCollection()->getPrevRevision( $revision );
			} else {
				$previousRevision = $row->previousRevision;
			}
			if ( !$previousRevision ) {
				return '';
			}
			if ( !$this->permissions->isAllowed( $previousRevision, 'view' ) ) {
				return '';
			}

			$format = $revision->getHtmlFormat();

			$content = Utils::htmlToPlaintext( $this->templating->getContent( $previousRevision, $format ) );
			return Message::plaintextParam( $content );

		case 'workflow-url':
			return $this->urlGenerator
				->workflowLink( null, $workflowId )
				->getFullURL();

		case 'post-url':
			if ( !$revision instanceof PostRevision ) {
				throw new FlowException( 'Expected PostRevision but received' . get_class( $revision ) );
			}
			return $this->urlGenerator
				->postLink( null, $workflowId, $revision->getPostId() )
				->getFullURL();

		case 'moderated-reason':
			// don't parse wikitext in the moderation reason
			return Message::plaintextParam( $revision->getModeratedReason() ?? '' );

		case 'topic-of-post':
			if ( !$revision instanceof PostRevision ) {
				throw new FlowException( 'Expected PostRevision but received ' . get_class( $revision ) );
			}

			$root = $revision->getRootPost();
			if ( !$this->permissions->isAllowed( $root, 'view-topic-title' ) ) {
				return '';
			}

			$content = $this->templating->getContent( $root, 'topic-title-wikitext' );

			// TODO: We need to use plaintextParam or similar to avoid parsing,
			// but the API output says "plaintext", which is confusing and
			// should be fixed.  From the API consumer's perspective, it's
			// topic-title-wikitext.
			return Message::plaintextParam( $content );

		// Strip the tags from the HTML version to produce text:
		// [[Red link 3]], [[Adrines]], [[Media:Earth.jpg]], http://example.com =>
		// Red link 3, Adrines, Media:Earth.jpg, http://example.com
		case 'topic-of-post-text-from-html':
			if ( !$revision instanceof PostRevision ) {
				throw new FlowException( 'Expected PostRevision but received ' . get_class( $revision ) );
			}

			$root = $revision->getRootPost();
			if ( !$this->permissions->isAllowed( $root, 'view-topic-title' ) ) {
				return '';
			}

			$content = $this->templating->getContent( $root, 'topic-title-plaintext' );

			return Message::plaintextParam( $content );

		case 'post-of-summary':
			if ( !$revision instanceof PostSummary ) {
				throw new FlowException( 'Expected PostSummary but received ' . get_class( $revision ) );
			}

			/** @var PostRevision $post */
			$post = $revision->getCollection()->getPost()->getLastRevision();
			// @phan-suppress-next-line PhanUndeclaredMethod Type not correctly inferred
			$permissionAction = $post->isTopicTitle() ? 'view-topic-title' : 'view';
			if ( !$this->permissions->isAllowed( $post, $permissionAction ) ) {
				return '';
			}

			// @phan-suppress-next-line PhanUndeclaredMethod Type not correctly inferred
			if ( $post->isTopicTitle() ) {
				return Message::plaintextParam( $this->templating->getContent(
					$post, 'topic-title-plaintext' ) );
			} else {
				return Message::rawParam( $this->templating->getContent( $post, 'fixed-html' ) );
			}

		case 'bundle-count':
			return Message::numParam( count( $revision ) );

		default:
			wfWarn( __METHOD__ . ': Unknown formatter parameter: ' . $param );
			return '';
		}
	}

	protected function msg( $key, ...$params ) {
		if ( $params ) {
			return wfMessage( $key, ...$params );
		}
		if ( !isset( $this->messages[$key] ) ) {
			$this->messages[$key] = new Message( $key );
		}
		return $this->messages[$key];
	}

	/**
	 * Determines the exact output content format, given the requested content format
	 * and the revision type.
	 *
	 * @param AbstractRevision $revision
	 * @return string Content format
	 * @throws FlowException If a per-revision format was given and it is
	 *  invalid for the revision type (topic title/non-topic title).
	 */
	public function decideContentFormat( AbstractRevision $revision ) {
		$requestedRevFormat = null;
		$requestedDefaultFormat = null;

		$alpha = $revision->getRevisionId()->getAlphadecimal();
		if ( isset( $this->revisionContentFormat[$alpha] ) ) {
			$requestedRevFormat = $this->revisionContentFormat[$alpha];
		} else {
			$requestedDefaultFormat = $this->contentFormat;
		}

		if ( $revision instanceof PostRevision && $revision->isTopicTitle() ) {
			return $this->decideTopicTitleContentFormat(
				$revision, $requestedRevFormat, $requestedDefaultFormat );
		} else {
			return $this->decideNonTopicTitleContentFormat(
				$revision, $requestedRevFormat, $requestedDefaultFormat );
		}
	}

	/**
	 * Decide the content format for a topic title
	 *
	 * @param PostRevision $topicTitle Topic title revision
	 * @param string|null $requestedRevFormat Format requested for this specific revision
	 * @param string|null $requestedDefaultFormat Default format requested
	 * @return string
	 * @throws FlowException If a per-revision format was given and it is
	 *  invalid for topic titles.
	 */
	protected function decideTopicTitleContentFormat(
		PostRevision $topicTitle,
		$requestedRevFormat,
		$requestedDefaultFormat
	) {
		if ( $requestedRevFormat !== null ) {
			if ( $requestedRevFormat !== 'topic-title-html' &&
				$requestedRevFormat !== 'topic-title-wikitext'
			) {
				throw new FlowException( 'Per-revision format for a topic title must be ' .
					'\'topic-title-html\' or \'topic-title-wikitext\'' );
			}
			return $requestedRevFormat;
		} else {
			// Since this is a default format, we'll canonicalize it.

			// Because these are both editable formats, and this is the only
			// editable topic title format.
			if ( $requestedDefaultFormat === 'topic-title-wikitext' || $requestedDefaultFormat === 'html' ||
				$requestedDefaultFormat === 'wikitext'
			) {
				return 'topic-title-wikitext';
			} else {
				return 'topic-title-html';
			}
		}
	}

	/**
	 * Decide the content format for revisions other than topic titles
	 *
	 * @param AbstractRevision $revision Revision to decide format for
	 * @param string|null $requestedRevFormat Format requested for this specific revision
	 * @param string|null $requestedDefaultFormat Default format requested
	 * @return string
	 * @throws FlowException If a per-revision format was given and it is
	 *  invalid for this type
	 */
	protected function decideNonTopicTitleContentFormat(
		AbstractRevision $revision,
		$requestedRevFormat,
		$requestedDefaultFormat
	) {
		if ( $requestedRevFormat !== null ) {
			if ( $requestedRevFormat === 'topic-title-html' ||
				$requestedRevFormat === 'topic-title-wikitext'
			) {
				throw new FlowException( 'Invalid per-revision format.  Only topic titles can use  ' .
					'\'topic-title-html\' and \'topic-title-wikitext\'' );
			}
			return $requestedRevFormat;
		} else {
			if ( $requestedDefaultFormat === 'topic-title-html' ||
				$requestedDefaultFormat === 'topic-title-wikitext'
			) {
				throw new FlowException( 'Default format of \'topic-title-html\' or ' .
					'\'topic-title-wikitext\' can only be used to format topic titles.' );
			}

			return $requestedDefaultFormat;
		}
	}
}
