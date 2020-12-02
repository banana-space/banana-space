<?php

class SearchNearMatchResultSetTest extends PHPUnit\Framework\TestCase {
	/**
	 * @covers SearchNearMatchResultSet::__construct
	 * @covers SearchNearMatchResultSet::numRows
	 */
	public function testNumRows() {
		$resultSet = new SearchNearMatchResultSet( null );
		$this->assertSame( 0, $resultSet->numRows() );

		$resultSet = new SearchNearMatchResultSet( Title::newMainPage() );
		$this->assertSame( 1, $resultSet->numRows() );
	}
}
