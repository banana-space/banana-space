<?php

namespace Wikimedia\Parsoid\Mocks;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Wikimedia\Parsoid\Config\SiteConfig;
use Wikimedia\Parsoid\Utils\Utils;

class MockSiteConfig extends SiteConfig {

	/** @var int Unix timestamp */
	private $fakeTimestamp = 946782245; // 2000-01-02T03:04:05Z

	/** @var int */
	private $timezoneOffset = 0; // UTC

	/** @var bool */
	private $interwikiMagic = true;

	/** @var int|null */
	private $tidyWhitespaceBugMaxLength = null;

	protected $namespaceMap = [
		'media' => -2,
		'special' => -1,
		'' => 0,
		'talk' => 1,
		'user' => 2,
		'user_talk' => 3,
		// Last one will be used by namespaceName
		'project' => 4, 'wp' => 4, 'wikipedia' => 4,
		'project_talk' => 5, 'wt' => 5, 'wikipedia_talk' => 5,
		'file' => 6,
		'file_talk' => 7,
		'category' => 14,
		'category_talk' => 15,
	];

	/** @var array<int, bool> */
	protected $namespacesWithSubpages = [];

	/** @var array */
	protected $interwikiMap = [];

	/** @var int */
	private $maxDepth = 40;

	/** @var string|null */
	private $linkPrefixRegex = null;

	/**
	 * @param array $opts
	 */
	public function __construct( array $opts ) {
		parent::__construct();

		if ( isset( $opts['rtTestMode'] ) ) {
			$this->rtTestMode = !empty( $opts['rtTestMode'] );
		}
		if ( isset( $opts['linting'] ) ) {
			$this->linterEnabled = $opts['linting'];
		}
		if ( isset( $opts['maxDepth'] ) ) {
			$this->maxDepth = $opts['maxDepth'];
		}
		$this->tidyWhitespaceBugMaxLength = $opts['tidyWhitespaceBugMaxLength'] ?? null;
		$this->linkPrefixRegex = $opts['linkPrefixRegex'] ?? null;
		$this->linkTrailRegex = $opts['linkTrailRegex'] ?? '/^([a-z]+)/sD'; // enwiki default

		// Use Monolog's PHP console handler
		$logger = new Logger( "Parsoid CLI" );
		$handler = new ErrorLogHandler();
		$handler->setFormatter( new LineFormatter( '%message%' ) );
		$logger->pushHandler( $handler );
		$this->setLogger( $logger );
	}

	/**
	 * Set the log channel, for debuggings
	 * @param LoggerInterface|null $logger
	 */
	public function setLogger( ?LoggerInterface $logger ): void {
		$this->logger = $logger;
	}

	public function tidyWhitespaceBugMaxLength(): int {
		return $this->tidyWhitespaceBugMaxLength ?? parent::tidyWhitespaceBugMaxLength();
	}

	public function allowedExternalImagePrefixes(): array {
		return [];
	}

	public function baseURI(): string {
		return '//my.wiki.example/wikix/';
	}

	public function redirectRegexp(): string {
		return '/(?i:#REDIRECT)/';
	}

	public function categoryRegexp(): string {
		return '/Category/';
	}

	public function bswRegexp(): string {
		return '/' .
				'NOGLOBAL|DISAMBIG|NOCOLLABORATIONHUBTOC|nocollaborationhubtoc|NOTOC|notoc|' .
				'NOGALLERY|nogallery|FORCETOC|forcetoc|TOC|toc|NOEDITSECTION|noeditsection|' .
				'NOTITLECONVERT|notitleconvert|NOTC|notc|NOCONTENTCONVERT|nocontentconvert|' .
				'NOCC|nocc|NEWSECTIONLINK|NONEWSECTIONLINK|HIDDENCAT|INDEX|NOINDEX|STATICREDIRECT' .
			'/';
	}

	/** @inheritDoc */
	public function canonicalNamespaceId( string $name ): ?int {
		return $this->namespaceMap[$name] ?? null;
	}

	/** @inheritDoc */
	public function namespaceId( string $name ): ?int {
		$name = Utils::normalizeNamespaceName( $name );
		return $this->namespaceMap[$name] ?? null;
	}

	/** @inheritDoc */
	public function namespaceName( int $ns ): ?string {
		static $map = null;
		if ( $map === null ) {
			$map = array_flip( $this->namespaceMap );
		}
		if ( !isset( $map[$ns] ) ) {
			return null;
		}
		return ucwords( strtr( $map[$ns], '_', ' ' ) );
	}

	/** @inheritDoc */
	public function namespaceHasSubpages( int $ns ): bool {
		return !empty( $this->namespacesWithSubpages[$ns] );
	}

	/** @inheritDoc */
	public function namespaceCase( int $ns ): string {
		return 'first-letter';
	}

	/** @inheritDoc */
	public function specialPageLocalName( string $alias ): ?string {
		throw new \BadMethodCallException( 'Not implemented' );
	}

	/**
	 * @param bool $val
	 */
	public function setInterwikiMagic( bool $val ): void {
		$this->interwikiMagic = $val;
	}

	public function interwikiMagic(): bool {
		return $this->interwikiMagic;
	}

	public function interwikiMap(): array {
		return $this->interwikiMap;
	}

	public function iwp(): string {
		return 'mywiki';
	}

	public function legalTitleChars() : string {
		return ' %!"$&\'()*,\-.\/0-9:;=?@A-Z\\\\^_`a-z~\x80-\xFF+';
	}

