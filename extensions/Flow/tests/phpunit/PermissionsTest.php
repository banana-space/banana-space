<?php

namespace Flow\Tests;

use Flow\Container;
use Flow\FlowActions;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\RevisionActionPermissions;
use User;

/**
 * @covers \Flow\Model\AbstractRevision
 * @covers \Flow\Model\PostRevision
 * @covers \Flow\RevisionActionPermissions
 *
 * @group Database
 * @group Flow
 */
class PermissionsTest extends PostRevisionTestCase {
	/**
	 * @var array
	 */
	protected $tablesUsed = [ 'user', 'user_groups' ];

	/**
	 * @var FlowActions
	 */
	protected $actions;

	/**
	 * @var PostRevision
	 */
	protected $topic;

	/**
	 * @var PostRevision
	 */
	protected $hiddenTopic;

	/**
	 * @var PostRevision
	 */
	protected $deletedTopic;

	/**
	 * @var PostRevision
	 */
	protected $suppressedTopic;

	/**
	 * @var PostRevision
	 */
	protected $post;

	/**
	 * @var PostRevision
	 */
	protected $hiddenPost;

	/**
	 * @var PostRevision
	 */
	protected $deletedPost;

	/**
	 * @var PostRevision
	 */
	protected $suppressedPost;

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

	protected function setUp() : void {
		parent::setUp();

		// We don't want local config getting in the way of testing whether or
		// not our permissions implementation works well.
		$this->resetPermissions();

		// load actions object
		$this->actions = Container::get( 'flow_actions' );
	}

