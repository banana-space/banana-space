<?php
/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
declare( strict_types = 1 );

namespace MWParsoid\Rest\Handler;

use Composer\Semver\Semver;
use ExtensionRegistry;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use LogicException;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MobileContext;
use MWParsoid\Config\PageConfigFactory;
use MWParsoid\ParsoidServices;
use MWParsoid\Rest\FormatHelper;
use RequestContext;
use Title;
use UIDGenerator;
use Wikimedia\Http\HttpAcceptParser;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ValidationException;
use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\Parsoid\Core\ClientError;
use Wikimedia\Parsoid\Core\PageBundle;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\Core\SelserData;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Utils\ContentUtils;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMDataUtils;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\PHPUtils;
use Wikimedia\Parsoid\Utils\Timing;

/**
 * Base class for Parsoid handlers.
 */
abstract class ParsoidHandler extends Handler {
	// TODO logging, timeouts(?), CORS
	// TODO content negotiation (routes.js routes.acceptable)
	// TODO handle MaxConcurrentCallsError (pool counter?)

	/** @var array Parsoid-specific settings array from $config */
	private $parsoidSettings;

	/** @var SiteConfig */
	protected $siteConfig;

	/** @var PageConfigFactory */
	protected $pageConfigFactory;

	/** @var DataAccess */
	protected $dataAccess;

	/** @var ExtensionRegistry */
	protected $extensionRegistry;

	/** @var StatsdDataFactoryInterface A statistics aggregator */
	protected $metrics;

	/** @var array */
	private $requestAttributes;

	/**
	 * @return static
	 */
	public static function factory(): ParsoidHandler {
		$services = MediaWikiServices::getInstance();
		$parsoidServices = new ParsoidServices( $services );
		// @phan-suppress-next-line PhanTypeInstantiateAbstractStatic
		return new static(
			$services->getMainConfig()->get( 'ParsoidSettings' ),
			$parsoidServices->getParsoidSiteConfig(),
			$parsoidServices->getParsoidPageConfigFactory(),
			$parsoidServices->getParsoidDataAccess()
		);
	}

	/**
	 * @param array $parsoidSettings
	 * @param SiteConfig $siteConfig
	 * @param PageConfigFactory $pageConfigFactory
	 * @param DataAccess $dataAccess
	 */
	public function __construct(
		array $parsoidSettings,
		SiteConfig $siteConfig,
		PageConfigFactory $pageConfigFactory,
		DataAccess $dataAccess
	) {
		$this->parsoidSettings = $parsoidSettings;
		$this->siteConfig = $siteConfig;
		$this->pageConfigFactory = $pageConfigFactory;
		$this->dataAccess = $dataAccess;
		$this->extensionRegistry = ExtensionRegistry::getInstance();
		$this->metrics = $siteConfig->metrics();
	}

	/** @inheritDoc */
	public function checkPreconditions() {
		// Execute this since this sets up state
		// needed for other functionality.
		parent::checkPreconditions();

		// Disable precondition checks by ignoring the return value above.
		// Parsoid/JS doesn't implement these checks.
		// See https://phabricator.wikimedia.org/T238849#5683035 for a discussion.
		return null;
	}

	/**
	 * Verify that the {domain} path parameter matches the actual domain.
	 * @param string $domain Domain name parameter to validate
	 */
	protected function assertDomainIsCorrect( $domain ): void {
		// We are cutting some corners here (IDN, non-ASCII casing)
		// since domain name support is provisional.
		// TODO use a proper validator instead
		$wgServer = \RequestContext::getMain()->getConfig()->get( 'Server' );
		$expectedDomain = wfParseUrl( $wgServer )['host'] ?? null;
		if ( !$expectedDomain ) {
			throw new LogicException( 'Cannot parse $wgServer' );
		}
		if ( strcasecmp( $expectedDomain, $domain ) === 0 ) {
			return;
		}

		// TODO probably the better
		if ( $this->extensionRegistry->isLoaded( 'MobileFrontend' ) ) {
			$mobileServer = MobileContext::singleton()->getMobileUrl( $wgServer );
			$expectedMobileDomain = wfParseUrl( $mobileServer )['host'] ?? null;
			if ( strcasecmp( $expectedMobileDomain, $domain ) === 0 ) {
				return;
			}
		}

		throw new ValidationException(
			new DataMessageValue( 'mwparsoid-invalid-domain', [], 'invalid-domain', [
				'expected' => $expectedDomain,
				'actual' => $domain,
			] ),
			'domain', $domain, []
		);
	}

