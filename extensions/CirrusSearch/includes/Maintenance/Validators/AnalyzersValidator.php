<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;
use Elastica\Index;
use RawMessage;
use Status;

class AnalyzersValidator extends Validator {
	/**
	 * @var Index
	 */
	private $index;

	/**
	 * @var array
	 */
	private $analysisConfig;

	/**
	 * @param Index $index
	 * @param array $analysisConfig
	 * @param Printer|null $out
	 */
	public function __construct( Index $index, array $analysisConfig, Printer $out = null ) {
		parent::__construct( $out );

		$this->index = $index;
		$this->analysisConfig = $analysisConfig;
	}

	/**
	 * @return Status
	 */
	public function validate() {
		$this->outputIndented( "Validating analyzers..." );
		$settings = $this->index->getSettings()->get();
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		if ( $this->checkConfig( $settings[ 'analysis' ], $this->analysisConfig ) ) {
			$this->output( "ok\n" );
		} else {
			$this->output( "cannot correct\n" );
			return Status::newFatal( new RawMessage(
				"This script encountered an index difference that requires that the index be\n" .
				"copied, indexed to, and then the old index removed. Re-run this script with the\n" .
				"--reindexAndRemoveOk --indexIdentifier=now parameters to do this." ) );
		}

		return Status::newGood();
	}
}