	/**
	 * Provides User, PostRevision (or null) & action to testPermissions, as
	 * well as the expected result: whether or not a certain user should be
	 * allowed to perform a certain action on a certain revision.
	 *
	 * I'm calling functions to fetch users & revisions. This is done because
	 * setUp is called only after dataProvider is executed, so it's impossible
	 * to create all these objects in setUp.
	 *
	 * "All data providers are executed before both the call to the
	 * setUpBeforeClass static method and the first call to the setUp method.
	 * Because of that you can't access any variables you create there from
	 * within a data provider. This is required in order for PHPUnit to be able
	 * to compute the total number of tests."
	 *
	 * @see http://phpunit.de/manual/3.7/en/writing-tests-for-phpunit.html
	 *
	 * @return array
	 */
	public function permissionsProvider() {
		return [
			// anon users can submit content, but not moderate
			[ 'anonUser', null, 'create-header', true ],
			// array( 'anonUser', 'header', 'edit-header', true ),
			[ 'anonUser', 'topic', 'edit-title', true ],
			[ 'anonUser', null, 'new-post', true ],
			[ 'anonUser', 'post', 'edit-post', false ],
			[ 'anonUser', 'post', 'hide-post', true ],
			[ 'anonUser', 'topic', 'hide-topic', true ],
			[ 'anonUser', 'topic', 'lock-topic', false ],
			[ 'anonUser', 'post', 'delete-post', false ],
			[ 'anonUser', 'topic', 'delete-topic', false ],
			[ 'anonUser', 'post', 'suppress-post', false ],
			[ 'anonUser', 'topic', 'suppress-topic', false ],
			[ 'anonUser', 'post', 'restore-post', false ],
			[ 'anonUser', 'topic', 'restore-topic', false ],
			[ 'anonUser', 'post', 'history', true ],
			[ 'anonUser', 'post', 'view', true ],
			[ 'anonUser', 'post', 'reply', true ],

			// unconfirmed users can also hide posts...
			[ 'unconfirmedUser', null, 'create-header', true ],
			// array( 'unconfirmedUser', 'header', 'edit-header', true ),
			[ 'unconfirmedUser', 'topic', 'edit-title', true ],
			[ 'unconfirmedUser', null, 'new-post', true ],
			[ 'unconfirmedUser', 'post', 'edit-post', true ], // can edit own post
			[ 'unconfirmedUser', 'post', 'hide-post', true ],
			[ 'unconfirmedUser', 'topic', 'hide-topic', true ],
			[ 'unconfirmedUser', 'topic', 'lock-topic', true ],
			[ 'unconfirmedUser', 'post', 'delete-post', false ],
			[ 'unconfirmedUser', 'topic', 'delete-topic', false ],
			[ 'unconfirmedUser', 'post', 'suppress-post', false ],
			[ 'unconfirmedUser', 'topic', 'suppress-topic', false ],
			[ 'unconfirmedUser', 'post', 'restore-post', false ], // $this->post is not hidden
			[ 'unconfirmedUser', 'topic', 'restore-topic', false ], // $this->topic is not hidden
			[ 'unconfirmedUser', 'post', 'history', true ],
			[ 'unconfirmedUser', 'post', 'view', true ],
			[ 'unconfirmedUser', 'post', 'reply', true ],

			// ... as well as restore hidden posts
			[ 'unconfirmedUser', 'hiddenPost', 'restore-post', true ],
			[ 'unconfirmedUser', 'hiddenTopic', 'restore-topic', true ],

			// ... but not restore deleted/suppressed posts
			[ 'unconfirmedUser', 'deletedPost', 'restore-post', false ],
			[ 'unconfirmedUser', 'deletedTopic', 'restore-topic', false ],
			[ 'unconfirmedUser', 'suppressedPost', 'restore-post', false ],
			[ 'unconfirmedUser', 'suppressedTopic', 'restore-topic', false ],

			// confirmed users are the same as unconfirmed users, in terms of permissions
			[ 'confirmedUser', null, 'create-header', true ],
			// array( 'confirmedUser', 'header', 'edit-header', true ),
			[ 'confirmedUser', 'topic', 'edit-title', true ],
			[ 'confirmedUser', null, 'new-post', true ],
			[ 'confirmedUser', 'post', 'edit-post', false ],
			[ 'confirmedUser', 'post', 'hide-post', true ],
			[ 'confirmedUser', 'topic', 'hide-topic', true ],
			[ 'confirmedUser', 'post', 'delete-post', false ],
			[ 'confirmedUser', 'topic', 'delete-topic', false ],
			[ 'confirmedUser', 'topic', 'lock-topic', true ],
			[ 'confirmedUser', 'post', 'suppress-post', false ],
			[ 'confirmedUser', 'topic', 'suppress-topic', false ],
			[ 'confirmedUser', 'post', 'restore-post', false ], // $this->post is not hidden
			[ 'confirmedUser', 'topic', 'restore-topic', false ], // $this->topic is not hidden
			[ 'confirmedUser', 'post', 'history', true ],
			[ 'confirmedUser', 'post', 'view', true ],
			[ 'confirmedUser', 'post', 'reply', true ],
			[ 'confirmedUser', 'hiddenPost', 'restore-post', true ],
			[ 'confirmedUser', 'hiddenTopic', 'restore-topic', true ],
			[ 'confirmedUser', 'deletedPost', 'restore-post', false ],
			[ 'confirmedUser', 'deletedTopic', 'restore-topic', false ],
			[ 'confirmedUser', 'suppressedPost', 'restore-post', false ],
			[ 'confirmedUser', 'suppressedTopic', 'restore-topic', false ],

			// sysops can do all (incl. editing posts) but suppressing
			[ 'sysopUser', null, 'create-header', true ],
			// array( 'sysopUser', 'header', 'edit-header', true ),
			[ 'sysopUser', 'topic', 'edit-title', true ],
			[ 'sysopUser', null, 'new-post', true ],
			[ 'sysopUser', 'post', 'edit-post', true ],
			[ 'sysopUser', 'post', 'hide-post', true ],
			[ 'sysopUser', 'topic', 'hide-topic', true ],
			[ 'sysopUser', 'topic', 'lock-topic', true ],
			[ 'sysopUser', 'post', 'delete-post', true ],
			[ 'sysopUser', 'topic', 'delete-topic', true ],
			[ 'sysopUser', 'post', 'suppress-post', false ],
			[ 'sysopUser', 'topic', 'suppress-topic', false ],
			[ 'sysopUser', 'post', 'restore-post', false ], // $this->post is not hidden
			[ 'sysopUser', 'topic', 'restore-topic', false ], // $this->topic is not hidden
			[ 'sysopUser', 'topic', 'history', true ],
			[ 'sysopUser', 'post', 'view', true ],
			[ 'sysopUser', 'post', 'reply', true ],
			[ 'sysopUser', 'hiddenPost', 'restore-post', true ],
			[ 'sysopUser', 'hiddenTopic', 'restore-topic', true ],
			[ 'sysopUser', 'deletedPost', 'restore-post', true ],
			[ 'sysopUser', 'deletedTopic', 'restore-topic', true ],
			[ 'sysopUser', 'suppressedPost', 'restore-post', false ],
			[ 'sysopUser', 'suppressedTopic', 'restore-topic', false ],

			// suppressors can do everything + suppress (but not edit!)
			[ 'suppressUser', null, 'create-header', true ],
			// array( 'suppressUser', 'header', 'edit-header', true ),
			[ 'suppressUser', 'topic', 'edit-title', true ],
			[ 'suppressUser', null, 'new-post', true ],
			[ 'suppressUser', 'post', 'edit-post', false ],
			[ 'suppressUser', 'post', 'hide-post', true ],
			[ 'suppressUser', 'topic', 'hide-topic', true ],
			[ 'suppressUser', 'topic', 'lock-topic', true ],
			[ 'suppressUser', 'post', 'delete-post', true ],
			[ 'suppressUser', 'topic', 'delete-topic', true ],
			[ 'suppressUser', 'post', 'suppress-post', true ],
			[ 'suppressUser', 'topic', 'suppress-topic', true ],
			[ 'suppressUser', 'post', 'restore-post', false ], // $this->post is not hidden
			[ 'suppressUser', 'topic', 'restore-topic', false ], // $this->topic is not hidden
			[ 'suppressUser', 'post', 'history', true ],
			[ 'suppressUser', 'post', 'view', true ],
			[ 'suppressUser', 'post', 'reply', true ],
			[ 'suppressUser', 'hiddenPost', 'restore-post', true ],
			[ 'suppressUser', 'hiddenTopic', 'restore-topic', true ],
			[ 'suppressUser', 'deletedPost', 'restore-post', true ],
			[ 'suppressUser', 'deletedTopic', 'restore-topic', true ],
			[ 'suppressUser', 'suppressedPost', 'restore-post', true ],
			[ 'suppressUser', 'suppressedTopic', 'restore-topic', true ],
		];
	}

