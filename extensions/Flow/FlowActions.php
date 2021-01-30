<?php

use Flow\Data\Listener\RecentChangesListener;
use Flow\Log\ModerationLogger;
use Flow\Model\AbstractRevision;
use Flow\Model\Header;
use Flow\Model\PostRevision;
use Flow\Model\PostSummary;
use Flow\RevisionActionPermissions;

/**
 * Flow actions: key => value map with key being the action name.
 * The value consists of an array of these below keys (and appropriate values):
 * * performs-writes: Must be boolean true for any action that writes to the wiki.
 *     actions with this set will additionally require the core 'edit' permission.
 * * log_type: the Special:Log filter to save actions to; false means 'not logged'.
 * * rc_insert: whether or not to insert the write action into RC table.
 * * permissions: array of permissions, where each key is the existing post
 *     state and the value is the right required to execute the action.  A blank
 *     value means anyone can take the action.  However, an omitted key means
 *     no one can perform the action described by that key.
 * * root-permissions: similar to 'permissions', but applies to the last revision
 *   of the root post (= the topic) for the revision the action is executed against.
 *   If root-permissions is omitted entirely, it doesn't affect what is allowed.
 *   However, if any keys are set, omitted keys are treated as prohibited.
 * * core-delete-permissions: array of rights, where any of those rights will
 *     give you permission to do the action on a deleted board (isAllowedAny).
 *     Empty string and omitted behave like 'permissions'.
 * * links: the set of read links to generate and return in API responses
 * * actions: the set of write links to generate and return in API responses
 * * history: all history-related information:
 *   * i18n-message: the i18n message key for this change type
 *   * i18n-params: array of i18n parameters for the provided message (see
 *     AbstractFormatter::processParam phpdoc for more details)
 *   * class: classname to be added to the list-item for this changetype
 *   * bundle: array with, again, all of the above information if multiple types
 *     should be bundled (then the bundle i18n & class will be used to generate
 *     the list-item; clicking on it will reveal the individual history entries)
 * * watch: Used by the WatchTopicListener to auto-subscribe users to topics. Only
 *   value value currently is immediate.
 *   * immediate: watchlist the title in the current process
 * * rc_title: Either 'article' or 'owner' to select between Workflow::getArticleTitle
 *     or Workflow::getOwnerTitle being used as the related recentchanges entry on insert
 * * editcount: True to increment user's edit count for this action
 * * modules: Modules to insert with RL to html page for this action instead of the defaults.
 * *   All actions other than view should have an array here, unless the default
 * *   modules are known to work.  You can specify an empty array, or a custom set of modules.
 * * moduleStyles: Style modules to insert with RL to html page for this action instead of the defaults
 * * hasUserGeneratedContent: Whether this action renders a page consisting of user-generated content
 */
