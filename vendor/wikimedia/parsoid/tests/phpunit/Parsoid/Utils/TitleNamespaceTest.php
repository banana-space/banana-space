<?php

namespace Test\Parsoid\Utils;

use Wikimedia\Parsoid\Mocks\MockSiteConfig;
use Wikimedia\Parsoid\Utils\TitleNamespace;

/**
 * @covers \Wikimedia\Parsoid\Utils\TitleNamespace
 */
class TitleNamespaceTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider provideNamespace
	 */
	public function testNamespace(
		$ns, $isATalk, $isUser, $isUserTalk, $isMedia, $isFile, $isCategory
	) {
		$namespace = new TitleNamespace( $ns, new MockSiteConfig( [] ) );
		$this->assertSame( $ns, $namespace->getId() );
		$this->assertSame( $isATalk, $namespace->isATalkNamespace() );
		$this->assertSame( $isUser, $namespace->isUser() );
		$this->assertSame( $isUserTalk, $namespace->isUserTalk() );
		$this->assertSame( $isMedia, $namespace->isMedia() );
		$this->assertSame( $isFile, $namespace->isFile() );
		$this->assertSame( $isCategory, $namespace->isCategory() );
	}

	public function provideNamespace() {
		return [
			'Media' => [ -2, false, false, false, true, false, false ],
			'Main' => [ 0, false, false, false, false, false, false ],
			'Talk' => [ 1, true, false, false, false, false, false ],
			'User' => [ 2, false, true, false, false, false, false ],
			'User Talk' => [ 3, true, false, true, false, false, false ],
			'File' => [ 6, false, false, false, false, true, false ],
			'File Talk' => [ 7, true, false, false, false, false, false ],
			'Category' => [ 14, false, false, false, false, false, true ],
			'Category Talk' => [ 15, true, false, false, false, false, false ],
		];
	}

}
