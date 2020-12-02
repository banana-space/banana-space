<?php

namespace Wikimedia\Parsoid\Mocks;

use Liuggio\StatsdClient\Entity\StatsdDataInterface;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;

class MockMetrics implements StatsdDataFactoryInterface {

	/** @inheritDoc */
	public function timing( $key, $time ) {
	}

	/** @inheritDoc */
	public function gauge( $key, $value ) {
	}

	/** @inheritDoc */
	public function set( $key, $value ) {
		return [];
	}

	/** @inheritDoc */
	public function increment( $key ) {
		return [];
	}

	/** @inheritDoc */
	public function decrement( $key ) {
		return [];
	}

	/** @inheritDoc */
	public function updateCount( $key, $delta ) {
		return [];
	}

	/** @inheritDoc */
	public function produceStatsdData(
		$key, $value = 1, $metric = StatsdDataInterface::STATSD_METRIC_COUNT
	) {
		return $metric;
	}

}
