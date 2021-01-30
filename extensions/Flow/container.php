<?php

use Flow\Data\FlowObjectCache;
use Flow\Data\Index\PostRevisionBoardHistoryIndex;
use Flow\Data\Index\PostRevisionTopicHistoryIndex;
use Flow\Data\Index\PostSummaryRevisionBoardHistoryIndex;
use Flow\Data\Index\TopKIndex;
use Flow\Data\Index\UniqueFeatureIndex;
use Flow\Data\Mapper\BasicObjectMapper;
use Flow\Data\Mapper\CachingObjectMapper;
use Flow\Data\ObjectLocator;
use Flow\Data\ObjectManager;
use Flow\Data\Storage\BasicDbStorage;
use Flow\Data\Storage\HeaderRevisionStorage;
use Flow\Data\Storage\PostRevisionBoardHistoryStorage;
use Flow\Data\Storage\PostRevisionStorage;
use Flow\Data\Storage\PostRevisionTopicHistoryStorage;
use Flow\Data\Storage\PostSummaryRevisionBoardHistoryStorage;
use Flow\Data\Storage\PostSummaryRevisionStorage;
use Flow\Data\Storage\TopicListStorage;
use MediaWiki\MediaWikiServices;

// This lets the index handle the initial query from HistoryPager,
// even when the UI limit is 500.  An extra item is requested
// so we know whether to link the pagination.
if ( !defined( 'FLOW_HISTORY_INDEX_LIMIT' ) ) {
	define( 'FLOW_HISTORY_INDEX_LIMIT', 501 );
}

// 501 * OVERFETCH_FACTOR from HistoryQuery + 1
// Basically, this is so we can try to fetch enough extra to handle
// exclude_from_history without retrying.
if ( !defined( 'FLOW_BOARD_TOPIC_HISTORY_POST_INDEX_LIMIT' ) ) {
	define( 'FLOW_BOARD_TOPIC_HISTORY_POST_INDEX_LIMIT', 682 );
}

$c = new Flow\Container;

// MediaWiki
if ( defined( 'RUN_MAINTENANCE_IF_MAIN' ) ) {
	$c['user'] = new User;
} else {
	$c['user'] = $GLOBALS['wgUser'] ?? new User;
}

// Flow config
$c['flow_actions'] = function ( $c ) {
	global $wgFlowActions;
	return new Flow\FlowActions( $wgFlowActions );
};

// Always returns the correct database for flow storage
$c['db.factory'] = function ( $c ) {
	global $wgFlowDefaultWikiDb, $wgFlowCluster;
	return new Flow\DbFactory( $wgFlowDefaultWikiDb, $wgFlowCluster );
};

// Database Access Layer external from main implementation
$c['repository.tree'] = function ( $c ) {
	return new Flow\Repository\TreeRepository(
		$c['db.factory'],
		$c['flowcache']
	);
};

$c['url_generator'] = function ( $c ) {
	return new Flow\UrlGenerator(
		$c['storage.workflow.mapper']
	);
};

$c['watched_items'] = function ( $c ) {
	return new Flow\WatchedTopicItems(
		$c['user'],
		wfGetDB( DB_REPLICA, 'watchlist' )
	);
};

$c['wiki_link_fixer'] = function ( $c ) {
	return new Flow\Parsoid\Fixer\WikiLinkFixer( new LinkBatch );
};

$c['bad_image_remover'] = function ( $c ) {
	return new Flow\Parsoid\Fixer\BadImageRemover(
		[ MediaWikiServices::getInstance()->getBadFileLookup(), 'isBadFile' ]
	);
};

$c['base_href_fixer'] = function ( $c ) {
	global $wgArticlePath;

	return new Flow\Parsoid\Fixer\BaseHrefFixer( $wgArticlePath );
};

$c['ext_link_fixer'] = function ( $c ) {
	return new Flow\Parsoid\Fixer\ExtLinkFixer();
};

$c['content_fixer'] = function ( $c ) {
	return new Flow\Parsoid\ContentFixer(
		$c['wiki_link_fixer'],
		$c['bad_image_remover'],
		$c['base_href_fixer'],
		$c['ext_link_fixer']
	);
};

$c['permissions'] = function ( $c ) {
	return new Flow\RevisionActionPermissions( $c['flow_actions'], $c['user'] );
};

$c['lightncandy'] = function ( $c ) {
	global $wgFlowServerCompileTemplates;

	return new Flow\TemplateHelper(
		__DIR__ . '/handlebars',
		$wgFlowServerCompileTemplates
	);
};

$c['templating'] = function ( $c ) {
	global $wgOut;

	return new Flow\Templating(
		$c['repository.username'],
		$c['url_generator'],
		$wgOut,
		$c['content_fixer'],
		$c['permissions']
	);
};

