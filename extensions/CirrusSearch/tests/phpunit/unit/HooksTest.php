<?php

namespace CirrusSearch;

class HooksTest extends CirrusTestCase {
	/**
	 * @covers \CirrusSearch\Hooks::prepareTitlesForLinksUpdate()
	 */
	public function testPrepareTitlesForLinksUpdate() {
		$titles = [ \Title::makeTitle( NS_MAIN, 'Title1' ), \Title::makeTitle( NS_MAIN, 'Title2' ) ];
		$this->assertEqualsCanonicalizing(
			[ 'Title1', 'Title2' ],
			Hooks::prepareTitlesForLinksUpdate( $titles, 2 ),
			'All titles must be returned'
		);
		$this->assertCount( 1, Hooks::prepareTitlesForLinksUpdate( $titles, 1 ) );
		$titles = [ \Title::makeTitle( NS_MAIN, 'Title1' ), \Title::makeTitle( NS_MAIN, 'Title' . chr( 130 ) ) ];
		$this->assertEqualsCanonicalizing( [ 'Title1', 'Title' . chr( 130 ) ],
			Hooks::prepareTitlesForLinksUpdate( $titles, 2 ),
			'Bad UTF8 links are kept by default'
		);
		$this->assertEquals( [ 'Title1' ], Hooks::prepareTitlesForLinksUpdate( $titles, 2, true ),
			'Bad UTF8 links can be filtered' );
	}
}