	/**
	 * @dataProvider permissionsProvider
	 */
	public function testPermissions( $userGetterName, $revisionGetterName, $action, $expected ) {
		$user = $this->$userGetterName();
		$revision = $revisionGetterName ? $this->$revisionGetterName() : null;

		$permissions = new RevisionActionPermissions( $this->actions, $user );
		$this->assertEquals( $expected, $permissions->isRevisionAllowed( $revision, $action ) );
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

	protected function topic() {
		if ( !$this->topic ) {
			$this->topic = $this->generateObject();
		}

		return $this->topic;
	}

	protected function hiddenTopic() {
		if ( !$this->hiddenTopic ) {
			$this->hiddenTopic = $this->generateObject( [
				'rev_change_type' => 'hide-topic',
				'rev_mod_state' => AbstractRevision::MODERATED_HIDDEN
			] );
		}

		return $this->hiddenTopic;
	}

	protected function deletedTopic() {
		if ( !$this->deletedTopic ) {
			$this->deletedTopic = $this->generateObject( [
				'rev_change_type' => 'delete-topic',
				'rev_mod_state' => AbstractRevision::MODERATED_DELETED
			] );
		}

		return $this->deletedTopic;
	}

	protected function suppressedTopic() {
		if ( !$this->suppressedTopic ) {
			$this->suppressedTopic = $this->generateObject( [
				'rev_change_type' => 'suppress-topic',
				'rev_mod_state' => AbstractRevision::MODERATED_SUPPRESSED
			] );
		}

		return $this->suppressedTopic;
	}

	protected function post() {
		if ( !$this->post ) {
			$this->post = $this->generateObject( [
				'tree_orig_user_id' => $this->unconfirmedUser()->getId(),
				'tree_orig_user_ip' => '',
				'tree_parent_id' => $this->topic()->getPostId()->getBinary()
			], [], 1 );
			$this->post->setRootPost( $this->generateObject( [
				'tree_orig_user_id' => $this->unconfirmedUser()->getId(),
				'tree_orig_user_ip' => '',
				'tree_parent_id' => $this->topic()->getPostId()->getBinary()
			], [], 1 ) );
		}

		return $this->post;
	}

	protected function hiddenPost() {
		if ( !$this->hiddenPost ) {
			$this->hiddenPost = $this->generateObject( [
				'tree_orig_user_id' => $this->unconfirmedUser()->getId(),
				'tree_orig_user_ip' => '',
				'tree_parent_id' => $this->topic()->getPostId()->getBinary(),
				'rev_change_type' => 'hide-post',
				'rev_mod_state' => AbstractRevision::MODERATED_HIDDEN
			], [], 1 );
		}

		return $this->hiddenPost;
	}

	protected function deletedPost() {
		if ( !$this->deletedPost ) {
			$this->deletedPost = $this->generateObject( [
				'tree_orig_user_id' => $this->unconfirmedUser()->getId(),
				'tree_orig_user_ip' => '',
				'tree_parent_id' => $this->topic()->getPostId()->getBinary(),
				'rev_change_type' => 'delete-post',
				'rev_mod_state' => AbstractRevision::MODERATED_DELETED
			], [], 1 );
		}

		return $this->deletedPost;
	}

	protected function suppressedPost() {
		if ( !$this->suppressedPost ) {
			$this->suppressedPost = $this->generateObject( [
				'tree_orig_user_id' => $this->unconfirmedUser()->getId(),
				'tree_orig_user_ip' => '',
				'tree_parent_id' => $this->topic()->getPostId()->getBinary(),
				'rev_change_type' => 'suppress-post',
				'rev_mod_state' => AbstractRevision::MODERATED_SUPPRESSED
			], [], 1 );
		}

		return $this->suppressedPost;
	}
}
