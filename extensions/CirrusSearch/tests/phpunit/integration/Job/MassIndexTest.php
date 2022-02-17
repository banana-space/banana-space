<?php

namespace CirrusSearch\Job;

use CirrusSearch\CirrusIntegrationTestCase;

/**
 * Test for MassIndex job.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @group CirrusSearch
 * @covers \CirrusSearch\Job\MassIndex
 */
class MassIndexTest extends CirrusIntegrationTestCase {
	/**
	 * @dataProvider workItemCountTestCases
	 */
	public function testWorkItemCount( $pageDBKeys, $expected ) {
		$job = new MassIndex( [
			'pageDBKeys' => $pageDBKeys,
		] );
		$this->assertEquals( $expected, $job->workItemCount() );
	}

	public static function workItemCountTestCases() {
		return [
			[ [], 0 ],
			[ [ 'Foo' ], 1 ],
			[ [ 'Cat', 'Cow', 'Puppy' ], 3 ],
		];
	}
}