// New Storage Impl
$c['flowcache'] = function ( $c ) {
	global $wgFlowCacheTime;
	return new FlowObjectCache(
		MediaWikiServices::getInstance()->getMainWANObjectCache(),
		$c['db.factory'], $wgFlowCacheTime
	);
};

// Batched username loader
$c['repository.username'] = function ( $c ) {
	return new Flow\Repository\UserNameBatch(
		new Flow\Repository\UserName\TwoStepUserNameQuery(
			$c['db.factory']
		)
	);
};
$c['collection.cache'] = function ( $c ) {
	return new Flow\Collection\CollectionCache();
};
// Individual workflow instances
$c['storage.workflow.mapper'] = function ( $c ) {
	return CachingObjectMapper::model(
		\Flow\Model\Workflow::class,
		[ 'workflow_id' ]
	);
};
$c['storage.workflow.backend'] = function ( $c ) {
	return new BasicDbStorage(
		$c['db.factory'],
		'flow_workflow',
		[ 'workflow_id' ]
	);
};
$c['storage.workflow.indexes.primary'] = function ( $c ) {
	return new UniqueFeatureIndex(
		$c['flowcache'],
		$c['storage.workflow.backend'],
		$c['storage.workflow.mapper'],
		'flow_workflow:v2:pk',
		[ 'workflow_id' ]
	);
};
$c['storage.workflow.indexes.title_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.workflow.backend'],
		$c['storage.workflow.mapper'],
		'flow_workflow:title:v2:',
		[ 'workflow_wiki', 'workflow_namespace', 'workflow_title_text', 'workflow_type' ],
		[
			'shallow' => $c['storage.workflow.indexes.primary'],
			'limit' => 1,
			'sort' => 'workflow_id'
		]
	);
};
$c['storage.workflow.indexes'] = function ( $c ) {
	return [
		$c['storage.workflow.indexes.primary'],
		$c['storage.workflow.indexes.title_lookup']
	];
};
$c['storage.workflow.listeners'] = function ( $c ) {
	return [
		'listener.topicpagecreation' => $c['listener.topicpagecreation'],
		'storage.workflow.listeners.topiclist' => new Flow\Data\Listener\WorkflowTopicListListener(
			$c['storage.topic_list'],
			$c['storage.topic_list.indexes.last_updated']
		),
	];
};
$c['storage.workflow'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.workflow.mapper'],
		$c['storage.workflow.backend'],
		$c['db.factory'],
		$c['storage.workflow.indexes'],
		$c['storage.workflow.listeners']
	);
};
$c['listener.recentchanges'] = function ( $c ) {
	// Recent change listeners go out to external services and
	// as such must only be run after the transaction is commited.
	return new Flow\Data\Listener\DeferredInsertLifecycleHandler(
		$c['deferred_queue'],
		new Flow\Data\Listener\RecentChangesListener(
			$c['flow_actions'],
			$c['repository.username'],
			new Flow\Data\Utils\RecentChangeFactory,
			$c['formatter.irclineurl']
		)
	);
};
$c['listener.topicpagecreation'] = function ( $c ) {
	return new Flow\Data\Listener\TopicPageCreationListener(
		$c['occupation_controller'],
		$c['deferred_queue']
	);
};
$c['listeners.notification'] = function ( $c ) {
	// Defer notifications triggering till end of request so we could get
	// article_id in the case of a new topic
	return new Flow\Data\Listener\DeferredInsertLifecycleHandler(
		$c['deferred_queue'],
		new Flow\Data\Listener\NotificationListener(
			$c['controller.notification']
		)
	);
};

$c['storage.post_board_history.backend'] = function ( $c ) {
	return new PostRevisionBoardHistoryStorage( $c['db.factory'] );
};
$c['storage.post_board_history.indexes.primary'] = function ( $c ) {
	return new PostRevisionBoardHistoryIndex(
		$c['flowcache'],
		// backend storage
		$c['storage.post_board_history.backend'],
		// data mapper
		$c['storage.post.mapper'],
		// key prefix
		'flow_revision:topic_list_history:post:v2',
		// primary key
		[ 'topic_list_id' ],
		// index options
		[
			'limit' => FLOW_BOARD_TOPIC_HISTORY_POST_INDEX_LIMIT,
			'sort' => 'rev_id',
			'order' => 'DESC'
		],
		$c['storage.topic_list']
	);
};

$c['storage.post_board_history.indexes'] = function ( $c ) {
	return [ $c['storage.post_board_history.indexes.primary'] ];
};

$c['storage.post_board_history'] = function ( $c ) {
	return new ObjectLocator(
		$c['storage.post.mapper'],
		$c['storage.post_board_history.backend'],
		$c['db.factory'],
		$c['storage.post_board_history.indexes']
	);
};

