<?php

namespace Flow\Data\Listener;

use Closure;
use Flow\Data\Utils\RecentChangeFactory;
use Flow\FlowActions;
use Flow\Formatter\IRCLineUrlFormatter;
use Flow\Model\AbstractRevision;
use Flow\Model\Workflow;
use Flow\Repository\UserNameBatch;
use MediaWiki\MediaWikiServices;
use RecentChange;

/**
 * Inserts mw recentchange rows for flow AbstractRevision instances.
 */
class RecentChangesListener extends AbstractListener {

	// Value used in rc_source field of recentchanges to identify flow specific changes
	public const SRC_FLOW = "flow";

	/**
	 * @var FlowActions
	 */
	protected $actions;

	/**
	 * @var UserNameBatch
	 */
	protected $usernames;

	/**
	 * @var RecentChangeFactory
	 */
	protected $rcFactory;

	/**
	 * @var IRCLineUrlFormatter
	 */
	protected $ircFormatter;

	/**
	 * @param FlowActions $actions
	 * @param UserNameBatch $usernames
	 * @param RecentChangeFactory $rcFactory
	 * @param IRCLineUrlFormatter $ircFormatter
	 */
	public function __construct(
		FlowActions $actions,
		UserNameBatch $usernames,
		RecentChangeFactory $rcFactory,
		IRCLineUrlFormatter $ircFormatter
	) {
		$this->actions = $actions;
		$this->usernames = $usernames;
		$this->rcFactory = $rcFactory;
		$this->ircFormatter = $ircFormatter;
	}

	/**
	 * @param AbstractRevision $revision
	 * @param array $row Revision row
	 * @param array $metadata
	 */
	public function onAfterInsert( $revision, array $row, array $metadata ) {
		global $wgRCFeeds;

		// No action on imported revisions
		if ( isset( $metadata['imported'] ) && $metadata['imported'] ) {
			return;
		}

		$action = $revision->getChangeType();
		$revisionId = $revision->getRevisionId()->getAlphadecimal();
		$timestamp = $revision->getRevisionId()->getTimestamp();
		/** @var Workflow $workflow */
		$workflow = $metadata['workflow'];
		$user = $revision->getUser();

		if ( !$this->isAllowed( $revision, $action ) ) {
			return;
		}

		$title = $this->getRcTitle( $workflow, $revision->getChangeType() );
		$autopatrolAllowed = MediaWikiServices::getInstance()->getPermissionManager()
			->userHasRight( $user, 'autopatrol' );
		$attribs = [
			'rc_namespace' => $title->getNamespace(),
			'rc_title' => $title->getDBkey(),
			'rc_user' => $row['rev_user_id'],
			'rc_user_text' => $this->usernames->get( wfWikiID(), $row['rev_user_id'], $row['rev_user_ip'] ),
			'rc_type' => RC_FLOW,
			'rc_source' => self::SRC_FLOW,
			'rc_minor' => 0,
			'rc_bot' => 0, // TODO: is revision by bot
			'rc_new' => 0,
			'rc_patrolled' => $autopatrolAllowed ? RecentChange::PRC_AUTOPATROLLED : RecentChange::PRC_UNPATROLLED,
			'rc_old_len' => $revision->getPreviousContentLength(),
			'rc_new_len' => $revision->getContentLength(),
			'rc_this_oldid' => 0,
			'rc_last_oldid' => 0,
			'rc_logid' => 0,
			'rc_log_type' => null,
			'rc_params' => serialize( [
				'flow-workflow-change' => [
					'action' => $action,
					'revision_type' => get_class( $revision ),
					'revision' => $revisionId,
					'workflow' => $workflow->getId()->getAlphadecimal(),
				],
			] ),
			'rc_cur_id' => 0,
			'rc_comment' => '',
			'rc_comment_text' => '',
			'rc_comment_data' => null,
			'rc_timestamp' => $timestamp,
			'rc_deleted' => 0,
		];

		$rc = $this->rcFactory->newFromRow( (object)$attribs );
		// Insert into db only, don't send to any RC feeds (yet)
		$rc->save( RecentChange::SEND_NONE );
		$feeds = $wgRCFeeds;
		// Override the IRC formatter with our own formatter
		foreach ( array_keys( $feeds ) as $name ) {
			$feeds[$name]['original_formatter'] = $feeds[$name]['formatter'];
			$feeds[$name]['formatter'] = $this->ircFormatter;
		}
		// pre-load the irc formatter which will be triggered via hook
		$this->ircFormatter->associate( $rc, [
			'revision' => $revision
		] + $metadata );
		// run the feeds/irc/etc external notifications
		$rc->notifyRCFeeds( $feeds );
	}

	/**
	 * @param Workflow $workflow
	 * @param string $action
	 * @return \Title
	 */
	public function getRcTitle( Workflow $workflow, $action ) {
		if ( $this->actions->getValue( $action, 'rc_title' ) === 'owner' ) {
			return $workflow->getOwnerTitle();
		} else {
			return $workflow->getArticleTitle();
		}
	}

	/**
	 * @param AbstractRevision $revision
	 * @param string $action
	 * @return bool
	 */
	public function isAllowed( AbstractRevision $revision, $action ) {
		$allowed = $this->actions->getValue( $action, 'rc_insert' );
		if ( $allowed instanceof Closure ) {
			$allowed = $allowed( $revision, $this );
		}

		return (bool)$allowed;
	}
}
