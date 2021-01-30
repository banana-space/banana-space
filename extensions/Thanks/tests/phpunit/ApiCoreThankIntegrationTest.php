<?php

use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;

/**
 * Integration tests for the Thanks API module
 *
 * @covers ApiCoreThank
 *
 * @group Thanks
 * @group Database
 * @group medium
 * @group API
 *
 * @author Addshore
 */
class ApiCoreThankIntegrationTest extends ApiTestCase {

	/**
	 * @var int filled in setUp
	 */
	private $revId;

	/**
	 * @var User filled in setUp
	 */
	private $uploader;

	/**
	 * @var int The ID of a deletion log entry.
	 */
	protected $logId;

	public function setUp() : void {
		parent::setUp();

		$this->uploader = $this->getTestUser( [ 'uploader' ] )->getUser();
		$user = $this->uploader;

		$pageName = __CLASS__;
		$content = __CLASS__;
		$pageTitle = Title::newFromText( $pageName );
		// If the page already exists, delete it, otherwise our edit will not result in a new revision
		if ( $pageTitle->exists() ) {
			$wikiPage = WikiPage::factory( $pageTitle );
			$wikiPage->doDeleteArticleReal( '', $user );
		}
		$result = $this->editPage( $pageName, $content, 'Summary', NS_MAIN, $user );
		/** @var Status $result */
		$result = $result->getValue();
		/** @var RevisionRecord $revisionRecord */
		$revisionRecord = $result['revision-record'];
		$this->revId = $revisionRecord->getId();

		// Create a 2nd page and delete it, so we can thank for the log entry.
		$pageToDeleteTitle = Title::newFromText( 'Page to delete' );
		$pageToDelete = WikiPage::factory( $pageToDeleteTitle );

		$updater = $pageToDelete->newPageUpdater( $user );
		$updater->setcontent(
			SlotRecord::MAIN,
			ContentHandler::makeContent( '', $pageToDeleteTitle )
		);
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( '' ) );

		$deleteStatus = $pageToDelete->doDeleteArticleReal( '', $user );
		$this->logId = $deleteStatus->getValue();

		DeferredUpdates::clearPendingUpdates();
	}

	public function testRequestWithoutToken() {
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage( 'The "token" parameter must be set.' );
		$this->doApiRequest( [
			'action' => 'thank',
			'source' => 'someSource',
			'rev' => 1,
		], null, false, $this->getTestSysop()->getUser() );
	}

	public function testValidRevRequest() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'rev' => $this->revId,
		], null, $this->getTestSysop()->getUser() );
		$this->assertSuccess( $result );
	}

	public function testValidLogRequest() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		], null, $this->getTestSysop()->getUser() );
		$this->assertSuccess( $result );
	}

	public function testLogRequestWithDisallowedLogType() {
		// Empty the log-type whitelist.
		$this->setMwGlobals( [ 'wgThanksLogTypeWhitelist' => [] ] );
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			"Log type 'delete' is not in the whitelist of permitted log types." );
		$this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		], null, $this->getTestSysop()->getUser() );
	}

	public function testLogThanksForADeletedLogEntry() {
		$this->mergeMwGlobalArrayValue( 'wgGroupPermissions', [
			'logdeleter' => [
				'read' => true,
				'writeapi' => true,
				'deletelogentry' => true
			]
		] );

		// Mark our test log entry as deleted.
		// To do this we briefly switch to a different test user.
		$logdeleter = $this->getTestUser( [ 'logdeleter' ] )->getUser();
		$this->doApiRequestWithToken( [
			'action' => 'revisiondelete',
			'type'   => 'logging',
			'ids'    => $this->logId,
			'hide'   => 'content',
		], null, $logdeleter );

		$sysop = $this->getTestSysop()->getUser();
		// Then try to thank for it, and we should get an exception.
		$this->expectException( ApiUsageException::class );
		$this->expectExceptionMessage(
			"The requested log entry has been deleted and thanks cannot be given for it." );
		$this->doApiRequestWithToken( [
			'action' => 'thank',
			'log' => $this->logId,
		], null, $sysop );
	}

	public function testValidRequestWithSource() {
		list( $result,, ) = $this->doApiRequestWithToken( [
			'action' => 'thank',
			'source' => 'someSource',
			'rev' => $this->revId,
		], null, $this->getTestSysop()->getUser() );
		$this->assertSuccess( $result );
	}

	protected function assertSuccess( $result ) {
		$this->assertEquals( [
			'result' => [
				'success' => 1,
				'recipient' => $this->uploader->getName(),
			],
		], $result );
	}

	public function testInvalidRequest() {
		$this->expectException( ApiUsageException::class );
		$this->doApiRequestWithToken( [ 'action' => 'thank' ] );
	}

}
