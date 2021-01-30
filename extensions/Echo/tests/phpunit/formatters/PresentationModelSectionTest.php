<?php

/**
 * @group Database
 */
class EchoPresentationModelSectionTest extends MediaWikiTestCase {

	/**
	 * @covers \EchoPresentationModelSection::getTruncatedSectionTitle
	 */
	public function testGetTruncatedSectionTitle_short() {
		$lang = Language::factory( 'en' );
		$section = new EchoPresentationModelSection(
			$this->makeEvent( [ 'event_extra' => serialize( [ 'section-title' => 'asdf' ] ) ] ),
			$this->getTestUser()->getUser(),
			$lang
		);

		$this->assertEquals( $lang->embedBidi( 'asdf' ), $section->getTruncatedSectionTitle() );
	}

	/**
	 * @covers \EchoPresentationModelSection::getTruncatedSectionTitle
	 */
	public function testGetTruncatedSectionTitle_long() {
		$lang = Language::factory( 'en' );
		$section = new EchoPresentationModelSection(
			$this->makeEvent( [ 'event_extra' => serialize( [ 'section-title' => str_repeat( 'a', 100 ) ] ) ] ),
			$this->getTestUser()->getUser(),
			$lang
		);

		$this->assertEquals(
			$lang->embedBidi( str_repeat( 'a', 50 ) . '...' ),
			$section->getTruncatedSectionTitle()
		);
	}

	/**
	 * @covers \EchoPresentationModelSection::getTitleWithSection
	 */
	public function testGetTitleWithSection() {
		$page = $this->getExistingTestPage();
		$section = new EchoPresentationModelSection(
			$this->makeEvent( [
				'event_page_id' => $page->getId(),
				'event_extra' => serialize( [ 'section-title' => 'asdf' ] ),
			] ),
			$this->getTestUser()->getUser(),
			Language::factory( 'en' )
		);

		$titleWithSection = $section->getTitleWithSection();

		$this->assertEquals( 'asdf', $titleWithSection->getFragment() );
		$this->assertEquals( $page->getTitle()->getPrefixedText(), $titleWithSection->getPrefixedText() );
	}

	/**
	 * @covers \EchoPresentationModelSection::exists
	 */
	public function testExists_no() {
		$section = new EchoPresentationModelSection(
			$this->makeEvent(),
			$this->getTestUser()->getUser(),
			Language::factory( 'en' )
		);

		$this->assertFalse( $section->exists() );
	}

	/**
	 * @covers \EchoPresentationModelSection::exists
	 */
	public function testExists_yes() {
		$section = new EchoPresentationModelSection(
			$this->makeEvent( [ 'event_extra' => serialize( [ 'section-title' => 'asdf' ] ) ] ),
			$this->getTestUser()->getUser(),
			Language::factory( 'en' )
		);

		$this->assertTrue( $section->exists() );
	}

	private function makeEvent( $config = [] ) {
		$agent = $this->getTestSysop()->getUser();
		return EchoEvent::newFromRow( (object)array_merge( [
			'event_id' => 12,
			'event_type' => 'welcome',
			'event_variant' => '1',
			'event_page_id' => 1,
			'event_deleted' => 0,
			'event_agent_id' => $agent->getId(),
			'event_extra' => '',
		], $config ) );
	}
}
