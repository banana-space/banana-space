<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;
use Elastica\Index;
use Status;

class ShardAllocationValidator extends Validator {
	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var array
	 */
	private $indexAllocation;

	/**
	 * @param Index $index
	 * @param array $indexAllocation
	 * @param Printer|null $out
	 */
	public function __construct( Index $index, array $indexAllocation, Printer $out = null ) {
		parent::__construct( $out );

		$this->index = $index;
		$this->indexAllocation = $indexAllocation;
	}

	/**
	 * @return Status
	 */
	public function validate() {
		$this->outputIndented( "\tValidating shard allocation settings..." );

		$actual = $this->fetchActualAllocation();
		$changed = false;
		foreach ( [ 'include', 'exclude', 'require' ] as $type ) {
			$desired = $this->indexAllocation[$type];
			if ( $desired ) {
				$this->output( "\n" );
				$this->outputIndented( "\t\tUpdating '$type' allocations..." );
				$this->set( $type, $desired );
				$this->output( "done" );
				$changed = true;
			}
			if ( isset( $actual[$type] ) ) {
				$undesired = array_filter( array_keys( $actual[$type] ),
					function ( $key ) use ( $actual, $type, $desired ) {
						return $actual[$type][$key] !== '' && !isset( $desired[$key] );
					}
				);

				if ( $undesired ) {
					$this->output( "\n" );
					$this->outputIndented( "\t\tClearing '$type' allocations..." );
					$this->set( $type, array_fill_keys( $undesired, '' ) );
					$this->output( "done" );
					$changed = true;
				}
			}
		}
		if ( $changed ) {
			$this->output( "\n" );
		} else {
			$this->output( "done\n" );
		}

		return Status::newGood();
	}

	/**
	 * @return array
	 */
	private function fetchActualAllocation() {
		$settings = $this->index->getSettings()->get();
		return $settings['routing']['allocation'] ?? [];
	}

	/**
	 * @param string $type
	 * @param array $allocation
	 */
	private function set( $type, $allocation ) {
		$this->index->getSettings()->set( [
			'routing' => [
				'allocation' => [
					$type => $allocation,
				]
			]
		] );
	}
}
