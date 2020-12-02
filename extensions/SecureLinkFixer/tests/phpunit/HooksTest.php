<?php
/**
 * Copyright (C) 2018 Kunal Mehta <legoktm@member.fsf.org>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace MediaWiki\SecureLinkFixer\Test;

use MediaWiki\SecureLinkFixer\Hooks;
use MediaWikiTestCase;

/**
 * @covers \MediaWiki\SecureLinkFixer\Hooks
 */
class HooksTest extends MediaWikiTestCase {

	/**
	 * @dataProvider provideOnLinkerMakeExternalLink
	 */
	public function testOnLinkerMakeExternalLink( $input, $expected ) {
		Hooks::onLinkerMakeExternalLink( $input );
		$this->assertSame( $expected, $input );
	}

	public function provideOnLinkerMakeExternalLink() {
		return [
			[ 'http://test.localhost/', 'http://test.localhost/' ],
			[ 'http://en.wikipedia.org/wiki/Main_Page', 'https://en.wikipedia.org/wiki/Main_Page' ],
			[ '//en.wikipedia.org/wiki/Main_Page', 'https://en.wikipedia.org/wiki/Main_Page' ],
			[ 'http://foo.dev/foo/', 'https://foo.dev/foo/' ],
			[ 'ftp://en.wikipedia.org/', 'ftp://en.wikipedia.org/' ],
			[ 'https://whatever.localhost/', 'https://whatever.localhost/' ],
			[ 'definitely invalid', 'definitely invalid' ],
		];
	}
}