	/**
	 * Get the parsed body by content-type
	 *
	 * @return array
	 */
	protected function getParsedBody(): array {
		$request = $this->getRequest();
		list( $contentType ) = explode( ';', $request->getHeader( 'Content-Type' )[0] ?? '', 2 );
		switch ( $contentType ) {
			case 'application/x-www-form-urlencoded':
			case 'multipart/form-data':
				return $request->getPostParams();
			case 'application/json':
				return json_decode( $request->getBody()->getContents(), true );
			default:
				throw new HttpException( 'Unsupported Media Type', 415 );
		}
	}

	/**
	 * Rough equivalent of req.local from Parsoid-JS.
	 * FIXME most of these should be replaced with more native ways of handling the request.
	 * @return array
	 */
	protected function &getRequestAttributes(): array {
		if ( $this->requestAttributes ) {
			return $this->requestAttributes;
		}

		// Porting note: this is the equivalent of the v3Middle middleware.
		$request = $this->getRequest();
		$body = ( $request->getMethod() === 'POST' ) ? $this->getParsedBody() : [];
		$opts = array_merge( $body, array_intersect_key( $request->getPathParams(),
			[ 'from' => true, 'format' => true ] ) );
		'@phan-var array<string,array|bool|string> $opts'; // @var array<string,array|bool|string> $opts
		$attribs = [
			'titleMissing' => empty( $request->getPathParams()['title'] ),
			'pageName' => $request->getPathParam( 'title' ) ?? '',
			'oldid' => $request->getPathParam( 'revision' ) ?? null,
			// "body_only" flag to return just the body (instead of the entire HTML doc)
			// We would like to deprecate use of this flag: T181657
			'body_only' => $request->getQueryParams()['body_only'] ?? $body['body_only'] ?? null,
			'errorEnc' => FormatHelper::ERROR_ENCODING[$opts['format']] ?? 'plain',
			'iwp' => wfWikiID(), // PORT-FIXME verify
			'subst' => (bool)( $request->getQueryParams()['subst'] ?? $body['subst'] ?? null ),
			'scrubWikitext' => (bool)( $body['scrub_wikitext']
				?? $request->getQueryParams()['scrub_wikitext']
				?? $body['scrubWikitext']
				?? $request->getQueryParams()['scrubWikitext']
				?? false ),
			'offsetType' => $body['offsetType']
				?? $request->getQueryParams()['offsetType']
				// Lint requests should return UCS2 offsets by default
				?? ( $opts['format'] === FormatHelper::FORMAT_LINT ? 'ucs2' : 'byte' ),
			'pagelanguage' => $request->getHeaderLine( 'Content-Language' ) ?: null,
		];

		if ( $request->getMethod() === 'POST' ) {
			if ( isset( $opts['original']['revid'] ) ) {
				$attribs['oldid'] = $opts['original']['revid'];
			}
			if ( isset( $opts['original']['title'] ) ) {
				$attribs['titleMissing'] = false;
				$attribs['pageName'] = $opts['original']['title'];
			}
		}

		$attribs['envOptions'] = [
			// We use `prefix` but ought to use `domain` (T206764)
			'prefix' => $attribs['iwp'],
			'domain' => $request->getPathParam( 'domain' ),
			'pageName' => $attribs['pageName'],
			'scrubWikitext' => $attribs['scrubWikitext'],
			'offsetType' => $attribs['offsetType'],
			'cookie' => $request->getHeaderLine( 'Cookie' ),
			'reqId' => $request->getHeaderLine( 'X-Request-Id' ),
			'userAgent' => $request->getHeaderLine( 'User-Agent' ),
			'htmlVariantLanguage' => $request->getHeaderLine( 'Accept-Language' ) ?: null,
			// Semver::satisfies checks below expect a valid outputContentVersion value.
			// Better to set it here instead of adding the default value at every check.
			'outputContentVersion' => Parsoid::defaultHTMLVersion(),
		];
		$attribs['opts'] = $opts;

		if ( empty( $this->parsoidSettings['debugApi'] ) ) {
			$this->assertDomainIsCorrect( $attribs['envOptions']['domain'] );
		}

		$this->requestAttributes = $attribs;
		return $this->requestAttributes;
	}

	/**
	 * FIXME: Combine with FormatHelper::parseContentTypeHeader
	 */
	private const NEW_SPEC =
		'#^https://www.mediawiki.org/wiki/Specs/(HTML|pagebundle)/(\d+\.\d+\.\d+)$#D';

