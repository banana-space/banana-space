<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

/**
 * Hooks for Thanks extension
 *
 * @file
 * @ingroup Extensions
 */
class ThanksHooks {

	/**
	 * Handler for HistoryTools and DiffTools hooks.
	 *
	 * Insert a 'thank' link into revision interface, if the user is allowed to thank.
	 *
	 * @param RevisionRecord $revisionRecord RevisionRecord object to add the thank link for
	 * @param array &$links Links to add to the revision interface
	 * @param ?RevisionRecord $oldRevisionRecord RevisionRecord object of the "old" revision
	 *   when viewing a diff
	 * @param UserIdentity $userIdentity The user performing the thanks.
	 */
	public static function insertThankLink(
		RevisionRecord $revisionRecord,
		array &$links,
		?RevisionRecord $oldRevisionRecord,
		UserIdentity $userIdentity
	) {
		$recipient = $revisionRecord->getUser();
		if ( $recipient === null ) {
			// Cannot see the user
			return;
		}

		$recipient = User::newFromIdentity( $recipient );
		$previous = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getPreviousRevision( $revisionRecord );

		$user = User::newFromIdentity( $userIdentity );

		// Don't let users thank themselves.
		// Exclude anonymous users.
		// Exclude users who are blocked.
		// Check whether bots are allowed to receive thanks.
		// Check if there's other revisions between $prev and $oldRev
		// (It supports discontinuous history created by Import or CX but
		// prevents thanking diff across multiple revisions)
		if ( !$user->isAnon()
			&& !$user->equals( $recipient )
			&& !self::isUserBlockedFromTitle( $user, $revisionRecord->getPageAsLinkTarget() )
			&& !$user->isBlockedGlobally()
			&& self::canReceiveThanks( $recipient )
			&& !$revisionRecord->isDeleted( RevisionRecord::DELETED_TEXT )
			&& ( !$oldRevisionRecord || !$previous ||
				$previous->getId() === $oldRevisionRecord->getId() )
		) {
			$links[] = self::generateThankElement(
				$revisionRecord->getId(),
				$user,
				$recipient
			);
		}
	}

	/**
	 * Check whether the user is blocked from the title associated with the revision.
	 *
	 * This queries the replicas for a block; if 'no block' is incorrectly reported, it
	 * will be caught by ApiThank::dieOnBlockedUser when the user attempts to thank.
	 *
	 * @param User $user
	 * @param LinkTarget $title
	 * @return bool
	 */
	private static function isUserBlockedFromTitle( User $user, LinkTarget $title ) {
		return MediaWikiServices::getInstance()->getPermissionManager()
			->isBlockedFrom( $user, $title, true );
	}

	/**
	 * Check whether a user is allowed to receive thanks or not
	 *
	 * @param User $user Recipient
	 * @return bool true if allowed, false if not
	 */
	protected static function canReceiveThanks( User $user ) {
		global $wgThanksSendToBots;

		if ( $user->isAnon() || $user->isSystemUser() ) {
			return false;
		}

		if ( !$wgThanksSendToBots && $user->isBot() ) {
			return false;
		}

		return true;
	}

	/**
	 * Helper for self::insertThankLink
	 * Creates either a thank link or thanked span based on users session
	 * @param int $id Revision or log ID to generate the thank element for.
	 * @param User $sender User who sends thanks notification.
	 * @param User $recipient User who receives thanks notification.
	 * @param string $type Either 'revision' or 'log'.
	 * @return string
	 */
	protected static function generateThankElement(
		$id, User $sender, User $recipient, $type = 'revision'
	) {
		// Check if the user has already thanked for this revision or log entry.
		// Session keys are backwards-compatible, and are also used in the ApiCoreThank class.
		$sessionKey = ( $type === 'revision' ) ? $id : $type . $id;
		if ( $sender->getRequest()->getSessionData( "thanks-thanked-$sessionKey" ) ) {
			return Html::element(
				'span',
				[ 'class' => 'mw-thanks-thanked' ],
				wfMessage( 'thanks-thanked', $sender->getName(), $recipient->getName() )->text()
			);
		}

		$genderCache = MediaWikiServices::getInstance()->getGenderCache();
		// Add 'thank' link
		$tooltip = wfMessage( 'thanks-thank-tooltip' )
			->params( $sender->getName(), $recipient->getName() )
			->text();

		$subpage = ( $type === 'revision' ) ? '' : 'Log/';
		return Html::element(
			'a',
			[
				'class' => 'mw-thanks-thank-link',
				'href' => SpecialPage::getTitleFor( 'Thanks', $subpage . $id )->getFullURL(),
				'title' => $tooltip,
				'data-' . $type . '-id' => $id,
				'data-recipient-gender' => $genderCache->getGenderOf( $recipient->getName(), __METHOD__ ),
			],
			wfMessage( 'thanks-thank', $sender->getName(), $recipient->getName() )->text()
		);
	}