$c['storage.post_summary_board_history.backend'] = function ( $c ) {
	return new PostSummaryRevisionBoardHistoryStorage( $c['db.factory'] );
};
$c['storage.post_summary_board_history.indexes.primary'] = function ( $c ) {
	return new PostSummaryRevisionBoardHistoryIndex(
		$c['flowcache'],
		// backend storage
		$c['storage.post_summary_board_history.backend'],
		// data mapper
		$c['storage.post_summary.mapper'],
		// key prefix
		'flow_revision:topic_list_history:post_summary:v2',
		// primary key
		[ 'topic_list_id' ],
		// index options
		[
			'limit' => FLOW_HISTORY_INDEX_LIMIT,
			'sort' => 'rev_id',
			'order' => 'DESC'
		],
		$c['storage.topic_list']
	);
};

$c['storage.post_summary_board_history.indexes'] = function ( $c ) {
	return [ $c['storage.post_summary_board_history.indexes.primary'] ];
};

$c['storage.post_summary_board_history'] = function ( $c ) {
	return new ObjectLocator(
		$c['storage.post_summary.mapper'],
		$c['storage.post_summary_board_history.backend'],
		$c['db.factory'],
		$c['storage.post_summary_board_history.indexes']
	);
};

$c['storage.header.listeners.username'] = function ( $c ) {
	return new Flow\Data\Listener\UserNameListener(
		$c['repository.username'],
		[
			'rev_user_id' => 'rev_user_wiki',
			'rev_mod_user_id' => 'rev_mod_user_wiki',
			'rev_edit_user_id' => 'rev_edit_user_wiki'
		]
	);
};
$c['storage.header.listeners'] = function ( $c ) {
	return [
		'reference.recorder' => $c['reference.recorder'],
		'storage.header.listeners.username' => $c['storage.header.listeners.username'],
		'listeners.notification' => $c['listeners.notification'],
		'listener.recentchanges' => $c['listener.recentchanges'],
		'listener.editcount' => $c['listener.editcount'],
	];
};
$c['storage.header.mapper'] = function ( $c ) {
	return CachingObjectMapper::model( \Flow\Model\Header::class, [ 'rev_id' ] );
};
$c['storage.header.backend'] = function ( $c ) {
	global $wgFlowExternalStore;
	return new HeaderRevisionStorage(
		$c['db.factory'],
		$wgFlowExternalStore
	);
};
$c['storage.header.indexes.primary'] = function ( $c ) {
	return new UniqueFeatureIndex(
		$c['flowcache'],
		$c['storage.header.backend'],
		$c['storage.header.mapper'],
		'flow_header:v2:pk',
		[ 'rev_id' ] // primary key
	);
};
$c['storage.header.indexes.header_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.header.backend'],
		$c['storage.header.mapper'],
		'flow_header:workflow:v3',
		[ 'rev_type_id' ],
		[
			'limit' => FLOW_HISTORY_INDEX_LIMIT,
			'sort' => 'rev_id',
			'order' => 'DESC',
			'shallow' => $c['storage.header.indexes.primary'],
			'create' => function ( array $row ) {
				return $row['rev_parent_id'] === null;
			},
		]
	);
};
$c['storage.header.indexes'] = function ( $c ) {
	return [
		$c['storage.header.indexes.primary'],
		$c['storage.header.indexes.header_lookup']
	];
};
$c['storage.header'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.header.mapper'],
		$c['storage.header.backend'],
		$c['db.factory'],
		$c['storage.header.indexes'],
		$c['storage.header.listeners']
	);
};

$c['storage.post_summary.mapper'] = function ( $c ) {
	return CachingObjectMapper::model(
		\Flow\Model\PostSummary::class,
		[ 'rev_id' ]
	);
};
$c['storage.post_summary.listeners.username'] = function ( $c ) {
	return new Flow\Data\Listener\UserNameListener(
		$c['repository.username'],
		[
			'rev_user_id' => 'rev_user_wiki',
			'rev_mod_user_id' => 'rev_mod_user_wiki',
			'rev_edit_user_id' => 'rev_edit_user_wiki'
		]
	);
};
$c['storage.post_summary.listeners'] = function ( $c ) {
	return [
		'listener.recentchanges' => $c['listener.recentchanges'],
		'storage.post_summary.listeners.username' => $c['storage.post_summary.listeners.username'],
		'listeners.notification' => $c['listeners.notification'],
		'storage.post_summary_board_history.indexes.primary' => $c['storage.post_summary_board_history.indexes.primary'],
		'listener.editcount' => $c['listener.editcount'],
		'reference.recorder' => $c['reference.recorder'],
	];
};
$c['storage.post_summary.backend'] = function ( $c ) {
	global $wgFlowExternalStore;
	return new PostSummaryRevisionStorage(
		$c['db.factory'],
		$wgFlowExternalStore
	);
};
$c['storage.post_summary.indexes.primary'] = function ( $c ) {
	return new UniqueFeatureIndex(
		$c['flowcache'],
		$c['storage.post_summary.backend'],
		$c['storage.post_summary.mapper'],
		'flow_post_summary:v2:pk',
		[ 'rev_id' ]
	);
};
$c['storage.post_summary.indexes.topic_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.post_summary.backend'],
		$c['storage.post_summary.mapper'],
		'flow_post_summary:workflow:v3',
		[ 'rev_type_id' ],
		[
			'limit' => FLOW_HISTORY_INDEX_LIMIT,
			'sort' => 'rev_id',
			'order' => 'DESC',
			'shallow' => $c['storage.post_summary.indexes.primary'],
			'create' => function ( array $row ) {
				return $row['rev_parent_id'] === null;
			},
		]
	);
};
$c['storage.post_summary.indexes'] = function ( $c ) {
	return [
		$c['storage.post_summary.indexes.primary'],
		$c['storage.post_summary.indexes.topic_lookup'],
	];
};
$c['storage.post_summary'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.post_summary.mapper'],
		$c['storage.post_summary.backend'],
		$c['db.factory'],
		$c['storage.post_summary.indexes'],
		$c['storage.post_summary.listeners']
	);
};

