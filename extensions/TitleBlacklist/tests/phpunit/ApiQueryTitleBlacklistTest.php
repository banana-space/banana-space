<?php
/**
 * Test the TitleBlacklist API.
 *
 * This wants to run with phpunit.php, like so:
 * cd $IP/tests/phpunit
 * php phpunit.php ../../extensions/TitleBlacklist/tests/ApiQueryTitleBlacklistTest.php
 *
 * The blacklist file is `testSource` and shared by all tests.
 *
 * Ian Baker <ian@wikimedia.org>
 */

ini_set( 'include_path', ini_get( 'include_path' ) . ':' .
	__DIR__ . '/../../../tests/phpunit/includes/api' );

/**
 * @group medium
 * @covers ApiQueryTitleBlacklist
 */
class ApiQueryTitleBlacklistTest extends ApiTestCase {

	function setUp() {
		parent::setUp();
		$this->doLogin();

		TitleBlacklist::destroySingleton();
		$this->setMwGlobals( 'wgTitleBlacklistSources', [
			[
				'type' => 'file',
				'src'  => __DIR__ . '/testSource',
			],
		] );
	}

	function tearDown() {
		TitleBlacklist::destroySingleton();
		parent::tearDown();
	}

	/**
	 * Verify we allow a title which is not blacklisted
	 */
	function testCheckingUnlistedTitle() {
		$unlisted = $this->doApiRequest( [
			'action' => 'titleblacklist',
			// evil_acc is blacklisted as <newaccountonly>
			'tbtitle' => 'evil_acc',
			'tbaction' => 'create',
			'tbnooverride' => true,
		] );

		$this->assertEquals(
			'ok',
			$unlisted[0]['titleblacklist']['result'],
			'Not blacklisted title returns ok'
		);
	}

	/**
	 * Verify tboverride works
	 */
	function testTboverride() {
		global $wgGroupPermissions;

		// Allow all users to override the titleblacklist
		$this->stashMwGlobals( 'wgGroupPermissions' );
		$wgGroupPermissions['*']['tboverride'] = true;

		$unlisted = $this->doApiRequest( [
			'action' => 'titleblacklist',
			'tbtitle' => 'bar',
			'tbaction' => 'create',
		] );

		$this->assertEquals(
			'ok',
			$unlisted[0]['titleblacklist']['result'],
			'Blacklisted title returns ok if the user is allowd to tboverride'
		);
	}

	/**
	 * Verify a blacklisted title gives out an error.
	 */
	function testCheckingBlackListedTitle() {
		$listed = $this->doApiRequest( [
			'action' => 'titleblacklist',
			'tbtitle' => 'bar',
			'tbaction' => 'create',
			'tbnooverride' => true,
		] );

		$this->assertEquals(
			'blacklisted',
			$listed[0]['titleblacklist']['result'],
			'Listed title returns error'
		);
		$this->assertEquals(
			"The title \"bar\" has been banned from creation.\nIt matches the following " .
				"blacklist entry: <code>[Bb]ar #example blacklist entry</code>",
			$listed[0]['titleblacklist']['reason'],
			'Listed title error text is as expected'
		);

		$this->assertEquals(
			"titleblacklist-forbidden-edit",
			$listed[0]['titleblacklist']['message'],
			'Correct blacklist message name is returned'
		);

		$this->assertEquals(
			"[Bb]ar #example blacklist entry",
			$listed[0]['titleblacklist']['line'],
			'Correct blacklist line is returned'
		);
	}

	/**
	 * Tests integration with the AntiSpoof extension
	 */
	function testAntiSpoofIntegration() {
		if ( !class_exists( 'AntiSpoof' ) ) {
			$this->markTestSkipped( "This test requires the AntiSpoof extension" );
		}

		$listed = $this->doApiRequest( [
			'action' => 'titleblacklist',
			'tbtitle' => 'AVVVV',
			'tbaction' => 'create',
			'tbnooverride' => true,
		] );

		$this->assertEquals(
			'blacklisted',
			$listed[0]['titleblacklist']['result'],
			'Spoofed title is blacklisted'
		);
	}
}
