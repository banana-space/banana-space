<?php

namespace Flow\Search\Updaters;

use Flow\Container;
use Flow\Exception\FlowException;
use Flow\Model\AbstractRevision;
use Flow\RevisionActionPermissions;
use Flow\Search\Connection;
use Flow\Search\Iterators\AbstractIterator;
use MWExceptionHandler;

abstract class AbstractUpdater {
	/**
	 * @var AbstractIterator
	 */
	public $iterator;

	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @var Connection
	 */
	protected $connection;

	/**
	 * @param AbstractIterator $iterator
	 * @param RevisionActionPermissions $permissions
	 */
	public function __construct( AbstractIterator $iterator, RevisionActionPermissions $permissions ) {
		$this->iterator = $iterator;
		$this->permissions = $permissions;
		$this->connection = Container::get( 'search.connection' );
	}

	/**
	 * @return string One of the Connection::*_TYPE_NAME constants
	 */
	abstract public function getTypeName();

	/**
	 * @param AbstractRevision $revision
	 * @return \Elastica\Document
	 */
	abstract public function buildDocument( AbstractRevision $revision );

	/**
	 * @param string|null $shardTimeout Timeout in Elasticsearch time format (1m, 15s, ...)
	 * @param int|null $clientSideTimeout
	 * @param int $batchSize
	 * @return int
	 */
	public function updateRevisions( $shardTimeout = null, $clientSideTimeout = null, $batchSize = 50 ) {
		if ( $clientSideTimeout !== null ) {
			$this->connection->setTimeout( $clientSideTimeout );
		}

		$documents = [];
		$count = 0;
		foreach ( $this->iterator as $revision ) {
			try {
				$documents[] = $this->buildDocument( $revision );
				$count++;
			} catch ( FlowException $e ) {
				// just ignore revisions that fail to build document...
				wfWarn( __METHOD__ . ': Failed to build document for ' .
					$revision->getRevisionId()->getAlphadecimal() . ': ' . $e->getMessage() );
				MWExceptionHandler::logException( $e );
			}

			// send documents in small batches
			if ( count( $documents ) > $batchSize ) {
				$this->sendDocuments( $documents, $shardTimeout );
				$documents = [];
			}
		}

		if ( $documents ) {
			// send remaining documents
			$this->sendDocuments( $documents, $shardTimeout );
		}

		return $count;
	}

	/**
	 * @param \Elastica\Document[] $documents
	 * @param string|null $shardTimeout Timeout in Elasticsearch time format (1m, 15s, ...)
	 */
	protected function sendDocuments( array $documents, $shardTimeout = null ) {
		if ( count( $documents ) === 0 ) {
			return;
		}

		try {
			// addDocuments (notice plural) is the bulk api
			$bulk = new \Elastica\Bulk( $this->connection->getClient() );
			if ( $shardTimeout !== null ) {
				$bulk->setShardTimeout( $shardTimeout );
			}

			$index = $this->connection->getFlowIndex( wfWikiID() );
			$type = $index->getType( $this->getTypeName() );
			$bulk->setType( $type );
			$bulk->addDocuments( $documents );
			$bulk->send();
		} catch ( \Exception $e ) {
			$documentIds = array_map( function ( $doc ) {
				return $doc->getId();
			}, $documents );
			wfWarn( __METHOD__ . ': Failed updating documents (' . implode( ',', $documentIds ) . '): ' .
				$e->getMessage() );
		}
	}
}
