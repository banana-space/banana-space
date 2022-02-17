<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\BuildDocument\Completion\SuggestBuilder;
use CirrusSearch\Connection;
use CirrusSearch\DataSender;
use CirrusSearch\ElasticaErrorHandler;
use CirrusSearch\Maintenance\Validators\AnalyzersValidator;
use CirrusSearch\MetaStore\MetaStoreIndex;
use CirrusSearch\MetaStore\MetaVersionStore;
use CirrusSearch\SearchConfig;
use Elastica;
use Elastica\Index;
use Elastica\Query;
use Elastica\Request;
use Elastica\Status;
use MWElasticUtils;

/**
 * Update the search configuration on the search backend for the title
 * suggest index.
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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class UpdateSuggesterIndex extends Maintenance {
	/**
	 * @var string language code we're building for
	 */
	private $langCode;

	/**
	 * @var int
	 */
	private $indexChunkSize;

	/**
	 * @var int
	 */
	private $indexRetryAttempts;

	/**
	 * @var string
	 */
	private $indexTypeName;

	/**
	 * @var string
	 */
	private $indexBaseName;

	/**
	 * @var string
	 */
	private $indexIdentifier;

	/**
	 * @var Index old suggester index that will be deleted at the end of the process
	 */
	private $oldIndex;

	/**
	 * @var int
	 */
	private $lastProgressPrinted;

	/**
	 * @var boolean optimize the index when done.
	 */
	private $optimizeIndex;

	/**
	 * @var array list of available plugins
	 */
	private $availablePlugins;

	/**
	 * @var string
	 */
	private $masterTimeout;

	/**
	 * @var ConfigUtils
	 */
	private $utils;

	/**
	 * @todo: public (used in closure)
	 * @var SuggestBuilder
	 */
	public $builder;

	/**
	 * @var array
	 */
	private $analysisConfig;

	/**
	 * @var bool
	 */
	private $recycle = false;

	/**
	 * @var string[]
	 */
	private $bannedPlugins;

	public function __construct() {
		parent::__construct();
		$this->addDescription( "Create a new suggester index. Always operates on a single cluster." );
		$this->addOption( 'baseName', 'What basename to use for all indexes, ' .
			'defaults to wiki id', false, true );
		$this->addOption( 'indexChunkSize', 'Documents per shard to index in a batch.   ' .
			'Note when changing the number of shards that the old shard size is used, not the new ' .
			'one.  If you see many errors submitting documents in bulk but the automatic retry as ' .
			'singles works then lower this number.  Defaults to 500.', false, true );
		$this->addOption( 'indexRetryAttempts', 'Number of times to back off and retry ' .
			'per failure.  Note that failures are not common but if Elasticsearch is in the process ' .
			'of moving a shard this can time out.  This will retry the attempt after some backoff ' .
			'rather than failing the whole reindex process.  Defaults to 5.', false, true );
		$this->addOption( 'optimize',
			'Optimize the index to 1 segment. Defaults to false.', false, false );
		$this->addOption( 'scoringMethod',
			'The scoring method to use when computing suggestion weights. ' .
			'Defaults to $wgCirrusSearchCompletionDefaultScore or quality if unset.', false, true );
		$this->addOption( 'masterTimeout',
			'The amount of time to wait for the master to respond to mapping ' .
			'updates before failing. Defaults to $wgCirrusSearchMasterTimeout.', false, true );
		$this->addOption( 'replicationTimeout',
			'The amount of time (seconds) to wait for the replica shards to initialize. ' .
			'Defaults to 3600 seconds.', false, true );
		$this->addOption( 'allocationIncludeTag',
			'Set index.routing.allocation.include.tag on the created index. Useful if you want to ' .
			'force the suggester index not to be allocated on a specific set of nodes.',
			false, true );
		$this->addOption( 'allocationExcludeTag',
			'Set index.routing.allocation.exclude.tag on the created index. Useful if you want ' .
			'to force the suggester index not to be allocated on a specific set of nodes.',
			false, true );
	}

	public function execute() {
		global $wgLanguageCode,
			$wgCirrusSearchBannedPlugins,
			$wgCirrusSearchMasterTimeout;

		$this->disablePoolCountersAndLogging();
		$this->masterTimeout = $this->getOption( 'masterTimeout', $wgCirrusSearchMasterTimeout );
		$this->indexTypeName = Connection::TITLE_SUGGEST_TYPE;

		$useCompletion = $this->getSearchConfig()->get( 'CirrusSearchUseCompletionSuggester' );

		if ( $useCompletion !== 'build' && $useCompletion !== 'yes' && $useCompletion !== true ) {
			$this->fatalError( "Completion suggester disabled, quitting..." );
		}

		// Check that all shards and replicas settings are set
		try {
			$this->getShardCount();
			$this->getReplicaCount();
			$this->getMaxShardsPerNode();
		} catch ( \Exception $e ) {
			$this->fatalError(
				"Failed to get shard count and replica count information: {$e->getMessage()}"
			);
		}

		$this->indexBaseName = $this->getOption(
			'baseName', $this->getSearchConfig()->get( SearchConfig::INDEX_BASE_NAME )
		);
		$this->indexChunkSize = $this->getOption( 'indexChunkSize', 500 );
		$this->indexRetryAttempts = $this->getOption( 'reindexRetryAttempts', 5 );

		$this->optimizeIndex = $this->getOption( 'optimize', false );

		$this->utils = new ConfigUtils( $this->getClient(), $this );

		$this->langCode = $wgLanguageCode;
		$this->bannedPlugins = $wgCirrusSearchBannedPlugins;

		$this->availablePlugins = $this->utils->scanAvailablePlugins( $this->bannedPlugins );
		$this->analysisConfig = $this->pickAnalyzer( $this->langCode, $this->availablePlugins )
			->buildConfig();

		$this->utils->checkElasticsearchVersion();

		try {
			// If the version does not exist it's certainly because nothing has been indexed.
			if ( !MetaStoreIndex::cirrusReady( $this->getConnection() ) ) {
				throw new \Exception(
					"Cirrus meta store does not exist, you must index your data first"
				);
			}

			if ( !$this->canWrite() ) {
				$this->fatalError( 'Index/Cluster is frozen. Giving up.' );
			}

			$this->builder = SuggestBuilder::create( $this->getConnection(),
				$this->getOption( 'scoringMethod' ), $this->indexBaseName );
			# check for broken indices and delete them
			$this->checkAndDeleteBrokenIndices();

			if ( !$this->canRecycle() ) {
				$this->rebuild();
			} else {
				$this->recycle();
			}
		} catch ( \Elastica\Exception\Connection\HttpException $e ) {
			$message = $e->getMessage();
			$this->log( "\nUnexpected Elasticsearch failure.\n" );
			$this->fatalError( "Http error communicating with Elasticsearch:  $message.\n" );
		} catch ( \Elastica\Exception\ExceptionInterface $e ) {
			$type = get_class( $e );
			$message = ElasticaErrorHandler::extractMessage( $e );
			$trace = $e->getTraceAsString();
			$this->log( "\nUnexpected Elasticsearch failure.\n" );
			$this->fatalError( "Elasticsearch failed in an unexpected way.  " .
				"This is always a bug in CirrusSearch.\n" .
				"Error type: $type\n" .
				"Message: $message\n" .
				"Trace:\n" . $trace );
		}

		return true;
	}

	/**
	 * Check the frozen indices
	 * @return true if the cluster/index is not frozen, false otherwise.
	 */
	private function canWrite() {
		// Reuse DataSender even if we don't send anything with it.
		$sender = new DataSender( $this->getConnection(), $this->getSearchConfig() );
		return $sender->isAvailableForWrites();
	}

	/**
	 * Check for duplicate indices that may have been created
	 * by a previous update that failed.
	 */
	private function checkAndDeleteBrokenIndices() {
		$indices = $this->utils->getAllIndicesByType( $this->getIndexTypeName() );
		if ( count( $indices ) < 2 ) {
			return;
		}
		$indexByName = [];
		foreach ( $indices as $name ) {
			$indexByName[$name] = $this->getConnection()->getIndex( $name );
		}

		$status = new Status( $this->getClient() );
		foreach ( $status->getIndicesWithAlias( $this->getIndexTypeName() ) as $aliased ) {
			// do not try to delete indices that are used in aliases
			unset( $indexByName[$aliased->getName()] );
		}
		foreach ( $indexByName as $name => $index ) {
			# double check with stats
			$stats = $index->getStats()->getData();
			// Extra check: if stats report usages we should not try to fix things
			// automatically.
			if ( $stats['_all']['total']['search']['suggest_total'] == 0 ) {
				$this->log( "Deleting broken index {$index->getName()}\n" );
				$this->deleteIndex( $index );
			} else {
				$this->log( "Broken index {$index->getName()} appears to be in use, " .
					"please check and delete.\n" );
			}

		}
		# If something went wrong the process will fail when calling pickIndexIdentifierFromOption
	}

	private function rebuild() {
		$oldIndexIdentifier = $this->utils->pickIndexIdentifierFromOption(
			'current', $this->getIndexTypeName()
		);
		$this->oldIndex = $this->getConnection()->getIndex(
			$this->indexBaseName, $this->indexTypeName, $oldIndexIdentifier
		);
		$this->indexIdentifier = $this->utils->pickIndexIdentifierFromOption(
			'now', $this->getIndexTypeName()
		);

		$this->createIndex();
		$this->indexData();
		if ( $this->optimizeIndex ) {
			$this->optimize();
		}
		$this->enableReplicas();
		$this->getIndex()->refresh();
		$this->validateAlias();
		$this->updateVersions();
		$this->deleteOldIndex();
		$this->log( "Done.\n" );
	}

	private function canRecycle() {
		global $wgCirrusSearchRecycleCompletionSuggesterIndex;
		if ( !$wgCirrusSearchRecycleCompletionSuggesterIndex ) {
			return false;
		}
		$oldIndexIdentifier = $this->utils->pickIndexIdentifierFromOption(
			'current', $this->getIndexTypeName()
		);
		$oldIndex = $this->getConnection()->getIndex(
			$this->indexBaseName, $this->indexTypeName, $oldIndexIdentifier
		);
		if ( !$oldIndex->exists() ) {
			$this->error( 'Index does not exist yet cannot recycle.' );
			return false;
		}
		$refresh = $oldIndex->getSettings()->getRefreshInterval();
		if ( $refresh != '-1' ) {
			$this->error( 'Refresh interval is not -1, cannot recycle.' );
			return false;
		}

		$shards = $oldIndex->getSettings()->get( 'number_of_shards' );
		// We check only the number of shards since it cannot be updated.
		if ( $shards != $this->getShardCount() ) {
			$this->error( 'Number of shards mismatch cannot recycle.' );
			return false;
		}

		$mMaj = explode( '.', SuggesterMappingConfigBuilder::VERSION, 2 )[0];
		$aMaj = explode( '.', SuggesterAnalysisConfigBuilder::VERSION, 2 )[0];

		try {
			$store = new MetaVersionStore( $this->getConnection() );
			$versionDoc = $store->find( $this->indexBaseName, $this->indexTypeName );
		} catch ( \Elastica\Exception\NotFoundException $nfe ) {
			$this->error( 'Index missing in mw_cirrus_metastore::version, cannot recycle.' );
			return false;
		}

		if ( $versionDoc->analysis_maj != $aMaj ) {
			$this->error( 'Analysis config version mismatch, cannot recycle.' );
			return false;
		}

		if ( $versionDoc->mapping_maj != $mMaj ) {
			$this->error( 'Mapping config version mismatch, cannot recycle.' );
			return false;
		}

		$validator = new AnalyzersValidator( $oldIndex, $this->analysisConfig, $this );
		$status = $validator->validate();
		if ( !$status->isOK() ) {
			$this->error( 'Analysis config differs, cannot recycle.' );
			return false;
		}

		return true;
	}

	/**
	 * Recycle a suggester index:
	 * 1/ index data (delete docs if it already exists)
	 * 2/ expunge deleted docs
	 * 3/ refresh the reader
	 *    - so we can run a quick delete on remaining docs
	 *      (the docs that were actually deleted)
	 *    - drawbacks we load the FST from an un-optimized index
	 * 4/ delete old docs
	 * 5/ optimize
	 * 6/ refresh the reader
	 *
	 * Drawbacks: the FST will be read from disk twice in a short
	 * amount of time.
	 * This is a trade off between cluster operation and disk operation.
	 * Recreating the index may require less disk operations but causes
	 * the cluster to rebalance.
	 * This is certainly the best strategy for small indices (less than 100k docs)
	 * but needs to be carefully tested on bigger indices with high QPS.
	 */
	private function recycle() {
		$this->log( "Recycling index {$this->getIndex()->getName()}\n" );
		$this->recycle = true;
		$this->indexData();
		// This is fragile... hopefully most of the docs will be deleted from the old segments
		// and will result in a fast operation.
		// New segments should not be affected.
		// Unfortunately if a failure causes the process to stop
		// the FST will maybe contains duplicates as it cannot (elastic 1.7)
		// filter deleted docs. We will rely on output deduplication
		// but this will certainly affect performances.

		$this->expungeDeletes();
		// Refresh the reader so we can scroll over remaining docs.
		// At this point we may read the new un-optimized FST segments
		// Old ones should be pretty small after expungeDeletes
		$this->getIndex()->refresh();

		$boolNot = new Elastica\Query\BoolQuery();
		$boolNot->addMustNot(
			new Elastica\Query\Term( [ "batch_id" => $this->builder->getBatchId() ] )
		);
		$bool = new Elastica\Query\BoolQuery();
		$bool->addFilter( $boolNot );

		$query = new Elastica\Query();
		$query->setQuery( $bool );
		$query->setSize( $this->indexChunkSize );
		$query->setSource( false );
		$query->setSort( [ '_doc' ] );
		$search = new \Elastica\Search( $this->getClient() );
		$search->setQuery( $query );
		$search->addIndex( $this->getIndex() );
		$scroll = new \Elastica\Scroll( $search, '15m' );

		$totalDocsToDump = -1;
		$docsDumped = 0;

		$this->log( "Deleting remaining docs from previous batch\n" );
		foreach ( $scroll as $results ) {
			if ( $totalDocsToDump === -1 ) {
				$totalDocsToDump = $results->getTotalHits();
				if ( $totalDocsToDump === 0 ) {
					break;
				}
				$docsDumped = 0;
			}
			$docIds = [];
			foreach ( $results as $result ) {
				$docsDumped++;
				$docIds[] = $result->getId();
			}
			$this->outputProgress( $docsDumped, $totalDocsToDump );
			if ( empty( $docIds ) ) {
				continue;
			}
			MWElasticUtils::withRetry( $this->indexRetryAttempts,
				function () use ( $docIds ) {
					$this->getType()->deleteIds( $docIds );
				}
			);
		}
		$this->log( "Done.\n" );
		// Old docs should be deleted now we can optimize and flush
		$this->optimize();

		// @todo add support for changing the number of replicas
		// if the setting was changed in cirrus config.
		// Workaround is to change the settings directly on the cluster.

		// Refresh the reader so it now uses the optimized FST,
		// and actually free and delete old segments.
		$this->getIndex()->refresh();
	}

	private function deleteOldIndex() {
		if ( $this->oldIndex && $this->oldIndex->exists() ) {
			$this->log( "Deleting " . $this->oldIndex->getName() . " ... " );
			// @todo Utilize $this->oldIndex->delete(...) once Elastica library is updated
			// to allow passing the master_timeout
			$this->oldIndex->request(
				'',
				Request::DELETE,
				[],
				[ 'master_timeout' => $this->masterTimeout ]
			);
			$this->output( "ok.\n" );
		}
	}

	/**
	 * Delete an index
	 * @param \Elastica\Index $index
	 */
	private function deleteIndex( \Elastica\Index $index ) {
		// @todo Utilize $this->oldIndex->delete(...) once Elastica library is updated
		// to allow passing the master_timeout
		$index->request(
			'',
			Request::DELETE,
			[],
			[ 'master_timeout' => $this->masterTimeout ]
		);
	}

	private function optimize() {
		$this->log( "Optimizing index..." );
		$this->getIndex()->forcemerge( [ 'max_num_segments' => 1 ] );
		$this->output( "ok.\n" );
	}

	private function expungeDeletes() {
		$this->log( "Purging deleted docs..." );
		$this->getIndex()->forcemerge( [ 'only_expunge_deletes' => 'true', 'flush' => 'false' ] );
		$this->output( "ok.\n" );
	}

	private function indexData() {
		// We build the suggestions by reading CONTENT and GENERAL indices.
		// This does not support extra indices like FILES on commons.
		$sourceIndexTypes = [ Connection::CONTENT_INDEX_TYPE, Connection::GENERAL_INDEX_TYPE ];

		$query = new Query();
		$query->setSource( [
			'includes' => $this->builder->getRequiredFields()
		] );

		$pageAndNs = new Elastica\Query\BoolQuery();
		$pageAndNs->addShould( new Elastica\Query\Term( [ "namespace" => NS_MAIN ] ) );
		$pageAndNs->addShould( new Elastica\Query\Term( [ "redirect.namespace" => NS_MAIN ] ) );
		$pageAndNs->addMust( new Elastica\Query\Type( Connection::PAGE_TYPE_NAME ) );
		$bool = new Elastica\Query\BoolQuery();
		$bool->addFilter( $pageAndNs );

		$query->setQuery( $bool );
		$query->setSort( [ '_doc' ] );

		foreach ( $sourceIndexTypes as $sourceIndexType ) {
			$sourceIndex = $this->getConnection()->getIndex( $this->indexBaseName, $sourceIndexType );
			$search = new \Elastica\Search( $this->getClient() );
			$search->setQuery( $query );
			$search->addIndex( $sourceIndex );
			$query->setSize( $this->indexChunkSize );
			$totalDocsToDump = -1;
			$scroll = new \Elastica\Scroll( $search, '15m' );

			$docsDumped = 0;
			$destinationType = $this->getIndex()->getType( Connection::TITLE_SUGGEST_TYPE_NAME );

			foreach ( $scroll as $results ) {
				if ( $totalDocsToDump === -1 ) {
					$totalDocsToDump = $results->getTotalHits();
					if ( $totalDocsToDump === 0 ) {
						$this->log( "No documents to index from $sourceIndexType\n" );
						break;
					}
					$this->log( "Indexing $totalDocsToDump documents from $sourceIndexType with " .
						"batchId: {$this->builder->getBatchId()}\n" );
				}
				$inputDocs = [];
				foreach ( $results as $result ) {
					$docsDumped++;
					$inputDocs[] = [
						'id' => $result->getId(),
						'source' => $result->getSource()
					];
				}

				$suggestDocs = $this->builder->build( $inputDocs );
				if ( empty( $suggestDocs ) ) {
					continue;
				}
				$this->outputProgress( $docsDumped, $totalDocsToDump );
				MWElasticUtils::withRetry( $this->indexRetryAttempts,
					function () use ( $destinationType, $suggestDocs ) {
						$destinationType->addDocuments( $suggestDocs );
					}
				);
			}
			$this->log( "Indexing from $sourceIndexType index done.\n" );
		}
	}

	public function validateAlias() {
		// @todo utilize the following once Elastica is updated to support passing
		// master_timeout. This is a copy of the Elastica\Index::addAlias() method
		// $this->getIndex()->addAlias( $this->getIndexTypeName(), true );
		$index = $this->getIndex();
		$name = $this->getIndexTypeName();

		$path = '_aliases';
		$data = [ 'actions' => [] ];
		$status = new Status( $index->getClient() );
		foreach ( $status->getIndicesWithAlias( $name ) as $aliased ) {
			$data['actions'][] = [ 'remove' => [ 'index' => $aliased->getName(), 'alias' => $name ] ];
		}

		$data['actions'][] = [ 'add' => [ 'index' => $index->getName(), 'alias' => $name ] ];

		$index->getClient()->request(
			$path, Request::POST, $data, [ 'master_timeout' => $this->masterTimeout ]
		);
	}

	/**
	 * public because php 5.3 does not support accessing private
	 * methods in a closure.
	 * @param int $docsDumped
	 * @param int $limit
	 */
	public function outputProgress( $docsDumped, $limit ) {
		if ( $docsDumped <= 0 ) {
			return;
		}
		$pctDone = (int)( ( $docsDumped / $limit ) * 100 );
		if ( $this->lastProgressPrinted == $pctDone ) {
			return;
		}
		$this->lastProgressPrinted = $pctDone;
		if ( ( $pctDone % 2 ) == 0 ) {
			$this->outputIndented( "\t$pctDone% done...\n" );
		}
	}

	public function log( $message, $channel = null ) {
		$date = new \DateTime();
		parent::output( $date->format( 'Y-m-d H:i:s' ) . " " . $message, $channel );
	}

	/**
	 * @param string $langCode
	 * @param array $availablePlugins
	 * @return AnalysisConfigBuilder
	 */
	private function pickAnalyzer( $langCode, array $availablePlugins = [] ) {
		$analysisConfigBuilder = new SuggesterAnalysisConfigBuilder( $langCode, $availablePlugins );
		$this->outputIndented( 'Picking analyzer...' .
								$analysisConfigBuilder->getDefaultTextAnalyzerType( $langCode ) .
								"\n" );
		return $analysisConfigBuilder;
	}

	private function createIndex() {
		// This is "create only" for now.
		if ( $this->getIndex()->exists() ) {
			throw new \Exception( "Index already exists." );
		}

		$mappingConfigBuilder = new SuggesterMappingConfigBuilder();

		// We create the index with 0 replicas, this is faster and will
		// stress less nodes with 4 shards and 2 replicas we would
		// stress 12 nodes (moreover with the optimize flag)
		$settings = [
			'number_of_shards' => $this->getShardCount(),
			// hacky but we still use auto_expand_replicas
			// for convenience on small install.
			'auto_expand_replicas' => "0-0",
			'refresh_interval' => -1,
			'analysis' => $this->analysisConfig,
			'routing.allocation.total_shards_per_node' => $this->getMaxShardsPerNode(),
		];

		if ( $this->hasOption( 'allocationIncludeTag' ) ) {
			$this->output( "Using routing.allocation.include.tag: " .
				"{$this->getOption( 'allocationIncludeTag' )}, the index might be stuck in red " .
				"if the cluster is not properly configured.\n" );
			$settings['routing.allocation.include.tag'] = $this->getOption( 'allocationIncludeTag' );
		}

		if ( $this->hasOption( 'allocationExcludeTag' ) ) {
			$this->output( "Using routing.allocation.exclude.tag: " .
				"{$this->getOption( 'allocationExcludeTag' )}, the index might be stuck in red " .
				"if the cluster is not properly configured.\n" );
			$settings['routing.allocation.exclude.tag'] = $this->getOption( 'allocationExcludeTag' );
		}

		$args = [
			'settings' => $settings,
			'mappings' => $mappingConfigBuilder->buildConfig()
		];
		// @todo utilize $this->getIndex()->create(...) once it supports setting
		// the master_timeout parameter.
		$this->getIndex()->request(
			'',
			Request::PUT,
			$args,
			[ 'master_timeout' => $this->masterTimeout ]
		);

		// Index create is async, we have to make sure that the index is ready
		// before sending any docs to it.
		$this->waitForGreen();
	}

	private function enableReplicas() {
		$this->log( "Enabling replicas...\n" );
		$args = [
			'index' => [
				'auto_expand_replicas' => $this->getReplicaCount(),
			],
		];

		$path = $this->getIndex()->getName() . "/_settings";
		$this->getIndex()->getClient()->request(
			$path,
			Request::PUT,
			$args,
			[ 'master_timeout' => $this->masterTimeout ]
		);

		// The previous call seems to be async, let's wait few sec
		// otherwise replication won't have time to start.
		sleep( 20 );

		// Index will be yellow while replica shards are being allocated.
		$this->waitForGreen( $this->getOption( 'replicationTimeout', 3600 ) );
	}

	private function waitForGreen( $timeout = 600 ) {
		$this->log( "Waiting for the index to go green...\n" );
		// Wait for the index to go green ( default 10 min)
		if ( !$this->utils->waitForGreen( $this->getIndex()->getName(), $timeout ) ) {
			$this->fatalError( "Failed to wait for green... please check config and " .
				"delete the {$this->getIndex()->getName()} index if it was created." );
		}
	}

	/**
	 * @return string Number of replicas this index should have. May be a range such as '0-2'
	 */
	private function getReplicaCount() {
		return $this->getConnection()->getSettings()->getReplicaCount( $this->indexTypeName );
	}

	private function getShardCount() {
		return $this->getConnection()->getSettings()->getShardCount( $this->indexTypeName );
	}

	/**
	 * @return int Maximum number of shards that can be allocated on a single elasticsearch
	 *  node. -1 for unlimited.
	 */
	private function getMaxShardsPerNode() {
		return $this->getConnection()->getSettings()->getMaxShardsPerNode( $this->indexTypeName );
	}

	private function updateVersions() {
		$this->log( "Updating tracking indexes..." );
		$store = new MetaVersionStore( $this->getConnection() );
		$store->update( $this->indexBaseName, $this->indexTypeName );
		$this->output( "ok.\n" );
	}

	/**
	 * @return \Elastica\Index being updated
	 */
	public function getIndex() {
		return $this->getConnection()->getIndex(
			$this->indexBaseName, $this->indexTypeName, $this->indexIdentifier
		);
	}

	/**
	 * @return \Elastica\Type
	 */
	public function getType() {
		return $this->getIndex()->getType( Connection::TITLE_SUGGEST_TYPE_NAME );
	}

	/**
	 * @return Elastica\Client
	 */
	protected function getClient() {
		return $this->getConnection()->getClient();
	}

	/**
	 * @return string name of the index type being updated
	 */
	protected function getIndexTypeName() {
		return $this->getConnection()->getIndexName( $this->indexBaseName, $this->indexTypeName );
	}
}

$maintClass = UpdateSuggesterIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
