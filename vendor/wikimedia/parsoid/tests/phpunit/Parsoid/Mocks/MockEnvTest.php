<?php

namespace Test\Parsoid\Mocks;

use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Config\PageContent;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\Parsoid\Mocks\MockEnv;

/**
 * Test the Parsoid\Tests\Mock* wrappers
 * @covers \Wikimedia\Parsoid\Mocks\MockEnv
 * @covers \Wikimedia\Parsoid\Mocks\MockDataAccess
 * @covers \Wikimedia\Parsoid\Mocks\MockPageConfig
 * @covers \Wikimedia\Parsoid\Mocks\MockPageContent
 * @covers \Wikimedia\Parsoid\Mocks\MockSiteConfig
 */
class MockEnvTest extends \PHPUnit\Framework\TestCase {

	public function testDefaultEnv() {
		$env = new MockEnv( [] );

		$this->assertSame(
			[ 'main' ],
			$env->getPageConfig()->getRevisionContent()->getRoles()
		);
		$this->assertTrue( $env->getPageConfig()->getRevisionContent()->hasRole( 'main' ) );
		$this->assertSame(
			'Some dummy source wikitext for testing.',
			$env->getPageConfig()->getRevisionContent()->getContent( 'main' )
		);
		$this->assertSame(
			'wikitext',
			$env->getPageConfig()->getRevisionContent()->getModel( 'main' )
		);
		$this->assertSame(
			'text/x-wiki',
			$env->getPageConfig()->getRevisionContent()->getFormat( 'main' )
		);

		$this->assertSame( 946782245, $env->getSiteConfig()->fakeTimestamp() );
		$this->assertFalse( $env->getSiteConfig()->rtTestMode() );
		ob_start();
		$env->log( 'prefix', 'foo', function () {
			$this->fail( 'Callback should not be called' );
		} );
		$this->assertSame( '', ob_get_clean() );
	}

	public function testEnvOptions() {
		$mockDataAccess = $this->getMockBuilder( DataAccess::class )->getMockForAbstractClass();
		$mockPageConfig = $this->getMockBuilder( PageConfig::class )
			->setMethods( [ 'getRevisionContent', 'getTitle' ] )
			->getMockForAbstractClass();
		$mockPageContent = $this->getMockBuilder( PageContent::class )->getMockForAbstractClass();
		$mockSiteConfig = $this->getMockBuilder( SiteConfig::class )
			->setMethods( [ 'legalTitleChars' ] )
			->getMockForAbstractClass();

		$mockPageConfig->method( 'getTitle' )->willReturn( 'Main Page' );
		$mockPageConfig->method( 'getRevisionContent' )->willReturn( $mockPageContent );
		$mockSiteConfig->method( 'legalTitleChars' )->willReturn( 'A-Za-z_ ' );

		$env = new MockEnv( [
			'pageContent' => 'Foo bar?',
		] );
		$this->assertSame(
			'Foo bar?',
			$env->getPageConfig()->getRevisionContent()->getContent( 'main' )
		);

		$env = new MockEnv( [
			'pageContent' => $mockPageContent,
		] );
		$this->assertSame(
			$mockPageContent,
			$env->getPageConfig()->getRevisionContent()
		);

		$env = new MockEnv( [
			'siteConfig' => $mockSiteConfig,
			'pageConfig' => $mockPageConfig,
			'dataAccess' => $mockDataAccess,
		] );
		$this->assertSame( $mockSiteConfig, $env->getSiteConfig() );
		$this->assertSame( $mockPageConfig, $env->getPageConfig() );
		$this->assertSame( $mockDataAccess, $env->getDataAccess() );

		$env = new MockEnv( [
			'traceFlags' => [ "dsr" ],
		] );
		$this->assertNotInstanceOf( \Psr\Log\NullLogger::class, $env->getSiteConfig()->getLogger() );
	}

}
