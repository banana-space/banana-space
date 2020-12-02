<?php

namespace Cite\Tests\Unit;

use Cite\ErrorReporter;
use Cite\ReferenceStack;
use LogicException;
use Parser;
use StripState;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\ReferenceStack
 *
 * @license GPL-2.0-or-later
 */
class ReferenceStackTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::pushInvalidRef
	 */
	public function testPushInvalidRef() {
		$stack = $this->newStack();

		$stack->pushInvalidRef();

		$this->assertSame( [ false ], $stack->refCallStack );
	}

	/**
	 * @covers ::pushRef
	 * @dataProvider providePushRef
	 */
	public function testPushRefs(
		array $refs,
		array $expectedOutputs,
		array $finalRefs,
		array $finalCallStack
	) {
		$mockStripState = $this->createMock( StripState::class );
		$mockStripState->method( 'unstripBoth' )->willReturnArgument( 0 );
		/** @var StripState $mockStripState */
		$stack = $this->newStack();

		for ( $i = 0; $i < count( $refs ); $i++ ) {
			$result = $stack->pushRef(
				$this->createMock( Parser::class ),
				$mockStripState,
				...$refs[$i]
			);

			$this->assertTrue( array_key_exists( $i, $expectedOutputs ),
				'Bad test, not enough expected outputs in fixture.' );
			$this->assertSame( $expectedOutputs[$i], $result );
		}

		$this->assertSame( $finalRefs, $stack->refs );
		$this->assertSame( $finalCallStack, $stack->refCallStack );
	}

	public function providePushRef() {
		return [
			'Anonymous ref in default group' => [
				[
					[ 'text', [], '', null, null, null, 'rtl' ]
				],
				[
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => null,
						'text' => 'text',
						'number' => 1,
					]
				],
				[
					'' => [
						[
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, '', null, null, 'text', [] ],
				]
			],
			'Anonymous ref in named group' => [
				[
					[ 'text', [], 'foo', null, null, null, 'rtl' ]
				],
				[
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => null,
						'text' => 'text',
						'number' => 1,
					]
				],
				[
					'foo' => [
						[
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				]
			],
			'Ref with text' => [
				[
					[ 'text', [], 'foo', null, null, null, 'rtl' ]
				],
				[
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => null,
						'text' => 'text',
						'number' => 1,
					]
				],
				[
					'foo' => [
						[
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				]
			],
			'Named ref with text' => [
				[
					[ 'text', [], 'foo', 'name', null, null, 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'name',
						'text' => 'text',
						'number' => 1,
					],
				],
				[
					'foo' => [
						'name' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'name',
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'name', null, 'text', [] ],
				]
			],
			'Follow after base' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', 'b', null, 'a', 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					null
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a text-b',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
				]
			],
			'Follow with no base' => [
				[
					[ 'text', [], 'foo', null, null, 'a', 'rtl' ]
				],
				[
					null
				],
				[
					'foo' => [
						[
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text',
							'follow' => 'a',
						]
					]
				],
				[
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				]
			],
			'Follow pointing to later ref' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', null, null, 'c', 'rtl' ],
					[ 'text-c', [], 'foo', 'c', null, null, 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					null,
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 3,
						'name' => 'c',
						'text' => 'text-c',
						'number' => 2,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
						],
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 2,
							'name' => null,
							'text' => 'text-b',
							'follow' => 'c',
						],
						'c' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 3,
							'name' => 'c',
							'text' => 'text-c',
							'number' => 2,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
					[ 'new', 2, 'foo', null, null, 'text-b', [] ],
					[ 'new', 3, 'foo', 'c', null, 'text-c', [] ],
				]
			],
			'Repeated ref, text in first tag' => [
				[
					[ 'text', [], 'foo', 'a', null, null, 'rtl' ],
					[ null, [], 'foo', 'a', null, null, 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text',
						'number' => 1,
					],
					[
						'count' => 1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text',
						'number' => 1,
					],
				],
				[
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text', [] ],
					[ 'increment', 1, 'foo', 'a', null, null, [] ],
				]
			],
			'Repeated ref, text in second tag' => [
				[
					[ null, [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text', [], 'foo', 'a', null, null, 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => null,
						'number' => 1,
					],
					[
						'count' => 1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text',
						'number' => 1,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, null, [] ],
					[ 'assign', 1, 'foo', 'a', null, 'text', [] ],
				]
			],
			'Repeated ref, mismatched text' => [
				[
					[ 'text-1', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-2', [], 'foo', 'a', null, null, 'rtl' ]
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-1',
						'number' => 1,
					],
					[
						'count' => 1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-1 cite_error_references_duplicate_key',
						'number' => 1,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-1 cite_error_references_duplicate_key',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-1', [] ],
					[ 'increment', 1, 'foo', 'a', null, 'text-2', [] ],
				]
			],
			'Named extends with no parent' => [
				[
					[ 'text-a', [], 'foo', 'a', 'b', null, 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
						'extends' => 'b',
						'extendsIndex' => 1,
					],
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
							'extends' => 'b',
							'extendsIndex' => 1,
						],
						'b' => [
							'number' => 1,
							'__placeholder__' => true,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', 'b', 'text-a', [] ],
				]
			],
			'Named extends before parent' => [
				[
					[ 'text-a', [], 'foo', 'a', 'b', null, 'rtl' ],
					[ 'text-b', [], 'foo', 'b', null, null, 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
						'extends' => 'b',
						'extendsIndex' => 1,
					],
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 2,
						'name' => 'b',
						'text' => 'text-b',
						'number' => 1,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
							'extends' => 'b',
							'extendsIndex' => 1,
						],
						'b' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 2,
							'name' => 'b',
							'text' => 'text-b',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', 'b', 'text-a', [] ],
					[ 'new-from-placeholder', 2, 'foo', 'b', null, 'text-b', [] ],
				]
			],
			'Named extends after parent' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', 'b', 'a', null, 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 2,
						'name' => 'b',
						'text' => 'text-b',
						'number' => 1,
						'extends' => 'a',
						'extendsIndex' => 1,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
						],
						'b' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 2,
							'name' => 'b',
							'text' => 'text-b',
							'number' => 1,
							'extends' => 'a',
							'extendsIndex' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
					[ 'new', 2, 'foo', 'b', 'a', 'text-b', [] ],
				]
			],
			'Anonymous extends with no parent' => [
				[
					[ 'text-a', [], 'foo', null, 'b', null, 'rtl' ],
				],
				[
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => null,
						'text' => 'text-a',
						'number' => 1,
						'extends' => 'b',
						'extendsIndex' => 1,
					]
				],
				[
					'foo' => [
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text-a',
							'number' => 1,
							'extends' => 'b',
							'extendsIndex' => 1,
						],
						'b' => [
							'number' => 1,
							'__placeholder__' => true,
						]
					],
				],
				[
					[ 'new', 1, 'foo', null, 'b', 'text-a', [] ],
				]
			],
			'Anonymous extends before parent' => [
				[
					[ 'text-a', [], 'foo', null, 'b', null, 'rtl' ],
					[ 'text-b', [], 'foo', 'b', null, null, 'rtl' ],
				],
				[
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 1,
						'name' => null,
						'text' => 'text-a',
						'number' => 1,
						'extends' => 'b',
						'extendsIndex' => 1,
					],
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 2,
						'name' => 'b',
						'text' => 'text-b',
						'number' => 1,
					]
				],
				[
					'foo' => [
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 1,
							'name' => null,
							'text' => 'text-a',
							'number' => 1,
							'extends' => 'b',
							'extendsIndex' => 1,
						],
						'b' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 2,
							'name' => 'b',
							'text' => 'text-b',
							'number' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', null, 'b', 'text-a', [] ],
					[ 'new-from-placeholder', 2, 'foo', 'b', null, 'text-b', [] ],
				]
			],
			'Anonymous extends after parent' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', null, 'a', null, 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 2,
						'name' => null,
						'text' => 'text-b',
						'number' => 1,
						'extends' => 'a',
						'extendsIndex' => 1,
					]
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
						],
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 2,
							'name' => null,
							'text' => 'text-b',
							'number' => 1,
							'extends' => 'a',
							'extendsIndex' => 1,
						]
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
					[ 'new', 2, 'foo', null, 'a', 'text-b', [] ],
				]
			],
			'Normal after extends' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', null, 'a', null, 'rtl' ],
					[ 'text-c', [], 'foo', 'c', null, null, 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					[
						'count' => -1,
						'dir' => 'rtl',
						'key' => 2,
						'name' => null,
						'text' => 'text-b',
						'number' => 1,
						'extends' => 'a',
						'extendsIndex' => 1,
					],
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 3,
						'name' => 'c',
						'text' => 'text-c',
						'number' => 2,
					],
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
						],
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 2,
							'name' => null,
							'text' => 'text-b',
							'number' => 1,
							'extends' => 'a',
							'extendsIndex' => 1,
						],
						'c' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 3,
							'name' => 'c',
							'text' => 'text-c',
							'number' => 2,
						],
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
					[ 'new', 2, 'foo', null, 'a', 'text-b', [] ],
					[ 'new', 3, 'foo', 'c', null, 'text-c', [] ],
				]
			],
			'Two incomplete follows' => [
				[
					[ 'text-a', [], 'foo', 'a', null, null, 'rtl' ],
					[ 'text-b', [], 'foo', null, null, 'd', 'rtl' ],
					[ 'text-c', [], 'foo', null, null, 'd', 'rtl' ],
				],
				[
					[
						'count' => 0,
						'dir' => 'rtl',
						'key' => 1,
						'name' => 'a',
						'text' => 'text-a',
						'number' => 1,
					],
					null,
					null
				],
				[
					'foo' => [
						'a' => [
							'count' => 0,
							'dir' => 'rtl',
							'key' => 1,
							'name' => 'a',
							'text' => 'text-a',
							'number' => 1,
						],
						0 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 2,
							'name' => null,
							'text' => 'text-b',
							'follow' => 'd',
						],
						1 => [
							'count' => -1,
							'dir' => 'rtl',
							'key' => 3,
							'name' => null,
							'text' => 'text-c',
							'follow' => 'd',
						],
					]
				],
				[
					[ 'new', 1, 'foo', 'a', null, 'text-a', [] ],
					[ 'new', 2, 'foo', null, null, 'text-b', [] ],
					[ 'new', 3, 'foo', null, null, 'text-c', [] ],
				]
			],
		];
	}

	/**
	 * @covers ::rollbackRefs
	 * @covers ::rollbackRef
	 * @dataProvider provideRollbackRefs
	 */
	public function testRollbackRefs(
		array $initialCallStack,
		array $initialRefs,
		int $rollbackCount,
		$expectedResult,
		array $expectedRefs = []
	) {
		$stack = $this->newStack();
		$stack->refCallStack = $initialCallStack;
		$stack->refs = $initialRefs;

		if ( is_string( $expectedResult ) ) {
			$this->expectException( LogicException::class );
			$this->expectExceptionMessage( $expectedResult );
		}
		$redoStack = $stack->rollbackRefs( $rollbackCount );
		$this->assertSame( $expectedResult, $redoStack );
		$this->assertSame( $expectedRefs, $stack->refs );
	}

	public function provideRollbackRefs() {
		return [
			'Empty stack' => [
				'initialCallStack' => [],
				'initialRefs' => [],
				'rollbackCount' => 0,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Attempt to overflow stack bounds' => [
				'initialCallStack' => [],
				'initialRefs' => [],
				'rollbackCount' => 1,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Skip invalid refs' => [
				'initialCallStack' => [ false ],
				'initialRefs' => [],
				'rollbackCount' => 1,
				'expectedResult' => [],
				'expectedRefs' => [],
			],
			'Missing group' => [
				'initialCallStack' => [
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				],
				'initialRefs' => [],
				'rollbackCount' => 1,
				'expectedResult' => 'Cannot roll back ref with unknown group "foo".',
			],
			'Find anonymous ref by key' => [
				'initialCallStack' => [
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				],
				'initialRefs' => [ 'foo' => [
					[
						'key' => 1,
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ 'text', [] ],
				],
				'expectedRefs' => [],
			],
			'Missing anonymous ref' => [
				'initialCallStack' => [
					[ 'new', 1, 'foo', null, null, 'text', [] ],
				],
				'initialRefs' => [ 'foo' => [
					[
						'key' => 2,
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => 'Cannot roll back unknown ref by key 1.',
			],
			'Assign text' => [
				'initialCallStack' => [
					[ 'assign', 1, 'foo', null, null, 'text-2', [] ],
				],
				'initialRefs' => [ 'foo' => [
					[
						'count' => 2,
						'key' => 1,
						'text' => 'text-1',
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ 'text-2', [] ],
				],
				'expectedRefs' => [ 'foo' => [
					[
						'count' => 1,
						'key' => 1,
						'text' => null,
					],
				] ],
			],
			'Increment' => [
				'initialCallStack' => [
					[ 'increment', 1, 'foo', null, null, null, [] ],
				],
				'initialRefs' => [ 'foo' => [
					[
						'count' => 2,
						'key' => 1,
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ null, [] ],
				],
				'expectedRefs' => [ 'foo' => [
					[
						'count' => 1,
						'key' => 1,
					],
				] ],
			],
			'Safely ignore placeholder' => [
				'initialCallStack' => [
					[ 'increment', 1, 'foo', null, null, null, [] ],
				],
				'initialRefs' => [ 'foo' => [
					[
						'placeholder' => true,
						'number' => 10,
					],
					[
						'count' => 2,
						'key' => 1,
					],
				] ],
				'rollbackCount' => 1,
				'expectedResult' => [
					[ null, [] ],
				],
				'expectedRefs' => [ 'foo' => [
					[
						'placeholder' => true,
						'number' => 10,
					],
					[
						'count' => 1,
						'key' => 1,
					],
				] ],
			],
		];
	}

	/**
	 * @covers ::rollbackRef
	 */
	public function testRollbackRefs_extends() {
		$stack = $this->newStack();

		$mockStripState = $this->createMock( StripState::class );
		$mockStripState->method( 'unstripBoth' )->willReturnArgument( 0 );
		/** @var StripState $mockStripState */
		$stack->pushRef(
			$this->createMock( Parser::class ),
			$mockStripState,
			'text', [],
			'foo', null, 'a', null, 'rtl'
		);
		$this->assertSame( 1, $stack->extendsCount['foo']['a'] );

		$stack->rollbackRefs( 1 );

		$this->assertSame( 0, $stack->extendsCount['foo']['a'] );
	}

	/**
	 * @covers ::popGroup
	 */
	public function testRemovals() {
		$stack = $this->newStack();
		$stack->refs = [ 'group1' => [], 'group2' => [] ];

		$this->assertSame( [], $stack->popGroup( 'group1' ) );
		$this->assertSame( [ 'group2' => [] ], $stack->refs );
	}

	/**
	 * @covers ::getGroups
	 */
	public function testGetGroups() {
		$stack = $this->newStack();
		$stack->refs = [ 'havenot' => [], 'have' => [ [ 'ref etc' ] ] ];

		$this->assertSame( [ 'have' ], $stack->getGroups() );
	}

	/**
	 * @covers ::hasGroup
	 */
	public function testHasGroup() {
		$stack = $this->newStack();
		$stack->refs = [ 'present' => [ [ 'ref etc' ] ], 'empty' => [] ];

		$this->assertFalse( $stack->hasGroup( 'absent' ) );
		$this->assertTrue( $stack->hasGroup( 'present' ) );
		$this->assertFalse( $stack->hasGroup( 'empty' ) );
	}

	/**
	 * @covers ::getGroupRefs
	 */
	public function testGetGroupRefs() {
		$stack = $this->newStack();
		$stack->refs = [ 'present' => [ [ 'ref etc' ] ], 'empty' => [] ];

		$this->assertSame( [], $stack->getGroupRefs( 'absent' ) );
		$this->assertSame( [ [ 'ref etc' ] ], $stack->getGroupRefs( 'present' ) );
		$this->assertSame( [], $stack->getGroupRefs( 'empty' ) );
	}

	/**
	 * @covers ::appendText
	 */
	public function testAppendText() {
		$stack = $this->newStack();

		$stack->appendText( 'group', 'name', 'set' );
		$this->assertSame( [ 'text' => 'set' ], $stack->refs['group']['name'] );

		$stack->appendText( 'group', 'name', ' and append' );
		$this->assertSame( [ 'text' => 'set and append' ], $stack->refs['group']['name'] );
	}

	/**
	 * @return ReferenceStack
	 */
	private function newStack() {
		$errorReporter = $this->createMock( ErrorReporter::class );
		$errorReporter->method( 'plain' )->willReturnArgument( 1 );
		/** @var ErrorReporter $errorReporter */
		return TestingAccessWrapper::newFromObject( new ReferenceStack( $errorReporter ) );
	}

}