$c['storage.topic_list.mapper'] = function ( $c ) {
	// Must be BasicObjectMapper, due to variance in when
	// we have workflow_last_update_timestamp
	return BasicObjectMapper::model(
		\Flow\Model\TopicListEntry::class
	);
};
$c['storage.topic_list.backend'] = function ( $c ) {
	return new TopicListStorage(
		// factory and table
		$c['db.factory'],
		'flow_topic_list',
		[ 'topic_list_id', 'topic_id' ]
	);
};
// Lookup from topic_id to its owning board id
$c['storage.topic_list.indexes.primary'] = function ( $c ) {
	return new UniqueFeatureIndex(
		$c['flowcache'],
		$c['storage.topic_list.backend'],
		$c['storage.topic_list.mapper'],
		'flow_topic_list:topic',
		[ 'topic_id' ]
	);
};

// Lookup from board to contained topics
/// In reverse order by topic_id
$c['storage.topic_list.indexes.reverse_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.topic_list.backend'],
		$c['storage.topic_list.mapper'],
		'flow_topic_list:list',
		[ 'topic_list_id' ],
		[ 'sort' => 'topic_id' ]
	);
};
/// In reverse order by topic last_updated
$c['storage.topic_list.indexes.last_updated'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.topic_list.backend'],
		$c['storage.topic_list.mapper'],
		'flow_topic_list_last_updated:list',
		[ 'topic_list_id' ],
		[
			'sort' => 'workflow_last_update_timestamp',
			'order' => 'desc'
		]
	);
};
$c['storage.topic_list.indexes'] = function ( $c ) {
	return [
		$c['storage.topic_list.indexes.primary'],
		$c['storage.topic_list.indexes.reverse_lookup'],
		$c['storage.topic_list.indexes.last_updated'],
	];
};
$c['storage.topic_list'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.topic_list.mapper'],
		$c['storage.topic_list.backend'],
		$c['db.factory'],
		$c['storage.topic_list.indexes']
	);
};
$c['storage.post.mapper'] = function ( $c ) {
	return CachingObjectMapper::model(
		\Flow\Model\PostRevision::class,
		[ 'rev_id' ]
	);
};
$c['storage.post.backend'] = function ( $c ) {
	global $wgFlowExternalStore;
	return new PostRevisionStorage(
		$c['db.factory'],
		$wgFlowExternalStore,
		$c['repository.tree']
	);
};
$c['storage.post.listeners.moderation_logging'] = function ( $c ) {
	return new Flow\Data\Listener\ModerationLoggingListener(
		$c['logger.moderation']
	);
};
$c['storage.post.listeners.username'] = function ( $c ) {
	return new Flow\Data\Listener\UserNameListener(
		$c['repository.username'],
		[
			'rev_user_id' => 'rev_user_wiki',
			'rev_mod_user_id' => 'rev_mod_user_wiki',
			'rev_edit_user_id' => 'rev_edit_user_wiki',
			'tree_orig_user_id' => 'tree_orig_user_wiki'
		]
	);
};
$c['storage.post.listeners.watch_topic'] = function ( $c ) {
	// Auto-subscribe users to the topic after performing specific actions
	return new Flow\Data\Listener\ImmediateWatchTopicListener(
		$c['watched_items']
	);
};
$c['storage.post.listeners'] = function ( $c ) {
	return [
		'reference.recorder' => $c['reference.recorder'],
		'collection.cache' => $c['collection.cache'],
		'storage.post.listeners.username' => $c['storage.post.listeners.username'],
		'storage.post.listeners.watch_topic' => $c['storage.post.listeners.watch_topic'],
		'listeners.notification' => $c['listeners.notification'],
		'storage.post.listeners.moderation_logging' => $c['storage.post.listeners.moderation_logging'],
		'listener.recentchanges' => $c['listener.recentchanges'],
		'listener.editcount' => $c['listener.editcount'],
		'storage.post_board_history.indexes.primary' => $c['storage.post_board_history.indexes.primary'],
	];
};
$c['storage.post.indexes.primary'] = function ( $c ) {
	return new UniqueFeatureIndex(
		$c['flowcache'],
		$c['storage.post.backend'],
		$c['storage.post.mapper'],
		'flow_revision:v4:pk',
		[ 'rev_id' ]
	);
};
// Each bucket holds a list of revisions in a single post
$c['storage.post.indexes.post_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.post.backend'],
		$c['storage.post.mapper'],
		'flow_revision:descendant',
		[ 'rev_type_id' ],
		[
			'limit' => 100,
			'sort' => 'rev_id',
			'order' => 'DESC',
			'shallow' => $c['storage.post.indexes.primary'],
			'create' => function ( array $row ) {
				// return true to create instead of merge index
				return $row['rev_parent_id'] === null;
			},
		]
	);
};
$c['storage.post.indexes'] = function ( $c ) {
	return [
		$c['storage.post.indexes.primary'],
		$c['storage.post.indexes.post_lookup'],
		$c['storage.post_topic_history.indexes.topic_lookup']
	];
};
$c['storage.post'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.post.mapper'],
		$c['storage.post.backend'],
		$c['db.factory'],
		$c['storage.post.indexes'],
		$c['storage.post.listeners']
	);
};

