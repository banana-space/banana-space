<?php

namespace Flow\Conversion;

use DOMDocument;
use DOMElement;
use DOMNode;
use FauxResponse;
use Flow\Container;
use Flow\Exception\FlowException;
use Flow\Exception\NoParserException;
use Flow\Exception\WikitextException;
use Flow\Parsoid\ContentFixer;
use Flow\Parsoid\Fixer\EmptyNodeFixer;
use Html;
use Language;
use Linker;
use MediaWiki\MediaWikiServices;
use OutputPage;
use RequestContext;
use Sanitizer;
use Title;
use VirtualRESTServiceClient;

abstract class Utils {

	public const PARSOID_VERSION = '2.0.0';

	/**
	 * @var VirtualRESTServiceClient
	 */
	protected static $serviceClient = null;

	/**
	 * @var \VirtualRESTService
	 */
	protected static $vrsObject = null;

	/**
	 * Convert from/to wikitext <=> html or topic-title-wikitext => topic-title-html.
	 * Only these pairs are supported.  html => wikitext requires Parsoid, and
	 * topic-title-html => topic-title-wikitext is not supported.
	 *
	 * @param string $from Format of content to convert: html|wikitext|topic-title-wikitext
	 * @param string $to Format to convert to: html|wikitext|topic-title-html
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws WikitextException When the requested conversion is unsupported
	 * @throws NoParserException When the conversion fails
	 * @param-taint $content escapes_escaped
	 */
	public static function convert( $from, $to, $content, Title $title ) {
		if ( $from === $to || $content === '' ) {
			return $content;
		}

		if ( $from === 'wt' ) {
			$from = 'wikitext';
		}

		if ( $from === 'wikitext' || $from === 'html' ) {
			if ( $to === 'wikitext' || $to === 'html' ) {
				if ( self::isParsoidConfigured() ) {
					return self::parsoid( $from, $to, $content, $title );
				} else {
					return self::parser( $from, $to, $content, $title );
				}
			} else {
				throw new WikitextException( "Conversion from '$from' to '$to' was requested, " .
					"but this is not supported." );
			}
		} else {
			return self::commentParser( $from, $to, $content );
		}
	}

	/**
	 * Basic conversion of html to plaintext for use in recent changes, history,
	 * and other places where a roundtrip is undesired.
	 *
	 * @param string $html
	 * @param int|null $truncateLength Maximum length in characters (including ellipses) or null for whole string.
	 * @param Language|null $lang Language to use for truncation.  Defaults to $wgLang
	 * @return string plaintext
	 */
	public static function htmlToPlaintext( $html, $truncateLength = null, Language $lang = null ) {
		/** @var Language $wgLang */
		global $wgLang;

		$plain = trim( Sanitizer::stripAllTags( $html ) );

		if ( $truncateLength === null ) {
			return $plain;
		} else {
			$lang = $lang ?: $wgLang;
			return $lang->truncateForVisual( $plain, $truncateLength );
		}
	}