$wgFlowActions = [
	'create-header' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			Header::MODERATED_NONE => '',
		],
		'links' => [ 'board-history', 'workflow', 'header-revision' ],
		'actions' => [ 'edit-header' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-create-header',
			'i18n-params' => [
				'user-links',
				'user-text',
			],
			'class' => 'flow-history-create-header',
		],
		'editcount' => true,
	],

	'edit-header' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			Header::MODERATED_NONE => '',
		],
		'links' => [ 'board-history', 'diff-header', 'workflow', 'header-revision' ],
		'actions' => [ 'edit-header', 'undo-edit-header' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-header',
			'i18n-params' => [
				'user-links',
				'user-text',
			],
			'class' => 'flow-history-edit-header',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'editcount' => true,
	],

	// @todo this is almost copy/paste from edit-header except the handler-class. find
	// a way to share.
	'undo-edit-header' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			Header::MODERATED_NONE => '',
		],
		'links' => [ 'board-history', 'diff-header', 'workflow', 'header-revision' ],
		'actions' => [ 'edit-header', 'undo-edit-header' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-header',
			'i18n-params' => [
				'user-links',
				'user-text',
			],
			'class' => 'flow-history-edit-header',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'editcount' => true,
		// theis modules/moduleStyles is repeated in all the undo-* actions. Find a way to share.
		'moduleStyles' => [
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'ext.flow.styles.base',
			'ext.flow.board.styles',
			'ext.flow.board.topic.styles',
		],
	],

	'create-topic-summary' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostSummary::MODERATED_NONE => '',
			PostSummary::MODERATED_LOCKED => [ 'flow-lock', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_HIDDEN => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_SUPPRESSED => [ 'flow-suppress' ],
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_LOCKED => '',
		],
		'links' => [
			'topic', 'topic-history', 'diff-post-summary', 'watch-topic', 'unwatch-topic',
			'summary-revision'
		],
		'actions' => [ 'edit-topic-summary', 'lock-topic', 'restore-topic' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-create-topic-summary',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-of-summary',
			],
			'class' => 'flow-history-create-topic-summary',
		],
		'editcount' => true,
	],

	'edit-topic-summary' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostSummary::MODERATED_NONE => '',
			PostSummary::MODERATED_LOCKED => [ 'flow-lock', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_HIDDEN => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_SUPPRESSED => [ 'flow-suppress' ],
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_LOCKED => '',
		],
		'links' => [
			'topic', 'topic-history', 'diff-post-summary', 'watch-topic', 'unwatch-topic',
			'summary-revision'
		],
		'actions' => [
			'edit-topic-summary', 'lock-topic', 'restore-topic', 'undo-edit-topic-summary'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-topic-summary',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-of-summary',
			],
			'class' => 'flow-history-edit-topic-summary',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'editcount' => true,
	],

	// @todo this is almost copy/paste from edit-topic-summary except the handler class. find a
	// way to share
	'undo-edit-topic-summary' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostSummary::MODERATED_NONE => '',
			PostSummary::MODERATED_LOCKED => [ 'flow-lock', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_HIDDEN => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostSummary::MODERATED_SUPPRESSED => [ 'flow-suppress' ],
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [ 'topic', 'topic-history', 'diff-post-summary', 'watch-topic', 'unwatch-topic' ],
		'actions' => [ 'edit-topic-summary', 'lock-topic', 'restore-topic', 'undo-edit-topic-summary' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-topic-summary',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-of-summary',
			],
			'class' => 'flow-history-edit-topic-summary',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'editcount' => true,
		'moduleStyles' => [
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'ext.flow.styles.base',
			'ext.flow.board.styles',
			'ext.flow.board.topic.styles',
		],
	],

	'edit-title' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [
			'topic', 'topic-history', 'diff-post', 'topic-revision', 'watch-topic', 'unwatch-topic'
		],
		'actions' => [
			'reply', 'thank', 'edit-title', 'lock-topic', 'hide-topic', 'delete-topic',
			'suppress-topic', 'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-title',
			'i18n-params' => [
				'user-links',
				'user-text',
				'workflow-url',
				'plaintext',
				'prev-plaintext',
			],
			'class' => 'flow-history-edit-title',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'watch' => [
			'immediate' => [ \Flow\Data\Listener\ImmediateWatchTopicListener::class, 'getCurrentUser' ],
		],
		'editcount' => true,
	],

	'new-topic' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_title' => 'owner',
		'rc_insert' => true,
		'exclude_from_contributions' => true,

		// If you add exclude_from_history to new change types, you *must* update
		// the *HistoryQuery's (to use doInternalQueries with a good overfetch factor).
		// You should also adjust the memcached indices for best results.
		'exclude_from_history' => true,

		// exclude_from_recentchanges only refers to the actual Special:RecentChanges.
		// It does not affect Special:Watchlist.
		'exclude_from_recentchanges' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [
			'topic-history', 'topic', 'post', 'topic-revision', 'watch-topic', 'unwatch-topic'
		],
		'actions' => [
			'reply', 'thank', 'edit-title', 'hide-topic', 'delete-topic', 'suppress-topic',
			'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-new-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'workflow-url',
				'wikitext',
			],
			'class' => 'flow-history-new-post',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'watch' => [
			'immediate' => [ \Flow\Data\Listener\ImmediateWatchTopicListener::class, 'getCurrentUser' ],
		],
		'editcount' => true,
	],

	'edit-post' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			// no permissions needed for own posts
			PostRevision::MODERATED_NONE => function (
				PostRevision $post, RevisionActionPermissions $permissions
			) {
				return $post->isCreator( $permissions->getUser() ) ? '' : 'flow-edit-post';
			}
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [ 'post-history', 'topic-history', 'topic', 'post', 'diff-post', 'post-revision' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post',
			'suppress-post', 'undo-edit-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-url',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-edit-post',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'watch' => [
			'immediate' => [ \Flow\Data\Listener\ImmediateWatchTopicListener::class, 'getCurrentUser' ],
		],
		'editcount' => true,
	],

	// @todo this is almost (but not quite) copy/paste from 'edit-post'. find a way to share?
	'undo-edit-post' => [
		'performs-writes' => true,
		'log_type' => false, // maybe?
		'rc_insert' => true,
		'permissions' => [
			// no permissions needed for own posts
			PostRevision::MODERATED_NONE => function (
				PostRevision $post, RevisionActionPermissions $permissions
			) {
				return $post->isCreator( $permissions->getUser() ) ? '' : 'flow-edit-post';
			}
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [ 'post-history', 'topic-history', 'topic', 'post', 'diff-post', 'post-revision' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post',
			'suppress-post', 'undo-edit-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-edit-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-url',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-edit-post',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'watch' => [
			'immediate' => [ \Flow\Data\Listener\ImmediateWatchTopicListener::class, 'getCurrentUser' ],
		],
		'editcount' => true,
		'moduleStyles' => [
			'mediawiki.ui.button',
			'mediawiki.ui.input',
			'ext.flow.styles.base',
			'ext.flow.board.styles',
			'ext.flow.board.topic.styles',
		],
	],

	'hide-post' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			// Permissions required to perform action. The key is the moderation state
			// of the post to perform the action against. The value is a string or array
			// of user rights that can allow this action.
			PostRevision::MODERATED_NONE => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
		],
		'root-permissions' => [
			// Can only hide within an unmoderated or hidden topic. This doesn't check for a specific
			// permissions because thats already done above in 'permissions', this just ensures the
			// topic is in an appropriate state.
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_HIDDEN => '',
		],
		'links' => [ 'topic', 'post', 'post-history', 'topic-history', 'post-revision' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post', 'suppress-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-hid-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'post-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-hide-post',
		],
	],

	'hide-topic' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
		],
		'links' => [
			'topic', 'post', 'topic-history', 'post-history', 'topic-revision', 'watch-topic', 'unwatch-topic'
		],
		'actions' => [
			'reply', 'thank', 'edit-title', 'restore-topic', 'hide-topic', 'delete-topic', 'suppress-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-hid-topic',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'workflow-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-hide-topic',
		],
	],

	'delete-post' => [
		'performs-writes' => true,
		'log_type' => 'delete',
		'rc_insert' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_HIDDEN => [ 'flow-delete', 'flow-suppress' ],
		],
		'links' => [
			'topic', 'post', 'post-history', 'topic-history', 'post-revision', 'watch-topic', 'unwatch-topic'
		],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post', 'suppress-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-deleted-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'post-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-delete-post',
		],
	],

	'delete-topic' => [
		'performs-writes' => true,
		'log_type' => 'delete',
		'rc_insert' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_HIDDEN => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_LOCKED => [ 'flow-delete', 'flow-suppress' ],
		],
		'links' => [ 'topic', 'topic-history', 'topic-revision', 'watch-topic', 'unwatch-topic' ],
		'actions' => [
			'reply', 'thank', 'edit-title', 'hide-topic', 'delete-topic', 'suppress-topic',
			'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-deleted-topic',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'workflow-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-delete-topic',
		],
	],

	'suppress-post' => [
		'performs-writes' => true,
		'log_type' => 'suppress',
		'rc_insert' => false,
		'permissions' => [
			PostRevision::MODERATED_NONE => 'flow-suppress',
			PostRevision::MODERATED_HIDDEN => 'flow-suppress',
			PostRevision::MODERATED_DELETED => 'flow-suppress',
		],
		'links' => [ 'topic', 'post', 'topic-history', 'post-revision' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post', 'suppress-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-suppressed-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'post-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-suppress-post',
		],
	],

	'suppress-topic' => [
		'performs-writes' => true,
		'log_type' => 'suppress',
		'rc_insert' => false,
		'permissions' => [
			PostRevision::MODERATED_NONE => 'flow-suppress',
			PostRevision::MODERATED_HIDDEN => 'flow-suppress',
			PostRevision::MODERATED_DELETED => 'flow-suppress',
			PostRevision::MODERATED_LOCKED => 'flow-suppress',
		],
		'links' => [ 'topic', 'topic-history', 'topic-revision', 'watch-topic', 'unwatch-topic' ],
		'actions' => [
			'reply', 'thank', 'edit-title', 'hide-topic', 'delete-topic', 'suppress-topic',
			'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-suppressed-topic',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'workflow-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-suppress-topic',
		],
	],

	'lock-topic' => [
		'performs-writes' => true,
		'log_type' => 'lock',
		'rc_insert' => true,
		'permissions' => [
			// Only non-moderated topic can be locked
			PostRevision::MODERATED_NONE => [ 'flow-lock', 'flow-delete', 'flow-suppress' ],
		],
		'links' => [ 'topic', 'topic-history', 'watch-topic', 'unwatch-topic', 'topic-revision' ],
		'actions' => [ 'edit-topic-summary', 'restore-topic', 'delete-topic', 'suppress-topic' ],
		'history' => [
			'i18n-message' => 'flow-rev-message-locked-topic',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'workflow-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => 'flow-history-locked-topic',
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],

	'restore-post' => [
		'performs-writes' => true,
		'log_type' => function ( PostRevision $revision, ModerationLogger $logger ) {
			$post = $revision->getCollection();
			$previousRevision = $post->getPrevRevision( $revision );
			if ( $previousRevision ) {
				// Kind of log depends on the previous change type:
				// * if post was deleted, restore should go to deletion log
				// * if post was suppressed, restore should go to suppression log
				global $wgFlowActions;
				return $wgFlowActions[$previousRevision->getModerationState() . '-post']['log_type'];
			}

			return '';
		},
		'rc_insert' => function ( PostRevision $revision, RecentChangesListener $recentChanges ) {
			$post = $revision->getCollection();
			$previousRevision = $post->getPrevRevision( $revision );
			if ( $previousRevision ) {
				// * if post was hidden/deleted, restore can go to RC
				// * if post was suppressed, restore can not go to RC
				global $wgFlowActions;
				return $wgFlowActions[$previousRevision->getModerationState() . '-post']['rc_insert'];
			}

			return true;
		},
		'permissions' => [
			PostRevision::MODERATED_HIDDEN => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'links' => [ 'topic', 'post', 'post-history', 'post-revision' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'restore-post', 'hide-post', 'delete-post', 'suppress-post'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-restored-post',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'post-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => function ( PostRevision $revision ) {
				$previous = $revision->getCollection()->getPrevRevision( $revision );
				$state = $previous->getModerationState();
				return "flow-history-un$state-post";
			}
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],

	'restore-topic' => [
		'performs-writes' => true,
		'log_type' => function ( PostRevision $revision, ModerationLogger $logger ) {
			$post = $revision->getCollection();
			$previousRevision = $post->getPrevRevision( $revision );
			if ( $previousRevision ) {
				// Kind of log depends on the previous change type:
				// * if topic was deleted, restore should go to deletion log
				// * if topic was suppressed, restore should go to suppression log
				global $wgFlowActions;
				return $wgFlowActions[$previousRevision->getModerationState() . '-topic']['log_type'];
			}

			return '';
		},
		'rc_insert' => function ( PostRevision $revision, RecentChangesListener $recentChanges ) {
			$post = $revision->getCollection();
			$previousRevision = $post->getPrevRevision( $revision );
			if ( $previousRevision ) {
				// * if topic was hidden/deleted, restore can go to RC
				// * if topic was suppressed, restore can not go to RC
				global $wgFlowActions;
				return $wgFlowActions[$previousRevision->getModerationState() . '-topic']['rc_insert'];
			}

			return true;
		},
		'permissions' => [
			PostRevision::MODERATED_LOCKED => [ 'flow-lock', 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_HIDDEN => [ 'flow-hide', 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'links' => [ 'topic', 'topic-history', 'topic-revision', 'watch-topic', 'unwatch-topic' ],
		'actions' => [
			'reply', 'thank', 'edit-title', 'hide-topic', 'delete-topic', 'suppress-topic',
			'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-restored-topic',
			'i18n-params' => [
				'user-links',
				'user-text',
				'creator-text',
				'workflow-url',
				'moderated-reason',
				'topic-of-post-text-from-html',
			],
			'class' => function ( PostRevision $revision ) {
				$previous = $revision->getCollection()->getPrevRevision( $revision );
				$state = $previous->getModerationState();
				return "flow-history-un$state-topic";
			}
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],

	'view' => [
		'performs-writes' => false,
		'hasUserGeneratedContent' => true,
		'log_type' => false, // don't log views
		'rc_insert' => false, // won't even be called, actually; only for writes
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
			// Everyone has permission to see this,
			// but hidden comments are only visible (collapsed) on permalinks directly to them.
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'core-delete-permissions' => [ 'deletedtext' ],
		'links' => [], // @todo
		'actions' => [], // view is not a recorded change type, no actions will be requested
		'history' => [], // views don't generate history
		'handler-class' => \Flow\Actions\ViewAction::class,
	],

	'reply' => [
		'performs-writes' => true,
		'log_type' => false,
		'rc_insert' => true,
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
		],
		'links' => [ 'topic-history', 'topic', 'post', 'post-revision', 'watch-topic', 'unwatch-topic' ],
		'actions' => [
			'reply', 'thank', 'edit-post', 'hide-post', 'delete-post', 'suppress-post',
			'edit-topic-summary', 'lock-topic', 'restore-topic'
		],
		'history' => [
			'i18n-message' => 'flow-rev-message-reply',
			'i18n-params' => [
				'user-links',
				'user-text',
				'post-url',
				'topic-of-post-text-from-html',
				'summary',
			],
			'class' => 'flow-history-reply',
			'bundle' => [
				'i18n-message' => 'flow-rev-message-reply-bundle',
				'i18n-params' => [
					'bundle-count'
				],
				'class' => 'flow-history-bundle',
			],
		],
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
		'watch' => [
			'immediate' => [ \Flow\Data\Listener\ImmediateWatchTopicListener::class, 'getCurrentUser' ],
		],
		'editcount' => true,
	],

	'history' => [
		'performs-writes' => false,
		'log_type' => false,
		'rc_insert' => false, // won't even be called, actually; only for writes
		'permissions' => [
			PostRevision::MODERATED_NONE => function (
				AbstractRevision $revision,
				RevisionActionPermissions $permissions
			) {
				static $previousCollectionId;

				/*
				 * To check permissions, both the current revision (revision-
				 * specific moderation state) & the last revision (global
				 * collection moderation state) will always be checked.
				 * This one has special checks to make sure "restore" actions
				 * are hidden when the user has no permissions to see the
				 * moderation state they were restored from.
				 * We don't want that test to happen; otherwise, when a post
				 * has just been restored in the most recent revisions, that
				 * would result in none of the previous revisions being
				 * available (because a user would need permissions for the
				 * state the last revision was restored from)
				 */
				$collection = $revision->getCollection();
				if ( $previousCollectionId && $collection->getId()->equals( $previousCollectionId ) ) {
					// doublecheck that this run is indeed against the most
					// recent revision, to get the global collection state
					try {
						/** @var Flow\Collection\CollectionCache $cache */
						$cache = \Flow\Container::get( 'collection.cache' );
						$lastRevision = $cache->getLastRevisionFor( $revision );
						if ( $revision->getRevisionId()->equals( $lastRevision->getRevisionId() ) ) {
							$previousCollectionId = null;
							return '';
						}
					} catch ( Exception $e ) {
						// nothing to do here; if fetching last revision failed,
						// we're just not testing any stored revision; that's ok
					}
				}
				$previousCollectionId = $collection->getId();

				/*
				 * If a revision was the result of a restore-action, we have
				 * to look at the previous revision what the original moderation
				 * status was; permissions for the restore-actions visibility
				 * is the same as the moderation (e.g. if user can't see
				 * suppress actions, he can't see restores from suppress.
				 */
				if ( strpos( $revision->getChangeType(), 'restore-' ) === 0 ) {
					$previous = $collection->getPrevRevision( $revision );

					if ( $previous === null ||
						$previous->getModerationState() === AbstractRevision::MODERATED_NONE
					) {
						return '';
					}

					return $permissions->getPermission( $previous, 'history' );
				}

				return '';
			},
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_DELETED => '',
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_HIDDEN => '',
			// No data should be shown for other moderation levels: if a topic
			// has been deleted, we don't want a bunch of irrelevant
			// "new reply", "edit", ... spam in there.
			// All we want is the "topic has been deleted", which will still be
			// displayed (root-permissions won't be tested for the topic, since
			// it is the root)
		],
		'core-delete-permissions' => [ 'deletedhistory' ],
		'history' => [], // views don't generate history
		'handler-class' => \Flow\Actions\FlowAction::class,
	],

	// Pseudo-action to determine when to show thank links,
	// currently no limitation. if you can see revision you
	// can thank.
	'thank' => [
		'performs-writes' => false,
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_DELETED => '',
			PostRevision::MODERATED_SUPPRESSED => '',
		],
	],

	'view-topic-summary' => [
		'performs-writes' => false,
		'hasUserGeneratedContent' => true,
		'log_type' => false, // don't log views
		'rc_insert' => false, // won't even be called, actually; only for writes
		'permissions' => [
			PostRevision::MODERATED_NONE => '',
			// Everyone has permission to see this,
			// but hidden comments are only visible (collapsed) on permalinks directly to them.
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_DELETED => [ 'flow-delete', 'flow-suppress' ],
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'root-permissions' => [
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
		],
		'core-delete-permissions' => [ 'deletedtext' ],
		'links' => [], // @todo
		'actions' => [], // view is not a recorded change type, no actions will be requested
		'history' => [], // views don't generate history
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],

	// This is only used when we specifically want to see the topic title.  If we're
	// cascading from a post (to view a post we need to be able to view the topic),
	// we'll use 'view' for both the post and topic root.  Unprivileged users shouldn't
	// be able to view a post in a deleted topic, but should be able to view the topic
	// title.
	'view-topic-title' => [
		'performs-writes' => false,
		'hasUserGeneratedContent' => true,
		'log_type' => false, // don't log views
		'rc_insert' => false, // won't even be called, actually; only for writes
		'permissions' => [
			// Everyone can see topic titles on existent boards, unless the
			// version you're viewing is suppressed, or the most recent version
			// is
			PostRevision::MODERATED_NONE => '',
			PostRevision::MODERATED_HIDDEN => '',
			PostRevision::MODERATED_LOCKED => '',
			PostRevision::MODERATED_DELETED => '',
			PostRevision::MODERATED_SUPPRESSED => 'flow-suppress',
		],
		'core-delete-permissions' => [ 'deletedtext' ],
		'links' => [], // @todo
		'actions' => [], // view is not a recorded change type, no actions will be requested
		'history' => [], // views don't generate history
		'modules' => [],
	],

	// Actions not tied to a particular revision change_type
	// or just move these to a different file
	// @todo: we should probably at least add 'permissions' in these below
	'compare-header-revisions' => [
		'hasUserGeneratedContent' => true,
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	'view-header' => [
		'hasUserGeneratedContent' => true,
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	'compare-post-revisions' => [
		'hasUserGeneratedContent' => true,
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	// @todo - This is a very bad action name, consolidate with view-post action
	'single-view' => [
		'hasUserGeneratedContent' => true,
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	'compare-postsummary-revisions' => [
		'hasUserGeneratedContent' => true,
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	'moderate-topic' => [
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],
	'moderate-post' => [
		'handler-class' => \Flow\Actions\FlowAction::class,
		'modules' => [],
	],

	// Other formatters have the same config as history
	'recentchanges' => 'history',
	'contributions' => 'history',
	'checkuser' => 'history',

	/*
	 * Backwards compatibility; these are old values that may have made their
	 * way into the database. patch-rev_change_type_update.sql should take care
	 * of these, but just to be sure ;)
	 * Instead of having the correct config-array as value, you can just
	 * reference another action.
	 */
	'flow-rev-message-edit-title' => 'edit-title',
	'flow-edit-title' => 'edit-title',
	'flow-rev-message-new-post' => 'new-topic',
	'flow-new-post' => 'new-topic',
	'flow-rev-message-edit-post' => 'edit-post',
	'flow-edit-post' => 'edit-post',
	'flow-rev-message-reply' => 'reply',
	'flow-reply' => 'reply',
	'flow-rev-message-restored-post' => 'restore-post',
	'flow-post-restored' => 'restore-post',
	'flow-rev-message-hid-post' => 'hide-post',
	'flow-post-hidden' => 'hide-post',
	'flow-rev-message-deleted-post' => 'delete-post',
	'flow-post-deleted' => 'delete-post',
	'flow-rev-message-censored-post' => 'suppress-post',
	'flow-post-censored' => 'suppress-post',
	'flow-rev-message-edit-header' => 'edit-header',
	'flow-edit-summary' => 'edit-header',
	'flow-rev-message-create-header' => 'create-header',
	'flow-create-summary' => 'create-header',
	'flow-create-header' => 'create-header',
	/*
	 * Backwards compatibility for previous suppression terminology (=censor).
	 * patch-censor_to_suppress.sql should take care of all of these occurrences.
	 */
	'censor-post' => 'suppress-post',
	'censor-topic' => 'suppress-topic',
	/*
	 * Backwards compatibility for old (separated) history actions
	 */
	'post-history' => 'history',
	'topic-history' => 'history',
	'board-history' => 'history',

	// The new-topic type used to be called new-post
	'new-post' => 'new-topic',

	// BC for lock-topic, which used to be called differently
	'close-topic' => 'lock-topic',
	'close-open-topic' => 'lock-topic',
];
