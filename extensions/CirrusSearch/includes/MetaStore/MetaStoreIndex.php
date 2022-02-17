<?php

namespace CirrusSearch\MetaStore;

use CirrusSearch\Connection;
use CirrusSearch\Maintenance\AnalysisConfigBuilder;
use CirrusSearch\Maintenance\AnalysisFilter;
use CirrusSearch\Maintenance\ConfigUtils;
use CirrusSearch\Maintenance\Printer;
use CirrusSearch\SearchConfig;

/**
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

/**
 * Utility class to manage a multipurpose metadata storage index for cirrus.
 * This store is used to store persistent states related to administrative
 * tasks (index settings upgrade, frozen indices, ...).
 */
class MetaStoreIndex {
	/**
	 * @const int major version, increment when adding an incompatible change
	 * to settings or mappings.
	 */
	const METASTORE_MAJOR_VERSION = 2;

	/**
	 * @const int minor version increment only when adding a new field to
	 * an existing mapping or a new mapping.
	 */
	const METASTORE_MINOR_VERSION = 0;

	/**
	 * @const string the doc id used to store version information related
	 * to the meta store itself. This value is not supposed to be changed.
	 */
	const METASTORE_VERSION_DOCID = 'metastore_version';

	/**
	 * @const string index name
	 */
	const INDEX_NAME = 'mw_cirrus_metastore';

	/**
	 * @const string previous index name (bc code)
	 */
	const OLD_INDEX_NAME = 'mw_cirrus_versions';

	/**
	 * @const string type for storing internal data
	 */
	const INTERNAL_TYPE = 'internal';

	/**
	 * @var Connection
	 */
	private $connection;

	/**
	 * @var \Elastica\Client
	 */
	private $client;

	/**
	 * @var Printer|null output handler
	 */
	private $out;

	/**
	 * @var SearchConfig
	 */
	private $config;

	/**
	 * @var ConfigUtils
	 */
	private $configUtils;

	/**
	 * @param Connection $connection
	 * @param Printer $out
	 * @param SearchConfig $config
	 */
	public function __construct(
		Connection $connection, Printer $out, SearchConfig $config
	) {
		$this->connection = $connection;
		$this->client = $connection->getClient();
		$this->configUtils = new ConfigUtils( $this->client, $out );
		$this->out = $out;
		$this->config = $config;
	}

	/**
	 * @return MetaVersionStore
	 */
	public function versionStore() {
		return new MetaVersionStore( $this->connection );
	}

	/**
	 * @return MetaNamespaceStore
	 */
	public function namespaceStore() {
		return new MetaNamespaceStore( $this->connection, $this->config->getWikiId() );
	}

	/**
	 * @return MetaSaneitizeJobStore
	 */
	public function saneitizeJobStore() {
		return new MetaSaneitizeJobStore( $this->connection );
	}

	/**
	 * @return MetaStore[]
	 */
	public function stores() {
		return [
			'version' => $this->versionStore(),
			'namespace' => $this->namespaceStore(),
			'saneitize' => $this->saneitizeJobStore(),
		];
	}

	/**
	 * Compare the current metastore version to an expected minimum
	 * acceptable version.
	 *
	 * @param int[] $expected The metastore version to expected, as a
	 *  two element array of major then minor version.
	 * @return bool True when the metastore index in elasticsearch matches
	 *  requirements
	 */
	public function versionIsAtLeast( array $expected ) {
		// $version >= $expected
		return (bool)version_compare(
			implode( '.', $this->metastoreVersion() ),
			implode( '.', $expected ),
			'>='
		);
	}

	/**
	 * @return \Elastica\Index|null Index on creation, or null if the index
	 *  already exists.
	 */
	public function createIfNecessary() {
		// If the mw_cirrus_metastore alias does not exists it
		// means we need to create everything from scratch.
		if ( self::cirrusReady( $this->connection ) ) {
			return null;
		}
		$this->log( self::INDEX_NAME . " missing, creating new metastore index.\n" );
		$newIndex = $this->createNewIndex();
		$this->switchAliasTo( $newIndex );
		return $newIndex;
	}