	/**
	 * Convert from/to wikitext/html via Parsoid/RESTBase.
	 *
	 * This will assume Parsoid/RESTBase is installed and configured.
	 *
	 * @param string $from Format of content to convert: html|wikitext
	 * @param string $to Format to convert to: html|wikitext
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws NoParserException When Parsoid/RESTBase operation fails
	 * @throws WikitextException When conversion is unsupported
	 */
	protected static function parsoid( $from, $to, $content, Title $title ) {
		$serviceClient = self::getServiceClient();

		if ( $from !== 'html' && $from !== 'wikitext' ) {
			throw new WikitextException( 'Unknown source format: ' . $from, 'process-wikitext' );
		}

		$prefixedDbTitle = $title->getPrefixedDBkey();
		$params = [
			$from => $content
		];
		if ( $from === 'html' ) {
			$params['scrub_wikitext'] = 'true';
		}
		$url = '/restbase/local/v1/transform/' . $from . '/to/' . $to . '/' .
			urlencode( $prefixedDbTitle );
		$request = [
			'method' => 'POST',
			'url' => $url,
			'body' => $params,
			'headers' => [
				'Accept' =>
					sprintf(
						'text/html; charset=utf-8; profile="https://www.mediawiki.org/wiki/Specs/HTML/%s"',
						self::PARSOID_VERSION
					),
				'User-Agent' => 'Flow-MediaWiki/' . MW_VERSION,
			],
		];
		$response = $serviceClient->run( $request );
		if ( $response['code'] !== 200 ) {
			if ( $response['error'] !== '' ) {
				$statusMsg = $response['error'];
			} else {
				$statusMsg = $response['code'];
			}
			$vrsInfo = $serviceClient->getMountAndService( '/restbase/' );
			$serviceName = $vrsInfo[1] ? $vrsInfo[1]->getName() : 'VRS service';
			$msg = "Request to " . $serviceName . " for \"$from\" to \"$to\" conversion of " .
				"content connected to title \"$prefixedDbTitle\" failed: $statusMsg";
			Container::get( 'default_logger' )->error(
				'Request to {service} for "{sourceFormat}" to "{targetFormat}" conversion of " .
					"content connected to title "{title}" failed.  Code: {code}, " .
					"Reason: "{reason}", Body: "{body}", Error: "{error}"',
				[
					'service' => $serviceName,
					'sourceFormat' => $from,
					'targetFormat' => $to,
					'title' => $prefixedDbTitle,
					'code' => $response['code'],
					'reason' => $response['reason'],
					'error' => $response['error'], // This is sometimes/always empty string
					'headers' => $response['headers'],
					'body' => $response['body'],
					'response' => $response,
				]
			);
			throw new NoParserException( $msg, 'process-wikitext' );
		}

		// Add attributes for parsoid version and base url if converting to HTML.
		$content = $response['body'];
		if ( $to === 'html' ) {
			$content = self::encodeHeadInfo( $content );
		}
		// HACK remove trailing newline inserted by Parsoid (T106925)
		if ( $to === 'wikitext' ) {
			$content = preg_replace( '/\\n$/', '', $content );
		}
		return $content;
	}

	/**
	 * Convert from/to topic-title-wikitext/topic-title-html using Linker::formatLinksInComment
	 *
	 * @param string $from Format of content to convert: topic-title-wikitext
	 * @param string $to Format of content to convert to: topic-title-html
	 * @param string $content Content to convert, in topic-title-wikitext format.
	 * @return string $content in HTML
	 * @throws WikitextException
	 */
	protected static function commentParser( $from, $to, $content ) {
		if (
			$from !== 'topic-title-wikitext' ||
			( $to !== 'topic-title-html' && $to !== 'topic-title-plaintext' )
		) {
			throw new WikitextException( "Conversion from '$from' to '$to' was requested, " .
				"but this is not supported." );
		}

		$html = Linker::formatLinksInComment( Sanitizer::escapeHtmlAllowEntities( $content ) );
		if ( $to === 'topic-title-plaintext' ) {
			return self::htmlToPlaintext( $html );
		} else {
			return $html;
		}
	}

	/**
	 * Convert from/to wikitext/html using Parser.
	 *
	 * This only supports wikitext to HTML.
	 *
	 * @param string $from Format of content to convert: wikitext
	 * @param string $to Format to convert to: html
	 * @param string $content
	 * @param Title $title
	 * @return string
	 * @throws WikitextException When the conversion is unsupported
	 */
	protected static function parser( $from, $to, $content, Title $title ) {
		if ( $from !== 'wikitext' || $to !== 'html' ) {
			throw new WikitextException( "Conversion from '$from' to '$to' was requested, but " .
				"core's Parser only supports 'wikitext' to 'html' conversion", 'process-wikitext' );
		}

		$options = new \ParserOptions;
		$options->setTidy( true );

		$output = MediaWikiServices::getInstance()->getParser()
			->parse( $content, $title, $options );
		return $output->getText( [ 'enableSectionEditLinks' => false ] );
	}

	/**
	 * Check to see whether a Parsoid or RESTBase service is configured.
	 *
	 * @return bool
	 */
	public static function isParsoidConfigured() {
		try {
			self::getVRSObject();
			return true;
		} catch ( NoParserException $e ) {
			return false;
		}
	}

	/**
	 * Returns Flow's Virtual REST Service for Parsoid/RESTBase.
	 * The Parsoid/RESTBase service will be mounted at /restbase/
	 * and will answer RESTBase v1 API requests.
	 *
	 * @return VirtualRESTServiceClient
	 * @throws NoParserException When Parsoid/RESTBase is unconfigured
	 */
	protected static function getServiceClient() {
		if ( self::$serviceClient === null ) {
			$sc = new VirtualRESTServiceClient(
				MediaWikiServices::getInstance()->getHttpRequestFactory()->createMultiClient()
			);
			$sc->mount( '/restbase/', self::getVRSObject() );
			self::$serviceClient = $sc;
		}
		return self::$serviceClient;
	}

