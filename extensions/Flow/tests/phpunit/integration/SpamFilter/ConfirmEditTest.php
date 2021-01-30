<?php

namespace Flow\Tests\SpamFilter;

use Flow\Model\PostRevision;
use Flow\Model\Workflow;
use Flow\SpamFilter\ConfirmEdit;
use GlobalVarConfig;
use Title;
use User;

/**
 * @covers \Flow\SpamFilter\ConfirmEdit
 */
class ConfirmEditTest extends \MediaWikiTestCase {

	public function testValidateDoesntBlowUp() {
		$filter = new ConfirmEdit;
		if ( !$filter->enabled() ) {
			$this->markTestSkipped( 'ConfirmEdit is not enabled' );
		}

		$user = User::newFromName( '127.0.0.1', false );
		$title = Title::newFromText( 'Topic:Tnprd6ksfu1v1nme' );
		$ownerTitle = Title::newMainPage();
		$workflow = Workflow::create( 'topic', $title );

		$oldRevision = PostRevision::createTopicPost( $workflow, $user, 'foo' );
		$newRevision = $oldRevision->newNextRevision( $user, 'bar', 'topic-title-wikitext', 'edit-title', $title );

		$request = $this->createMock( \WebRequest::class );
		$request->expects( $this->any() )
			->method( 'wasPosted' )
			->will( $this->returnValue( true ) );

		$context = $this->createMock( \IContextSource::class );

		$context->expects( $this->any() )
			->method( 'getUser' )
			->will( $this->returnValue( $user ) );

		// ConfirmEdit::filter() requires a Config that has most MW globals
		$context->expects( $this->any() )
			->method( 'getConfig' )
			->willReturn( new GlobalVarConfig );

		$context->expects( $this->any() )
			->method( 'getRequest' )
			->will( $this->returnValue( $request ) );

		$status = $filter->validate( $context, $newRevision, $oldRevision, $title, $ownerTitle );
		$this->assertInstanceOf( \Status::class, $status );
		$this->assertTrue( $status->isGood() );
	}
}