	public function createOrUpgradeIfNecessary() {
		$this->fixOldName();
		$newIndex = $this->createIfNecessary();
		if ( $newIndex === null ) {
			list( $major, $minor ) = $this->metastoreVersion();
			if ( $major < self::METASTORE_MAJOR_VERSION ) {
				$this->log( self::INDEX_NAME . " major version mismatch upgrading.\n" );
				$this->majorUpgrade();
			} elseif ( $major == self::METASTORE_MAJOR_VERSION &&
				$minor < self::METASTORE_MINOR_VERSION
			) {
				$this->log(
					self::INDEX_NAME . " minor version mismatch trying to upgrade mapping.\n"
				);
				$this->minorUpgrade();
			} elseif ( $major > self::METASTORE_MAJOR_VERSION ||
				$minor > self::METASTORE_MINOR_VERSION
			) {
				throw new \Exception(
					"Metastore version $major.$minor found, cannot upgrade to a lower version: " .
						self::METASTORE_MAJOR_VERSION . "." . self::METASTORE_MINOR_VERSION
				);
			}
		}
	}

	private function buildIndexConfiguration() {
		$plugins = $this->configUtils->scanAvailablePlugins(
			$this->config->get( 'CirrusSearchBannedPlugins' ) );
		$filter = new AnalysisFilter();
		list( $analysis, $mappings ) = $filter->filterAnalysis(
			// Why 'aa'? It comes first? Hoping it receives generic language treatment.
			( new AnalysisConfigBuilder( 'aa', $plugins ) )->buildConfig(),
			$this->buildMapping()
		);

		return [
			// Don't forget to update METASTORE_MAJOR_VERSION when changing something
			// in the settings.
			'settings' => [
				'number_of_shards' => 1,
				'auto_expand_replicas' => '0-2',
				'analysis' => $analysis,
			],
			'mappings' => $mappings,
		];
	}

	/**
	 * Create a new metastore index.
	 * @param string $suffix index suffix
	 * @return \Elastica\Index the newly created index
	 */
	public function createNewIndex( $suffix = 'first' ) {
		$name = self::INDEX_NAME . '_' . $suffix;
		$this->log( "Creating metastore index... $name" );
		// @todo utilize $this->getIndex()->create(...) once it supports setting
		// the master_timeout parameter.
		$index = $this->client->getIndex( $name );
		$index->request(
			'',
			\Elastica\Request::PUT,
			$this->buildIndexConfiguration(),
			[ 'master_timeout' => $this->getMasterTimeout() ]
		);
		$this->log( " ok\n" );
		$this->configUtils->waitForGreen( $index->getName(), 3600 );
		$this->storeMetastoreVersion( $index );
		return $index;
	}

	/**
	 * Increment :
	 *   - self:METASTORE_MAJOR_VERSION for incompatible changes
	 *   - self:METASTORE_MINOR_VERSION when adding new field or new mappings
	 * @return array[] the mapping
	 */
	private function buildMapping() {
		$properties = [
			'type' => [ 'type' => 'keyword' ],
			'wiki' => [ 'type' => 'keyword' ],
		];

		foreach ( $this->stores() as $store ) {
			// TODO: Reuse field definition implementations from page indices?
			$storeProperties = $store->buildIndexProperties();
			if ( !$storeProperties ) {
				continue;
			}
			$overlap = array_intersect_key( $properties, $storeProperties );
			if ( $overlap ) {
				throw new \Exception( 'Metastore property overlap on: ' . implode( ', ', array_keys( $overlap ) ) );
			}
			$properties += $storeProperties;
		}

		return [
			self::INDEX_NAME => [
				'dynamic' => false,
				'properties' => $properties,
			],
		];
	}

	private function minorUpgrade() {
		$config = $this->buildIndexConfiguration();
		$index = $this->connection->getIndex( self::INDEX_NAME );
		foreach ( $this->buildMapping() as $type => $mapping ) {
			$index->getType( $type )->request(
				'_mapping',
				\Elastica\Request::PUT,
				$config['mappings'],
				[
					'master_timeout' => $this->getMasterTimeout(),
				]
			);
		}
		$this->storeMetastoreVersion( $index );
	}