	/**
	 * @return \VirtualRESTService
	 * @throws NoParserException
	 */
	private static function getVRSObject() {
		if ( !self::$vrsObject ) {
			self::$vrsObject = self::makeVRSObject();
		}
		return self::$vrsObject;
	}

	/**
	 * Creates the Virtual REST Service object to be used in Flow's
	 * API calls.  The method determines whether to instantiate a
	 * ParsoidVirtualRESTService or a RestbaseVirtualRESTService
	 * object based on configuration directives: if
	 * `$wgVirtualRestConfig['modules']['restbase']` is defined,
	 * RESTBase is chosen; otherwise Parsoid is used.
	 * For backwards compatibility, $wgFlowParsoid* variables are used
	 * to specify a Parsoid configuration as a fall back.
	 *
	 * @return \VirtualRESTService the VirtualRESTService object to use
	 * @throws NoParserException When Parsoid/RESTBase is not configured
	 */
	private static function makeVRSObject() {
		global $wgVirtualRestConfig, $wgFlowParsoidURL, $wgFlowParsoidPrefix,
			$wgFlowParsoidTimeout, $wgFlowParsoidForwardCookies,
			$wgFlowParsoidHTTPProxy;

		// the params array to create the service object with
		$params = [];
		// the VRS class to use; defaults to Parsoid
		$class = 'ParsoidVirtualRESTService';
		// the global virtual rest service config object, if any
		$vrs = $wgVirtualRestConfig;
		// HACK: don't use RESTbase because it'll drop data-parsoid, see T115236
		/*if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['restbase'] ) ) {
			// if restbase is available, use it
			$params = $vrs['modules']['restbase'];
			$params['parsoidCompat'] = false; // backward compatibility
			$class = 'RestbaseVirtualRESTService';
		} else
		*/
		if ( isset( $vrs['modules'] ) && isset( $vrs['modules']['parsoid'] ) ) {
			// there's a global parsoid config, use it next
			$params = $vrs['modules']['parsoid'];
			$params['restbaseCompat'] = true;
		} else {
			// no global modules defined, fall back to old defaults
			if ( !$wgFlowParsoidURL ) {
				throw new NoParserException( 'Flow Parsoid configuration is unavailable', 'process-wikitext' );
			}
			$params = [
				'URL' => $wgFlowParsoidURL,
				'prefix' => $wgFlowParsoidPrefix,
				'timeout' => $wgFlowParsoidTimeout,
				'HTTPProxy' => $wgFlowParsoidHTTPProxy,
				'forwardCookies' => $wgFlowParsoidForwardCookies
			];
		}
		// merge the global and service-specific params
		if ( isset( $vrs['global'] ) ) {
			$params = array_merge( $vrs['global'], $params );
		}
		// set up cookie forwarding
		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
		if ( $params['forwardCookies'] &&
				!MediaWikiServices::getInstance()->getPermissionManager()->isEveryoneAllowed( 'read' )
		) {
			if ( PHP_SAPI === 'cli' ) {
				// From the command line we need to generate a cookie
				$params['forwardCookies'] = self::generateForwardedCookieForCli();
			} else {
				$params['forwardCookies'] = RequestContext::getMain()->getRequest()->getHeader( 'Cookie' );
			}
		} else {
			$params['forwardCookies'] = false;
		}
		// create the VRS object and return it
		return new $class( $params );
	}

