<?php

namespace Flow\Notifications;

use EchoEvent;
use EchoEventMapper;
use EchoModerationController;
use ExtensionRegistry;
use Flow\Container;
use Flow\Conversion\Utils;
use Flow\Exception\FlowException;
use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\Model\UUID;
use Flow\Model\Workflow;
use Flow\Repository\TreeRepository;
use Language;
use MediaWiki\MediaWikiServices;
use Title;
use User;

class Controller {
	/**
	 * @var Language
	 */
	protected $language;

	/**
	 * @var TreeRepository
	 */
	protected $treeRepository;

	/**
	 * @param Language $language
	 * @param TreeRepository $treeRepository
	 */
	public function __construct( Language $language, TreeRepository $treeRepository ) {
		$this->language = $language;
		$this->treeRepository = $treeRepository;
	}

	public static function onBeforeCreateEchoEvent( &$notifs, &$categories, &$icons ) {
		$notifs += require dirname( dirname( __DIR__ ) ) . "/Notifications.php";
		$categories['flow-discussion'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-flow-discussion',
		];
		$icons['flow-new-topic'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-new-topic-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-new-topic-rtl.svg'
			]
		];
		$icons['flowusertalk-new-topic'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-new-topic-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-new-topic-rtl.svg'
			]
		];
		$icons['flow-post-edited'] = $icons['flowusertalk-post-edited'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-post-edited-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-post-edited-rtl.svg'
			]
		];
		$icons['flow-topic-renamed'] = $icons['flowusertalk-topic-renamed'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-topic-renamed-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-topic-renamed-rtl.svg'
			]
		];
		$icons['flow-topic-resolved'] = $icons['flowusertalk-topic-resolved'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-topic-resolved-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-topic-resolved-rtl.svg'
			]
		];
		$icons['flow-topic-reopened'] = $icons['flowusertalk-topic-reopened'] = [
			'path' => [
				'ltr' => 'Flow/modules/notification/icon/flow-topic-reopened-ltr.svg',
				'rtl' => 'Flow/modules/notification/icon/flow-topic-reopened-rtl.svg'
			]
		];
	}

	/**
	 * Causes notifications to be fired for a Header-related event.
	 * @param array $data Associative array of parameters.
	 * * revision: The PostRevision created by the action. Always required.
	 * * board-workflow: The Workflow object for the board. Always required.
	 * * timestamp: Original event timestamp, for imports. Optional.
	 * * extra-data: Additional data to pass along to Event extra.
	 * @return array Array of created EchoEvent objects.
	 * @throws FlowException When $data contains unexpected types/values
	 */
	public function notifyHeaderChange( $data = [] ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return [];
		}

		if ( isset( $data['extra-data'] ) ) {
			$extraData = $data['extra-data'];
		} else {
			$extraData = [];
		}

		$revision = $data['revision'];
		if ( !$revision instanceof Header ) {
			throw new FlowException( 'Expected Header but received ' . get_class( $revision ) );
		}
		$boardWorkflow = $data['board-workflow'];
		if ( !$boardWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $boardWorkflow ) );
		}

		$user = $revision->getUser();
		list( $mentionedUsers, $mentionsSkipped ) = $this->getMentionedUsersAndSkipState( $revision );

		$extraData['content'] = Utils::htmlToPlaintext( $revision->getContent(), 200, $this->language );
		$extraData['revision-id'] = $revision->getRevisionId();
		$extraData['collection-id'] = $revision->getCollectionId();
		$extraData['target-page'] = $boardWorkflow->getArticleTitle()->getArticleID();
		// pass along mentioned users to other notification, so it knows who to ignore
		$extraData['mentioned-users'] = $mentionedUsers;
		$title = $boardWorkflow->getOwnerTitle();

		$info = [
			'agent' => $user,
			'title' => $title,
			'extra' => $extraData,
		];

		// Allow a specific timestamp to be set - useful when importing existing data
		if ( isset( $data['timestamp'] ) ) {
			$info['timestamp'] = $data['timestamp'];
		}

		$events = [ EchoEvent::create( [ 'type' => 'flow-description-edited' ] + $info ) ];
		if ( $title->getNamespace() === NS_USER_TALK ) {
			$events[] = EchoEvent::create( [ 'type' => 'flowusertalk-description-edited' ] + $info );
		}
		if ( $mentionedUsers ) {
			$mentionEvents = $this->generateMentionEvents(
				$revision,
				null,
				$boardWorkflow,
				$user,
				$mentionedUsers,
				$mentionsSkipped
			);
			$events = array_merge( $events, $mentionEvents );
		}

		return $events;
	}

	/**
	 * Causes notifications to be fired for a Flow event.
	 * @param string $eventName The event that occurred. Choice of:
	 * * flow-post-reply
	 * * flow-topic-renamed
	 * * flow-post-edited
	 * @param array $data Associative array of parameters.
	 * * user: The user who made the change. Always required.
	 * * revision: The PostRevision created by the action. Always required.
	 * * title: The Title on which this Topic sits. Always required.
	 * * topic-workflow: The Workflow object for the topic. Always required.
	 * * topic-title: The Title of the Topic that the post belongs to. Required except for topic renames.
	 * * old-subject: The old subject of a Topic. Required for topic renames.
	 * * new-subject: The new subject of a Topic. Required for topic renames.
	 * @return array Array of created EchoEvent objects.
	 * @throws FlowException When $data contains unexpected types/values
	 */
	public function notifyPostChange( $eventName, $data = [] ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return [];
		}

		if ( isset( $data['extra-data'] ) ) {
			$extraData = $data['extra-data'];
		} else {
			$extraData = [];
		}

		$revision = $data['revision'];
		if ( !$revision instanceof PostRevision ) {
			throw new FlowException( 'Expected PostRevision but received ' . get_class( $revision ) );
		}
		$topicRevision = $data['topic-title'];
		if ( !$topicRevision instanceof PostRevision ) {
			throw new FlowException( 'Expected PostRevision but received ' . get_class( $topicRevision ) );
		}
		$topicWorkflow = $data['topic-workflow'];
		if ( !$topicWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $topicWorkflow ) );
		}

		$user = $revision->getUser();
		list( $mentionedUsers, $mentionsSkipped ) = $this->getMentionedUsersAndSkipState( $revision );
		$title = $topicWorkflow->getOwnerTitle();

		$extraData['revision-id'] = $revision->getRevisionId();
		$extraData['post-id'] = $revision->getPostId();
		$extraData['topic-workflow'] = $topicWorkflow->getId();
		$extraData['target-page'] = $topicWorkflow->getArticleTitle()->getArticleID();
		// pass along mentioned users to other notification, so it knows who to ignore
		$extraData['mentioned-users'] = $mentionedUsers;

		switch ( $eventName ) {
			case 'flow-post-reply':
				$extraData += [
					'reply-to' => $revision->getReplyToId(),
					'content' => Utils::htmlToPlaintext( $revision->getContent(), 200, $this->language ),
					'topic-title' => $this->language->truncateForVisual( $topicRevision->getContent( 'topic-title-plaintext' ), 200 ),
				];

				// if we're looking at the initial post (submitted along with the topic
				// title), we don't want to send the flow-post-reply notification,
				// because users will already receive flow-new-topic as well
				if ( $this->isFirstPost( $revision, $topicWorkflow ) ) {
					// if users were mentioned here, we'll want to make sure
					// that they weren't also mentioned in the topic title (in
					// which case they would get 2 notifications...)
					if ( $mentionedUsers ) {
						list( $mentionedInTitle, $mentionsSkippedInTitle ) =
							$this->getMentionedUsersAndSkipState( $topicRevision );
						$mentionedUsers = array_diff_key( $mentionedUsers, $mentionedInTitle );
						$mentionsSkipped = $mentionsSkipped || $mentionsSkippedInTitle;
						$extraData['mentioned-users'] = $mentionedUsers;
					}

					return $this->generateMentionEvents(
						$revision,
						$topicRevision,
						$topicWorkflow,
						$user,
						$mentionedUsers,
						$mentionsSkipped
					);
				}

			break;
			case 'flow-topic-renamed':
				$previousRevision = $revision->getCollection()->getPrevRevision( $revision );
				$extraData += [
					'old-subject' => $this->language->truncateForVisual( $previousRevision->getContent( 'topic-title-plaintext' ), 200 ),
					'new-subject' => $this->language->truncateForVisual( $revision->getContent( 'topic-title-plaintext' ), 200 ),
				];
			break;
			case 'flow-post-edited':
				$extraData += [
					'content' => Utils::htmlToPlaintext( $revision->getContent(), 200, $this->language ),
					'topic-title' => $this->language->truncateForVisual( $topicRevision->getContent( 'topic-title-plaintext' ), 200 ),
				];
			break;
		}

		$info = [
			'agent' => $user,
			'title' => $title,
			'extra' => $extraData,
		];

		// Allow a specific timestamp to be set - useful when importing existing data
		if ( isset( $data['timestamp'] ) ) {
			$info['timestamp'] = $data['timestamp'];
		}

		$events = [ EchoEvent::create( [ 'type' => $eventName ] + $info ) ];
		if ( $title->getNamespace() === NS_USER_TALK ) {
			$usertalkEvent = str_replace( 'flow-', 'flowusertalk-', $eventName );
			$events[] = EchoEvent::create( [ 'type' => $usertalkEvent ] + $info );
		}
		if ( $mentionedUsers ) {
			$mentionEvents = $this->generateMentionEvents(
				$revision,
				$topicRevision,
				$topicWorkflow,
				$user,
				$mentionedUsers,
				$mentionsSkipped
			);
			$events = array_merge( $events, $mentionEvents );
		}

		return $events;
	}

	/**
	 * Causes notifications to be fired for a Summary-related event.
	 * @param array $data Associative array of parameters.
	 * * revision: The PostRevision created by the action. Always required.
	 * * topic-title: The PostRevision object for the topic title. Always required.
	 * * topic-workflow: The Workflow object for the board. Always required.
	 * * extra-data: Additional data to pass along to Event extra.
	 * @return array Array of created EchoEvent objects.
	 * @throws FlowException When $data contains unexpected types/values
	 */
	public function notifySummaryChange( $data = [] ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return [];
		}

		$revision = $data['revision'];
		if ( !$revision instanceof PostSummary ) {
			throw new FlowException( 'Expected PostSummary but received ' . get_class( $revision ) );
		}
		$topicRevision = $data['topic-title'];
		if ( !$topicRevision instanceof PostRevision ) {
			throw new FlowException( 'Expected PostRevision but received ' . get_class( $topicRevision ) );
		}
		$topicWorkflow = $data['topic-workflow'];
		if ( !$topicWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $topicWorkflow ) );
		}

		$user = $revision->getUser();
		list( $mentionedUsers, $mentionsSkipped ) = $this->getMentionedUsersAndSkipState( $revision );

		$extraData = [];
		$extraData['content'] = Utils::htmlToPlaintext( $revision->getContent(), 200, $this->language );
		$extraData['revision-id'] = $revision->getRevisionId();
		$extraData['prev-revision-id'] = $revision->getPrevRevisionId();
		$extraData['topic-workflow'] = $topicWorkflow->getId();
		$extraData['topic-title'] = $this->language->truncateForVisual( $topicRevision->getContent( 'topic-title-plaintext' ), 200 );
		$extraData['target-page'] = $topicWorkflow->getArticleTitle()->getArticleID();
		// pass along mentioned users to other notification, so it knows who to ignore
		$extraData['mentioned-users'] = $mentionedUsers;
		$title = $topicWorkflow->getOwnerTitle();

		$info = [
			'agent' => $user,
			'title' => $title,
			'extra' => $extraData,
		];

		// Allow a specific timestamp to be set - useful when importing existing data
		if ( isset( $data['timestamp'] ) ) {
			$info['timestamp'] = $data['timestamp'];
		}

		$events = [ EchoEvent::create( [ 'type' => 'flow-summary-edited' ] + $info ) ];
		if ( $title->getNamespace() === NS_USER_TALK ) {
			$events[] = EchoEvent::create( [ 'type' => 'flowusertalk-summary-edited' ] + $info );
		}
		if ( $mentionedUsers ) {
			$mentionEvents = $this->generateMentionEvents(
				$revision,
				$topicRevision,
				$topicWorkflow,
				$user,
				$mentionedUsers,
				$mentionsSkipped
			);
			$events = array_merge( $events, $mentionEvents );
		}

		return $events;
	}

	/**
	 * Triggers notifications for a new topic.
	 * @param array $params Associative array of parameters, all required:
	 * * board-workflow: Workflow object for the Flow board.
	 * * topic-workflow: Workflow object for the new Topic.
	 * * topic-title: PostRevision object for the "topic post", containing the
	 *    title.
	 * * first-post: PostRevision object for the first post, or null when no first post.
	 * * user: The User who created the topic.
	 * @return array Array of created EchoEvent objects.
	 * @throws FlowException When $params contains unexpected types/values
	 */
	public function notifyNewTopic( $params ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			// Nothing to do here.
			return [];
		}

		$topicWorkflow = $params['topic-workflow'];
		if ( !$topicWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $topicWorkflow ) );
		}
		$topicTitle = $params['topic-title'];
		if ( !$topicTitle instanceof PostRevision ) {
			throw new FlowException( 'Expected PostRevision but received ' . get_class( $topicTitle ) );
		}
		$firstPost = $params['first-post'];
		if ( $firstPost !== null && !$firstPost instanceof PostRevision ) {
			throw new FlowException( 'Expected PostRevision but received ' . get_class( $firstPost ) );
		}
		$user = $topicTitle->getUser();
		$boardWorkflow = $params['board-workflow'];
		if ( !$boardWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $boardWorkflow ) );
		}

		list( $mentionedUsers, $mentionsSkipped ) = $this->getMentionedUsersAndSkipState( $topicTitle );

		$title = $boardWorkflow->getArticleTitle();
		$events = [];
		$eventData = [
			'agent' => $user,
			'title' => $title,
			'extra' => [
				'board-workflow' => $boardWorkflow->getId(),
				'topic-workflow' => $topicWorkflow->getId(),
				'post-id' => $firstPost ? $firstPost->getRevisionId() : null,
				'topic-title' => $this->language->truncateForVisual( $topicTitle->getContent( 'topic-title-plaintext' ), 200 ),
				'content' => $firstPost
					? Utils::htmlToPlaintext( $firstPost->getContent(), 200, $this->language )
					: null,
				// Force a read from master database since this could be a new page
				'target-page' => [
					$topicWorkflow->getOwnerTitle()->getArticleID( Title::GAID_FOR_UPDATE ),
					$topicWorkflow->getArticleTitle()->getArticleID( Title::GAID_FOR_UPDATE ),
				],
				// pass along mentioned users to other notification, so it knows who to ignore
				// also look at users mentioned in first post: if there are any, this
				// (flow-new-topic) notification shouldn't go through (because they'll
				// already receive the mention notification)
				'mentioned-users' => $mentionedUsers,
			]
		];
		$events[] = EchoEvent::create( [ 'type' => 'flow-new-topic' ] + $eventData );
		if ( $title->getNamespace() === NS_USER_TALK ) {
			$events[] = EchoEvent::create( [ 'type' => 'flowusertalk-new-topic' ] + $eventData );
		}

		if ( $mentionedUsers ) {
			$mentionEvents = $this->generateMentionEvents(
				$topicTitle,
				$topicTitle,
				$topicWorkflow,
				$user,
				$mentionedUsers,
				$mentionsSkipped
			);
			$events = array_merge( $events, $mentionEvents );
		}

		return $events;
	}

	/**
	 * Triggers notifications when a topic is resolved or reopened.
	 *
	 * @param string $type flow-topic-resolved|flow-topic-reopened
	 * @param array $data
	 * @return array
	 * @throws \Flow\Exception\InvalidDataException
	 * @throws FlowException
	 * @throws \MWException
	 */
	public function notifyTopicLocked( $type, $data = [] ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			return [];
		}

		$revision = $data['revision'];
		if ( !$revision instanceof PostRevision ) {
			throw new FlowException( 'Expected PostSummary but received ' . get_class( $revision ) );
		}
		$topicWorkflow = $data['topic-workflow'];
		if ( !$topicWorkflow instanceof Workflow ) {
			throw new FlowException( 'Expected Workflow but received ' . get_class( $topicWorkflow ) );
		}

		$extraData = [];
		$extraData['topic-workflow'] = $topicWorkflow->getId();
		$extraData['topic-title'] = Utils::htmlToPlaintext( $revision->getContent( 'topic-title-html' ), 200, $this->language );
		$extraData['target-page'] = $topicWorkflow->getArticleTitle()->getArticleID();
		// I'll treat resolve & reopen as the same notification type, but pass the
		// different type so presentation models can differentiate
		$extraData['type'] = $type;
		$title = $topicWorkflow->getOwnerTitle();

		$info = [
			'agent' => $revision->getUser(),
			'title' => $title,
			'extra' => $extraData,
		];

		// Allow a specific timestamp to be set - useful when importing existing data
		if ( isset( $data['timestamp'] ) ) {
			$info['timestamp'] = $data['timestamp'];
		}

		$events = [ EchoEvent::create( [ 'type' => 'flow-topic-resolved' ] + $info ) ];
		if ( $title->getNamespace() === NS_USER_TALK ) {
			$events[] = EchoEvent::create( [ 'type' => 'flowusertalk-topic-resolved' ] + $info );
		}
		return $events;
	}

	public function notifyFlowEnabledOnTalkpage( User $user ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			// Nothing to do here.
			return [];
		}

		$events = [];
		$events[] = EchoEvent::create( [
			'type' => 'flow-enabled-on-talkpage',
			'agent' => $user,
			'title' => $user->getTalkPage(),
		] );

		return $events;
	}

	/**
	 * @param AbstractRevision $content The (post|topic|header) revision that contains the content of the mention
	 * @param PostRevision|null $topic Topic PostRevision object, if relevant (e.g. not for Header)
	 * @param Workflow $workflow
	 * @param User $user User who created the new post
	 * @param array $mentionedUsers
	 * @param bool $mentionsSkipped Were mentions skipped due to too many mentions being attempted?
	 * @return bool|EchoEvent[]
	 * @throws \Flow\Exception\InvalidDataException
	 * @throws \MWException
	 */
	protected function generateMentionEvents(
		AbstractRevision $content,
		?PostRevision $topic,
		Workflow $workflow,
		User $user,
		array $mentionedUsers,
		$mentionsSkipped
	) {
		global $wgEchoMentionStatusNotifications, $wgFlowMaxMentionCount;

		if ( count( $mentionedUsers ) === 0 ) {
			return false;
		}

		$extraData = [];
		$extraData['mentioned-users'] = $mentionedUsers;
		$extraData['target-page'] = $workflow->getArticleTitle()->getArticleID();
		// don't include topic content again if the notification IS in the title
		// @phan-suppress-next-line PhanImpossibleTypeComparison
		$extraData['content'] = $content === $topic ? '' : Utils::htmlToPlaintext( $content->getContent(), 200, $this->language );
		// lets us differentiate between different revision types
		$extraData['revision-type'] = $content->getRevisionType();

		// additional data needed to render link to post
		if ( $extraData['revision-type'] === 'post' ) {
			$extraData['post-id'] = $content->getCollection()->getId();
		}
		// needed to render topic title text & link to topic
		if ( $topic !== null ) {
			$extraData['topic-workflow'] = $workflow->getId();
			$extraData['topic-title'] = $this->language->truncateForVisual( $topic->getContent( 'topic-title-plaintext' ), 200 );
		}

		$events = [];
		$events[] = EchoEvent::create( [
			'type' => 'flow-mention',
			'title' => $workflow->getOwnerTitle(),
			'extra' => $extraData,
			'agent' => $user,
		] );
		if ( $wgEchoMentionStatusNotifications && $mentionsSkipped ) {
			$extra = [
				'topic-workflow' => $workflow->getId(),
				'max-mentions' => $wgFlowMaxMentionCount,
				// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
				'section-title' => $extraData['topic-title'],
				'failure-type' => 'too-many',
			];
			if ( $content->getRevisionType() === 'post' ) {
				$extra['post-id'] = $content->getCollection()->getId();
			}
			$events[] = EchoEvent::create( [
				'type' => 'flow-mention-failure-too-many',
				'title' => $workflow->getOwnerTitle(),
				'extra' => $extra,
				'agent' => $user,
			] );
		}
		return $events;
	}

	/**
	 * Analyses a PostRevision to determine which users are mentioned.
	 *
	 * @param AbstractRevision $revision The Post to analyse.
	 * @return array
	 *          0 => int[] Array of user IDs
	 *          1 => bool Were some mentions ignored due to $wgFlowMaxMentionCount?
	 * @phan-return array{0:int[],1:bool}
	 */
	protected function getMentionedUsersAndSkipState( AbstractRevision $revision ) {
		// At the moment, it is not possible to get a list of mentioned users from HTML
		// unless that HTML comes from Parsoid. But VisualEditor (what is currently used
		// to convert wikitext to HTML) does not currently use Parsoid.
		$wikitext = $revision->getContentInWikitext();
		$mentions = $this->getMentionedUsersFromWikitext( $wikitext );

		// if this post had a previous revision (= this is an edit), we don't
		// want to pick up on the same mentions as in the previous edit, only
		// new mentions
		$previousRevision = $revision->getCollection()->getPrevRevision( $revision );
		if ( $previousRevision !== null ) {
			$previousWikitext = $previousRevision->getContentInWikitext();
			$previousMentions = $this->getMentionedUsersFromWikitext( $previousWikitext );
			$mentions = array_diff( $mentions, $previousMentions );
		}

		return $this->filterMentionedUsers( $mentions, $revision );
	}

	/**
	 * Process an array of users linked to in a comment into a list of users
	 * who should actually be notified.
	 *
	 * Removes duplicates, anonymous users, self-mentions, and mentions of the
	 * owner of the talk page
	 * @param User[] $mentions Array of User objects
	 * @param AbstractRevision $revision The Post that is being examined.
	 * @return array
	 *          0 => int[] Array of user IDs
	 *          1 => bool Were some mentions ignored due to $wgFlowMaxMentionCount?
	 * @phan-return array{0:int[],1:bool}
	 */
	protected function filterMentionedUsers( $mentions, AbstractRevision $revision ) {
		global $wgFlowMaxMentionCount;

		$outputMentions = [];
		$mentionsSkipped = false;

		foreach ( $mentions as $mentionedUser ) {
			// Don't notify anonymous users
			if ( $mentionedUser->isAnon() ) {
				continue;
			}

			// Don't notify the user who made the post
			if ( $mentionedUser->getId() == $revision->getUserId() ) {
				continue;
			}

			if ( count( $outputMentions ) >= $wgFlowMaxMentionCount ) {
				$mentionsSkipped = true;
				break;
			}

			$outputMentions[$mentionedUser->getId()] = $mentionedUser->getId();
		}

		return [ $outputMentions, $mentionsSkipped ];
	}

	/**
	 * Examines a wikitext string and finds users that were mentioned
	 * @param string $wikitext
	 * @return User[] Array of User objects
	 */
	protected function getMentionedUsersFromWikitext( $wikitext ) {
		$title = Title::newMainPage(); // Bogus title used for parser

		$options = new \ParserOptions;
		$options->setTidy( true );

		$output = MediaWikiServices::getInstance()->getParser()
			->parse( $wikitext, $title, $options );

		$links = $output->getLinks();

		if ( !isset( $links[NS_USER] ) || !is_array( $links[NS_USER] ) ) {
			// Nothing
			return [];
		}

		$users = [];
		foreach ( $links[NS_USER] as $dbk => $page_id ) {
			$user = User::newFromName( $dbk );
			if ( !$user || $user->isAnon() ) {
				continue;
			}

			$users[$user->getId()] = $user;
		}

		return $users;
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification
	 *
	 * @param EchoEvent $event
	 * @param string &$bundleString Determines how the notification should be bundled
	 * @return bool True for success
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'flow-new-topic':
			case 'flowusertalk-new-topic':
				$board = $event->getExtraParam( 'board-workflow' );
				if ( $board instanceof UUID ) {
					$bundleString = $event->getType() . '-' . $board->getAlphadecimal();
				}
				break;

			case 'flow-post-reply':
			case 'flowusertalk-post-reply':
			case 'flow-post-edited':
			case 'flowusertalk-post-edited':
			case 'flow-summary-edited':
			case 'flowusertalk-summary-edited':
				$topic = $event->getExtraParam( 'topic-workflow' );
				if ( $topic instanceof UUID ) {
					$bundleString = $event->getType() . '-' . $topic->getAlphadecimal();
				}
				break;

			case 'flow-description-edited':
			case 'flowusertalk-description-edited':
				$headerId = $event->getExtraParam( 'collection-id' );
				if ( $headerId instanceof UUID ) {
					$bundleString = $event->getType() . '-' . $headerId->getAlphadecimal();
				}
				break;
		}
		return true;
	}

	/**
	 * Get the owner of the page if the workflow belongs to a talk page
	 *
	 * @param string $topicId Topic workflow UUID
	 * @return array Map from userid to User object
	 */
	protected static function getTalkPageOwner( $topicId ) {
		$talkUser = [];
		// Owner of talk page should always get a reply notification
		/** @var Workflow|null $workflow */
		$workflow = Container::get( 'storage' )
			->getStorage( 'Workflow' )
			->get( UUID::create( $topicId ) );
		if ( $workflow ) {
			$title = $workflow->getOwnerTitle();
			if ( $title->isTalkPage() ) {
				$user = User::newFromName( $title->getDBkey() );
				if ( $user && $user->getId() ) {
					$talkUser[$user->getId()] = $user;
				}
			}
		}
		return $talkUser;
	}

	/**
	 * @param PostRevision $revision
	 * @param Workflow $workflow
	 * @return bool
	 */
	protected function isFirstPost( PostRevision $revision, Workflow $workflow ) {
		$postId = $revision->getPostId();
		$workflowId = $workflow->getId();
		$replyToId = $revision->getReplyToId();

		// if the post is not a direct reply to the topic, it definitely can't be
		// first post
		if ( !$replyToId->equals( $workflowId ) ) {
			return false;
		}

		/*
		 * We don't want to go fetch the entire topic tree, so we'll use a crude
		 * technique to figure out if we're dealing with the first post: check if
		 * they were posted at (almost) the exact same time.
		 * If they're more than 1 second apart, it's very likely a not-first-post
		 * (or a very slow server, upgrade your machine!). False positives on the
		 * other side are also very rare: who on earth can refresh the page, read
		 * the post and write a meaningful reply in just 1 second? :)
		 */
		$diff = (int)$postId->getTimestamp( TS_UNIX ) - (int)$workflowId->getTimestamp( TS_UNIX );
		return $diff <= 1;
	}

	/**
	 * Gets ID of topmost post
	 *
	 * This is the lowest-number post, numbering them using a pre-order depth-first
	 *  search
	 *
	 * @param EchoEvent[] $bundledEvents
	 * @return UUID|null Post ID, or null on failure
	 */
	public function getTopmostPostId( array $bundledEvents ) {
		$postIds = [];
		foreach ( $bundledEvents as $event ) {
			$postId = $event->getExtraParam( 'post-id' );
			if ( $postId instanceof UUID ) {
				$postIds[$postId->getAlphadecimal()] = $postId;
			}
		}

		$rootPaths = $this->treeRepository->findRootPaths( $postIds );

		// We do this so we don't have to walk the whole topic.
		$deepestCommonRoot = $this->getDeepestCommonRoot( $rootPaths );

		$subtree = $this->treeRepository->fetchSubtreeIdentityMap( $deepestCommonRoot );

		$topmostPostId = $this->getFirstPreorderDepthFirst( $postIds, $deepestCommonRoot, $subtree );
		return $topmostPostId;
	}

	/**
	 * Walks a (sub)tree in pre-order depth-first search order and return the first
	 *  post ID from a specified list
	 *
	 * @param array $relevantPostIds Associative array mapping alphadecimal post ID to
	 *  UUID post ID
	 * @param UUID $root Root node
	 * @param array $tree Tree structure
	 * @return UUID|null First post ID found, or null on failure
	 */
	protected function getFirstPreorderDepthFirst( array $relevantPostIds, UUID $root, array $tree ) {
		$rootAlpha = $root->getAlphadecimal();

		if ( isset( $relevantPostIds[$rootAlpha] ) ) {
			return $root;
		}

		if ( isset( $tree[$rootAlpha]['children'] ) ) {
			$children = array_keys( $tree[$rootAlpha]['children'] );
		} else {
			$children = [];
		}

		foreach ( $children as $child ) {
			$relevantPostId = $this->getFirstPreorderDepthFirst( $relevantPostIds, UUID::create( $child ), $tree );
			if ( $relevantPostId !== null ) {
				return $relevantPostId;
			}
		}

		return null;
	}

	/**
	 * Gets the deepest common root post
	 *
	 * This is the root of the smallest subtree all the posts are in.
	 *
	 * @param array[] $rootPaths Associative array mapping post IDs to root paths
	 * @return UUID|null Common root, or null on failure
	 */
	protected function getDeepestCommonRoot( array $rootPaths ) {
		if ( count( $rootPaths ) == 0 ) {
			return null;
		}

		$deepestRoot = null;
		$possibleDeepestRoot = null;

		$firstPath = reset( $rootPaths );
		$pathLength = count( $firstPath );

		for ( $i = 0; $i < $pathLength; $i++ ) {
			$possibleDeepestRoot = $firstPath[$i];

			foreach ( $rootPaths as $path ) {
				if ( !isset( $path[$i] ) || !$path[$i]->equals( $possibleDeepestRoot ) ) {
					// Mismatch.  Return the last match we found
					return $deepestRoot;
				}
			}

			$deepestRoot = $possibleDeepestRoot;
		}

		return $deepestRoot;
	}

	/**
	 * Moderate or unmoderate Flow notifications associated with a topic.
	 *
	 * @param UUID $topicId
	 * @param bool $moderated Whether the events need to be moderated or unmoderated
	 * @throws FlowException
	 */
	public function moderateTopicNotifications( UUID $topicId, $moderated ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			// Nothing to do here.
			return;
		}

		$title = Title::makeTitle( NS_TOPIC, ucfirst( $topicId->getAlphadecimal() ) );
		$pageId = $title->getArticleID();
		\DeferredUpdates::addCallableUpdate( function () use ( $pageId, $moderated ) {
			$eventMapper = new EchoEventMapper();
			$eventIds = $eventMapper->fetchIdsByPage( $pageId );

			EchoModerationController::moderate( $eventIds, $moderated );
		} );
	}

	/**
	 * Moderate or unmoderate Flow notifications associated with a post within a topic.
	 *
	 * @param UUID $topicId
	 * @param UUID $postId
	 * @param bool $moderated Whether the events need to be moderated or unmoderated
	 * @throws FlowException
	 */
	public function moderatePostNotifications( UUID $topicId, UUID $postId, $moderated ) {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
			// Nothing to do here.
			return;
		}

		$title = Title::makeTitle( NS_TOPIC, ucfirst( $topicId->getAlphadecimal() ) );
		$pageId = $title->getArticleID();
		\DeferredUpdates::addCallableUpdate( function () use ( $pageId, $postId, $moderated ) {
			$eventMapper = new \EchoEventMapper();
			$moderatedPostIdAlpha = $postId->getAlphadecimal();
			$eventIds = [];

			$events = $eventMapper->fetchByPage( $pageId );

			foreach ( $events as $event ) {
				/** @var UUID|string $eventPostId */
				$eventPostId = $event->getExtraParam( 'post-id' );
				$eventPostIdAlpha = $eventPostId instanceof UUID ? $eventPostId->getAlphadecimal() : $eventPostId;
				if ( $eventPostIdAlpha === $moderatedPostIdAlpha ) {
					$eventIds[] = $event->getId();
				}
			}

			EchoModerationController::moderate( $eventIds, $moderated );
		} );
	}
}
