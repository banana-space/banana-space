<?php

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config\Api;

use Wikimedia\Parsoid\Config\DataAccess as IDataAccess;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Config\PageContent;
use Wikimedia\Parsoid\Mocks\MockPageContent;
use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * DataAccess via MediaWiki's Action API
 *
 * Note this is intended for testing, not performance.
 */
class DataAccess implements IDataAccess {

	/** @var ApiHelper */
	private $api;

	/**
	 * @name Caching
	 * @todo Someone should librarize MediaWiki core's MapCacheLRU so we can
	 *  pull it in via composer and use it here.
	 * @{
	 */

	private const MAX_CACHE_LEN = 100;

	/**
	 * @var array
	 */
	private $cache = [];

	/**
	 * @var SiteConfig
	 */
	private $siteConfig = null;

	/**
	 * Get from cache
	 * @param string $key
	 * @return mixed
	 */
	private function getCache( string $key ) {
		if ( isset( $this->cache[$key] ) ) {
			$ret = $this->cache[$key];
			// The LRU cache uses position in the array to indicate recency, so
			// move the accessed key to the end.
			unset( $this->cache[$key] );
			$this->cache[$key] = $ret;
			return $ret;
		}
		return null;
	}

	/**
	 * Set a value into cache
	 * @param string $key
	 * @param mixed $value Not null.
	 */
	private function setCache( string $key, $value ): void {
		if ( isset( $this->cache[$key] ) ) {
			// The LRU cache uses position in the array to indicate recency, so
			// remove the old entry so the new version goes at the end.
			unset( $this->cache[$key] );
		} elseif ( count( $this->cache ) >= self::MAX_CACHE_LEN ) {
			reset( $this->cache );
			$evictKey = key( $this->cache );
			unset( $this->cache[$evictKey] );
		}
		$this->cache[$key] = $value;
	}

	/** @} */

	/**
	 * @param ApiHelper $api
	 * @param ?SiteConfig $siteConfig
	 * @param array $opts
	 */
	public function __construct( ApiHelper $api, ?SiteConfig $siteConfig, array $opts ) {
		$this->api = $api;
		$this->siteConfig = $siteConfig;
	}

	/** @inheritDoc */
	public function getPageInfo( PageConfig $pageConfig, array $titles ): array {
		if ( !$titles ) {
			return [];
		}

		$ret = [];
		foreach ( array_chunk( $titles, 50 ) as $batch ) {
			$data = $this->api->makeRequest( [
				'action' => 'query',
				'prop' => 'info|pageprops',
				'ppprop' => 'disambiguation',
				'titles' => implode( '|', $batch ),
			] )['query'];
			$norm = [];
			if ( isset( $data['normalized'] ) ) {
				foreach ( $data['normalized'] as $n ) {
					$from = $n['from'];
					if ( $n['fromencoded'] ) {
						$from = rawurldecode( $from );
					}
					$norm[$from] = $n['to'];
				}
			}
			$pages = [];
			foreach ( $data['pages'] as $p ) {
				$pages[$p['title']] = $p;
			}
			foreach ( $batch as $title ) {
				$ttitle = $title;
				while ( isset( $norm[$ttitle] ) ) {
					$ttitle = $norm[$ttitle];
				}
				$page = $pages[$ttitle] ?? [];
				$ret[$title] = [
					'pageId' => $page['pageid'] ?? null,
					'revId' => $page['lastrevid'] ?? null,
					'missing' => $page['missing'] ?? false,
					'known' => ( $page['known'] ?? false ),
					'redirect' => $page['redirect'] ?? false,
					'disambiguation' => ( $page['pageprops']['disambiguation'] ?? false ) !== false,
					'invalid' => $page['invalid'] ?? false,
				];
				if ( !( $ret[$title]['missing'] || $ret[$title]['invalid'] ) ) {
					$ret[$title]['known'] = true;
				}
			}
		}

		return $ret;
	}