$c['storage.post_topic_history.backend'] = function ( $c ) {
	return new PostRevisionTopicHistoryStorage(
		$c['storage.post.backend'],
		$c['repository.tree']
	);
};

$c['storage.post_topic_history.indexes.topic_lookup'] = function ( $c ) {
	return new PostRevisionTopicHistoryIndex(
		$c['flowcache'],
		$c['storage.post_topic_history.backend'],
		$c['storage.post.mapper'],
		'flow_revision:topic_history:post:v2',
		[ 'topic_root_id' ],
		[
			'limit' => FLOW_BOARD_TOPIC_HISTORY_POST_INDEX_LIMIT,
			'sort' => 'rev_id',
			'order' => 'DESC',
			// Why does topic history have a shallow compactor, but not board history?
			'shallow' => $c['storage.post.indexes.primary'],
			'create' => function ( array $row ) {
				// only create new indexes for new topics, so it has to be
				// of type 'post' and have no parent post & revision
				if ( $row['rev_type'] !== 'post' ) {
					return false;
				}
				return $row['tree_parent_id'] === null && $row['rev_parent_id'] === null;
			},
		]
	);
};

$c['storage.post_topic_history.indexes'] = function ( $c ) {
	return [
		$c['storage.post_topic_history.indexes.topic_lookup'],
	];
};

$c['storage.post_topic_history'] = function ( $c ) {
	return new ObjectLocator(
		$c['storage.post.mapper'],
		$c['storage.post_topic_history.backend'],
		$c['db.factory'],
		$c['storage.post_topic_history.indexes']
	);
};

$c['storage.manager_list'] = function ( $c ) {
	return [
		\Flow\Model\Workflow::class => 'storage.workflow',
		'Workflow' => 'storage.workflow',

		\Flow\Model\PostRevision::class => 'storage.post',
		'PostRevision' => 'storage.post',
		'post' => 'storage.post',

		\Flow\Model\PostSummary::class => 'storage.post_summary',
		'PostSummary' => 'storage.post_summary',
		'post-summary' => 'storage.post_summary',

		\Flow\Model\TopicListEntry::class => 'storage.topic_list',
		'TopicListEntry' => 'storage.topic_list',

		\Flow\Model\Header::class => 'storage.header',
		'Header' => 'storage.header',
		'header' => 'storage.header',

		'PostRevisionBoardHistoryEntry' => 'storage.post_board_history',

		'PostSummaryBoardHistoryEntry' => 'storage.post_summary_board_history',

		'PostRevisionTopicHistoryEntry' => 'storage.post_topic_history',

		\Flow\Model\WikiReference::class => 'storage.wiki_reference',
		'WikiReference' => 'storage.wiki_reference',

		\Flow\Model\URLReference::class => 'storage.url_reference',
		'URLReference' => 'storage.url_reference',
	];
};
$c['storage'] = function ( $c ) {
	return new \Flow\Data\ManagerGroup(
		$c,
		$c['storage.manager_list']
	);
};
$c['loader.root_post'] = function ( $c ) {
	return new \Flow\Repository\RootPostLoader(
		$c['storage'],
		$c['repository.tree']
	);
};

// Queue of callbacks to run by DeferredUpdates, but only
// on successful commit
$c['deferred_queue'] = function ( $c ) {
	return new SplQueue;
};

