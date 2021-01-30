<?php

namespace Flow\Tests;

use Flow\BlockFactory;

/**
 * @covers \Flow\BlockFactory
 *
 * @group Flow
 */
class BlockFactoryTest extends FlowTestCase {

	public function provideDataCreateBlocks() {
		return [
			[
				'discussion',
				[
					\Flow\Block\HeaderBlock::class,
					\Flow\Block\TopicListBlock::class,
					\Flow\Block\BoardHistoryBlock::class,
				]
			],
			[
				'topic',
				[
					\Flow\Block\TopicBlock::class,
					\Flow\Block\TopicSummaryBlock::class,
				]
			],
		];
	}

	/**
	 * @covers \Flow\Block\AbstractBlock::__construct
	 * @covers \Flow\Block\BoardHistoryBlock::__construct
	 * @covers \Flow\Block\HeaderBlock::__construct
	 * @covers \Flow\Block\TopicBlock::__construct
	 * @covers \Flow\Block\TopicListBlock::__construct
	 * @covers \Flow\Block\TopicSummaryBlock::__construct
	 * @dataProvider provideDataCreateBlocks
	 */
	public function testCreateBlocks( $workflowType, array $expectedResults ) {
		$factory = $this->createBlockFactory();
		$workflow = $this->mockWorkflow( $workflowType );

		$blocks = $factory->createBlocks( $workflow );
		$this->assertEquals( count( $blocks ), count( $expectedResults ) );

		$results = [];
		foreach ( $blocks as $obj ) {
			$results[] = get_class( $obj );
		}
		$this->assertEquals( $results, $expectedResults );
	}

	public function testCreateBlocksWithInvalidInputException() {
		$factory = $this->createBlockFactory();
		$workflow = $this->mockWorkflow( 'a-bad-database-flow-workflow' );
		$this->expectException( \Flow\Exception\DataModelException::class );
		$factory->createBlocks( $workflow );
	}

	protected function createBlockFactory() {
		$storage = $this->getMockBuilder( \Flow\Data\ManagerGroup::class )
			->disableOriginalConstructor()
			->getMock();

		$rootPostLoader = $this->getMockBuilder( \Flow\Repository\RootPostLoader::class )
			->disableOriginalConstructor()
			->getMock();

		return new BlockFactory( $storage, $rootPostLoader );
	}

	protected function mockWorkflow( $type ) {
		$workflow = $this->getMockBuilder( \Flow\Model\Workflow::class )
			->disableOriginalConstructor()
			->getMock();
		$workflow->expects( $this->any() )
			->method( 'getType' )
			->will( $this->returnValue( $type ) );

		return $workflow;
	}
}
