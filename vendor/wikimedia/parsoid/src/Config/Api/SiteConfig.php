<?php

declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config\Api;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Wikimedia\Parsoid\Config\SiteConfig as ISiteConfig;
use Wikimedia\Parsoid\Mocks\MockMetrics;
use Wikimedia\Parsoid\Utils\ConfigUtils;
use Wikimedia\Parsoid\Utils\UrlUtils;
use Wikimedia\Parsoid\Utils\Utils;

/**
 * SiteConfig via MediaWiki's Action API
 *
 * Note this is intended for testing, not performance.
 */
class SiteConfig extends ISiteConfig {

	/** @var ApiHelper */
	private $api;

	/** @var array|null */
	private $siteData, $protocols;

	/** @var string|null */
	private $baseUri, $relativeLinkPrefix;

	/** @var string */
	private $savedCategoryRegexp, $savedRedirectRegexp, $savedBswRegexp;

	/** @phan-var array<int,string> */
	protected $nsNames = [], $nsCase = [];

	/** @phan-var array<string,int> */
	protected $nsIds = [], $nsCanon = [];

	/** @phan-var array<int,bool> */
	protected $nsWithSubpages = [];

	/** @phan-var array<string,string> */
	private $specialPageNames = [];

	/** @phan-var array */
	private $specialPageAliases = [];

	/** @var array|null */
	private $interwikiMap, $variants,
		$langConverterEnabled, $apiMagicWords, $paramMWs,
		$apiVariables, $apiFunctionHooks,
		$allMWs, $extensionTags;

	/** @var int|null */
	private $widthOption;

	/** @var int */
	private $maxDepth = 40;

	private $featureDetectionDone = false;
	private $hasVideoInfo = false;

	/**
	 * @param ApiHelper $api
	 * @param array $opts
	 */
	public function __construct( ApiHelper $api, array $opts ) {
		parent::__construct();

		$this->api = $api;

		if ( isset( $opts['rtTestMode'] ) ) {
			$this->rtTestMode = !empty( $opts['rtTestMode'] );
		}

		if ( isset( $opts['linting'] ) ) {
			$this->linterEnabled = !empty( $opts['linting'] );
		}

		if ( isset( $opts['addHTMLTemplateParameters'] ) ) {
			$this->addHTMLTemplateParameters = !empty( $opts['addHTMLTemplateParameters'] );
		}

		if ( isset( $opts['maxDepth'] ) ) {
			$this->maxDepth = $opts['maxDepth'];
		}

		if ( isset( $opts['logger'] ) ) {
			$this->setLogger( $opts['logger'] );
		} else {
			// Use Monolog's PHP console handler
			$logger = new Logger( "Parsoid CLI" );
			$handler = new ErrorLogHandler();
			// Don't suppress inline newlines
			$handler->setFormatter( new LineFormatter( '%message%', null, true ) );
			$logger->pushHandler( $handler );
			$this->setLogger( $logger );
		}

		if ( isset( $opts['wt2htmlLimits'] ) ) {
			$this->wt2htmlLimits = array_merge(
				$this->wt2htmlLimits, $opts['wt2htmlLimits']
			);
		}
		if ( isset( $opts['html2wtLimits'] ) ) {
			$this->html2wtLimits = array_merge(
				$this->html2wtLimits, $opts['html2wtLimits']
			);
		}
	}

	protected function reset() {
		$this->siteData = null;
		$this->baseUri = null;
		$this->relativeLinkPrefix = null;
		// Superclass value reset since parsertests reuse SiteConfig objects
		$this->linkTrailRegex = false;
		$this->magicWordMap = null;
	}

	/**
	 * Combine sets of regex fragments
	 * @param string[][] $res
	 *  - $regexes[0] are case-insensitive regex fragments. Must not be empty.
	 *  - $regexes[1] are case-sensitive regex fragments. Must not be empty.
	 * @return string Combined regex fragment. May be an alternation. Assumes
	 *  the outer environment is case-sensitive.
	 */
	private function combineRegexArrays( array $res ): string {
		if ( $res ) {
			if ( isset( $res[0] ) ) {
				$res[0] = '(?i:' . implode( '|', $res[0] ) . ')';
			}
			if ( isset( $res[1] ) ) {
				$res[1] = '(?:' . implode( '|', $res[1] ) . ')';
			}
			return implode( '|', $res );
		}
		// None? Return a failing regex
		return '(?!)';
	}

