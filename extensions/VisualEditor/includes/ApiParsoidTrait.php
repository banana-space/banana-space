<?php
/**
 * Helper functions for contacting Parsoid/RESTBase.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

trait ApiParsoidTrait {

	/**
	 * @var VirtualRESTServiceClient
	 */
	protected $serviceClient = null;

	/**
	 * @var LoggerInterface
	 */
	protected $logger = null;

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger() : LoggerInterface {
		return $this->logger ?: new NullLogger();
	}

	/**
	 * @param LoggerInterface $logger
	 */
	protected function setLogger( LoggerInterface $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Creates the virtual REST service object to be used in VE's API calls. The
	 * method determines whether to instantiate a ParsoidVirtualRESTService or a
	 * RestbaseVirtualRESTService object based on configuration directives: if
	 * $wgVirtualRestConfig['modules']['restbase'] is defined, RESTBase is chosen,
	 * otherwise Parsoid is used (either by using the MW Core config, or the
	 * VE-local one).
	 *
	 * @return VirtualRESTService the VirtualRESTService object to use
	 */
	protected function getVRSObject() : VirtualRESTService {
		global $wgVisualEditorParsoidAutoConfig;
		// the params array to create the service object with
		$params = [];
		// the VRS class to use, defaults to Parsoid
		$class = ParsoidVirtualRESTService::class;
		// The global virtual rest service config object, if any
		$vrs = $this->getConfig()->get( 'VirtualRestConfig' );
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
			// if restbase is available, use it
			$params = $vrs['modules']['restbase'];
			// backward compatibility
			$params['parsoidCompat'] = false;
			$class = RestbaseVirtualRESTService::class;
		} elseif ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} elseif ( $wgVisualEditorParsoidAutoConfig ) {
			$params = $vrs['modules']['parsoid'] ?? [];
			$params['restbaseCompat'] = true;
			// forward cookies on private wikis
			$params['forwardCookies'] = !MediaWikiServices::getInstance()
				->getPermissionManager()->isEveryoneAllowed( 'read' );
		} else {
			// No global modules defined, so no way to contact the document server.
			$this->dieWithError( 'apierror-visualeditor-docserver-unconfigured', 'no_vrs' );
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		if ( $params['forwardCookies'] ) {
			$params['forwardCookies'] = $this->getRequest()->getHeader( 'Cookie' );
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	/**
	 * Creates the object which directs queries to the virtual REST service, depending on the path.
	 *
	 * @return VirtualRESTServiceClient
	 */
	protected function getVRSClient() : VirtualRESTServiceClient {
		if ( !$this->serviceClient ) {
			$this->serviceClient = new VirtualRESTServiceClient(
				MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient() );
			$this->serviceClient->mount( '/restbase/', $this->getVRSObject() );
		}
		return $this->serviceClient;
	}

	/**
	 * Accessor function for all RESTbase requests
	 *
	 * @param Title $title The title of the page to use as the parsing context
	 * @param string $method The HTTP method, either 'GET' or 'POST'
	 * @param string $path The RESTbase api path
	 * @param array $params Request parameters
	 * @param array $reqheaders Request headers
	 * @return array The RESTbase server's response, 'code', 'reason', 'headers' and 'body'
	 */
	protected function requestRestbase(
		Title $title, string $method, string $path, array $params, array $reqheaders = []
	) : array {
		$request = [
			'method' => $method,
			'url' => '/restbase/local/v1/' . $path
		];
		if ( $method === 'GET' ) {
			$request['query'] = $params;
		} else {
			$request['body'] = $params;
		}
		// Should be synchronised with modules/ve-mw/init/ve.init.mw.ArticleTargetLoader.js
		$defaultReqHeaders = [
			'Accept' =>
				'text/html; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/HTML/2.0.0"',
			'Accept-Language' => self::getPageLanguage( $title )->getCode(),
			'User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
			'Api-User-Agent' => 'VisualEditor-MediaWiki/' . MW_VERSION,
		];
		// $reqheaders take precedence over $defaultReqHeaders
		$request['headers'] = $reqheaders + $defaultReqHeaders;
		$response = $this->getVRSClient()->run( $request );
		if ( $response['code'] === 200 && $response['error'] === "" ) {
			// If response was served directly from Varnish, use the response
			// (RP) header to declare the cache hit and pass the data to the client.
			$headers = $response['headers'];
			$rp = null;
			if ( isset( $headers['x-cache'] ) && strpos( $headers['x-cache'], 'hit' ) !== false ) {
				$rp = 'cached-response=true';
			}
			if ( $rp !== null ) {
				$resp = $this->getRequest()->response();
				$resp->header( 'X-Cache: ' . $rp );
			}
		} elseif ( $response['error'] !== '' ) {
			$this->dieWithError(
				[ 'apierror-visualeditor-docserver-http-error', wfEscapeWikiText( $response['error'] ) ],
				'apierror-visualeditor-docserver-http-error'
			);
		} else {
			// error null, code not 200
			$this->getLogger()->warning(
				__METHOD__ . ": Received HTTP {code} from RESTBase",
				[
					'code' => $response['code'],
					'trace' => ( new Exception )->getTraceAsString(),
					'response' => $response['body'],
					'requestPath' => $path,
					'requestIfMatch' => $reqheaders['If-Match'] ?? '',
				]
			);
			$this->dieWithError(
				[ 'apierror-visualeditor-docserver-http', $response['code'] ],
				'apierror-visualeditor-docserver-http'
			);
		}
		return $response;
	}

	/**
	 * Get the latest revision of a title
	 *
	 * @param Title $title Page title
	 * @return RevisionRecord A revision record
	 */
	protected function getLatestRevision( Title $title ) : RevisionRecord {
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$latestRevision = $revisionLookup->getRevisionByTitle( $title );
		if ( $latestRevision !== null ) {
			return $latestRevision;
		}
		$this->dieWithError( 'apierror-visualeditor-latestnotfound', 'latestnotfound' );
	}

	/**
	 * Get a specific revision of a title
	 *
	 * If the oldid is ommitted or is 0, the latest revision will be fetched.
	 *
	 * If the oldid is invalid, an API error will be reported.
	 *
	 * @param Title $title Page title
	 * @param int|string|null $oldid Optional revision ID.
	 *  Should be an integer but will validate and convert user input strings.
	 * @return RevisionRecord A revision record
	 */
	protected function getValidRevision( Title $title, $oldid = null ) : RevisionRecord {
		$revisionLookup = MediaWikiServices::getInstance()->getRevisionLookup();
		$revision = null;
		if ( $oldid === null || $oldid === 0 ) {
			return $this->getLatestRevision( $title );
		} else {
			$revisionRecord = $revisionLookup->getRevisionById( $oldid );
			if ( $revisionRecord ) {
				return $revisionRecord;
			}
		}
		$this->dieWithError( [ 'apierror-nosuchrevid', $oldid ], 'oldidnotfound' );
	}

	/**
	 * Request page HTML from RESTBase
	 *
	 * @param RevisionRecord $revision Page revision
	 * @return array The RESTBase server's response
	 */
	protected function requestRestbasePageHtml( RevisionRecord $revision ) : array {
		$title = Title::newFromLinkTarget( $revision->getPageAsLinkTarget() );
		return $this->requestRestbase(
			$title,
			'GET',
			'page/html/' . urlencode( $title->getPrefixedDBkey() ) .
				'/' . $revision->getId() .
				'?redirect=false&stash=true',
			[]
		);
	}

	/**
	 * Transform HTML to wikitext via Parsoid through RESTbase.
	 *
	 * @param string $path The RESTbase path of the transform endpoint
	 * @param Title $title The title of the page
	 * @param array $data An array of the HTML and the 'scrub_wikitext' option
	 * @param array $parserParams Parsoid parser parameters to pass in
	 * @param string|null $etag The ETag to set in the HTTP request header
	 * @return string Body of the RESTbase server's response
	 */
	protected function postData(
		string $path, Title $title, array $data, array $parserParams, ?string $etag
	) : string {
		$path .= urlencode( $title->getPrefixedDBkey() );
		if ( isset( $parserParams['oldid'] ) && $parserParams['oldid'] ) {
			$path .= '/' . $parserParams['oldid'];
		}
		// Adapted from RESTBase mwUtil.parseETag()
		// ETag is not expected when creating a new page (oldid=0)
		if ( isset( $parserParams['oldid'] ) && $parserParams['oldid'] && !preg_match( '/
			^(?:W\\/)?"?
			([^"\\/]+)
			(?:\\/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}))
			(?:\\/([^"]+))?
			"?$
		/x', $etag ) ) {
			$this->getLogger()->info(
				__METHOD__ . ": Received funny ETag from client: {etag}",
				[
					'etag' => $etag,
					'requestPath' => $path,
				]
			);
		}
		return $this->requestRestbase(
			$title,
			'POST', $path, $data,
			[ 'If-Match' => $etag ]
		)['body'];
	}

	/**
	 * Transform HTML to wikitext via Parsoid through RESTbase. Wrapper for ::postData().
	 *
	 * @param Title $title The title of the page
	 * @param string $html The HTML of the page to be transformed
	 * @param array $parserParams Parsoid parser parameters to pass in
	 * @param string|null $etag The ETag to set in the HTTP request header
	 * @return string Body of the RESTbase server's response
	 */
	protected function postHTML(
		Title $title, string $html, array $parserParams, ?string $etag
	) : string {
		return $this->postData(
			'transform/html/to/wikitext/', $title,
			[ 'html' => $html, 'scrub_wikitext' => 1 ], $parserParams, $etag
		);
	}

	/**
	 * Get the page language from a title, using the content language as fallback on special pages
	 * @param Title $title Title
	 * @return Language Content language
	 */
	public static function getPageLanguage( Title $title ) : Language {
		if ( $title->isSpecial( 'CollabPad' ) ) {
			// Use the site language for CollabPad, as getPageLanguage just
			// returns the interface language for special pages.
			// TODO: Let the user change the document language on multi-lingual sites.
			return MediaWikiServices::getInstance()->getContentLanguage();
		} else {
			return $title->getPageLanguage();
		}
	}

	/**
	 * @see ApiBase
	 * @param string|array|Message $msg See ApiErrorFormatter::addError()
	 * @param string|null $code See ApiErrorFormatter::addError()
	 * @param array|null $data See ApiErrorFormatter::addError()
	 * @param int|null $httpCode HTTP error code to use
	 */
	abstract public function dieWithError( $msg, $code = null, $data = null, $httpCode = null );

	/**
	 * @see ContextSource
	 * @return Config
	 */
	abstract public function getConfig();

	/**
	 * @see ContextSource
	 * @return WebRequest
	 */
	abstract public function getRequest();
}
