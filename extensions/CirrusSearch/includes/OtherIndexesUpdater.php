<?php

namespace CirrusSearch;

use Elastica\Multi\Search as MultiSearch;
use MediaWiki\Logger\LoggerFactory;
use Title;

/**
 * Tracks whether a Title is known on other indexes.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 */
class OtherIndexesUpdater extends Updater {
	/** @var string Local site we're tracking */
	private $localSite;

	/**
	 * @param Connection $readConnection
	 * @param string|null $writeToClusterName
	 * @param string $localSite
	 */
	public function __construct( Connection $readConnection, $writeToClusterName, $localSite ) {
		parent::__construct( $readConnection, $writeToClusterName );
		$this->localSite = $localSite;
	}

	/**
	 * @param SearchConfig $config
	 * @param string|null $cluster
	 * @param string $localSite
	 * @return OtherIndexesUpdater
	 */
	public static function buildOtherIndexesUpdater( SearchConfig $config, $cluster, $localSite ): OtherIndexesUpdater {
		$connection = Connection::getPool( $config, $cluster );
		return new self( $connection, $cluster, $localSite );
	}

	/**
	 * Get the external index identifiers for title.
	 * @param SearchConfig $config
	 * @param Title $title
	 * @param string|null $cluster cluster (as in CirrusSearchWriteClusters) to filter on
	 * @return ExternalIndex[] array of external indices.
	 */
	public static function getExternalIndexes( SearchConfig $config, Title $title, $cluster = null ) {
		$namespace = $title->getNamespace();
		$indices = [];
		foreach ( $config->get( 'CirrusSearchExtraIndexes' )[$namespace] ?? [] as $indexName ) {
			$ei = new ExternalIndex( $config, $indexName );
			if ( $cluster === null || !$ei->isClusterBlacklisted( $cluster ) ) {
				$indices[] = $ei;
			}
		}
		return $indices;
	}

	/**
	 * Get any extra indexes to query, if any, based on namespaces
	 * @param SearchConfig $config
	 * @param int[] $namespaces An array of namespace ids
	 * @return ExternalIndex[] array of indexes
	 */
	public static function getExtraIndexesForNamespaces( SearchConfig $config, array $namespaces ) {
		$extraIndexes = [];
		foreach ( $config->get( 'CirrusSearchExtraIndexes' ) ?: [] as $namespace => $indexes ) {
			if ( !in_array( $namespace, $namespaces ) ) {
				continue;
			}
			foreach ( $indexes as $indexName ) {
				$extraIndexes[] = new ExternalIndex( $config, $indexName );
			}
		}
		return $extraIndexes;
	}

	/**
	 * Update the indexes for other wiki that also store information about $titles.
	 * @param Title[] $titles array of titles in other indexes to update
	 */
	public function updateOtherIndex( $titles ) {
		if ( !$this->connection->getConfig()->getElement( 'CirrusSearchWikimediaExtraPlugin', 'super_detect_noop' ) ) {
			$this->logFailure( $titles, 'super_detect_noop plugin not enabled' );
			return;
		}

		$updates = [];

		// Build multisearch to find ids to update
		$findIdsMultiSearch = new MultiSearch( $this->connection->getClient() );
		$findIdsClosures = [];
		$readClusterName = $this->connection->getConfig()->getClusterAssignment()->getCrossClusterName();
		foreach ( $titles as $title ) {
			foreach ( self::getExternalIndexes( $this->connection->getConfig(), $title ) as $otherIndex ) {
				$searchIndex = $otherIndex->getSearchIndex( $readClusterName );
				$type = $this->connection->getPageType( $searchIndex );
				$query = $this->queryForTitle( $title );
				$search = $type->createSearch( $query );
				$findIdsMultiSearch->addSearch( $search );
				$findIdsClosures[] = function ( $docId ) use ( $otherIndex, &$updates, $title ) {
					// The searchIndex, including the cluster specified, is needed
					// as this gets passed to the ExternalIndex constructor in
					// the created jobs.
					if ( !isset( $updates[spl_object_hash( $otherIndex )] ) ) {
						$updates[spl_object_hash( $otherIndex )] = [ $otherIndex, [] ];
					}
					$updates[spl_object_hash( $otherIndex )][1][] = [
						'docId' => $docId,
						'ns' => $title->getNamespace(),
						'dbKey' => $title->getDBkey(),
					];
				};
			}
		}
		$findIdsClosuresCount = count( $findIdsClosures );
		if ( $findIdsClosuresCount === 0 ) {
			// No other indexes to check.
			return;
		}

		// Look up the ids and run all closures to build the list of updates
		$this->start( new MultiSearchRequestLog(
			$this->connection->getClient(),
			'searching for {numIds} ids in other indexes',
			'other_idx_lookup',
			[ 'numIds' => $findIdsClosuresCount ]
		) );
		$findIdsMultiSearchResult = $findIdsMultiSearch->search();
		try {
			$this->success();
			foreach ( $findIdsClosures as $i => $closure ) {
				$results = $findIdsMultiSearchResult[$i]->getResults();
				if ( count( $results ) ) {
					$closure( $results[0]->getId() );
				}
			}
		} catch ( \Elastica\Exception\ExceptionInterface $e ) {
			$this->failure( $e );
			return;
		}

		$this->runUpdates( reset( $titles ), $updates );
	}

	protected function runUpdates( Title $title, array $updates ) {
		// These are split into a job per index so one index
		// being frozen doesn't block updates to other indexes
		// in the same update. Also because the external indexes
		// may be configured to write to different clusters.
		foreach ( $updates as $data ) {
			list( $otherIndex, $actions ) = $data;
			$this->pushElasticaWriteJobs( $actions, function ( array $chunk, string $cluster ) use ( $otherIndex ) {
				// Name of the index to write to on whatever cluster is connected to
				$indexName = $otherIndex->getIndexName();
				// Index name and, potentially, a replica group identifier. Needed to
				// create an appropriate ExternalIndex instance in the job.
				$externalIndex = $otherIndex->getGroupAndIndexName();
				return Job\ElasticaWrite::build(
					$cluster,
					'sendOtherIndexUpdates',
					[ $this->localSite, $indexName, $chunk ],
					[ 'external-index' => $externalIndex ]
				);
			} );
		}
	}

	/**
	 * @param Title[] $titles
	 * @param string $reason
	 */
	private function logFailure( array $titles, $reason = '' ) {
		$articleIDs = array_map( function ( Title $title ) {
			return $title->getArticleID();
		}, $titles );
		if ( $reason ) {
			$reason = " ($reason)";
		}
		LoggerFactory::getInstance( 'CirrusSearchChangeFailed' )->info(
			"Other Index$reason for article ids: " . implode( ',', $articleIDs ) );
	}

	/**
	 * @param Title $title
	 * @return \Elastica\Query
	 */
	private function queryForTitle( Title $title ) {
		$bool = new \Elastica\Query\BoolQuery();

		// Note that we need to use the keyword indexing of title so the analyzer gets out of the way.
		$bool->addFilter( new \Elastica\Query\Term( [ 'title.keyword' => $title->getText() ] ) );
		$bool->addFilter( new \Elastica\Query\Term( [ 'namespace' => $title->getNamespace() ] ) );

		$query = new \Elastica\Query( $bool );
		$query->setStoredFields( [] ); // We only need the _id so don't load the _source
		$query->setSize( 1 );

		return $query;
	}

}