	/**
	 * Switch the mw_cirrus_metastore alias to this new index name.
	 * @param \Elastica\Index $index
	 */
	private function switchAliasTo( $index ) {
		$name = $index->getName();
		$oldIndexName = $this->getAliasedIndexName();
		if ( $oldIndexName !== null ) {
			$this->log( "Switching " . self::INDEX_NAME . " alias from $oldIndexName to $name.\n" );
		} else {
			$this->log( "Creating " . self::INDEX_NAME . " alias to $name.\n" );
		}

		if ( $oldIndexName == $name ) {
			throw new \Exception(
				"Cannot switch aliases old and new index names are identical: $name"
			);
		}
		// Create the alias
		$path = '_aliases';
		$data = [ 'actions' => [
			[
				'add' => [
					'index' => $name,
					'alias' => self::INDEX_NAME,
				]
			],
		] ];
		if ( $oldIndexName !== null ) {
			$data['actions'][] = [
					'remove' => [
						'index' => $oldIndexName,
						'alias' => self::INDEX_NAME,
					]
				];
		}
		$this->client->request( $path, \Elastica\Request::POST, $data,
			[ 'master_timeout' => $this->getMasterTimeout() ] );
		if ( $oldIndexName !== null ) {
			$this->log( "Deleting old index $oldIndexName\n" );
			$this->connection->getIndex( $oldIndexName )->delete();
		}
	}

	/**
	 * @return string|null the current index behind the self::INDEX_NAME
	 * alias or null if the alias does not exist
	 */
	private function getAliasedIndexName() {
		// FIXME: Elastica seems to have trouble parsing the error reason
		// for this endpoint. Running a simple HEAD first to check if it
		// exists
		$resp = $this->client->request( '_alias/' . self::INDEX_NAME, \Elastica\Request::HEAD, [] );
		if ( $resp->getStatus() === 404 ) {
			return null;
		}
		$resp = $this->client->request( '_alias/' . self::INDEX_NAME, \Elastica\Request::GET, [] );
		$indexName = null;
		foreach ( $resp->getData() as $index => $aliases ) {
			if ( isset( $aliases['aliases'][self::INDEX_NAME] ) ) {
				if ( $indexName !== null ) {
					throw new \Exception( "Multiple indices are aliased with " . self::INDEX_NAME .
						", please fix manually." );
				}
				$indexName = $index;
			}
		}
		return $indexName;
	}

	private function majorUpgrade() {
		$plugins = $this->configUtils->scanAvailableModules();
		if ( !array_search( 'reindex', $plugins ) ) {
			throw new \Exception( "The reindex module is mandatory to upgrade the metastore" );
		}
		$index = $this->createNewIndex( (string)time() );
		// Reindex everything except the internal type, it's not clear
		// yet if we just need to filter the metastore version info or
		// the whole internal type. Currently we only use the internal
		// type for storing the metastore version.
		$reindex = [
			'source' => [
				'index' => self::INDEX_NAME,
				'query' => [
					'bool' => [
						'must_not' => [
							[ 'term' => [ 'type' => self::INTERNAL_TYPE ] ],
							// metastore prior to 1.0 used elasticsearch index
							// 'type' instead of a type field
							[ 'type' => [ 'value' => self::INTERNAL_TYPE ] ],
						],
					]
				],
			],
			'dest' => [ 'index' => $index->getName() ],
		];
		if ( !$this->versionIsAtLeast( [ 1, 0 ] ) ) {
			// FROZEN_TYPE assumed to be empty
			// MetaVersionStore docs need the index_name field added
			// and doc id's prefixed.
			// MetaSaneitizeJobStore  already prefixed
			// INTERNAL_TYPE is not copied
			$version = MetaVersionStore::METASTORE_TYPE;
			$sanitize = MetaSaneitizeJobStore::METASTORE_TYPE;
			$indexName = self::INDEX_NAME;
			$reindex['script'] = [
				'lang' => 'painless',
				'source' => <<<EOD
ctx._source.type = ctx._type;
if (ctx._type == '{$version}') {
	ctx._source.index_name = ctx._id;
	ctx._id = ctx._type + '-' + ctx._id;
}
if (ctx._type == '{$sanitize}') {
	ctx._source.wiki = ctx._source.sanitize_job_wiki;
}
ctx._type = '{$indexName}';
EOD
			];
		}
		// reindex is extremely fast so we can wait for it
		// we might consider using the task manager if this process
		// becomes longer and/or prone to curl timeouts
		$this->client->request( '_reindex',
			\Elastica\Request::POST,
			$reindex,
			[ 'wait_for_completion' => 'true' ]
		);
		$index->refresh();
		$this->switchAliasTo( $index );
	}

