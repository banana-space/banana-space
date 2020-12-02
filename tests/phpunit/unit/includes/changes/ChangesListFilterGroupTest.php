<?php

/**
 * @covers ChangesListFilterGroup
 */
class ChangesListFilterGroupTest extends \MediaWikiUnitTestCase {
	public function testReservedCharacter() {
		$this->expectException( MWException::class );
		$this->expectExceptionMessage(
			"Group names may not contain '_'.  Use the naming convention: 'camelCase'" );
		new MockChangesListFilterGroup(
			[
				'type' => 'some_type',
				'name' => 'group_name',
				'priority' => 1,
				'filters' => [],
			]
		);
	}

	public function testAutoPriorities() {
		$group = new MockChangesListFilterGroup(
			[
				'type' => 'some_type',
				'name' => 'groupName',
				'isFullCoverage' => true,
				'priority' => 1,
				'filters' => [
					[ 'name' => 'hidefoo' ],
					[ 'name' => 'hidebar' ],
					[ 'name' => 'hidebaz' ],
				],
			]
		);

		$filters = $group->getFilters();
		$this->assertEquals(
			[
				-2,
				-3,
				-4,
			],
			array_map(
				function ( $f ) {
					return $f->getPriority();
				},
				array_values( $filters )
			)
		);
	}

	// Get without warnings
	public function testGetFilter() {
		$group = new MockChangesListFilterGroup(
			[
				'type' => 'some_type',
				'name' => 'groupName',
				'isFullCoverage' => true,
				'priority' => 1,
				'filters' => [
					[ 'name' => 'foo' ],
				],
			]
		);

		$this->assertEquals(
			'foo',
			$group->getFilter( 'foo' )->getName()
		);

		$this->assertNull(
			$group->getFilter( 'bar' )
		);
	}
}