	/**
	 * @param OutputPage $outputPage The OutputPage to add the module to.
	 */
	protected static function addThanksModule( OutputPage $outputPage ) {
		$confirmationRequired = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksConfirmationRequired' );
		$outputPage->addModules( [ 'ext.thanks.corethank' ] );
		$outputPage->addJsConfigVars( 'thanks-confirmation-required', $confirmationRequired );
	}

	/**
	 * Handler for PageHistoryBeforeList hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryBeforeList
	 *
	 * @param WikiPage|Article|ImagePage|CategoryPage $page Not used
	 * @param RequestContext $context RequestContext object
	 */
	public static function onPageHistoryBeforeList( $page, $context ) {
		if ( $context->getUser()->isLoggedIn() ) {
			static::addThanksModule( $context->getOutput() );
		}
	}

	/**
	 * Handler for DifferenceEngineViewHeader hook.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/DifferenceEngineViewHeader
	 * @param DifferenceEngine $diff DifferenceEngine object that's calling.
	 */
	public static function onDifferenceEngineViewHeader( $diff ) {
		if ( $diff->getUser()->isLoggedIn() ) {
			static::addThanksModule( $diff->getOutput() );
		}
	}

	/**
	 * Add Thanks events to Echo
	 *
	 * @param array &$notifications array of Echo notifications
	 * @param array &$notificationCategories array of Echo notification categories
	 * @param array &$icons array of icon details
	 */
	public static function onBeforeCreateEchoEvent(
		&$notifications, &$notificationCategories, &$icons
	) {
		$notificationCategories['edit-thank'] = [
			'priority' => 3,
			'tooltip' => 'echo-pref-tooltip-edit-thank',
		];

		$notifications['edit-thank'] = [
			'category' => 'edit-thank',
			'group' => 'positive',
			'section' => 'message',
			'presentation-model' => 'EchoCoreThanksPresentationModel',
			'bundle' => [
				'web' => true,
				'expandable' => true,
			],
		];

		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$notifications['flow-thank'] = [
				'category' => 'edit-thank',
				'group' => 'positive',
				'section' => 'message',
				'presentation-model' => 'EchoFlowThanksPresentationModel',
				'bundle' => [
					'web' => true,
					'expandable' => true,
				],
			];
		}