$c['submission_handler'] = function ( $c ) {
	return new Flow\SubmissionHandler(
		$c['storage'],
		$c['db.factory'],
		$c['deferred_queue']
	);
};
$c['factory.block'] = function ( $c ) {
	return new Flow\BlockFactory(
		$c['storage'],
		$c['loader.root_post']
	);
};
$c['factory.loader.workflow'] = function ( $c ) {
	return new Flow\WorkflowLoaderFactory(
		$c['storage'],
		$c['factory.block'],
		$c['submission_handler']
	);
};
// Initialized in Flow\Hooks to facilitate only loading the flow container
// when flow is specifically requested to run. Extension initialization
// must always happen before calling flow code.
$c['occupation_controller'] = Flow\Hooks::getOccupationController();

$c['helper.archive_name'] = function ( $c ) {
	return new Flow\Import\ArchiveNameHelper();
};

$c['controller.opt_in'] = function ( $c ) {
	return new Flow\Import\OptInController(
		$c['occupation_controller'],
		$c['controller.notification'],
		$c['helper.archive_name'],
		$c['db.factory'],
		$c['default_logger'],
		$c['occupation_controller']->getTalkpageManager()

	);
};

$c['controller.notification'] = function ( $c ) {
	return new \Flow\Notifications\Controller(
		MediaWikiServices::getInstance()->getContentLanguage(),
		$c['repository.tree']
	);
};

// Initialized in Flow\Hooks to faciliate only loading the flow container
// when flow is specifically requested to run. Extension initialization
// must always happen before calling flow code.
$c['controller.abusefilter'] = Flow\Hooks::getAbuseFilter();

$c['controller.contentlength'] = function ( $c ) {
	global $wgMaxArticleSize;

	// wgMaxArticleSize is in kilobytes,
	// whereas this really is characters (it uses
	// mb_strlen), so it's not the exact same limit.
	$maxCharCount = $wgMaxArticleSize * 1024;

	return new Flow\SpamFilter\ContentLengthFilter( $maxCharCount );
};

$c['controller.spamfilter'] = function ( $c ) {
	return new Flow\SpamFilter\Controller(
		$c['controller.contentlength'],
		new Flow\SpamFilter\SpamRegex,
		new Flow\SpamFilter\RateLimits,
		new Flow\SpamFilter\SpamBlacklist,
		$c['controller.abusefilter'],
		new Flow\SpamFilter\ConfirmEdit
	);
};

$c['query.categoryviewer'] = function ( $c ) {
	return new Flow\Formatter\CategoryViewerQuery(
		$c['storage']
	);
};
$c['formatter.categoryviewer'] = function ( $c ) {
	return new Flow\Formatter\CategoryViewerFormatter(
		$c['permissions']
	);
};
$c['query.singlepost'] = function ( $c ) {
	return new Flow\Formatter\SinglePostQuery(
		$c['storage'],
		$c['repository.tree']
	);
};
$c['query.checkuser'] = function ( $c ) {
	return new Flow\Formatter\CheckUserQuery(
		$c['storage'],
		$c['repository.tree']
	);
};

$c['formatter.irclineurl'] = function ( $c ) {
	return new Flow\Formatter\IRCLineUrlFormatter(
		$c['permissions'],
		$c['formatter.revision.factory']->create()
	);
};

$c['formatter.checkuser'] = function ( $c ) {
	return new Flow\Formatter\CheckUserFormatter(
		$c['permissions'],
		$c['formatter.revision.factory']->create()
	);
};
$c['formatter.revisionview'] = function ( $c ) {
	return new Flow\Formatter\RevisionViewFormatter(
		$c['url_generator'],
		$c['formatter.revision.factory']->create()
	);
};
$c['formatter.revision.diff.view'] = function ( $c ) {
	return new Flow\Formatter\RevisionDiffViewFormatter(
		$c['formatter.revisionview'],
		$c['url_generator']
	);
};
$c['query.topiclist'] = function ( $c ) {
	return new Flow\Formatter\TopicListQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['permissions'],
		$c['watched_items']
	);
};
$c['query.topic.history'] = function ( $c ) {
	return new Flow\Formatter\TopicHistoryQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['flow_actions']
	);
};
$c['query.post.history'] = function ( $c ) {
	return new Flow\Formatter\PostHistoryQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['flow_actions']
	);
};
$c['query.changeslist'] = function ( $c ) {
	$query = new Flow\Formatter\ChangesListQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['flow_actions']
	);
	$query->setExtendWatchlist( $c['user']->getOption( 'extendwatchlist' ) );

	return $query;
};
$c['query.postsummary'] = function ( $c ) {
	return new Flow\Formatter\PostSummaryQuery(
		$c['storage'],
		$c['repository.tree']
	);
};
$c['query.header.view'] = function ( $c ) {
	return new Flow\Formatter\HeaderViewQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['permissions']
	);
};
$c['query.post.view'] = function ( $c ) {
	return new Flow\Formatter\PostViewQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['permissions']
	);
};
$c['query.postsummary.view'] = function ( $c ) {
	return new Flow\Formatter\PostSummaryViewQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['permissions']
	);
};
$c['formatter.changeslist'] = function ( $c ) {
	return new Flow\Formatter\ChangesListFormatter(
		$c['permissions'],
		$c['formatter.revision.factory']->create()
	);
};