	/** @inheritDoc */
	public function getFileInfo( PageConfig $pageConfig, array $files ): array {
		$sc = $this->siteConfig;
		$ret = array_fill_keys( array_keys( $files ), null );
		if ( $sc && $sc->hasVideoInfo() ) {
			$prefix = "vi";
			$propName = "videoinfo";
		} else {
			$prefix = "ii";
			$propName = "imageinfo";
		}
		$apiArgs = [
			'action' => 'query',
			'format' => 'json',
			'formatversion' => 2,
			'rawcontinue' => 1,
			'prop' => $propName,
			"${prefix}badfilecontexttitle" => $pageConfig->getTitle(),
			"${prefix}prop" => implode( '|', [
				'mediatype', 'mime', 'size', 'url', 'badfile'
			] )
		];
		if ( $prefix === 'vi' ) {
			$apiArgs["viprop"] .= '|derivatives|timedtext';
		}
		foreach ( $files as $name => $dims ) {
			$imgNS = $sc ? $sc->namespaceName( $sc->canonicalNamespaceId( "File" ) ) : "File";
			$apiArgs['titles'] = "$imgNS:$name";
			if ( isset( $dims['width'] ) && $dims['width'] !== null ) {
				$apiArgs["${prefix}urlwidth"] = $dims['width'];
				if ( isset( $dims['page'] ) ) {
					$apiArgs["${prefix}urlparam"] = "page{$dims['page']}-{$dims['width']}px";
				}
			}
			if ( isset( $dims['height'] ) && $dims['height'] !== null ) {
				$apiArgs["${prefix}urlheight"] = $dims['height'];
			}
			if ( isset( $dims['seek'] ) ) {
				$apiArgs["${prefix}urlparam"] = "seek={$dims['seek']}";
			}
			$data = $this->api->makeRequest( $apiArgs );

			$fileinfo = $data['query']['pages'][0][$propName][0]; // Expect exactly 1 row
			if ( isset( $fileinfo['filemissing'] ) ) {
				$fileinfo = null;
			} else {
				self::stripProto( $fileinfo, 'url' );
				self::stripProto( $fileinfo, 'thumburl' );
				self::stripProto( $fileinfo, 'descriptionurl' );
				self::stripProto( $fileinfo, 'descriptionshorturl' );
				foreach ( $fileinfo['responsiveUrls'] ?? [] as $density => $url ) {
					self::stripProto( $fileinfo['responsiveUrls'], (string)$density );
				}
				if ( $prefix === 'vi' ) {
					foreach ( $fileinfo['thumbdata']['derivatives'] ?? [] as $j => $d ) {
						self::stripProto( $fileinfo['thumbdata']['derivatives'][$j], 'src' );
					}
					foreach ( $fileinfo['thumbdata']['timedtext'] ?? [] as $j => $d ) {
						self::stripProto( $fileinfo['thumbdata']['timedtext'][$j], 'src' );
					}
				}
			}
			$ret[$name] = $fileinfo;
		}

		return $ret;
	}

	/**
	 * Convert the given URL into protocol-relative form.
	 *
	 * @param ?array &$obj
	 * @param string $key
	 */
	private static function stripProto( ?array &$obj, string $key ): void {
		if ( $obj !== null && !empty( $obj[$key] ) ) {
			$obj[$key] = preg_replace( '#^https?://#', '//', $obj[$key] );
		}
	}

	/** @inheritDoc */
	public function doPst( PageConfig $pageConfig, string $wikitext ): string {
		$key = implode( ':', [ 'pst', md5( $pageConfig->getTitle() ) , md5( $wikitext ) ] );
		$ret = $this->getCache( $key );
		if ( $ret === null ) {
			$data = $this->api->makeRequest( [
				'action' => 'parse',
				'title' => $pageConfig->getTitle(),
				'text' => $wikitext,
				'contentmodel' => 'wikitext',
				'onlypst' => 1,
			] );
			$ret = $data['parse']['text'];
			$this->setCache( $key, $ret );
		}
		return $ret;
	}