	/**
	 * Combines:
	 *  routes.acceptable
	 *  apiUtils.validateAndSetOutputContentVersion
	 *  apiUtils.parseProfile
	 *
	 * @param array &$attribs Request attributes from getRequestAttributes()
	 * @return bool
	 */
	protected function acceptable( array &$attribs ): bool {
		$request = $this->getRequest();
		$format = $attribs['opts']['format'];

		if ( $format === FormatHelper::FORMAT_WIKITEXT ) {
			return true;
		}

		$acceptHeader = $request->getHeader( 'Accept' );
		if ( !$acceptHeader ) {
			return true;
		}

		$parser = new HttpAcceptParser();
		$acceptableTypes = $parser->parseAccept( $acceptHeader[0] );  // FIXME: Multiple headers valid?
		if ( !$acceptableTypes ) {
			return true;
		}

		// `acceptableTypes` is already sorted by quality.
		foreach ( $acceptableTypes as $t ) {
			$type = "{$t['type']}/{$t['subtype']}";
			$profile = $t['params']['profile'] ?? null;
			if (
				( $format === FormatHelper::FORMAT_HTML && $type === 'text/html' ) ||
				( $format === FormatHelper::FORMAT_PAGEBUNDLE && $type === 'application/json' )
			) {
				if ( $profile ) {
					preg_match( self::NEW_SPEC, $profile, $matches );
					if ( $matches && strtolower( $matches[1] ) === $format ) {
						$contentVersion = Parsoid::resolveContentVersion( $matches[2] );
						if ( $contentVersion ) {
							$attribs['envOptions']['outputContentVersion'] = $contentVersion;
							return true;
						} else {
							continue;
						}
					} else {
						continue;
					}
				} else {
					return true;
				}
			} elseif (
				( $type === '*/*' ) ||
				( $format === FormatHelper::FORMAT_HTML && $type === 'text/*' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string $title The page to be transformed
	 * @param int|null $revision The revision to be transformed
	 * @param string|null $wikitextOverride
	 *   Custom wikitext to use instead of the real content of the page.
	 * @param string|null $pagelanguageOverride
	 * @return PageConfig
	 */
	protected function createPageConfig(
		string $title, ?int $revision, string $wikitextOverride = null,
		string $pagelanguageOverride = null
	): PageConfig {
		$title = $title ? Title::newFromText( $title ) : Title::newMainPage();
		if ( !$title ) {
			// TODO use proper validation
			throw new LogicException( 'Title not found!' );
		}
		$user = RequestContext::getMain()->getUser();
		return $this->pageConfigFactory->create(
			$title, $user, $revision, $wikitextOverride, $pagelanguageOverride,
			$this->parsoidSettings
		);
	}

	/**
	 * Redirect to another Parsoid URL (e.g. canonization)
	 * @param string $path Target URL
	 * @param array $queryParams Query parameters
	 * @return Response
	 */
	protected function createRedirectResponse( string $path, array $queryParams = [] ): Response {
		// porting note: this is  more or less the equivalent of apiUtils.redirect()

		// FIXME there should be a better way to do this
		global $wgRestPath;
		$path = wfExpandUrl( "$wgRestPath$path", PROTO_CURRENT );

		// FIXME this should not be necessary in the REST entry point
		unset( $queryParams['title'] );

		if ( $queryParams ) {
			$path .= ( strpos( $path, '?' ) === false ? '?' : '&' )
				. http_build_query( $queryParams, '', '&', PHP_QUERY_RFC3986 );
		}
		if ( $this->getRequest()->getMethod() === 'POST' ) {
			$response = $this->getResponseFactory()->createTemporaryRedirect( $path );
		} else {
			$response = $this->getResponseFactory()->createLegacyTemporaryRedirect( $path );
		}
		$response->setHeader( 'Cache-Control', 'private,no-cache,s-maxage=0' );
		return $response;
	}

	/**
	 * Expand the current URL with the latest revision number and redirect there.
	 * Will return an error response if the page does not exist.
	 * @param PageConfig $pageConfig
	 * @param array $attribs Request attributes from getRequestAttributes()
	 * @return Response
	 */
	protected function createRedirectToOldidResponse(
		PageConfig $pageConfig, array $attribs
	): Response {
		// porting note: this is  more or less the equivalent of apiUtils.redirectToOldid()
		$domain = $attribs['envOptions']['domain'];
		$format = $this->getRequest()->getPathParam( 'format' );
		$target = $pageConfig->getTitle();
		$encodedTarget = PHPUtils::encodeURIComponent( $target );
		$revid = $pageConfig->getRevisionId();

		if ( $revid === null ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'message' => 'Page not found.',
			] );
		}

		$this->metrics->increment( 'redirectToOldid.' . $format );

		if ( $this->getRequest()->getMethod() === 'POST' ) {
			$from = $this->getRequest()->getPathParam( 'from' );
			$newPath = "/$domain/v3/transform/$from/to/$format/$encodedTarget/$revid";
		} else {
			$newPath = "/$domain/v3/page/$format/$encodedTarget/$revid";
		}
		return $this->createRedirectResponse( $newPath, $this->getRequest()->getQueryParams() );
	}

	/**
	 * Wikitext -> HTML helper.
	 * Porting note: this is the rough equivalent of routes.wt2html.
	 * Spec'd in https://phabricator.wikimedia.org/T75955 and the API tests.
	 * @param PageConfig $pageConfig
	 * @param array $attribs Request attributes from getRequestAttributes()
	 * @param string|null $wikitext Wikitext to transform (or null to use the page specified in
	 *   the request attributes).
	 * @return Response
	 */
	protected function wt2html(
		PageConfig $pageConfig, array $attribs, string $wikitext = null
	) {
		$request = $this->getRequest();
		$opts = $attribs['opts'];
		$format = $opts['format'];
		$oldid = $attribs['oldid'];

		$needsPageBundle = ( $format === FormatHelper::FORMAT_PAGEBUNDLE );
		$doSubst = ( $wikitext !== null && $attribs['subst'] );

		// Performance Timing options
		// init refers to time elapsed before parsing begins
		$metrics = $this->metrics;
		$timing = Timing::start( $metrics );

		if ( Semver::satisfies( $attribs['envOptions']['outputContentVersion'],
			'!=' . Parsoid::defaultHTMLVersion() ) ) {
			$metrics->increment( 'wt2html.parse.version.notdefault' );
		}

		if ( $wikitext === null && !$oldid ) {
			// Redirect to the latest revid
			return $this->createRedirectToOldidResponse( $pageConfig, $attribs );
		}

		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );

		if ( $doSubst ) {
			if ( $format !== FormatHelper::FORMAT_HTML ) {
				return $this->getResponseFactory()->createHttpError( 501, [
					'message' => 'Substitution is only supported for the HTML format.',
				] );
			}
			$wikitext = $parsoid->substTopLevelTemplates(
				$pageConfig, $wikitext
			);
			$pageConfig = $this->createPageConfig(
				$attribs['pageName'], (int)$attribs['oldid'], $wikitext
			);
		}

		if (
			!empty( $this->parsoidSettings['devAPI'] ) &&
			( $request->getQueryParams()['follow_redirects'] ?? false )
		) {
			$content = $pageConfig->getRevisionContent();
			$redirectTarget = $content ? $content->getRedirectTarget() : null;
			if ( $redirectTarget ) {
				$redirectInfo = $this->dataAccess->getPageInfo(
					$pageConfig, [ $redirectTarget ]
				);
				$encodedTarget = PHPUtils::encodeURIComponent( $redirectTarget );
				$redirectPath =
					"/{$attribs['envOptions']['domain']}/v3/page/$encodedTarget/wikitext";
				if ( $redirectInfo['revId'] ) {
					$redirectPath .= '/' . $redirectInfo['revId'];
				}
				return $this->createRedirectResponse( "", $request->getQueryParams() );
			}
		}

		$reqOpts = array_merge( [
			'pageBundle' => $needsPageBundle,
			// When substing, set data-parsoid to be discarded, so that the subst'ed
			// content is considered new when it comes back.
			'discardDataParsoid' => $doSubst,
			'contentmodel' => $opts['contentmodel'] ?? null,
		], $attribs['envOptions'] );

		// VE, the only client using body_only property,
		// doesn't want section tags when this flag is set.
		// (T181226)
		if ( $attribs['body_only'] ) {
			$reqOpts['wrapSections'] = false;
			$reqOpts['body_only'] = true;
		}

		if ( $wikitext === null && $oldid ) {
			$reqOpts['logLinterData'] = true;
			$mstr = 'pageWithOldid';
		} else {
			$mstr = 'wt';
		}

		// XXX: Not necessary, since it's in the pageConfig
		// if ( isset( $attribs['pagelanguage'] ) ) {
		// 	$reqOpts['pagelanguage'] = $attribs['pagelanguage'];
		// }

		$timing->end( "wt2html.$mstr.init" );
		$metrics->timing(
			"wt2html.$mstr.size.input",
			# Should perhaps be strlen instead (or cached!): T239841
			mb_strlen( $pageConfig->getPageMainContent() )
		);
		$parseTiming = Timing::start( $metrics );

		if ( $format === FormatHelper::FORMAT_LINT ) {
			try {
				$lints = $parsoid->wikitext2lint( $pageConfig, $reqOpts );
			} catch ( ClientError $e ) {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => $e->getMessage(),
				] );
			} catch ( ResourceLimitExceededException $e ) {
				return $this->getResponseFactory()->createHttpError( 413, [
					'message' => $e->getMessage(),
				] );
			}
			$response = $this->getResponseFactory()->createJson( $lints );
		} else {
			try {
				$out = $parsoid->wikitext2html(
					$pageConfig, $reqOpts, $headers
				);
			} catch ( ClientError $e ) {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => $e->getMessage(),
				] );
			} catch ( ResourceLimitExceededException $e ) {
				return $this->getResponseFactory()->createHttpError( 413, [
					'message' => $e->getMessage(),
				] );
			}
			if ( $needsPageBundle ) {
				$response = $this->getResponseFactory()->createJson( $out->responseData() );
				FormatHelper::setContentType( $response, FormatHelper::FORMAT_PAGEBUNDLE,
					$out->version );
			} else {
				$response = $this->getResponseFactory()->create();
				FormatHelper::setContentType( $response, FormatHelper::FORMAT_HTML,
					$attribs['envOptions']['outputContentVersion'] );
				$response->getBody()->write( $out );
				$response->setHeader( 'Content-Language', $headers['content-language'] );
				$response->addHeader( 'Vary', $headers['vary'] );
			}
			if ( $request->getMethod() === 'GET' ) {
				$tid = UIDGenerator::newUUIDv1();
				$response->addHeader( 'Etag', "W/\"{$oldid}/{$tid}\"" );
			}
		}

		$parseTiming->end( "wt2html.$mstr.parse" );
		$metrics->timing( "wt2html.$mstr.size.output", $response->getBody()->getSize() );
		$timing->end( 'wt2html.total' );

		if ( $wikitext !== null ) {
			// Don't cache requests when wt is set in case somebody uses
			// GET for wikitext parsing
			$response->setHeader( 'Cache-Control', 'private,no-cache,s-maxage=0' );
		} elseif ( $oldid ) {
			// FIXME this should be handled in core (cf OutputPage::sendCacheControl)
			if ( $request->getHeaderLine( 'Cookie' ) ||
				$request->getHeaderLine( 'Authorization' ) ) {
				// Don't cache requests with a session.
				$response->setHeader( 'Cache-Control', 'private,no-cache,s-maxage=0' );
			}
			// Indicate the MediaWiki revision in a header as well for
			// ease of extraction in clients.
			$response->setHeader( 'Content-Revision-Id', $oldid );
		} else {
			throw new LogicException( 'Should be unreachable' );
		}
		return $response;
	}

	/**
	 * HTML -> wikitext helper.
	 * Porting note: this is the rough equivalent of routes.html2wt.
	 * @param PageConfig $pageConfig
	 * @param array $attribs Request attributes from getRequestAttributes()
	 * @param string|null $html HTML to transform (or null to use the page specified in
	 *   the request attributes).
	 * @return Response
	 */
	protected function html2wt(
		PageConfig $pageConfig, array $attribs, string $html = null
	) {
		$request = $this->getRequest();
		$opts = $attribs['opts'];
		$envOptions = $attribs['envOptions'];
		$metrics = $this->metrics;

		// Performance Timing options
		$timing = Timing::start( $metrics );

		$doc = DOMUtils::parseHTML( $html );

		// send domparse time, input size and init time to statsd/Graphite
		// init time is the time elapsed before serialization
		// init.domParse, a component of init time, is the time elapsed
		// from html string to DOM tree
		$timing->end( 'html2wt.init.domparse' );
		# Should perhaps be strlen instead (or cached!): T239841
		$metrics->timing( 'html2wt.size.input', mb_strlen( $html ) );
		$timing->end( 'html2wt.init' );

		$original = $opts['original'] ?? null;
		$oldBody = null;
		$origPb = null;

		// Get the content version of the edited doc, if available
		$vEdited = DOMUtils::extractInlinedContentVersion( $doc );

		// Check for version mismatches between original & edited doc
		if ( !isset( $original['html'] ) ) {
			$envOptions['inputContentVersion'] = $vEdited ?? Parsoid::defaultHTMLVersion();
		} else {
			$vOriginal = FormatHelper::parseContentTypeHeader(
				$original['html']['headers']['content-type'] ?? '' );
			if ( $vOriginal === null ) {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => 'Content-type of original html is missing.',
				] );
			}
			if ( $vEdited === null ) {
				// If version of edited doc is unavailable we assume
				// the edited doc is derived from the original doc.
				// No downgrade necessary
				$envOptions['inputContentVersion'] = $vOriginal;
			} elseif ( $vEdited === $vOriginal ) {
				// No downgrade necessary
				$envOptions['inputContentVersion'] = $vOriginal;
			} else {
				$envOptions['inputContentVersion'] = $vEdited;
				// We need to downgrade the original to match the the edited doc's version.
				$downgrade = Parsoid::findDowngrade( $vOriginal, $vEdited );
				// Downgrades are only for pagebundle
				if ( $downgrade && $opts['from'] === FormatHelper::FORMAT_PAGEBUNDLE ) {
					$metrics->increment(
						"downgrade.from.{$downgrade['from']}.to.{$downgrade['to']}"
					);
					$origPb = new PageBundle(
						$original['html']['body'],
						$original['data-parsoid']['body'] ?? null,
						$original['data-mw']['body'] ?? null
					);
					if ( !$origPb->validate( $vOriginal, $errorMessage ) ) {
						return $this->getResponseFactory()->createHttpError(
							400, [ 'message' => $errorMessage ]
						);
					}
					$downgradeTiming = Timing::start( $metrics );
					Parsoid::downgrade( $downgrade, $origPb );
					$downgradeTiming->end( 'downgrade.time' );
					$oldBody = DOMCompat::getBody( DOMUtils::parseHTML( $origPb->html ) );
				} else {
					$err = "Modified ({$vEdited}) and original ({$vOriginal}) html are of "
						. 'different type, and no path to downgrade.';
					return $this->getResponseFactory()->createHttpError( 400, [ 'message' => $err ] );
				}
			}
		}

		$metrics->increment(
			'html2wt.original.version.' . $envOptions['inputContentVersion']
		);
		if ( !$vEdited ) {
			$metrics->increment( 'html2wt.original.version.notinline' );
		}

		// If available, the modified data-mw blob is applied, while preserving
		// existing inline data-mw.  But, no data-parsoid application, since
		// that's internal, we only expect to find it in its original,
		// unmodified form.
		if ( $opts['from'] === FormatHelper::FORMAT_PAGEBUNDLE && isset( $opts['data-mw'] )
			&& Semver::satisfies( $envOptions['inputContentVersion'], '^999.0.0' )
		) {
			// `opts` isn't a revision, but we'll find a `data-mw` there.
			$pb = new PageBundle( '',
				[ 'ids' => [] ],  // So it validates
				$opts['data-mw']['body'] ?? null );
			if ( !$pb->validate( $envOptions['inputContentVersion'], $errorMessage ) ) {
				return $this->getResponseFactory()->createHttpError( 400,
					[ 'message' => $errorMessage ] );
			}
			DOMDataUtils::applyPageBundle( $doc, $pb );
		}

		$oldhtml = null;

		if ( $original ) {
			if ( $opts['from'] === FormatHelper::FORMAT_PAGEBUNDLE ) {
				// Apply the pagebundle to the parsed doc.  This supports the
				// simple edit scenarios where data-mw might not necessarily
				// have been retrieved.
				if ( !$origPb ) {
					$origPb = new PageBundle( '', $original['data-parsoid']['body'] ?? null,
						$original['data-mw']['body'] ?? null );
				}

				// Verify that the top-level parsoid object either doesn't contain
				// offsetType, or that it matches the conversion that has been
				// explicitly requested.
				if ( isset( $origPb->parsoid['offsetType'] ) ) {
					$offsetType = $envOptions['offsetType'] ?? 'byte';
					$origOffsetType = $origPb->parsoid['offsetType'];
					if ( $origOffsetType !== $offsetType ) {
						return $this->getResponseFactory()->createHttpError( 406, [
							'message' => 'DSR offsetType mismatch: ' .
								$origOffsetType . ' vs ' . $offsetType,
						] );
					}
				}

				$pb = $origPb;
				// However, if a modified data-mw was provided,
				// original data-mw is omitted to avoid losing deletions.
				if ( isset( $opts['data-mw'] )
					&& Semver::satisfies( $envOptions['inputContentVersion'], '^999.0.0' )
				) {
					// Don't modify `origPb`, it's used below.
					$pb = new PageBundle( '', $pb->parsoid, [ 'ids' => [] ] );
				}
				if ( !$pb->validate( $envOptions['inputContentVersion'], $errorMessage ) ) {
					return $this->getResponseFactory()->createHttpError( 400,
						[ 'message' => $errorMessage ] );
				}
				DOMDataUtils::applyPageBundle( $doc, $pb );
			}

			// If we got original html, parse it
			if ( isset( $original['html'] ) ) {
				if ( !$oldBody ) {
					$oldBody = DOMCompat::getBody( DOMUtils::parseHTML( $original['html']['body'] ) );
				}
				if ( $opts['from'] === FormatHelper::FORMAT_PAGEBUNDLE ) {
					if ( !$origPb->validate( $envOptions['inputContentVersion'], $errorMessage ) ) {
						return $this->getResponseFactory()->createHttpError( 400,
							[ 'message' => $errorMessage ] );
					}
					DOMDataUtils::applyPageBundle( $oldBody->ownerDocument, $origPb );
				}
				$oldhtml = ContentUtils::toXML( $oldBody );
			}
		}

		// As per https://www.mediawiki.org/wiki/Parsoid/API#v1_API_entry_points
		//   "Both it and the oldid parameter are needed for
		//    clean round-tripping of HTML retrieved earlier with"
		// So, no oldid => no selser
		$hasOldId = (bool)$attribs['oldid'];

		if ( $hasOldId && !empty( $this->parsoidSettings['useSelser'] ) ) {
			if ( !$pageConfig->getRevisionContent() ) {
				return $this->getResponseFactory()->createHttpError( 409, [
					'message' => 'Could not find previous revision. Has the page been locked / deleted?'
				] );
			}

			// FIXME: T234548/T234549 - $pageConfig->getPageMainContent() is deprecated:
			// should use $env->topFrame->getSrcText()
			$selserData = new SelserData( $pageConfig->getPageMainContent(), $oldhtml );
		} else {
			$selserData = null;
		}

		$html = ContentUtils::toXML( $doc );
		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );

		try {
			$wikitext = $parsoid->html2wikitext( $pageConfig, $html, [
				'scrubWikitext' => $envOptions['scrubWikitext'],
				'inputContentVersion' => $envOptions['inputContentVersion'],
				'offsetType' => $envOptions['offsetType'],
				'contentmodel' => $opts['contentmodel'] ?? null,
			], $selserData );
		} catch ( ClientError $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => $e->getMessage(),
			] );
		} catch ( ResourceLimitExceededException $e ) {
			return $this->getResponseFactory()->createHttpError( 413, [
				'message' => $e->getMessage(),
			] );
		}

		$timing->end( 'html2wt.total' );
		# Should perhaps be strlen instead (or cached!): T239841
		$metrics->timing( 'html2wt.size.output', mb_strlen( $wikitext ) );

		$response = $this->getResponseFactory()->create();
		FormatHelper::setContentType( $response, FormatHelper::FORMAT_WIKITEXT );
		$response->getBody()->write( $wikitext );
		return $response;
	}

	/**
	 * Pagebundle -> pagebundle helper.
	 * Porting note: this is the rough equivalent of routes.pb2pb.
	 * @param PageConfig $pageConfig
	 * @param array<string,array> $attribs
	 * @return Response
	 */
	protected function pb2pb( PageConfig $pageConfig, array $attribs ) {
		$request = $this->getRequest();
		$opts = $attribs['opts'];

		$revision = $opts['previous'] ?? $opts['original'] ?? null;
		if ( !isset( $revision['html'] ) ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Missing revision html.',
			] );
		}

		$vOriginal = FormatHelper::parseContentTypeHeader(
			$revision['html']['headers']['content-type'] ?? '' );
		if ( $vOriginal === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => 'Content-type of revision html is missing.',
			] );
		}
		$attribs['envOptions']['inputContentVersion'] = $vOriginal;
		'@phan-var array<string,array> $attribs'; // @var array<string,array> $attribs

		$this->metrics->increment(
			'pb2pb.original.version.' . $attribs['envOptions']['inputContentVersion']
		);

		if ( !empty( $opts['updates'] ) ) {
			// If we're only updating parts of the original version, it should
			// satisfy the requested content version, since we'll be returning
			// that same one.
			// FIXME: Since this endpoint applies the acceptable middleware,
			// `getOutputContentVersion` is not what's been passed in, but what
			// can be produced.  Maybe that should be selectively applied so
			// that we can update older versions where it makes sense?
			// Uncommenting below implies that we can only update the latest
			// version, since carrot semantics is applied in both directions.
			// if ( !Semver::satisfies(
			// 	$attribs['envOptions']['inputContentVersion'],
			// 	"^{$attribs['envOptions']['outputContentVersion']}"
			// ) ) {
			// 	return $this->getResponseFactory()->createHttpError( 415, [
			// 		'message' => 'We do not know how to do this conversion.',
			// 	] );
			// }
			if ( !empty( $opts['updates']['redlinks'] ) ) {
				// Q(arlolra): Should redlinks be more complex than a bool?
				// See gwicke's proposal at T114413#2240381
				return $this->updateRedLinks( $pageConfig, $attribs, $revision );
			} elseif ( isset( $opts['updates']['variant'] ) ) {
				return $this->languageConversion( $pageConfig, $attribs, $revision );
			} else {
				return $this->getResponseFactory()->createHttpError( 400, [
					'message' => 'Unknown transformation.',
				] );
			}
		}

		// TODO(arlolra): subbu has some sage advice in T114413#2365456 that
		// we should probably be more explicit about the pb2pb conversion
		// requested rather than this increasingly complex fallback logic.
		$downgrade = Parsoid::findDowngrade(
			$attribs['envOptions']['inputContentVersion'],
			$attribs['envOptions']['outputContentVersion']
		);
		if ( $downgrade ) {
			$pb = new PageBundle(
				$revision['html']['body'],
				$revision['data-parsoid']['body'] ?? null,
				$revision['data-mw']['body'] ?? null
			);
			if ( !$pb->validate( $attribs['envOptions']['inputContentVersion'], $errorMessage ) ) {
				return $this->getResponseFactory()->createHttpError(
					400, [ 'message' => $errorMessage ]
				);
			}
			Parsoid::downgrade( $downgrade, $pb );

			if ( !empty( $attribs['body_only'] ) ) {
				$doc = DOMUtils::parseHTML( $pb->html );
				$body = DOMCompat::getBody( $doc );
				$pb->html = ContentUtils::toXML( $body, [
					'innerXML' => true,
				] );
			}

			$response = $this->getResponseFactory()->createJson( $pb->responseData() );
			FormatHelper::setContentType(
				$response, FormatHelper::FORMAT_PAGEBUNDLE, $pb->version
			);
			return $response;
		// Ensure we only reuse from semantically similar content versions.
		} elseif ( Semver::satisfies( $attribs['envOptions']['outputContentVersion'],
			'^' . $attribs['envOptions']['inputContentVersion'] ) ) {
			return $this->wt2html( $pageConfig, $attribs, null );
		} else {
			return $this->getResponseFactory()->createHttpError( 415, [
				'message' => 'We do not know how to do this conversion.',
			] );
		}
	}

	/**
	 * Update red links on a document.
	 *
	 * @param PageConfig $pageConfig
	 * @param array $attribs
	 * @param array $revision
	 * @return Response
	 */
	protected function updateRedLinks(
		PageConfig $pageConfig, array $attribs, array $revision
	) {
		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );

		$html = $parsoid->html2html(
			$pageConfig, 'redlinks', $revision['html']['body'], [], $headers
		);

		$out = new PageBundle(
			$html,
			$revision['data-parsoid']['body'] ?? null,
			$revision['data-mw']['body'] ?? null,
			$attribs['envOptions']['inputContentVersion'],
			$headers,
			$revision['contentmodel'] ?? null
		);
		if ( !$out->validate( $attribs['envOptions']['inputContentVersion'], $errorMessage ) ) {
			return $this->getResponseFactory()->createHttpError(
				400,
				[ 'message' => $errorMessage ]
			);
		}
		$response = $this->getResponseFactory()->createJson( $out->responseData() );
		FormatHelper::setContentType(
			$response, FormatHelper::FORMAT_PAGEBUNDLE, $out->version
		);
		return $response;
	}

	/**
	 * Do variant conversion on a document.
	 *
	 * @param PageConfig $pageConfig
	 * @param array $attribs
	 * @param array $revision
	 * @return Response
	 */
	protected function languageConversion(
		PageConfig $pageConfig, array $attribs, array $revision
	) {
		$opts = $attribs['opts'];
		$source = $opts['updates']['variant']['source'] ?? null;
		$target = $opts['updates']['variant']['target'] ??
			$attribs['envOptions']['htmlVariantLanguage'];

		if ( !$target ) {
			return $this->getResponseFactory()->createHttpError(
				400, [ 'message' => 'Target variant is required.' ]
			);
		}

		if ( !$this->siteConfig->langConverterEnabledForLanguage(
			$pageConfig->getPageLanguage()
		) ) {
			return $this->getResponseFactory()->createHttpError(
				400, [ 'message' => 'LanguageConversion is not enabled on this article.' ]
			);
		}

		$parsoid = new Parsoid( $this->siteConfig, $this->dataAccess );

		$pb = new PageBundle(
			$revision['html']['body'],
			$revision['data-parsoid']['body'] ?? null,
			$revision['data-mw']['body'] ?? null,
			$attribs['envOptions']['inputContentVersion'],
			$revision['html']['headers'] ?? null,
			$revision['contentmodel'] ?? null
		);
		$out = $parsoid->pb2pb(
			$pageConfig, 'variant', $pb,
			[
				'variant' => [
					'source' => $source,
					'target' => $target,
				]
			]
		);

		$response = $this->getResponseFactory()->createJson( $out->responseData() );
		FormatHelper::setContentType(
			$response, FormatHelper::FORMAT_PAGEBUNDLE, $out->version
		);
		return $response;
	}
}
