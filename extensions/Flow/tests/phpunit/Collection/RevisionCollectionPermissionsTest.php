<?php

namespace Flow\Tests\Collection;

use Flow\Container;
use Flow\FlowActions;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\RevisionActionPermissions;
use Flow\Tests\PostRevisionTestCase;
use MediaWiki\Block\DatabaseBlock;
use User;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 *
 * @group Database
 * @group Flow
 */
class RevisionCollectionPermissionsTest extends PostRevisionTestCase {
	/**
	 * @var FlowActions
	 */
	protected $actions;

	/**
	 * Map of action name to moderation status, as helper for
	 * $this->generateRevision()
	 *
	 * @var array
	 */
	protected $moderation = [
		'restore-post' => AbstractRevision::MODERATED_NONE,
		'hide-post' => AbstractRevision::MODERATED_HIDDEN,
		'delete-post' => AbstractRevision::MODERATED_DELETED,
		'suppress-post' => AbstractRevision::MODERATED_SUPPRESSED,
	];

	/**
	 * @var User
	 */
	protected $blockedUser;

	/**
	 * @var User
	 */
	protected $anonUser;

	/**
	 * @var User
	 */
	protected $unconfirmedUser;

	/**
	 * @var User
	 */
	protected $confirmedUser;

	/**
	 * @var User
	 */
	protected $sysopUser;

	/**
	 * @var User
	 */
	protected $suppressUser;

	/**
	 * @var DatabaseBlock
	 */
	protected $block;

	protected function setUp() : void {
		parent::setUp();

		$this->clearExtraLifecycleHandlers();

		// We don't want local config getting in the way of testing whether or
		// not our permissions implementation works well.
		$this->resetPermissions();

		// When external store is used, data is written to "blobs" table, which
		// by default doesn't exist - let's just not use externalstorage in test
		$this->setMwGlobals( 'wgFlowExternalStore', false );

		// load actions object
		$this->actions = Container::get( 'flow_actions' );

		// block a user
		$blockedUser = $this->blockedUser();
		$this->block = new DatabaseBlock( [
			'address' => $blockedUser->getName(),
			'by' => $this->getTestSysop()->getUser()->getId(),
			'user' => $blockedUser->getId()
		] );
		$this->block->insert();
		// ensure that block made it into the database
		wfGetDB( DB_MASTER )->commit( __METHOD__, 'flush' );
	}

	/**
	 * Provides User, permissions test action, and revision actions (with
	 * expected permission results for test action).
	 *
	 * Basically: a new post is created and the actions in $actions are
	 * performed. After that, we'll check if $action is allowed on all of those
	 * revisions, with the expected true/false value from $actions as result.
	 *
	 * @return array
	 */
	public function permissionsProvider() {
		return [
			// irregardless of current status, if a user has no permissions for
			// a specific revision, he can't see it
			[ 'confirmedUser', 'view', [
				// Key is the moderation action; value is the 'view' permission
				// for that corresponding revision after all moderation is done.
				// In this case, a post will be created with 3 revisions:
				// [1] create post, [2] suppress, [3] restore
				// After creating all revisions, all of these will be tested for
				// 'view' permissions against that specific revision. Here:
				// [1] should be visible (this + last rev not suppressed)
				// [2] should not (was suppressed)
				// [3] should be visible again (undid suppression)
				[ 'new-post' => true ],
				[ 'suppress-post' => false ],
				[ 'restore-post' => true ],
			] ],
			[ 'suppressUser', 'view', [
				[ 'new-post' => true ],
				[ 'suppress-post' => true ],
				[ 'restore-post' => true ],
			] ],

			// last moderation status should always bubble down to previous revs
			[ 'confirmedUser', 'view', [
				[ 'new-post' => false ],
				[ 'suppress-post' => false ],
				[ 'restore-post' => false ],
				[ 'suppress-post' => false ],
			] ],
			[ 'suppressUser', 'view', [
				[ 'new-post' => true ],
				[ 'suppress-post' => true ],
				[ 'restore-post' => true ],
				[ 'suppress-post' => true ],
			] ],

			// bug 61715
			[ 'confirmedUser', 'history', [
				[ 'new-post' => false ],
				[ 'suppress-post' => false ],
			] ],
			[ 'confirmedUser', 'history', [
				[ 'new-post' => true ],
				[ 'suppress-post' => false ],
				[ 'restore-post' => false ],
			] ],
		];
	}

