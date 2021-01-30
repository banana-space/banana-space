<?php

/**
 * @coversDefaultClass EchoContainmentSet
 */
class EchoContainmentSetTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::addTitleIDsFromUserOption
	 * @dataProvider addTitlesFromUserOptionProvider
	 * @param User $user
	 * @param string $prefData
	 * @param string $contains
	 * @param bool $expected
	 */
	public function testAddTitlesFromUserOption(
		$prefData, string $contains, bool $expected
	) {
		$user = $this->getDefaultUserMock();
		$user->method( 'getOption' )
			->willReturn( $prefData );
		$containmentSet = new EchoContainmentSet( $user );
		$containmentSet->addTitleIDsFromUserOption( 'preference-name' );
		$this->assertSame( $expected, $containmentSet->contains( $contains ) );
	}

	public function addTitlesFromUserOptionProvider() :array {
		return [
			[
				'foo',
				'bar',
				false
			],
			[
				[ 'foo', 'bar' ],
				'foo',
				false
			],
			[
				"foo\nbar",
				'bar',
				true
			],
			[
				'{"foo":"bar"}',
				'bar',
				false
			]

		];
	}

	private function getDefaultUserMock() {
		return $this->getMockBuilder( User::class )
			->disableOriginalConstructor()
			->getMock();
	}

}
