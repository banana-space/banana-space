<?php

namespace CirrusSearch\MetaStore;

use CirrusSearch\Connection;

class MetaSaneitizeJobStore implements MetaStore {
	const METASTORE_TYPE = "sanitize";

	/** @var Connection */
	private $connection;

	public function __construct( Connection $connection ) {
		$this->connection = $connection;
	}

	/**
	 * @param string $jobName
	 * @return string the job id
	 */
	public static function docId( $jobName ) {
		return implode( '-', [
			self::METASTORE_TYPE,
			wfWikiId(),
			$jobName
		] );
	}

	/**
	 * @param string $jobName
	 * @param int $idOffset The starting page id of the job
	 * @param string|null $cluster target cluster for this job (null for all writable clusters)
	 * @return \Elastica\Document
	 */
	public function create( $jobName, $idOffset, $cluster = null ) {
		$doc = new \Elastica\Document(
			self::docId( $jobName ),
			[
				'type' => self::METASTORE_TYPE,
				'wiki' => wfWikiID(),
				'sanitize_job_loop_id' => 0,
				'sanitize_job_wiki' => wfWikiID(), // Deprecated, use common wiki field
				'sanitize_job_created' => time(),
				'sanitize_job_updated' => time(),
				'sanitize_job_last_loop' => null,
				'sanitize_job_cluster' => $cluster,
				'sanitize_job_id_offset' => $idOffset,
				'sanitize_job_ids_sent' => 0,
				'sanitize_job_ids_sent_total' => 0,
				'sanitize_job_jobs_sent' => 0,
				'sanitize_job_jobs_sent_total' => 0
			]
		);
		$this->getType()->addDocument( $doc );
		return $doc;
	}

	/**
	 * @param string $jobName job name.
	 * @return \Elastica\Document|null
	 */
	public function get( $jobName ) {
		try {
			// Try to fetch the JobInfo from one of the metastore
			return $this->getType()->getDocument( self::docId( $jobName ) );
		} catch ( \Elastica\Exception\NotFoundException $e ) {
			return null;
		}
	}

	/**
	 * TODO: Might be more comfortable with something that
	 * wraps the document and guarantees something sane
	 * is provided here.
	 *
	 * @param \Elastica\Document $jobInfo
	 */
	public function update( \Elastica\Document $jobInfo ) {
		if ( $jobInfo->get( 'type' ) != self::METASTORE_TYPE ) {
			throw new \Exception( "Wrong document type" );
		}
		$version = time();
		$jobInfo->set( 'sanitize_job_updated', $version );
		$jobInfo->setVersion( $version );
		$jobInfo->setVersionType( 'external' );
		$this->getType()->addDocument( $jobInfo );
	}

	/**
	 * @param string $jobName
	 */
	public function delete( $jobName ) {
		$this->getType()->deleteById( self::docId( $jobName ) );
	}

	private function getType() {
		return MetaStoreIndex::getElasticaType( $this->connection );
	}

	/**
	 * @return array
	 */
	public function buildIndexProperties() {
		return [];
	}
}
