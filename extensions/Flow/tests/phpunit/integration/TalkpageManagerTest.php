<?php

namespace Flow\Tests;

use Flow\Container;
use Flow\TalkpageManager;
use HashConfig;
use MediaWikiTestCase;
use Title;
use User;
use WikiPage;
use WikitextContent;

/**
 * @covers \Flow\TalkpageManager
 *
 * @group Flow
 * @group Database
 */
class TalkpageManagerTest extends MediaWikiTestCase {
	/**
	 * @var TalkpageManager
	 */
	protected $talkpageManager;

	public function setUp() : void {
		parent::setUp();
		$this->talkpageManager = Container::get( 'occupation_controller' );

		$this->tablesUsed = array_merge( $this->tablesUsed, [
			'page',
			'revision',
			'ip_changes',
		] );
	}

	public function testCheckIfCreationIsPossible() {
		$existentTitle = Title::newFromText( 'Exists' );
		$status = WikiPage::factory( $existentTitle )
			->doEditContent(
				new WikitextContent( 'This exists' ),
				"with an edit summary"
			);
		if ( !$status->isGood() ) {
			$this->fail( $status->getMessage()->plain() );
		}

		$existTrueStatus = $this->talkpageManager->checkIfCreationIsPossible( $existentTitle, /*mustNotExist*/ true );
		$this->assertTrue( $existTrueStatus->hasMessage( 'flow-error-allowcreation-already-exists' ),
			'Error when page already exists and mustNotExist true was passed' );
		$this->assertFalse( $existTrueStatus->isOK(),
			'Error when page already exists and mustNotExist true was passed' );

		$existFalseStatus = $this->talkpageManager->checkIfCreationIsPossible( $existentTitle, /*mustNotExist*/ false );
		$this->assertFalse( $existFalseStatus->hasMessage( 'flow-error-allowcreation-already-exists' ),
			'No error when page already exists and mustNotExist false was passed' );
		$this->assertTrue( $existFalseStatus->isOK(),
			'No error when page already exists and mustNotExist false was passed' );
	}

	public function testCheckIfUserHasPermission() {
		global $wgNamespaceContentModels;

		$tempModels = $wgNamespaceContentModels;
		$tempModels[NS_USER_TALK] = CONTENT_MODEL_FLOW_BOARD;

		$unconfirmedUser = User::newFromName( 'UTFlowUnconfirmed' );

		// TODO: remove this once core no longer accesses wgNamespaceContentModels directly.
		$this->setMwGlobals( [
			'wgNamespaceContentModels' => $tempModels,
			'wgFlowReadOnly' => false,
		] );

		$this->overrideMwServices( new HashConfig( [
			'wgNamespaceContentModels' => $tempModels,
			'wgFlowReadOnly' => false,
		] ) );

		$permissionStatus = $this->talkpageManager->checkIfUserHasPermission(
			Title::newFromText( 'User talk:Test123' ), $unconfirmedUser );
		$this->assertTrue( $permissionStatus->isOK(),
			'No error when enabling Flow board in default-Flow namespace' );

		$permissionStatus = $this->talkpageManager->checkIfUserHasPermission(
			Title::newFromText( 'User:Test123' ), $unconfirmedUser );
		$this->assertFalse( $permissionStatus->isOK(),
			'Error when user without flow-create-board enables Flow board in non-default-Flow namespace' );
		$this->assertTrue( $permissionStatus->hasMessage( 'flow-error-allowcreation-flow-create-board' ),
			'Correct error thrown when user does not have flow-create-board right' );

		$adminUser = User::newFromName( 'UTSysop' );
		$adminUser->addGroup( 'flow-bot' );

		$permissionStatus = $this->talkpageManager->checkIfUserHasPermission(
			Title::newFromText( 'User:Test123' ), $adminUser );
		$this->assertTrue( $permissionStatus->isOK(),
			'No error when user with flow-create-board enables Flow board in non-default-Flow namespace' );
	}
}
