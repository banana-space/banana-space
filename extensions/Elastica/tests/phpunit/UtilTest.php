<?php

use Elastica\Exception\InvalidException;

/**
 * Test Util functions.
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
 */

/**
 * @covers MWElasticUtils
 */
class UtilTest extends MediaWikiTestCase {
	public function testBackoffDelay() {
		for ( $i = 0; $i < 100; $i++ ) {
			$this->assertLessThanOrEqual( 16, MWElasticUtils::backoffDelay( 1 ) );
			$this->assertLessThanOrEqual( 256, MWElasticUtils::backoffDelay( 5 ) );
		}
	}

	public function testWithRetry() {
		$calls = 0;
		$func = function () use ( &$calls ) {
			$calls++;
			if ( $calls <= 5 ) {
				throw new InvalidException();
			}
		};
		$errorCallbackCalls = 0;
		MWElasticUtils::withRetry( 5, $func,
			function ( $e, $errCount ) use ( &$errorCallbackCalls ) {
				$errorCallbackCalls++;
				$this->assertEquals( "Elastica\Exception\InvalidException", get_class( $e ) );
			}
		);
		$this->assertEquals( 6, $calls );
		$this->assertEquals( 5, $errorCallbackCalls );
	}
}
