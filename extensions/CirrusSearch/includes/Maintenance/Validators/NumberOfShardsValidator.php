<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;
use Elastica\Index;
use RawMessage;
use Status;

class NumberOfShardsValidator extends Validator {
	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var int
	 */
	protected $shardCount;

	/**
	 * @param Index $index
	 * @param int $shardCount
	 * @param Printer|null $out
	 */
	public function __construct( Index $index, $shardCount, Printer $out = null ) {
		parent::__construct( $out );

		$this->index = $index;
		$this->shardCount = $shardCount;
	}

	/**
	 * @return Status
	 */
	public function validate() {
		$this->outputIndented( "\tValidating number of shards..." );
		$settings = $this->index->getSettings()->get();
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$actualShardCount = $settings['number_of_shards'];
		if ( $actualShardCount == $this->shardCount ) {
			$this->output( "ok\n" );
		} else {
			$this->output( "is $actualShardCount but should be " . $this->shardCount . "...cannot correct!\n" );
			return Status::newFatal( new RawMessage(
				"Number of shards is incorrect and cannot be changed without a rebuild. You can solve this\n" .
				"problem by running this program again with either --startOver or --reindexAndRemoveOk.  Make\n" .
				"sure you understand the consequences of either choice..  This script will now continue to\n" .
				"validate everything else." ) );
		}

		return Status::newGood();
	}
}