	/**
	 * Add a new namespace to the config
	 *
	 * Protected access to let mocks and parser tests versions
	 * add new namespaces as required.
	 *
	 * @param array $ns Namespace info
	 */
	protected function addNamespace( array $ns ): void {
		$id = (int)$ns['id'];
		$this->nsNames[$id] = $ns['name'];
		$this->nsIds[Utils::normalizeNamespaceName( $ns['name'] )] = $id;
		$this->nsCanon[Utils::normalizeNamespaceName( $ns['canonical'] ?? $ns['name'] )] = $id;
		if ( $ns['subpages'] ) {
			$this->nsWithSubpages[$id] = true;
		}
		$this->nsCase[$id] = (string)$ns['case'];
	}

	private function detectFeatures(): void {
		if ( !$this->featureDetectionDone ) {
			$this->featureDetectionDone = true;
			$data = $this->api->makeRequest( [ 'action' => 'paraminfo', 'modules' => 'query' ] );
			$props = $data["paraminfo"]["modules"][0]["parameters"]["0"]["type"] ?? [];
			$this->hasVideoInfo = array_search( 'videoinfo', $props, true ) !== false;
		}
	}

	public function hasVideoInfo(): bool {
		$this->detectFeatures();
		return $this->hasVideoInfo;
	}

	/**
	 * Load site data from the Action API, if necessary
	 */
	private function loadSiteData(): void {
		if ( $this->siteData !== null ) {
			return;
		}

		$data = $this->api->makeRequest( [
			'action' => 'query',
			'meta' => 'siteinfo',
			'siprop' => 'general|protocols|namespaces|namespacealiases|magicwords|interwikimap|'
				. 'languagevariants|defaultoptions|specialpagealiases|extensiontags|'
				. 'functionhooks|variables',
		] )['query'];

		$this->siteData = $data['general'];
		$this->widthOption = $data['general']['thumblimits'][$data['defaultoptions']['thumbsize']];
		$this->protocols = $data['protocols'];
		$this->apiVariables = $data['variables'];
		$this->apiFunctionHooks = $data['functionhooks'];

		// Process namespace data from API
		foreach ( $data['namespaces'] as $ns ) {
			$this->addNamespace( $ns );
		}
		foreach ( $data['namespacealiases'] as $ns ) {
			$this->nsIds[Utils::normalizeNamespaceName( $ns['alias'] )] = $ns['id'];
		}

		// Process magic word data from API
		$bsws = [];
		$this->paramMWs = [];
		$this->allMWs = [];

		// Recast the API results in the format that core MediaWiki returns internally
		// This enables us to use the Production SiteConfig without changes and add the
		// extra overhead to this developer API usage.
		$this->apiMagicWords = [];
		foreach ( $data['magicwords'] as $mw ) {
			$cs = (int)$mw['case-sensitive'];
			$mwName = $mw['name'];
			$this->apiMagicWords[$mwName][] = $cs;
			$pmws = [];
			$allMWs = [];
			foreach ( $mw['aliases'] as $alias ) {
				$this->apiMagicWords[$mwName][] = $alias;
				if ( substr( $alias, 0, 2 ) === '__' && substr( $alias, -2 ) === '__' ) {
					$bsws[$cs][] = preg_quote( substr( $alias, 2, -2 ), '@' );
				}
				if ( strpos( $alias, '$1' ) !== false ) {
					$pmws[$cs][] = strtr( preg_quote( $alias, '/' ), [ '\\$1' => "(.*?)" ] );
				}
				$allMWs[$cs][] = preg_quote( $alias, '/' );
			}

			if ( $pmws ) {
				$this->paramMWs[$mwName] = '/^(?:' . $this->combineRegexArrays( $pmws ) . ')$/uDS';
			}
			$this->allMWs[$mwName] = '/^(?:' . $this->combineRegexArrays( $allMWs ) . ')$/D';
		}

		$bswRegexp = $this->combineRegexArrays( $bsws );

		// Parse interwiki map data from the API
		$this->interwikiMap = ConfigUtils::computeInterwikiMap( $data['interwikimap'] );

		// Parse variant data from the API
		$this->langConverterEnabled = [];
		$this->variants = [];
		foreach ( $data['languagevariants'] as $base => $variants ) {
			if ( $this->siteData['langconversion'] ) {
				$this->langConverterEnabled[$base] = true;
				foreach ( $variants as $code => $vdata ) {
					$this->variants[$code] = [
						'base' => $base,
						'fallbacks' => $vdata['fallbacks'],
					];
				}
			}
		}

		// Parse extension tag data from the API
		$this->extensionTags = [];
		foreach ( $data['extensiontags'] as $tag ) {
			$tag = preg_replace( '/^<|>$/D', '', $tag );
			$this->ensureExtensionTag( $tag );
		}

		$this->specialPageAliases = $data['specialpagealiases'];
		$this->specialPageNames = [];
		foreach ( $this->specialPageAliases as $special ) {
			$alias = strtr( mb_strtoupper( $special['realname'] ), ' ', '_' );
			$this->specialPageNames[$alias] = $special['aliases'][0];
			foreach ( $special['aliases'] as $alias ) {
				$alias = strtr( mb_strtoupper( $alias ), ' ', '_' );
				$this->specialPageNames[$alias] = $special['aliases'][0];
			}
		}

		$redirect = '(?i:\#REDIRECT)';
		$quote = function ( $s ) {
			$q = preg_quote( $s, '@' );
			# Note that PHP < 7.3 doesn't escape # in preg_quote.  That means
			# that the $redirect regexp will fail if used with the `x` flag.
			# Manually hack around this for PHP 7.2; can remove this workaround
			# once minimum PHP version >= 7.3
			if ( preg_quote( '#' ) === '#' ) {
				$q = str_replace( '#', '\\#', $q );
			}
			return $q;
		};
		foreach ( $data['magicwords'] as $mw ) {
			if ( $mw['name'] === 'redirect' ) {
				$redirect = implode( '|', array_map( $quote, $mw['aliases'] ) );
				if ( !$mw['case-sensitive'] ) {
					$redirect = '(?i:' . $redirect . ')';
				}
				break;
			}
		}
		$category = $this->quoteTitleRe( $this->nsNames[14] ?? 'Category', '@' );
		if ( $category !== 'Category' ) {
			$category = "(?:$category|Category)";
		}

		$this->savedCategoryRegexp = "@{$category}@";
		$this->savedRedirectRegexp = "@{$redirect}@";
		$this->savedBswRegexp = "@{$bswRegexp}@";
	}

