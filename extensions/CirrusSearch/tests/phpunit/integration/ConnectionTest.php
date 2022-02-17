<?php

namespace CirrusSearch;

/**
 * Make sure cirrus doens't break any hooks.
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
 * @covers \CirrusSearch\Connection
 */
class ConnectionTest extends CirrusIntegrationTestCase {
	public static function provideNamespacesInIndexType() {
		return [
			// Standard:
			[ [ NS_MAIN ], [ NS_MAIN => true ], [], 'content', 1 ],
			[ [ NS_MAIN ], [ NS_MAIN => true ], [], 'general', false ],

			// Commons:
			[ [ NS_MAIN ], [ NS_MAIN => true ], [ NS_FILE => 'file' ], 'file', 1 ],

			// Funky:
			[ [ NS_MAIN ], [ NS_MAIN => true ], [ NS_FILE => 'file', NS_FILE_TALK => 'file' ], 'file', 2 ],
			[ [ NS_MAIN ], [ NS_MAIN => true ], [ NS_FILE => 'file', NS_FILE_TALK => 'file' ], 'conent', false ],
			[ [ NS_MAIN, NS_FILE ], [ NS_MAIN => true ], [], 'content', 2 ],
			[ [ NS_MAIN, NS_FILE ], [ NS_MAIN => true ], [ NS_FILE => 'file' ], 'file', 1 ],
			[ [ NS_MAIN, NS_FILE ], [ NS_MAIN => true ], [ NS_FILE => 'file' ], 'content', 1 ],
			[ [ NS_MAIN, NS_FILE, NS_FILE_TALK ], [ NS_MAIN => true ], [ NS_FILE => 'file' ], 'content', 2 ],
			[ [ NS_MAIN, NS_FILE, NS_FILE_TALK ], [ NS_MAIN => true ], [], 'content', 3 ],
			[ [ NS_MAIN ], [ NS_MAIN => true, NS_FILE => true ], [ NS_FILE => 'file' ], 'content', 1 ],
			[ [ NS_MAIN ], [ NS_MAIN => true, NS_FILE => true ], [ NS_FILE => 'file' ], 'file', 1 ],
			[ [ NS_MAIN, NS_FILE ], [ NS_MAIN => true, NS_FILE => true ], [ NS_FILE => 'file' ], 'content', 1 ],
			[ [ NS_MAIN, NS_FILE ], [ NS_MAIN => true, NS_FILE => true ], [ NS_FILE => 'file' ], 'file', 1 ],
		];
	}

	public function extractIndexSuffixProvider() {
		return [
			'basic index name' => [
				'content',
				'testwiki_content_first',
			],
			'timestamped index name' => [
				'general',
				'testwiki_general_12345678',
			],
			'indexBaseName with underscore' => [
				'content',
				'test_thiswiki_content_first'
			],
			'handles user defined suffixes' => [
				'file',
				'zomgwiki_file_654321',
			],
		];
	}

	/**
	 * @dataProvider extractIndexSuffixProvider
	 */
	public function testExtractIndexSuffixFromIndexName( $expected, $name ) {
		$config = new HashSearchConfig( [
			'CirrusSearchNamespaceMappings' => [
				NS_FILE => 'file',
			],
			// Needed for constructor to not blow up
			'CirrusSearchServers' => [ 'localhost' ],
		] );
		$conn = new Connection( $config );
		$this->assertEquals( $expected, $conn->extractIndexSuffix( $name ) );
	}

	public function testExtractIndexSuffixThrowsExceptionOnUnknown() {
		$config = new HashSearchConfig( [
			'CirrusSearchNamespaceMappings' => [],
			// Needed for constructor to not blow up
			'CirrusSearchServers' => [ 'localhost' ],
		] );
		$conn = new Connection( $config );
		$this->expectException( \Exception::class );
		$conn->extractIndexSuffix( 'testwiki_file_first' );
	}

	public function testGetAllIndexTypes() {
		$con = new Connection( new HashSearchConfig( [
			'CirrusSearchServers' => [ 'localhost' ],
			'CirrusSearchNamespaceMappings' => []
		] ) );
		$this->assertArrayEquals( [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE ],
			$con->getAllIndexTypes() );
		$this->assertArrayEquals( [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE ],
			$con->getAllIndexTypes( Connection::PAGE_TYPE_NAME ) );
		$this->assertArrayEquals( [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE, Connection::ARCHIVE_INDEX_TYPE ],
			$con->getAllIndexTypes( null ) );
		$this->assertArrayEquals( [ Connection::ARCHIVE_INDEX_TYPE ],
			$con->getAllIndexTypes( Connection::ARCHIVE_TYPE_NAME ) );

		$con = new Connection( new HashSearchConfig( [
			'CirrusSearchServers' => [ 'localhost' ],
			'CirrusSearchNamespaceMappings' => [ NS_FILE => 'file' ]
		] ) );

		$this->assertArrayEquals( [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE, 'file' ],
			$con->getAllIndexTypes() );
		$this->assertArrayEquals( [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE, 'file' ],
			$con->getAllIndexTypes( Connection::PAGE_TYPE_NAME ) );
		$this->assertArrayEquals(
			[
				Connection::CONTENT_INDEX_TYPE,
				Connection::GENERAL_INDEX_TYPE,
				Connection::ARCHIVE_INDEX_TYPE,
				'file'
			],
			$con->getAllIndexTypes( null )
		);
		$this->assertArrayEquals( [ Connection::ARCHIVE_INDEX_TYPE ],
			$con->getAllIndexTypes( Connection::ARCHIVE_TYPE_NAME ) );
	}

	public function providePoolCaching() {
		return [
			'constant returns same' => [
				'config' => [
					'CirrusSearchServers' => [ 'localhost:9092' ],
				],
				'update' => [],
			],
			'separate clusters' => [
				'config' => [
					'CirrusSearchDefaultCluster' => 'a',
					'CirrusSearchReplicaGroup' => 'default',
					'CirrusSearchClusters' => [
						'a' => [ 'localhost:9092', 'replica' => 'a' ],
						'b' => [ 'localhost:9192', 'replica' => 'b' ],
					],
				],
				'update' => [
					'CirrusSearchDefaultCluster' => 'b',
				],
			],
			'separate replica groups' => [
				'config' => [
					'CirrusSearchDefaultCluster' => 'ut',
					'CirrusSearchReplicaGroup' => 'a',
					'CirrusSearchClusters' => [
						'a' => [ 'localhost:9092', 'replica' => 'ut', 'group' => 'a' ],
						'b' => [ 'localhost:9192', 'replica' => 'ut', 'group' => 'b' ],
					],
				],
				'update' => [
					'CirrusSearchReplicaGroup' => 'b',
				],
			],
		];
	}

	/**
	 * @dataProvider providePoolCaching
	 */
	public function testPoolCaching( array $config, array $update ) {
		$conn = Connection::getPool( new HashSearchConfig( $config ) );
		$conn2 = Connection::getPool( new HashSearchConfig( $config ) );
		$this->assertEquals( $conn, $conn2 );

		if ( $update ) {
			$conn3 = Connection::getPool( new HashSearchConfig( $update + $config ) );
			$this->assertNotEquals( $conn, $conn3 );
		}
	}
}
