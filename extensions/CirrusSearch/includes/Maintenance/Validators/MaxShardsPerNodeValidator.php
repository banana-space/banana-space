<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;
use Elastica\Index;
use Status;

class MaxShardsPerNodeValidator extends Validator {
	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var string
	 */
	private $indexType;

	/**
	 * @var int
	 */
	private $maxShardsPerNode;

	/**
	 * @param Index $index
	 * @param string $indexType
	 * @param int $maxShardsPerNode
	 * @param Printer|null $out
	 */
	public function __construct( Index $index, $indexType, $maxShardsPerNode, Printer $out = null ) {
		parent::__construct( $out );

		$this->index = $index;
		$this->indexType = $indexType;
		$this->maxShardsPerNode = $maxShardsPerNode;
	}

	/**
	 * @return Status
	 */
	public function validate() {
		$this->outputIndented( "\tValidating max shards per node..." );
		$settings = $this->index->getSettings()->get();
		// Elasticsearch uses negative numbers or an unset value to represent unlimited. We accept 'unlimited'
		// but it is resolved when reading configuration and not here.
		$actualMaxShardsPerNode = $settings['routing']['allocation']['total_shards_per_node'] ?? -1;
		$expectedMaxShardsPerNode = $this->maxShardsPerNode;
		if ( $actualMaxShardsPerNode == $expectedMaxShardsPerNode ) {
			$this->output( "ok\n" );
		} else {
			$this->output( "is $actualMaxShardsPerNode but should be $expectedMaxShardsPerNode..." );
			$this->index->getSettings()->set( [
				'routing.allocation.total_shards_per_node' => $expectedMaxShardsPerNode
			] );
			$this->output( "corrected\n" );
		}

		return Status::newGood();
	}
}