	/** @inheritDoc */
	public function parseWikitext( PageConfig $pageConfig, string $wikitext ): array {
		$revid = $pageConfig->getRevisionId();
		$key = implode( ':', [ 'parse', md5( $pageConfig->getTitle() ), md5( $wikitext ), $revid ] );
		$ret = $this->getCache( $key );
		if ( $ret === null ) {
			$params = [
				'action' => 'parse',
				'title' => $pageConfig->getTitle(),
				'text' => $wikitext,
				'contentmodel' => 'wikitext',
				'prop' => 'text|modules|jsconfigvars|categories',
				'disablelimitreport' => 1,
				'wrapoutputclass' => '',
			];
			if ( $revid !== null ) {
				$params['revid'] = $revid;
			}
			$data = $this->api->makeRequest( $params )['parse'];

			$cats = [];
			foreach ( $data['categories'] as $c ) {
				$cats[$c['category']] = $c['sortkey'];
			}

			$ret = [
				'html' => $data['text'],
				'modules' => $data['modules'],
				'modulescripts' => $data['modulescripts'],
				'modulestyles' => $data['modulestyles'],
				'categories' => $cats,
			];
			$this->setCache( $key, $ret );
		}
		return $ret;
	}

	/** @inheritDoc */
	public function preprocessWikitext( PageConfig $pageConfig, string $wikitext ): array {
		$revid = $pageConfig->getRevisionId();
		$key = implode( ':', [ 'preprocess', md5( $pageConfig->getTitle() ), md5( $wikitext ), $revid ] );
		$ret = $this->getCache( $key );
		if ( $ret === null ) {
			$params = [
				'action' => 'expandtemplates',
				'title' => $pageConfig->getTitle(),
				'text' => $wikitext,
				'prop' => 'properties|wikitext|categories|modules|jsconfigvars',
			];
			if ( $revid !== null ) {
				$params['revid'] = $revid;
			}
			$data = $this->api->makeRequest( $params )['expandtemplates'];

			$cats = [];
			foreach ( ( $data['categories'] ?? [] ) as $c ) {
				$cats[$c['category']] = $c['sortkey'];
			}

			$ret = [
				'wikitext' => $data['wikitext'],
				'modules' => $data['modules'] ?? [],
				'modulescripts' => $data['modulescripts'] ?? [],
				'modulestyles' => $data['modulestyles'] ?? [],
				'categories' => $cats,
				'properties' => $data['properties'] ?? [],
			];
			$this->setCache( $key, $ret );
		}
		return $ret;
	}

	/** @inheritDoc */
	public function fetchPageContent(
		PageConfig $pageConfig, string $title, int $oldid = 0
	): ?PageContent {
		$key = implode( ':', [ 'content', md5( $title ), $oldid ] );
		$ret = $this->getCache( $key );
		if ( $ret === null ) {
			$params = [
				'action' => 'query',
				'prop' => 'revisions',
				'rvprop' => 'content',
				'rvslots' => '*',
			];
			if ( $oldid !== 0 ) {
				$params['revids'] = $oldid;
			} else {
				$params['titles'] = $title;
				$params['rvlimit'] = 1;
			}

			$data = $this->api->makeRequest( $params );
			$pageData = $data['query']['pages'][0];
			if ( isset( $pageData['missing'] ) ) {
				return null;
			} else {
				$ret = $pageData['revisions'][0]['slots'];
				// PORT-FIXME set the redirect field if needed
				$this->setCache( $key, $ret );
			}
		}
		return new MockPageContent( $ret );
	}

	/** @inheritDoc */
	public function fetchTemplateData( PageConfig $pageConfig, string $title ): ?array {
		$key = implode( ':', [ 'templatedata', md5( $title ) ] );
		$ret = $this->getCache( $key );
		if ( $ret === null ) {
			$data = $this->api->makeRequest( [
				'action' => 'templatedata',
				'includeMissingTitles' => 1,
				'titles' => $title,
				'redirects' => 1,
			] )['pages'];
			$ret = reset( $data );
			$this->setCache( $key, $ret );
		}
		return $ret;
	}

	/** @inheritDoc */
	public function logLinterData( PageConfig $pageConfig, array $lints ): void {
		foreach ( $lints as $l ) {
			error_log( PHPUtils::jsonEncode( $l ) );
		}
	}

	/**
	 * @param array $parsoidSettings
	 * @return DataAccess
	 */
	public static function fromSettings( array $parsoidSettings ): DataAccess {
		$api = ApiHelper::fromSettings( $parsoidSettings );
		return new DataAccess( $api, null, [] );
	}

}
