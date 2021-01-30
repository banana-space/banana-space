<?php

namespace Flow;

use Flow\Block\AbstractBlock;
use Flow\Block\BoardHistoryBlock;
use Flow\Block\HeaderBlock;
use Flow\Block\TopicBlock;
use Flow\Block\TopicListBlock;
use Flow\Block\TopicSummaryBlock;
use Flow\Data\ManagerGroup;
use Flow\Exception\DataModelException;
use Flow\Exception\InvalidDataException;
use Flow\Model\Workflow;
use Flow\Repository\RootPostLoader;

class BlockFactory {
	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var RootPostLoader
	 */
	protected $rootPostLoader;

	public function __construct(
		ManagerGroup $storage,
		RootPostLoader $rootPostLoader
	) {
		$this->storage = $storage;
		$this->rootPostLoader = $rootPostLoader;
	}

	/**
	 * @param Workflow $workflow
	 * @return AbstractBlock[]
	 * @throws DataModelException When the workflow type is unrecognized
	 * @throws InvalidDataException When multiple blocks share the same name
	 */
	public function createBlocks( Workflow $workflow ) {
		switch ( $workflow->getType() ) {
			case 'discussion':
				$blocks = [
					new HeaderBlock( $workflow, $this->storage ),
					new TopicListBlock( $workflow, $this->storage ),
					new BoardHistoryBlock( $workflow, $this->storage ),
				];
				break;

			case 'topic':
				$blocks = [
					new TopicBlock( $workflow, $this->storage, $this->rootPostLoader ),
					new TopicSummaryBlock( $workflow, $this->storage ),
				];
				break;

			default:
				throw new DataModelException( 'Not Implemented', 'process-data' );
		}

		$return = [];
		/** @var AbstractBlock[] $blocks */
		foreach ( $blocks as $block ) {
			if ( isset( $return[$block->getName()] ) ) {
				throw new InvalidDataException( 'Multiple blocks with same name is not yet supported', 'fail-load-data' );
			}
			$return[$block->getName()] = $block;
		}

		return $return;
	}
}