	/**
	 * Turns given $content string into a DOMDocument object.
	 *
	 * Note that, by default, $content will be prefixed with <?xml encoding="utf-8"?> to force
	 * libxml to interpret the content as UTF-8. If for some reason you don't want this to happen,
	 * or you are certain that your input already has <?xml encoding="utf-8"?> or
	 * <meta http-equiv="Content-Type" content="text/html; charset=utf-8"> , then you can disable
	 * this behavior by setting $utf8Fragment=false to disable this behavior.
	 *
	 * Some libxml errors are forgivable, libxml errors that aren't
	 * ignored will throw a WikitextException.
	 *
	 * The default error codes allowed are:
	 *        9 - allow illegal characters (they are removed, but this option means it
	 *             doesn't trigger an error.
	 * 	 76 - allow unexpected end tag. This is typically old wikitext using deprecated tags.
	 * 	513 - allow multiple tags with same id
	 * 	801 - allow unrecognized tags like figcaption
	 *
	 * @param string $content
	 * @param bool $utf8Fragment If true, prefix $content with <?xml encoding="utf-8"?>
	 * @param array $ignoreErrorCodes
	 * @return DOMDocument
	 * @throws WikitextException
	 * @see http://www.xmlsoft.org/html/libxml-xmlerror.html
	 */
	public static function createDOM(
		$content,
		$utf8Fragment = true,
		array $ignoreErrorCodes = [ 9, 76, 513, 801 ]
	) {
		$dom = new DOMDocument();

		// Otherwise the parser may attempt to load the dtd from an external source.
		// See: https://www.mediawiki.org/wiki/XML_External_Entity_Processing
		$loadEntities = libxml_disable_entity_loader( true );

		// don't output warnings
		$useErrors = libxml_use_internal_errors( true );

		// Work around DOMDocument's morbid insistence on using iso-8859-1
		// Even $dom = new DOMDocument( '1.0', 'utf-8' ); doesn't work, you have to specify
		// encoding ="utf-8" in the string fed to loadHTML()
		$html = ( $utf8Fragment ? '<?xml encoding="utf-8"?>' : '' ) . $content;
		$dom->loadHTML( $html, LIBXML_PARSEHUGE );

		libxml_disable_entity_loader( $loadEntities );

		// check error codes; if not in the supplied list of ignorable errors,
		// throw an exception
		$errors = array_filter(
			libxml_get_errors(),
			function ( $error ) use( $ignoreErrorCodes ) {
				return !in_array( $error->code, $ignoreErrorCodes );
			}
		);

		// restore libxml state before anything else
		libxml_clear_errors();
		libxml_use_internal_errors( $useErrors );

		if ( $errors ) {
			throw new WikitextException(
				implode(
					"\n",
					array_map(
						function ( $error ) {
							return $error->message;
						},
						$errors
					)
				) . "\n\nFrom source content:\n" . $content,
				'process-wikitext'
			);
		}

		return $dom;
	}

	/**
	 * Handler for FlowAddModules, avoids rest of Flow having to be aware if
	 * Parsoid is in use.
	 *
	 * @param OutputPage $out
	 * @return bool
	 */
	public static function onFlowAddModules( OutputPage $out ) {
		if ( self::isParsoidConfigured() ) {
			// The module is only necessary when we are using parsoid.
			// XXX We only need the Parsoid CSS if some content being
			// rendered has getContentFormat() === 'html'.
			$out->addModuleStyles( [
				'mediawiki.skinning.content.parsoid',
				'ext.cite.style',
			] );
		}

		return true;
	}

	/**
	 * Retrieves the html of the node's children.
	 *
	 * @param DOMNode|null $node
	 * @return string html of the nodes children
	 */
	public static function getInnerHtml( DOMNode $node = null ) {
		$html = '';
		if ( $node ) {
			$dom = $node instanceof DOMDocument ? $node : $node->ownerDocument;
			// Don't use saveHTML(), it has bugs (T217766); instead use XML serialization
			// with a workaround for empty non-void nodes
			$fixer = new ContentFixer( new EmptyNodeFixer );
			$fixer->applyToDom( $dom, Title::newMainPage() );

			foreach ( $node->childNodes as $child ) {
				$html .= $dom->saveXML( $child );
			}
		}
		return $html;
	}

	/**
	 * Gets the HTML of a node. This is like getInnterHtml(), but includes the node's tag itself too.
	 * @param DOMNode $node
	 * @return string HTML
	 */
	public static function getOuterHtml( DOMNode $node ) {
		$dom = $node instanceof DOMDocument ? $node : $node->ownerDocument;
		// Don't use saveHTML(), it has bugs (T217766); instead use XML serialization
		// with a workaround for empty non-void nodes
		$fixer = new ContentFixer( new EmptyNodeFixer );
		$fixer->applyToDom( $dom, Title::newMainPage() );
		return $dom->saveXML( $node );
	}

