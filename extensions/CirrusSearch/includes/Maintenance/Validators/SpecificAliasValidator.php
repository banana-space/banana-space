<?php

namespace CirrusSearch\Maintenance\Validators;

use CirrusSearch\Maintenance\Printer;
use CirrusSearch\Maintenance\Reindexer;
use Elastica\Client;
use RawMessage;
use Status;

class SpecificAliasValidator extends IndexAliasValidator {
	/**
	 * @var Reindexer
	 */
	private $reindexer;

	/**
	 * @var array
	 */
	private $reindexParams;

	/**
	 * @var Validator[]
	 */
	private $reindexValidators;

	/**
	 * @var bool
	 */
	private $reindexAndRemoveOk;

	/**
	 * @var bool
	 */
	private $tooFewReplicas;

	/**
	 * @param Client $client
	 * @param string $aliasName
	 * @param string $specificIndexName
	 * @param bool $startOver
	 * @param Reindexer $reindexer
	 * @param array $reindexParams
	 * @param Validator[] $reindexValidators
	 * @param bool $reindexAndRemoveOk
	 * @param bool $tooFewReplicas
	 * @param Printer|null $out
	 */
	public function __construct(
		Client $client,
		$aliasName,
		$specificIndexName,
		$startOver,
		Reindexer $reindexer,
		array $reindexParams,
		array $reindexValidators,
		$reindexAndRemoveOk,
		$tooFewReplicas,
		Printer $out = null
	) {
		// @todo: this constructor takes too many arguments - refactor!

		parent::__construct( $client, $aliasName, $specificIndexName, $startOver, $out );

		$this->reindexer = $reindexer;
		$this->reindexParams = $reindexParams;
		$this->reindexValidators = $reindexValidators;
		$this->reindexAndRemoveOk = $reindexAndRemoveOk;
		$this->tooFewReplicas = $tooFewReplicas;
	}

	/**
	 * @param string[] $add
	 * @param string[] $remove
	 * @return Status
	 */
	protected function updateIndices( array $add, array $remove ) {
		if ( !$remove ) {
			return $this->updateFreeIndices( $add );
		}

		if ( !$this->reindexAndRemoveOk ) {
			$this->output( "cannot correct!\n" );
			return Status::newFatal( new RawMessage(
				"The alias is held by another index which means it might be actively serving\n" .
				"queries.  You can solve this problem by running this program again with\n" .
				"--reindexAndRemoveOk.  Make sure you understand the consequences of either\n" .
				"choice." ) );
		}

		try {
			$this->output( "is taken...\n" );
			$this->outputIndented( "\tReindexing...\n" );
			$this->reindexer->reindex( ...$this->reindexParams );
			if ( $this->tooFewReplicas ) {
				foreach ( $this->reindexValidators as $validator ) {
					$status = $validator->validate();
					if ( !$status->isOK() ) {
						return $status;
					}
				}

				$this->reindexer->waitForShards();
			}
		} catch ( \Exception $e ) {
			return Status::newFatal( new RawMessage( $e->getMessage() ) );
		}

		// now add alias & remove indices for real
		$status = Status::newGood();
		$status->merge( $this->swapAliases( $add ) );
		$toRemove = [];
		foreach ( $remove as $indexToRemove ) {
			$resp = $this->client->request( $indexToRemove . '/_alias', 'GET' );
			if ( !$resp->isOk() ) {
				return Status::newFatal( "Cannot fetch aliases of the old index $indexToRemove" );
			}
			if ( count( $resp->getData()[$indexToRemove]['aliases'] ?? [] ) > 0 ) {
				$this->outputIndented( "\t$indexToRemove still have active aliases, cannot remove.\n" );
			} else {
				$toRemove[] = $indexToRemove;
			}
		}
		$status->merge( parent::updateIndices( [], $toRemove ) );
		return $status;
	}

	/**
	 * @param string[] $add
	 * @return Status
	 */
	public function updateFreeIndices( array $add ) {
		$this->output( "alias is free..." );

		foreach ( $add as $indexName ) {
			$index = $this->client->getIndex( $indexName );
			$index->addAlias( $this->aliasName, false );
		}

		$this->output( "corrected\n" );

		return Status::newGood();
	}

	/**
	 * @param array $add
	 * @return Status
	 */
	public function swapAliases( array $add ) {
		$this->outputIndented( "\tSwapping alias..." );

		foreach ( $add as $indexName ) {
			$index = $this->client->getIndex( $indexName );
			$index->addAlias( $this->aliasName, true );
		}

		$this->output( "done\n" );

		return Status::newGood();
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	protected function shouldRemoveFromAlias( $name ) {
		return true;
	}
}