$c['query.contributions'] = function ( $c ) {
	return new Flow\Formatter\ContributionsQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['db.factory'],
		$c['flow_actions']
	);
};
$c['formatter.contributions'] = function ( $c ) {
	return new Flow\Formatter\ContributionsFormatter(
		$c['permissions'],
		$c['formatter.revision.factory']->create()
	);
};
$c['formatter.contributions.feeditem'] = function ( $c ) {
	return new Flow\Formatter\FeedItemFormatter(
		$c['permissions'],
		$c['formatter.revision.factory']->create()
	);
};
$c['query.board.history'] = function ( $c ) {
	return new Flow\Formatter\BoardHistoryQuery(
		$c['storage'],
		$c['repository.tree'],
		$c['flow_actions']
	);
};

$c['formatter.revision.factory'] = function ( $c ) {
	global $wgFlowMaxThreadingDepth;

	return new Flow\Formatter\RevisionFormatterFactory(
		$c['permissions'],
		$c['templating'],
		$c['repository.username'],
		$wgFlowMaxThreadingDepth
	);
};
$c['formatter.topiclist'] = function ( $c ) {
	return new Flow\Formatter\TopicListFormatter(
		$c['url_generator'],
		$c['formatter.revision.factory']->create()
	);
};
$c['formatter.topiclist.toc'] = function ( $c ) {
	return new Flow\Formatter\TocTopicListFormatter(
		$c['templating']
	);
};
$c['formatter.topic'] = function ( $c ) {
	return new Flow\Formatter\TopicFormatter(
		$c['url_generator'],
		$c['formatter.revision.factory']->create()
	);
};
$c['search.connection'] = function ( $c ) {
	if ( defined( 'MW_PHPUNIT_TEST' ) && !ExtensionRegistry::getInstance()->isLoaded( 'Elastica' ) ) {
		/*
		 * ContainerTest::testInstantiateAll instantiates everything
		 * in container and doublechecks it's not null.
		 * Flow runs on Jenkins don't currently load Extension:Elastica,
		 * which is required to be able to construct this object.
		 * Because search is not currently in use, let's not add the
		 * dependency in Jenkins and just return a bogus value to not
		 * make the test fail ;)
		 */
		return 'not-supported';
	}

	global $wgFlowSearchServers, $wgFlowSearchConnectionAttempts;
	return new Flow\Search\Connection( $wgFlowSearchServers, $wgFlowSearchConnectionAttempts );
};
$c['search.index.iterators.header'] = function ( $c ) {
	return new \Flow\Search\Iterators\HeaderIterator( $c['db.factory'] );
};
$c['search.index.iterators.topic'] = function ( $c ) {
	return new \Flow\Search\Iterators\TopicIterator( $c['db.factory'], $c['loader.root_post'] );
};
$c['search.index.updaters'] = function ( $c ) {
	// permissions for anon user
	$anonPermissions = new Flow\RevisionActionPermissions( $c['flow_actions'], new User );
	return [
		'topic' => new \Flow\Search\Updaters\TopicUpdater( $c['search.index.iterators.topic'], $anonPermissions, $c['loader.root_post'] ),
		'header' => new \Flow\Search\Updaters\HeaderUpdater( $c['search.index.iterators.header'], $anonPermissions )
	];
};

$c['logger.moderation'] = function ( $c ) {
	return new Flow\Log\ModerationLogger(
		$c['flow_actions']
	);
};

$c['storage.wiki_reference.mapper'] = function ( $c ) {
	return BasicObjectMapper::model(
		\Flow\Model\WikiReference::class
	);
};
$c['storage.wiki_reference.backend'] = function ( $c ) {
	return new BasicDbStorage(
		$c['db.factory'],
		'flow_wiki_ref',
		[
			'ref_src_wiki',
			'ref_src_namespace',
			'ref_src_title',
			'ref_src_object_id',
			'ref_type',
			'ref_target_namespace',
			'ref_target_title'
		]
	);
};
$c['storage.wiki_reference.indexes.source_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.wiki_reference.backend'],
		$c['storage.wiki_reference.mapper'],
		'flow_ref:wiki:by-source:v3',
		[
			'ref_src_wiki',
			'ref_src_namespace',
			'ref_src_title',
		],
		[
			'order' => 'ASC',
			'sort' => 'ref_src_object_id',
		]
	);
};
$c['storage.wiki_reference.indexes.revision_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.wiki_reference.backend'],
		$c['storage.wiki_reference.mapper'],
		'flow_ref:wiki:by-revision:v3',
		[
			'ref_src_wiki',
			'ref_src_object_type',
			'ref_src_object_id',
		],
		[
			'order' => 'ASC',
			'sort' => [ 'ref_target_namespace', 'ref_target_title' ],
		]
	);
};
$c['storage.wiki_reference.indexes'] = function ( $c ) {
	return [
		$c['storage.wiki_reference.indexes.source_lookup'],
		$c['storage.wiki_reference.indexes.revision_lookup'],
	];
};
$c['storage.wiki_reference'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.wiki_reference.mapper'],
		$c['storage.wiki_reference.backend'],
		$c['db.factory'],
		$c['storage.wiki_reference.indexes'],
		[]
	);
};

