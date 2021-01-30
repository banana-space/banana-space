<?php

namespace Flow\Data\Listener;

use Flow\Model\Workflow;
use Flow\WatchedTopicItems;
use MediaWiki\MediaWikiServices;
use User;

/**
 * Class to immediately subscribe users to the article title when one of the
 * actions specified in the constructor is inserted.
 */
class ImmediateWatchTopicListener extends AbstractTopicInsertListener {
	/**
	 * @var WatchedTopicItems
	 */
	protected $watchedTopicItems;

	/**
	 * @param WatchedTopicItems $watchedTopicItems Helper class for watching titles
	 */
	public function __construct( WatchedTopicItems $watchedTopicItems ) {
		$this->watchedTopicItems = $watchedTopicItems;
	}

	/**
	 * @param string $changeType
	 * @param Workflow $workflow
	 */
	public function onAfterInsertExpectedChange( $changeType, Workflow $workflow ) {
		$users = static::getUsersToSubscribe( $changeType, 'immediate', [ $this->watchedTopicItems ] );

		foreach ( $users as $user ) {
			if ( !$user instanceof User ) {
				continue;
			}
			$title = $workflow->getArticleTitle();

			// see https://phabricator.wikimedia.org/T223165
			if ( MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $user, 'editmywatchlist' ) ) {
				MediaWikiServices::getInstance()->getWatchedItemStore()
					->addWatchBatchForUser( $user, [
							$title->getSubjectPage(),
							$title->getTalkPage()
						] );
				$user->invalidateCache();
			}
			$this->watchedTopicItems->addOverrideWatched( $title );
		}
	}

	/**
	 * @param WatchedTopicItems $watchedTopicItems
	 * @return User[]
	 */
	public static function getCurrentUser( WatchedTopicItems $watchedTopicItems ) {
		return [ $watchedTopicItems->getUser() ];
	}
}