	/**
	 * BC strategy to reuse mw_cirrus_versions as the new mw_cirrus_metastore
	 * If mw_cirrus_versions exists with no mw_cirrus_metastore
	 */
	private function fixOldName() {
		if ( !$this->client->getIndex( self::OLD_INDEX_NAME )->exists() ) {
			return;
		}
		// Old mw_cirrus_versions exists, if mw_cirrus_metastore alias does not
		// exist we must create it
		if ( !$this->client->getIndex( self::INDEX_NAME )->exists() ) {
			$this->log( "Adding transition alias to " . self::OLD_INDEX_NAME . "\n" );
			// Old one exists but new one does not
			// we need to create an alias
			$index = $this->client->getIndex( self::OLD_INDEX_NAME );
			$this->switchAliasTo( $index );
			// The version check (will return 0.0 for
			// mw_cirrus_versions) should schedule an minor or
			// major upgrade.
		}
	}

	/**
	 * @return int[] major, minor version
	 */
	public function metastoreVersion() {
		return self::getMetastoreVersion( $this->connection );
	}

	/**
	 * @return int[] major, minor version
	 */
	public function runtimeVersion() {
		return [ self::METASTORE_MAJOR_VERSION, self::METASTORE_MINOR_VERSION ];
	}

	/**
	 * @param \Elastica\Index $index new index
	 */
	private function storeMetastoreVersion( $index ) {
		$index->getType( self::INDEX_NAME )->addDocument(
			new \Elastica\Document(
				self::METASTORE_VERSION_DOCID,
				[
					'type' => self::INTERNAL_TYPE,
					'metastore_major_version' => self::METASTORE_MAJOR_VERSION,
					'metastore_minor_version' => self::METASTORE_MINOR_VERSION,
				]
			)
		);
	}

	/**
	 * @param string $msg log message
	 */
	private function log( $msg ) {
		if ( $this->out ) {
			$this->out->output( $msg );
		}
	}

	public static function getElasticaType( Connection $connection ) {
		return $connection->getIndex( self::INDEX_NAME )->getType( self::INDEX_NAME );
	}

	public function elasticaType() {
		return self::getElasticaType( $this->connection );
	}

	/**
	 * Check if cirrus is ready by checking if some indices have been created on this cluster
	 * @param Connection $connection
	 * @return bool
	 */
	public static function cirrusReady( Connection $connection ) {
		return $connection->getIndex( self::INDEX_NAME )->exists() ||
			$connection->getIndex( self::OLD_INDEX_NAME )->exists();
	}

	/**
	 * @param Connection $connection
	 * @return int[] the major and minor version of the meta store
	 * [0, 0] means that the metastore has never been created
	 */
	public static function getMetastoreVersion( Connection $connection ) {
		try {
			$doc = self::getElasticaType( $connection )
				->getDocument( self::METASTORE_VERSION_DOCID );
		} catch ( \Elastica\Exception\NotFoundException $e ) {
			return [ 0, 0 ];
		} catch ( \Elastica\Exception\ResponseException $e ) {
			// BC code in case the metastore alias does not exist yet
			$fullError = $e->getResponse()->getFullError();
			if ( isset( $fullError['type'] )
				&& $fullError['type'] === 'index_not_found_exception'
				&& isset( $fullError['index'] )
				&& $fullError['index'] === self::INDEX_NAME
			) {
				return [ 0, 0 ];
			}
			throw $e;
		}
		return [
			(int)$doc->get( 'metastore_major_version' ),
			(int)$doc->get( 'metastore_minor_version' )
		];
	}

	private function getMasterTimeout() {
		return $this->config->get( 'CirrusSearchMasterTimeout' );
	}
}
