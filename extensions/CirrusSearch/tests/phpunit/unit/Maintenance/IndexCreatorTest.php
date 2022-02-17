<?php

namespace CirrusSearch\Tests\Maintenance;

use CirrusSearch\CirrusTestCase;
use CirrusSearch\Maintenance\IndexCreator;
use Elastica\Index;
use Elastica\Response;

/**
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
 *
 * @covers \CirrusSearch\Maintenance\IndexCreator
 */
class IndexCreatorTest extends CirrusTestCase {

	/**
	 * @dataProvider createIndexProvider
	 */
	public function testCreateIndex( $rebuild, $maxShardsPerNode, Response $response ) {
		$index = $this->getIndex( $response );

		$indexCreator = new IndexCreator( $index, [], [] );

		$status = $indexCreator->createIndex(
			$rebuild,
			$maxShardsPerNode,
			4, // shardCount
			'0-2', // replicaCount
			30, // refreshInterval
			[], // mergeSettings
			true, // searchAllFields
			[] // extra index settings
		);

		$this->assertInstanceOf( 'Status', $status );
	}

	public function createIndexProvider() {
		$successResponse = new Response( [] );
		$errorResponse = new Response( [ 'error' => 'index creation failed' ] );

		return [
			[ true, 'unlimited', $successResponse ],
			[ true, 2, $successResponse ],
			[ true, 2, $errorResponse ],
			[ false, 'unlimited', $successResponse ],
			[ false, 2, $successResponse ],
			[ false, 'unlimited', $errorResponse ]
		];
	}

	private function getIndex( $response ) {
		$index = $this->getMockBuilder( Index::class )
			->disableOriginalConstructor()
			->getMock();

		$index->expects( $this->any() )
			->method( 'create' )
			->will( $this->returnValue( $response ) );

		return $index;
	}
}