		$icons['thanks'] = [
			'path' => [
				'ltr' => 'Thanks/userTalk-constructive-ltr.svg',
				'rtl' => 'Thanks/userTalk-constructive-rtl.svg'
			]
		];
	}

	/**
	 * Add user to be notified on echo event
	 * @param EchoEvent $event The event.
	 * @param User[] &$users The user list to add to.
	 */
	public static function onEchoGetDefaultNotifiedUsers( $event, &$users ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
			case 'flow-thank':
				$extra = $event->getExtra();
				if ( !$extra || !isset( $extra['thanked-user-id'] ) ) {
					break;
				}
				$recipientId = $extra['thanked-user-id'];
				$recipient = User::newFromId( $recipientId );
				$users[$recipientId] = $recipient;
				break;
		}
	}

	/**
	 * Handler for LocalUserCreated hook
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LocalUserCreated
	 * @param User $user User object that was created.
	 * @param bool $autocreated True when account was auto-created
	 */
	public static function onAccountCreated( $user, $autocreated ) {
		// New users get echo preferences set that are not the default settings for existing users.
		// Specifically, new users are opted into email notifications for thanks.
		if ( !$autocreated ) {
			$user->setOption( 'echo-subscriptions-email-edit-thank', true );
			$user->saveSettings();
		}
	}

	/**
	 * Add thanks button to SpecialMobileDiff page
	 * @param OutputPage &$output OutputPage object
	 * @param MobileContext $ctx MobileContext object
	 * @param array $revisions Array with two elements, either nulls or RevisionRecord objects for
	 *     the two revisions that are being compared in the diff
	 */
	public static function onBeforeSpecialMobileDiffDisplay( &$output, $ctx, $revisions ) {
		$rev = $revisions[1];

		// If the MobileFrontend extension is installed and the user is
		// logged in or recipient is not a bot if bots cannot receive thanks, show a 'Thank' link.
		if ( $rev
			&& ExtensionRegistry::getInstance()->isLoaded( 'MobileFrontend' )
			&& $rev->getUser()
			&& self::canReceiveThanks( User::newFromIdentity( $rev->getUser() ) )
			&& $output->getUser()->isLoggedIn()
		) {
			$output->addModules( [ 'ext.thanks.mobilediff' ] );

			if ( $output->getRequest()->getSessionData( 'thanks-thanked-' . $rev->getId() ) ) {
				// User already sent thanks for this revision
				$output->addJsConfigVars( 'wgThanksAlreadySent', true );
			}

		}
	}

	/**
	 * Handler for GetLogTypesOnUser.
	 * So users can just type in a username for target and it'll work.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/GetLogTypesOnUser
	 * @param string[] &$types The list of log types, to add to.
	 */
	public static function onGetLogTypesOnUser( array &$types ) {
		$types[] = 'thanks';
	}

	/**
	 * Handler for BeforePageDisplay.  Inserts javascript to enhance thank
	 * links from static urls to in-page dialogs along with reloading
	 * the previously thanked state.
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 * @param OutputPage $out OutputPage object
	 * @param Skin $skin The skin in use.
	 */
	public static function onBeforePageDisplay( OutputPage $out, $skin ) {
		$title = $out->getTitle();
		// Add to Flow boards.
		if ( $title instanceof Title && $title->hasContentModel( 'flow-board' ) ) {
			$out->addModules( 'ext.thanks.flowthank' );
		}
		// Add to Special:Log.
		if ( $title->isSpecial( 'Log' ) ) {
			static::addThanksModule( $out );
		}
	}

	/**
	 * Conditionally load API module 'flowthank' depending on whether or not
	 * Flow is installed.
	 *
	 * @param ApiModuleManager $moduleManager Module manager instance
	 */
	public static function onApiMainModuleManager( ApiModuleManager $moduleManager ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Flow' ) ) {
			$moduleManager->addModule(
				'flowthank',
				'action',
				'ApiFlowThank'
			);
		}
	}

	/**
	 * Handler for EchoGetBundleRule hook, which defines the bundle rules for each notification.
	 *
	 * @param EchoEvent $event The event being notified.
	 * @param string &$bundleString Determines how the notification should be bundled.
	 */
	public static function onEchoGetBundleRules( $event, &$bundleString ) {
		switch ( $event->getType() ) {
			case 'edit-thank':
				$bundleString = 'edit-thank';
				// Try to get either the revid or logid parameter.
				$revOrLogId = $event->getExtraParam( 'logid' );
				if ( $revOrLogId ) {
					// avoid collision with revision ids
					$revOrLogId = 'log' . $revOrLogId;
				} else {
					$revOrLogId = $event->getExtraParam( 'revid' );
				}
				if ( $revOrLogId ) {
					$bundleString .= $revOrLogId;
				}
				break;
			case 'flow-thank':
				$bundleString = 'flow-thank';
				$postId = $event->getExtraParam( 'post-id' );
				if ( $postId ) {
					$bundleString .= $postId;
				}
				break;
		}
	}

	/**
	 * Insert a 'thank' link into the log interface, if the user is allowed to thank.
	 *
	 * @link https://www.mediawiki.org/wiki/Manual:Hooks/LogEventsListLineEnding
	 * @param LogEventsList $page The log events list.
	 * @param string &$ret The lineending HTML, to modify.
	 * @param DatabaseLogEntry $entry The log entry.
	 * @param string[] &$classes CSS classes to add to the line.
	 * @param string[] &$attribs HTML attributes to add to the line.
	 * @throws ConfigException
	 */
	public static function onLogEventsListLineEnding(
		LogEventsList $page, &$ret, DatabaseLogEntry $entry, &$classes, &$attribs
	) {
		$user = $page->getUser();

		// Don't thank if anonymous or blocked or if user is deleted from the log entry
		if (
			$user->isAnon()
			|| $entry->isDeleted( LogPage::DELETED_USER )
			|| self::isUserBlockedFromTitle( $user, $entry->getTarget() )
			|| $user->isBlockedGlobally()
		) {
			return;
		}

		// Make sure this log type is whitelisted.
		$logTypeWhitelist = MediaWikiServices::getInstance()
			->getMainConfig()
			->get( 'ThanksLogTypeWhitelist' );
		if ( !in_array( $entry->getType(), $logTypeWhitelist ) ) {
			return;
		}

		// Don't thank if no recipient,
		// or if recipient is the current user or unable to receive thanks.
		// Don't check for deleted revision (this avoids extraneous queries from Special:Log).
		$recipient = $entry->getPerformer();
		if ( !$recipient
			|| $recipient->getId() === $user->getId()
			|| !self::canReceiveThanks( $recipient )
		) {
			return;
		}

		// Create thank link either for the revision (if there is an associated revision ID)
		// or the log entry.
		$type = $entry->getAssociatedRevId() ? 'revision' : 'log';
		$id = $entry->getAssociatedRevId() ?: $entry->getId();
		$thankLink = self::generateThankElement( $id, $user, $recipient, $type );

		// Add parentheses to match what's done with Thanks in revision lists and diff displays.
		$ret .= ' ' . wfMessage( 'parentheses' )->rawParams( $thankLink )->escaped();
	}
}
