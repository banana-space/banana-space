<?php

/**
 * @author Addshore
 * @covers TitleArrayFromResult
 */
class TitleArrayFromResultTest extends MediaWikiUnitTestCase {

	private function getMockResultWrapper( $row = null, $numRows = 1 ) {
		$resultWrapper = $this->getMockBuilder( Wikimedia\Rdbms\IResultWrapper::class )
			->disableOriginalConstructor();

		$resultWrapper = $resultWrapper->getMock();
		$resultWrapper->expects( $this->atLeastOnce() )
			->method( 'current' )
			->will( $this->returnValue( $row ) );
		$resultWrapper->expects( $this->any() )
			->method( 'numRows' )
			->will( $this->returnValue( $numRows ) );

		return $resultWrapper;
	}

	private function getRowWithTitle( $namespace = 3, $title = 'foo' ) {
		return (object)[
			'page_namespace' => $namespace,
			'page_title' => $title,
		];
	}

	/**
	 * @covers TitleArrayFromResult::__construct
	 */
	public function testConstructionWithFalseRow() {
		$row = false;
		$resultWrapper = $this->getMockResultWrapper( $row );

		$object = new TitleArrayFromResult( $resultWrapper );

		$this->assertEquals( $resultWrapper, $object->res );
		$this->assertSame( 0, $object->key );
		$this->assertEquals( $row, $object->current );
	}

	/**
	 * @covers TitleArrayFromResult::__construct
	 */
	public function testConstructionWithRow() {
		$namespace = 0;
		$title = 'foo';
		$row = $this->getRowWithTitle( $namespace, $title );
		$resultWrapper = $this->getMockResultWrapper( $row );

		$object = new TitleArrayFromResult( $resultWrapper );

		$this->assertEquals( $resultWrapper, $object->res );
		$this->assertSame( 0, $object->key );
		$this->assertInstanceOf( Title::class, $object->current );
		$this->assertEquals( $namespace, $object->current->mNamespace );
		$this->assertEquals( $title, $object->current->mTextform );
	}

	public static function provideNumberOfRows() {
		return [
			[ 0 ],
			[ 1 ],
			[ 122 ],
		];
	}

	/**
	 * @dataProvider provideNumberOfRows
	 * @covers TitleArrayFromResult::count
	 */
	public function testCountWithVaryingValues( $numRows ) {
		$object = new TitleArrayFromResult( $this->getMockResultWrapper(
			$this->getRowWithTitle(),
			$numRows
		) );
		$this->assertEquals( $numRows, $object->count() );
	}

	/**
	 * @covers TitleArrayFromResult::current
	 */
	public function testCurrentAfterConstruction() {
		$namespace = 0;
		$title = 'foo';
		$row = $this->getRowWithTitle( $namespace, $title );
		$object = new TitleArrayFromResult( $this->getMockResultWrapper( $row ) );
		$this->assertInstanceOf( Title::class, $object->current() );
		$this->assertEquals( $namespace, $object->current->mNamespace );
		$this->assertEquals( $title, $object->current->mTextform );
	}

	public function provideTestValid() {
		return [
			[ $this->getRowWithTitle(), true ],
			[ false, false ],
		];
	}

	/**
	 * @dataProvider provideTestValid
	 * @covers TitleArrayFromResult::valid
	 */
	public function testValid( $input, $expected ) {
		$object = new TitleArrayFromResult( $this->getMockResultWrapper( $input ) );
		$this->assertEquals( $expected, $object->valid() );
	}

	// @todo unit test for key()
	// @todo unit test for next()
	// @todo unit test for rewind()
}