	/**
	 * @dataProvider permissionsProvider
	 *
	 * @group Broken
	 */
	public function testPermissions( $userGetterName, $permissionAction, array $actions ) {
		// NOTE: the provider cannot create the User object, because it would be creating the
		// user in the real database tables, not the fake tables provided by MediaWikiTestCase.
		/** @var User $user */
		$user = $this->$userGetterName();

		$permissions = new RevisionActionPermissions( $this->actions, $user );

		// we'll have to process this in 2 steps: first do all of the actions,
		// so we have a full tree of moderated revisions
		$revision = null;
		$revisions = [];
		$debug = [];
		foreach ( $actions as $action ) {
			$expect = current( $action );
			$action = key( $action );
			$debug[] = $action . ':' . ( $expect ? 'true' : 'false' );
			$revisions[] = $revision = $this->generateRevision( $action, $revision );
		}

		// commit pending db transaction
		Container::get( 'db.factory' )->getDB( DB_MASTER )->commit( __METHOD__, 'flush' );

		$debug = implode( ' ', $debug );
		// secondly, iterate all revisions & see if expected permissions line up
		foreach ( $actions as $action ) {
			$expected = current( $action );
			$revision = array_shift( $revisions );
			$this->assertEquals(
				$expected,
				$permissions->isAllowed( $revision, $permissionAction ),
				'User ' . $user->getName() . ' should ' . ( $expected ? '' : 'not ' ) .
					'be allowed action ' . $permissionAction . ' on revision ' . key( $action ) .
					' : ' . $debug . ' : ' . json_encode( $revision::toStorageRow( $revision ) )
			);
		}
	}

	protected function blockedUser() {
		if ( !$this->blockedUser ) {
			$this->blockedUser = User::newFromName( 'UTFlowBlockee' );
			$this->blockedUser->addToDatabase();
			// note: the block will be added in setUp & deleted in tearDown;
			// otherwise this is just any regular user
		}

		return $this->blockedUser;
	}

	protected function anonUser() {
		if ( !$this->anonUser ) {
			$this->anonUser = new User;
		}

		return $this->anonUser;
	}

	protected function unconfirmedUser() {
		if ( !$this->unconfirmedUser ) {
			$this->unconfirmedUser = User::newFromName( 'UTFlowUnconfirmed' );
			$this->unconfirmedUser->addToDatabase();
			$this->unconfirmedUser->addGroup( 'user' );
		}

		return $this->unconfirmedUser;
	}

	protected function confirmedUser() {
		if ( !$this->confirmedUser ) {
			$this->confirmedUser = User::newFromName( 'UTFlowConfirmed' );
			$this->confirmedUser->addToDatabase();
			$this->confirmedUser->addGroup( 'autoconfirmed' );
		}

		return $this->confirmedUser;
	}

	protected function sysopUser() {
		if ( !$this->sysopUser ) {
			$this->sysopUser = User::newFromName( 'UTFlowSysop' );
			$this->sysopUser->addToDatabase();
			$this->sysopUser->addGroup( 'sysop' );
		}

		return $this->sysopUser;
	}

	protected function suppressUser() {
		if ( !$this->suppressUser ) {
			$this->suppressUser = User::newFromName( 'UTFlowSuppress' );
			$this->suppressUser->addToDatabase();
			$this->suppressUser->addGroup( 'suppress' );
		}

		return $this->suppressUser;
	}

	/**
	 * @param string $action
	 * @param AbstractRevision|null $parent
	 * @param array $overrides
	 * @return PostRevision
	 */
	public function generateRevision( $action, AbstractRevision $parent = null, array $overrides = [] ) {
		$overrides['rev_change_type'] = $action;

		if ( $parent ) {
			$overrides['rev_parent_id'] = $parent->getRevisionId()->getBinary();
			$overrides['tree_rev_descendant_id'] = $parent->getPostId()->getBinary();
			$overrides['rev_type_id'] = $parent->getPostId()->getBinary();
		}

		switch ( $action ) {
			case 'restore-post':
				$overrides += [
					'rev_mod_state' => $this->moderation[$action], // AbstractRevision::MODERATED_NONE
					'rev_mod_user_id' => null,
					'rev_mod_user_ip' => null,
					'rev_mod_timestamp' => null,
					'rev_mod_reason' => 'unit test',
				];
				break;

			case 'hide-post':
			case 'delete-post':
			case 'suppress-post':
				$overrides += [
					'rev_mod_state' => $this->moderation[$action], // AbstractRevision::MODERATED_(HIDDEN|DELETED|SUPPRESSED)
					'rev_mod_user_id' => 1,
					'rev_mod_user_ip' => null,
					'rev_mod_timestamp' => wfTimestampNow(),
					'rev_mod_reason' => 'unit test',
				];
				break;

			default:
				// nothing special
				break;
		}

		$revision = $this->generateObject( $overrides );
		$this->store( $revision );

		return $revision;
	}
}