	/**
	 * Set the log channel, for debugging
	 * @param LoggerInterface|null $logger
	 */
	public function setLogger( ?LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	public function galleryOptions(): array {
		$this->loadSiteData();
		return $this->siteData['galleryoptions'];
	}

	public function allowedExternalImagePrefixes(): array {
		$this->loadSiteData();
		return $this->siteData['externalimages'] ?? [];
	}

	/**
	 * Determine the article base URI and relative prefix
	 */
	private function determineArticlePath(): void {
		$this->loadSiteData();

		$url = $this->siteData['server'] . $this->siteData['articlepath'];

		if ( substr( $url, -2 ) !== '$1' ) {
			throw new \UnexpectedValueException( "Article path '$url' does not have '$1' at the end" );
		}
		$url = substr( $url, 0, -2 );

		$bits = UrlUtils::parseUrl( $url );
		if ( !$bits ) {
			throw new \UnexpectedValueException( "Failed to parse article path '$url'" );
		}

		if ( empty( $bits['path'] ) ) {
			$path = '/';
		} else {
			$path = UrlUtils::removeDotSegments( $bits['path'] );
		}

		$relParts = [ 'query' => true, 'fragment' => true ];
		$base = array_diff_key( $bits, $relParts );
		$rel = array_intersect_key( $bits, $relParts );

		$i = strrpos( $path, '/' );
		$base['path'] = substr( $path, 0, $i + 1 );
		$rel['path'] = '.' . substr( $path, $i );

		$this->baseUri = UrlUtils::assembleUrl( $base );
		$this->relativeLinkPrefix = UrlUtils::assembleUrl( $rel );
	}

	public function baseURI(): string {
		if ( $this->baseUri === null ) {
			$this->determineArticlePath();
		}
		return $this->baseUri;
	}

	public function relativeLinkPrefix(): string {
		if ( $this->relativeLinkPrefix === null ) {
			$this->determineArticlePath();
		}
		return $this->relativeLinkPrefix;
	}

	/** @inheritDoc */
	public function canonicalNamespaceId( string $name ): ?int {
		$this->loadSiteData();
		return $this->nsCanon[Utils::normalizeNamespaceName( $name )] ?? null;
	}

	/** @inheritDoc */
	public function namespaceId( string $name ): ?int {
		$this->loadSiteData();
		return $this->nsIds[Utils::normalizeNamespaceName( $name )] ?? null;
	}

	/** @inheritDoc */
	public function namespaceName( int $ns ): ?string {
		$this->loadSiteData();
		return $this->nsNames[$ns] ?? null;
	}

	/** @inheritDoc */
	public function namespaceHasSubpages( int $ns ): bool {
		$this->loadSiteData();
		return $this->nsWithSubpages[$ns] ?? false;
	}

	/** @inheritDoc */
	public function namespaceCase( int $ns ): string {
		$this->loadSiteData();
		return $this->nsCase[$ns] ?? 'first-letter';
	}

	/** @inheritDoc */
	public function specialPageLocalName( string $alias ): ?string {
		$this->loadSiteData();
		$alias = strtr( mb_strtoupper( $alias ), ' ', '_' );
		return $this->specialPageNames[$alias] ?? null;
	}

	public function interwikiMagic(): bool {
		$this->loadSiteData();
		return $this->siteData['interwikimagic'];
	}

	public function interwikiMap(): array {
		$this->loadSiteData();
		return $this->interwikiMap;
	}

	public function iwp(): string {
		$this->loadSiteData();
		return $this->siteData['wikiid'];
	}

	public function legalTitleChars() : string {
		$this->loadSiteData();
		return $this->siteData['legaltitlechars'];
	}

	public function linkPrefixRegex(): ?string {
		$this->loadSiteData();

		if ( !empty( $this->siteData['linkprefixcharset'] ) ) {
			return '/[' . $this->siteData['linkprefixcharset'] . ']+$/Du';
		} else {
			// We don't care about super-old MediaWiki, so don't try to parse 'linkprefix'.
			return null;
		}
	}

	/** @inheritDoc */
	protected function linkTrail(): string {
		$this->loadSiteData();
		return $this->siteData['linktrail'];
	}

	public function lang(): string {
		$this->loadSiteData();
		return $this->siteData['lang'];
	}

	public function mainpage(): string {
		$this->loadSiteData();
		return $this->siteData['mainpage'];
	}

	public function responsiveReferences(): array {
		$this->loadSiteData();
		return [
			'enabled' => $this->siteData['citeresponsivereferences'] ?? false,
			'threshold' => 10,
		];
	}

	public function rtl(): bool {
		$this->loadSiteData();
		return $this->siteData['rtl'];
	}

	/** @inheritDoc */
	public function langConverterEnabled( string $lang ): bool {
		$this->loadSiteData();
		return $this->langConverterEnabled[$lang] ?? false;
	}

	public function script(): string {
		$this->loadSiteData();
		return $this->siteData['script'];
	}

	public function scriptpath(): string {
		$this->loadSiteData();
		return $this->siteData['scriptpath'];
	}

	public function server(): string {
		$this->loadSiteData();
		return $this->siteData['server'];
	}

	/** @inheritDoc */
	public function getModulesLoadURI(): string {
		// In JS, you could override the load uri
		// via conf.parsoid.modulesLoadURI, but custom values aren't
		// exported via siteinfo.
		// Parsoid/JS always makes this protocol-relative, so match
		// that (for now at least)
		$path = parent::getModulesLoadURI();
		return preg_replace( '#^https?://#', '//', $path );
	}

	public function redirectRegexp(): string {
		$this->loadSiteData();
		return $this->savedRedirectRegexp;
	}

	public function categoryRegexp(): string {
		$this->loadSiteData();
		return $this->savedCategoryRegexp;
	}

	public function bswRegexp(): string {
		$this->loadSiteData();
		return $this->savedBswRegexp;
	}

	public function timezoneOffset(): int {
		$this->loadSiteData();
		return $this->siteData['timeoffset'];
	}

	public function variants(): array {
		$this->loadSiteData();
		return $this->variants;
	}

	public function widthOption(): int {
		$this->loadSiteData();
		return $this->widthOption;
	}

	/** @inheritDoc */
	protected function getVariableIDs(): array {
		$this->loadSiteData();
		return $this->apiVariables;
	}

	/** @inheritDoc */
	protected function getFunctionHooks(): array {
		$this->loadSiteData();
		return $this->apiFunctionHooks;
	}

	/** @inheritDoc */
	protected function getMagicWords(): array {
		$this->loadSiteData();
		return $this->apiMagicWords;
	}

	/** @inheritDoc */
	public function getMagicWordMatcher( string $id ): string {
		$this->loadSiteData();
		return $this->allMWs[$id] ?? '/^(?!)$/';
	}

	/** @inheritDoc */
	public function getParameterizedAliasMatcher( array $words ): callable {
		$this->loadSiteData();
		$regexes = array_intersect_key( $this->paramMWs, array_flip( $words ) );
		return function ( $text ) use ( $regexes ) {
			/**
			 * $name is the canonical magic word name
			 * $re has patterns for matching aliases
			 */
			foreach ( $regexes as $name => $re ) {
				if ( preg_match( $re, $text, $m ) ) {
					unset( $m[0] );

					// Ex. regexp here is, /^(?:(?:|vinculo\=(.*?)|enlace\=(.*?)|link\=(.*?)))$/uS
					// Check all the capture groups for a value, if not, it's safe to return an
					// empty string since we did get a match.
					foreach ( $m as $v ) {
						if ( $v !== '' ) {
							return [ 'k' => $name, 'v' => $v ];
						}
					}
					return [ 'k' => $name, 'v' => '' ];
				}
			}
			return null;
		};
	}

	/**
	 * This function is public so it can be used to synchronize env for
	 * hybrid parserTests.  The parserTests setup includes the definition
	 * of a number of non-standard extension tags, whose names are passed
	 * over from the JS side in hybrid testing.
	 * @param string $tag Name of an extension tag assumed to be present
	 */
	public function ensureExtensionTag( string $tag ): void {
		$this->loadSiteData();
		$this->extensionTags[mb_strtolower( $tag )] = true;
	}

	/** @inheritDoc */
	protected function getNonNativeExtensionTags(): array {
		$this->loadSiteData();
		return $this->extensionTags;
	}

	/** @inheritDoc */
	public function getMaxTemplateDepth(): int {
		// Not in the API result
		return $this->maxDepth;
	}

	/** @inheritDoc */
	protected function getSpecialNSAliases(): array {
		$nsAliases = [
			'Special',
		];
		foreach ( $this->nsIds as $name => $id ) {
			if ( $id === -1 ) {
				$nsAliases[] = $this->quoteTitleRe( $name, '!' );
			}
		}
		return $nsAliases;
	}

	/** @inheritDoc */
	protected function getSpecialPageAliases( string $specialPage ): array {
		$spAliases = [ $specialPage ];
		foreach ( $this->specialPageAliases as $special ) {
			if ( $special['realname'] === $specialPage ) {
				$spAliases = array_merge( $spAliases, $special['aliases'] );
				break;
			}
		}
		return $spAliases;
	}

	/** @inheritDoc */
	protected function getProtocols(): array {
		$this->loadSiteData();
		return $this->protocols;
	}

	/**
	 * @param array $parsoidSettings
	 * @return SiteConfig
	 */
	public static function fromSettings( array $parsoidSettings ): SiteConfig {
		$opts = [];
		if ( isset( $parsoidSettings['linting'] ) ) {
			$opts['linting'] = !empty( $parsoidSettings['linting'] );
		}
		if ( isset( $parsoidSettings['wt2htmlLimits'] ) ) {
			$opts['wt2htmlLimits'] = $parsoidSettings['wt2htmlLimits'];
		}
		if ( isset( $parsoidSettings['html2wtLimits'] ) ) {
			$opts['html2wtLimits'] = $parsoidSettings['html2wtLimits'];
		}
		$api = ApiHelper::fromSettings( $parsoidSettings );
		return new SiteConfig( $api, $opts );
	}

	/** @inheritDoc */
	public function metrics(): ?StatsdDataFactoryInterface {
		static $metrics = null;
		if ( $metrics === null ) {
			$metrics = new MockMetrics();
		}
		return $metrics;
	}

}
