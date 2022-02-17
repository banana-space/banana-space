<?php

namespace CirrusSearch;

use BagOStuff;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MultiHttpClient;
use WANObjectCache;

/**
 * Base InterwikiResolver class.
 * Subclasses just need to provide the full matrix array
 * by implementing loadMatrix(), the resulting matrix will
 * be stored by this base class.
 */
abstract class BaseInterwikiResolver implements InterwikiResolver {
	const CONFIG_CACHE_TTL = 600;

	/** @var array[]|null full IW matrix (@see loadMatrix()) */
	private $matrix;

	/** @var SearchConfig main wiki config */
	protected $config;

	/** @var bool use cirrus config dump API */
	private $useConfigDumpApi;

	/**
	 * @var MultiHttpClient http client to fetch config of other wikis
	 */
	private $httpClient;

	/**
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @var BagOStuff
	 */
	private $srvCache;

	/**
	 * @param SearchConfig $config
	 * @param \MultiHttpClient|null $client http client to fetch cirrus config
	 * @param WANObjectCache|null $wanCache Cache object for caching repeated requests
	 * @param BagOStuff|null $srvCache Local server cache object for caching repeated requests
	 * @param InterwikiLookup|null $iwLookup
	 * @throws \Exception
	 */
	public function __construct(
		SearchConfig $config,
		MultiHttpClient $client = null,
		WANObjectCache $wanCache = null,
		BagOStuff $srvCache = null,
		InterwikiLookup $iwLookup = null
	) {
		$this->config = $config;
		$this->useConfigDumpApi = $this->config->get( 'CirrusSearchFetchConfigFromApi' );
		if ( $client === null ) {
			$client = MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient( [
				'connTimeout' => $this->config->get( 'CirrusSearchInterwikiHTTPConnectTimeout' ),
				'reqTimeout' => $this->config->get( 'CirrusSearchInterwikiHTTPTimeout' )
			] );
		}
		$this->httpClient = $client;
		$this->interwikiLookup = $iwLookup ?: MediaWikiServices::getInstance()->getInterwikiLookup();
		$this->srvCache = $srvCache ?: MediaWikiServices::getInstance()->getLocalServerObjectCache();
	}

	/**
	 * @return string[]
	 */
	public function getSisterProjectPrefixes() {
		$matrix = $this->getMatrix();
		return $matrix['sister_projects'] ?? [];
	}

	/**
	 * @return SearchConfig[] configs of sister project indexed by interwiki prefix
	 */
	public function getSisterProjectConfigs() {
		$prefixes = $this->getSisterProjectPrefixes();
		return $this->loadConfigFromAPI( $prefixes, [], [ $this, 'minimalSearchConfig' ] );
	}

	/**
	 * @param string $wikiId
	 * @return string|null
	 */
	public function getInterwikiPrefix( $wikiId ) {
		$matrix = $this->getMatrix();
		return $matrix['prefixes_by_wiki'][$wikiId] ?? null;
	}

	/**
	 * @param string $lang
	 * @return string[] a two elements array [ 'prefix', 'language' ]
	 */
	public function getSameProjectWikiByLang( $lang ) {
		$matrix = $this->getMatrix();
		// Most of the time the language is equal to the interwiki prefix.
		// But it's not always the case, use the language_map to identify the interwiki prefix first.
		$lang = $matrix['language_map'][$lang] ?? $lang;
		return isset( $matrix['cross_language'][$lang] ) ? [ $matrix['cross_language'][$lang], $lang ] : [];
	}

	/**
	 * @param string $lang
	 * @return SearchConfig[] single element array: [ interwiki => SearchConfig ]
	 */
	public function getSameProjectConfigByLang( $lang ) {
		$wikiAndPrefix = $this->getSameProjectWikiByLang( $lang );
		if ( empty( $wikiAndPrefix ) ) {
			return [];
		}
		list( $wiki, $prefix ) = $wikiAndPrefix;
		return $this->loadConfigFromAPI(
			[ $prefix => $wiki ],
			[ HashSearchConfig::FLAG_LOAD_CONT_LANG ],
			[ $this, 'minimalSearchConfig' ] );
	}

	/** @return array[] */
	private function getMatrix() {
		if ( $this->matrix === null ) {
			$this->matrix = $this->loadMatrix();

		}
		return $this->matrix;
	}

	/**
	 * Load the interwiki matric information
	 * The returned array must include the following keys:
	 * - sister_projects: an array with the list of sister wikis indexed by
	 *   interwiki prefix
	 * - cross_language: an array with the list of wikis running the same
	 *   project/site indexed by interwiki prefix
	 * - language_map: an array with the list of interwiki prefixes where
	 *   where the language code of the wiki does not match the prefix
	 * - prefixes_by_wiki: an array with the list of interwiki indexed
	 *   by wikiID
	 *
	 * The result of this method is stored in the current InterwikiResolver instance
	 * so it can be called only once per request.
	 *
	 * return array[]
	 */
	abstract protected function loadMatrix();

