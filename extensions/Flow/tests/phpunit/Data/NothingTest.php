<?php

namespace Flow\Tests\Data;

use Flow\Data\Utils\SortArrayByKeys;
use Flow\Tests\FlowTestCase;

/**
 * @group Flow
 */
class FlowNothingTest extends FlowTestCase {

	public function sortArrayByKeysProvider() {
		return [

			[
				'Basic one key sort',
				// keys to sort by
				[ 'id' ],
				// array to sort
				[
					[ 'id' => 5 ],
					[ 'id' => 7 ],
					[ 'id' => 6 ],
				],
				// expected result
				[
					[ 'id' => 5 ],
					[ 'id' => 6 ],
					[ 'id' => 7 ],
				],
			],

			[
				'Multi-key sort',
				// keys to sort by
				[ 'id', 'qq' ],
				// array to sort
				[
					[ 'id' => 5, 'qq' => 4 ],
					[ 'id' => 5, 'qq' => 2 ],
					[ 'id' => 7, 'qq' => 1 ],
					[ 'id' => 6, 'qq' => 3 ],
					[ 'qq' => 9, 'id' => 4 ],
				],
				// expected result
				[
					[ 'qq' => 9, 'id' => 4 ],
					[ 'id' => 5, 'qq' => 2 ],
					[ 'id' => 5, 'qq' => 4 ],
					[ 'id' => 6, 'qq' => 3 ],
					[ 'id' => 7, 'qq' => 1 ],
				],
			],

		];
	}

	/**
	 * @covers \Flow\Data\Utils\SortArrayByKeys
	 * @dataProvider sortArrayByKeysProvider
	 */
	public function testSortArrayByKeys( $message, array $keys, array $array, array $sorted, $strict = true ) {
		usort( $array, new SortArrayByKeys( $keys, $strict ) );
		$this->assertEquals( $sorted, $array );
	}
}