	public function linkPrefixRegex(): ?string {
		return $this->linkPrefixRegex;
	}

	protected function linkTrail(): string {
		throw new \BadMethodCallException(
			'Should not be used. linkTrailRegex() is overridden here.' );
	}

	public function linkTrailRegex(): ?string {
		return $this->linkTrailRegex;
	}

	public function lang(): string {
		return 'en';
	}

	public function mainpage(): string {
		return 'Main Page';
	}

	public function responsiveReferences(): array {
		return [
			'enabled' => true,
			'threshold' => 10,
		];
	}

	public function rtl(): bool {
		return false;
	}

	/** @inheritDoc */
	public function langConverterEnabled( string $lang ): bool {
		return $lang === 'sr';
	}

	public function script(): string {
		return '/wx/index.php';
	}

	public function scriptpath(): string {
		return '/wx';
	}

	public function server(): string {
		return '//my.wiki.example';
	}

	public function timezoneOffset(): int {
		return $this->timezoneOffset;
	}

	public function variants(): array {
		return [
			'sr' => [
				'base' => 'sr',
				'fallbacks' => [
					'sr-ec'
				]
			],
			'sr-ec' => [
				'base' => 'sr',
				'fallbacks' => [
					'sr'
				]
			],
			'sr-el' => [
				'base' => 'sr',
				'fallbacks' => [
					'sr'
				]
			]
		];
	}

	public function widthOption(): int {
		return 220;
	}

	/** @inheritDoc */
	protected function getVariableIDs(): array {
		return []; // None for now
	}

	/** @inheritDoc */
	protected function getFunctionHooks(): array {
		return []; // None for now
	}

	/** @inheritDoc */
	protected function getMagicWords(): array {
		// make all magic words case-sensitive
		return [ 'toc'           => [ 1, 'toc' ],
			'img_thumbnail' => [ 1, 'thumb' ],
			'img_none'      => [ 1, 'none' ],
			'__notoc__'     => [ 1, '__notoc__' ]
		];
	}

	/** @inheritDoc */
	public function getMagicWordMatcher( string $id ): string {
		if ( $id === 'toc' ) {
			return '/^TOC$/';
		} else {
			return '/(?!)/';
		}
	}

	/** @inheritDoc */
	public function getParameterizedAliasMatcher( array $words ): callable {
		$paramMWs = [
			'img_lossy' => "/^(?:(?i:lossy\=(.*?)))$/uS",
			'timedmedia_thumbtime' => "/^(?:(?i:thumbtime\=(.*?)))$/uS",
			'timedmedia_starttime' => "/^(?:(?i:start\=(.*?)))$/uS",
			'timedmedia_endtime' => "/^(?:(?i:end\=(.*?)))$/uS",
			'timedmedia_disablecontrols' => "/^(?:(?i:disablecontrols\=(.*?)))$/uS",
			'img_manualthumb' => "/^(?:(?:thumbnail\=(.*?)|thumb\=(.*?)))$/uS",
			'img_width' => "/^(?:(?:(.*?)px))$/uS",
			'img_lang' => "/^(?:(?:lang\=(.*?)))$/uS",
			'img_page' => "/^(?:(?:page\=(.*?)|page (.*?)))$/uS",
			'img_upright' => "/^(?:(?:upright\=(.*?)|upright (.*?)))$/uS",
			'img_link' => "/^(?:(?:link\=(.*?)))$/uS",
			'img_alt' => "/^(?:(?:alt\=(.*?)))$/uS",
			'img_class' => "/^(?:(?:class\=(.*?)))$/uS"
		];
		$regexes = array_intersect_key( $paramMWs, array_flip( $words ) );
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

	/** @inheritDoc */
	protected function getNonNativeExtensionTags(): array {
		return [
			'indicator' => true,
			'timeline' => true,
			'hiero' => true,
			'charinsert' => true,
			'inputbox' => true,
			'imagemap' => true,
			'source' => true,
			'syntaxhighlight' => true,
			'section' => true,
			'score' => true,
			'templatedata' => true,
			'math' => true,
			'ce' => true,
			'chem' => true,
			'graph' => true,
			'maplink' => true,
			'categorytree' => true,
			'templatestyles' => true
		];
	}

	/** @inheritDoc */
	public function getMaxTemplateDepth(): int {
		return $this->maxDepth;
	}

	/** @inheritDoc */
	protected function getSpecialPageAliases( string $specialPage ): array {
		if ( $specialPage === 'Booksources' ) {
			return [ 'Booksources', 'BookSources' ]; // Mock value
		} else {
			throw new \BadMethodCallException( 'Not implemented' );
		}
	}

	/** @inheritDoc */
	protected function getSpecialNSAliases(): array {
		return [ "Special", "special" ]; // Mock value
	}

	/** @inheritDoc */
	protected function getProtocols(): array {
		return [ "http:", "https:", "irc:", "ircs:", "news:", "ftp:", "mailto:", "gopher:", "//" ];
	}

	public function fakeTimestamp(): ?int {
		return $this->fakeTimestamp;
	}

	/**
	 * Set the fake timestamp for testing
	 * @param int|null $ts Unix timestamp
	 */
	public function setFakeTimestamp( ?int $ts ): void {
		$this->fakeTimestamp = $ts;
	}

	/**
	 * Set the timezone offset for testing
	 * @param int $offset Offset from UTC
	 */
	public function setTimezoneOffset( int $offset ): void {
		$this->timezoneOffset = $offset;
	}

	public function scrubBidiChars(): bool {
		return true;
	}
}