$c['storage.url_reference.mapper'] = function ( $c ) {
	return BasicObjectMapper::model(
		\Flow\Model\URLReference::class
	);
};
$c['storage.url_reference.backend'] = function ( $c ) {
	return new BasicDbStorage(
		// factory and table
		$c['db.factory'],
		'flow_ext_ref',
		[
			'ref_src_wiki',
			'ref_src_namespace',
			'ref_src_title',
			'ref_src_object_id',
			'ref_type',
			'ref_target',
		]
	);
};

$c['storage.url_reference.indexes.source_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.url_reference.backend'],
		$c['storage.url_reference.mapper'],
		'flow_ref:url:by-source:v3',
		[
			'ref_src_wiki',
			'ref_src_namespace',
			'ref_src_title',
		],
		[
			'order' => 'ASC',
			'sort' => 'ref_src_object_id',
		]
	);
};
$c['storage.url_reference.indexes.revision_lookup'] = function ( $c ) {
	return new TopKIndex(
		$c['flowcache'],
		$c['storage.url_reference.backend'],
		$c['storage.url_reference.mapper'],
		'flow_ref:url:by-revision:v3',
		[
			'ref_src_wiki',
			'ref_src_object_type',
			'ref_src_object_id',
		],
		[
			'order' => 'ASC',
			'sort' => [ 'ref_target' ],
		]
	);
};
$c['storage.url_reference.indexes'] = function ( $c ) {
	return [
		$c['storage.url_reference.indexes.source_lookup'],
		$c['storage.url_reference.indexes.revision_lookup'],
	];
};
$c['storage.url_reference'] = function ( $c ) {
	return new ObjectManager(
		$c['storage.url_reference.mapper'],
		$c['storage.url_reference.backend'],
		$c['db.factory'],
		$c['storage.url_reference.indexes'],
		[]
	);
};

$c['reference.updater.links-tables'] = function ( $c ) {
	return new Flow\LinksTableUpdater( $c['storage'] );
};

$c['reference.clarifier'] = function ( $c ) {
	return new Flow\ReferenceClarifier( $c['storage'], $c['url_generator'] );
};

$c['reference.extractor'] = function ( $c ) {
	$default = [
		new Flow\Parsoid\Extractor\ImageExtractor,
		new Flow\Parsoid\Extractor\PlaceholderExtractor,
		new Flow\Parsoid\Extractor\WikiLinkExtractor,
		new Flow\Parsoid\Extractor\ExtLinkExtractor,
		new Flow\Parsoid\Extractor\TransclusionExtractor,
	];
	$extractors = [
		'header' => $default,
		'post-summary' => $default,
		'post' => $default,
	];
	// In addition to the defaults header and summaries collect
	// the related categories.
	$extractors['header'][] = $extractors['post-summary'][] = new Flow\Parsoid\Extractor\CategoryExtractor;

	return new Flow\Parsoid\ReferenceExtractor( $extractors );
};

$c['reference.recorder'] = function ( $c ) {
	return new Flow\Data\Listener\ReferenceRecorder(
		$c['reference.extractor'],
		$c['reference.updater.links-tables'],
		$c['storage'],
		$c['repository.tree'],
		$c['deferred_queue']
	);
};

$c['user_merger'] = function ( $c ) {
	return new Flow\Data\Utils\UserMerger(
		$c['db.factory'],
		$c['storage']
	);
};

$c['importer'] = function ( $c ) {
	$importer = new Flow\Import\Importer(
		$c['storage'],
		$c['factory.loader.workflow'],
		$c['db.factory'],
		$c['deferred_queue'],
		$c['occupation_controller']
	);

	$importer->addPostprocessor( new Flow\Import\Postprocessor\SpecialLogTopic(
		$c['occupation_controller']->getTalkpageManager()
	) );

	return $importer;
};

$c['listener.editcount'] = function ( $c ) {
	return new \Flow\Data\Listener\EditCountListener( $c['flow_actions'] );
};

$c['formatter.undoedit'] = function ( $c ) {
	return new Flow\Formatter\RevisionUndoViewFormatter(
		$c['formatter.revisionview']
	);
};

$c['board_mover'] = function ( $c ) {
	return new Flow\BoardMover(
		$c['db.factory'],
		$c['storage'],
		$c['occupation_controller']->getTalkpageManager()
	);
};

$c['default_logger'] = function () {
	return MediaWiki\Logger\LoggerFactory::getInstance( 'Flow' );
};

return $c;