	/**
	 * @param string[] $wikis
	 * @param string[] $hashConfigFlags constructor flags for SearchConfig
	 * @param callable $fallbackConfig function to load the config if the
	 * api is not usable or if a failure occurs
	 * @return SearchConfig[] config indexed by iw prefix
	 */
	private function loadConfigFromAPI( $wikis, array $hashConfigFlags, $fallbackConfig ) {
		$endpoints = [];
		foreach ( $wikis as $prefix => $wiki ) {
			$iw = $this->interwikiLookup->fetch( $prefix );
			if ( !$iw || !$this->useConfigDumpApi || !$iw->isLocal() ) {
				continue;
			}
			$api = $iw->getAPI();
			if ( !$api ) {
				$parts = parse_url( $iw->getURL() );
				if ( !isset( $parts['host'] ) ) {
					continue;
				}
				$api = $parts['scheme'] ?? 'http';
				$api .= '://' . $parts['host'];
				$api .= isset( $parts['port'] ) ? ':' . $parts['port'] : '';
				$api .= '/w/api.php';
			}
			$endpoints[$prefix] = [ 'url' => $api, 'wiki' => $wiki ];
		}

		if ( !empty( $endpoints ) ) {
			$prefixes = array_keys( $endpoints );
			asort( $prefixes );
			$cacheKey = implode( '-', $prefixes );
			$configs = $this->srvCache->getWithSetCallback(
				$this->srvCache->makeKey( 'cirrussearch-load-iw-config', $cacheKey ),
				self::CONFIG_CACHE_TTL,
				function () use ( $endpoints ) {
					return $this->sendConfigDumpRequest( $endpoints );
				}
			);
		} else {
			$configs = [];
		}
		$retValue = [];
		foreach ( $wikis as $prefix => $wiki ) {
			if ( isset( $configs[$prefix] ) ) {
				$config = $configs[$prefix];
				$config['_wikiID'] = $wiki;

				$retValue[$prefix] = new HashSearchConfig(
					$config,
					array_merge( $hashConfigFlags, [ HashSearchConfig::FLAG_INHERIT ] )
				);
			} else {
				$retValue[$prefix] = $fallbackConfig( $wiki, $hashConfigFlags );
			}
		}
		return $retValue;
	}

	/**
	 * @param array[] $endpoints list of arrays containing 'url' and 'wiki', indexed by iw prefix
	 * @return array[] list of array containing extracted config vars, failed wikis
	 * are not returned.
	 */
	private function sendConfigDumpRequest( $endpoints ) {
		$logger = LoggerFactory::getInstance( 'CirrusSearch' );
		$reqs = [];
		foreach ( $endpoints as $prefix => $info ) {
			$reqs[$prefix] = [
				'method' => 'GET',
				'url' => $info['url'],
				'query' => [
					'action' => 'cirrus-config-dump',
					'format' => 'json',
					'formatversion' => '2',
				]
			];
		}
		if ( empty( $reqs ) ) {
			return [];
		}
		$responses = $this->httpClient->runMulti( $reqs );
		$configs = [];
		foreach ( $responses as $prefix => $response ) {
			if ( $response['response']['code'] !== 200 ) {
				$logger->warning(
					'Failed to fetch config for {wiki} at {url}: ' .
					'http status {httpstatus} : {clienterror}',
					[
						'wiki' => $endpoints[$prefix]['wiki'],
						'url' => $endpoints[$prefix]['url'],
						'httpstatus' => $response['response']['code'],
						'clienterror' => $response['response']['error']
					]
				);
				continue;
			}

			$data = json_decode( $response['response']['body'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$logger->warning(
					'Failed to fetch config for {wiki} at {url}: ' .
					'json error code {jsonerrorcode}',
					[
						'wiki' => $endpoints[$prefix]['wiki'],
						'url' => $endpoints[$prefix]['url'],
						'jsonerrorcode' => json_last_error()
					]
				);
				continue;
			}

			if ( isset( $data['error'] ) ) {
				$logger->warning(
					'Failed to fetch config for {wiki} at {url}: {apierrormessage}',
					[
						'wiki' => $endpoints[$prefix]['wiki'],
						'url' => $endpoints[$prefix]['url'],
						'apierrormessage' => $data['error']
					]
				);
				continue;
			}
			unset( $data['warnings'] );
			$configs[$prefix] = $data;
		}
		return $configs;
	}

	/**
	 * Minimal config needed to run a search on a target wiki
	 * living on the same cluster as the host wiki
	 *
	 * @param string $wiki
	 * @param string[] $hashConfigFlags constructor flags for HashSearchConfig
	 * @return SearchConfig
	 */
	protected function minimalSearchConfig( $wiki, array $hashConfigFlags ) {
		return new HashSearchConfig(
			[
				'_wikiID' => $wiki,
				'CirrusSearchIndexBaseName' => $wiki,
			],
			array_merge( [ HashSearchConfig::FLAG_INHERIT ], $hashConfigFlags )
		);
	}
}