	/**
	 * Encode information from the <head> tag as attributes on the <body> tag, then
	 * drop the <head>.
	 *
	 * Specifically, add the Parsoid version number in the parsoid-version attribute;
	 * put the href of the <base> tag in the base-url attribute;
	 * and remove the class attribute from the <body>.
	 *
	 * @param string $html HTML
	 * @return string HTML with <head> information encoded as attributes on the <body>
	 * @throws WikitextException
	 * @suppress PhanUndeclaredMethod,PhanTypeMismatchArgumentNullable Apparently a phan bug / wrong built-in PHP stubs
	 */
	public static function encodeHeadInfo( $html ) {
		$dom = ContentFixer::createDOM( $html );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$head = $dom->getElementsByTagName( 'head' )->item( 0 );
		$base = $head ? $head->getElementsByTagName( 'base' )->item( 0 ) : null;
		$body->setAttribute( 'parsoid-version', self::PARSOID_VERSION );
		if ( $base instanceof DOMElement && $base->getAttribute( 'href' ) ) {
			$body->setAttribute( 'base-url', $base->getAttribute( 'href' ) );
		}
		// The class attribute is not used by us and is wastefully long, remove it
		$body->removeAttribute( 'class' );
		return self::getOuterHtml( $body );
	}

	/**
	 * Put the base URI from the <body>'s base-url attribute back in the <head> as a <base> tag.
	 * This reverses (part of) the transformation done by encodeHeadInfo().
	 *
	 * @param string $html HTML (may be a full document, <body> tag  or unwrapped <body> contents)
	 * @return string HTML (<html> tag with <head> and <body>) with the <base> tag restored
	 * @throws WikitextException
	 * @suppress PhanUndeclaredMethod,PhanTypeMismatchArgumentNullable Apparently a phan bug / wrong built-in PHP stubs
	 */
	public static function decodeHeadInfo( $html ) {
		$dom = ContentFixer::createDOM( $html );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$baseUrl = $body->getAttribute( 'base-url' );
		return Html::rawElement( 'html', [],
			Html::rawElement( 'head', [],
				// Only set base href if there's a value to set.
				$baseUrl ? Html::element( 'base', [ 'href' => $baseUrl ] ) : ''
			) .
			self::getOuterHtml( $body )
		);
	}

	/**
	 * Get the Parsoid version from HTML content stored in the database.
	 * This interprets the transformation done by encodeHeadInfo().
	 *
	 * @param string $html
	 * @return string|null Parsoid version number, or null if none found
	 * @suppress PhanUndeclaredMethod Apparently a phan bug / wrong built-in PHP stubs
	 */
	public static function getParsoidVersion( $html ) {
		$dom = ContentFixer::createDOM( $html );
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		$version = $body->getAttribute( 'parsoid-version' );
		return $version !== '' ? $version : null;
	}

	/**
	 * Subpage links from Parsoid don't contain any direct context, its applied via
	 * a <base href="..."> tag, so here we apply a similar rule resolving against
	 * $title
	 *
	 * @param string $text
	 * @param Title $title Title to resolve relative links against
	 * @return Title|null
	 */
	public static function createRelativeTitle( $text, Title $title ) {
		// currently parsoid always uses enough ../ or ./ to go
		// back to the root, a bit of a kludge but just assume we
		// can strip and will end up with a non-relative text.
		$text = preg_replace( '|^(\.\.?/)+|', '', $text );

		if ( $text && ( $text[0] === '/' || $text[0] === '#' ) ) {
			return Title::newFromText( $title->getDBkey() . $text, $title->getNamespace() );
		}

		return Title::newFromText( $text );
	}

	// @todo move into FauxRequest
	public static function generateForwardedCookieForCli() {
		global $wgCookiePrefix;

		$user = Container::get( 'occupation_controller' )->getTalkpageManager();
		// This takes a request object, but doesnt set the cookies against it.
		// patch at https://gerrit.wikimedia.org/r/177403
		$user->setCookies( null, null, /* rememberMe */ true );
		$response = RequestContext::getMain()->getRequest()->response();
		if ( !$response instanceof FauxResponse ) {
			throw new FlowException( 'Expected a FauxResponse in CLI environment' );
		}
		$cookies = $response->getCookies();

		// now we need to convert the array into the cookie format of
		// foo=bar; baz=bang
		$output = [];
		foreach ( $cookies as $key => $value ) {
			$output[] = "$wgCookiePrefix$key={$value['value']}";
		}

		return implode( '; ', $output );
	}
}
