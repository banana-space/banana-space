<?php

$notificationTemplate = [
	'category' => 'flow-discussion',
	'group' => 'other',
];

$newTopicNotification = [
	'presentation-model' => \Flow\Notifications\NewTopicPresentationModel::class,
	'bundle' => [
		'web' => true,
		'email' => true,
		'expandable' => true,
	],
] + $notificationTemplate;

$descriptionEditedNotification = [
	'presentation-model' => \Flow\Notifications\HeaderEditedPresentationModel::class,
	'bundle' => [
		'web' => true,
		'email' => true,
	],
] + $notificationTemplate;

$postEditedNotification = [
	'presentation-model' => \Flow\Notifications\PostEditedPresentationModel::class,
	'bundle' => [
		'web' => true,
		'email' => true,
	],
] + $notificationTemplate;

$postReplyNotification = [
	'presentation-model' => \Flow\Notifications\PostReplyPresentationModel::class,
	'bundle' => [
		'web' => true,
		'email' => true,
		'expandable' => true,
	],
] + $notificationTemplate;

$topicRenamedNotification = [
	'presentation-model' => \Flow\Notifications\TopicRenamedPresentationModel::class,
	'primary-link' => [
		'message' => 'flow-notification-link-text-view-post',
		'destination' => 'flow-post'
	],
] + $notificationTemplate;

$summaryEditedNotification = [
	'presentation-model' => \Flow\Notifications\SummaryEditedPresentationModel::class,
	'bundle' => [
		'web' => true,
		'email' => true,
	],
] + $notificationTemplate;

$topicResolvedNotification = [
	'presentation-model' => \Flow\Notifications\TopicResolvedPresentationModel::class,
] + $notificationTemplate;

$notifications = [
	'flow-new-topic' => [
		'section' => 'message',
		'user-locators' => [
			'EchoUserLocator::locateUsersWatchingTitle',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $newTopicNotification,
	'flowusertalk-new-topic' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $newTopicNotification,
	'flow-post-reply' => [
		'section' => 'message',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locateUsersWatchingTopic',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $postReplyNotification,
	'flowusertalk-post-reply' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $postReplyNotification,
	'flow-post-edited' => [
		'section' => 'alert',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locatePostAuthors',
		],
		'user-filters' => [
			'EchoUserLocator::locateTalkPageOwner',
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $postEditedNotification,
	'flowusertalk-post-edited' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $postEditedNotification,
	'flow-topic-renamed' => [
		'section' => 'message',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locateUsersWatchingTopic',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $topicRenamedNotification,
	'flowusertalk-topic-renamed' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $topicRenamedNotification,
	'flow-summary-edited' => [
		'section' => 'message',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locateUsersWatchingTopic',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $summaryEditedNotification,
	'flowusertalk-summary-edited' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $summaryEditedNotification,
	'flow-description-edited' => [
		'section' => 'message',
		'user-locators' => [
			'EchoUserLocator::locateUsersWatchingTitle',
		],
		'user-filters' => [
			'EchoUserLocator::locateTalkPageOwner',
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $descriptionEditedNotification,
	'flowusertalk-description-edited' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
		'user-filters' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $descriptionEditedNotification,
	'flow-mention' => [
		'category' => 'mention',
		'presentation-model' => \Flow\Notifications\MentionPresentationModel::class,
		'section' => 'alert',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locateMentionedUsers',
		],
	] + $notificationTemplate,
	'flow-enabled-on-talkpage' => [
		'category' => 'system',
		'presentation-model' => \Flow\Notifications\FlowEnabledOnTalkpagePresentationModel::class,
		'section' => 'message',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner'
		],
		'canNotifyAgent' => true,
	] + $notificationTemplate,
	'flow-topic-resolved' => [
		'section' => 'message',
		'user-locators' => [
			'Flow\\Notifications\\UserLocator::locateUsersWatchingTopic',
		],
		'user-filters' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $topicResolvedNotification,
	'flowusertalk-topic-resolved' => [
		'category' => 'edit-user-talk',
		'section' => 'alert',
		'user-locators' => [
			'EchoUserLocator::locateTalkPageOwner',
		],
	] + $topicResolvedNotification,
	'flow-mention-failure-too-many' => [
		'user-locators' => [
			'EchoUserLocator::locateEventAgent'
		],
		'canNotifyAgent' => true,
		'section' => 'alert',
		'presentation-model' => \Flow\Notifications\MentionStatusPresentationModel::class,
	] + $notificationTemplate,
];

return $notifications;
