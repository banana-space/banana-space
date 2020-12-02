<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Config;

use DOMDocument;
use DOMElement;
use DOMNode;
use Wikimedia\Parsoid\Core\ContentModelHandler;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;
use Wikimedia\Parsoid\Logger\ParsoidLogger;
use Wikimedia\Parsoid\Parsoid;
use Wikimedia\Parsoid\Tokens\Token;
use Wikimedia\Parsoid\Utils\DataBag;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;
use Wikimedia\Parsoid\Utils\Title;
use Wikimedia\Parsoid\Utils\TitleException;
use Wikimedia\Parsoid\Utils\TitleNamespace;
use Wikimedia\Parsoid\Utils\TokenUtils;
use Wikimedia\Parsoid\Utils\Utils;
use Wikimedia\Parsoid\Wt2Html\Frame;
use Wikimedia\Parsoid\Wt2Html\PageConfigFrame;
use Wikimedia\Parsoid\Wt2Html\ParserPipelineFactory;
use Wikimedia\Parsoid\Wt2Html\TT\Sanitizer;

// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic

/**
 * Environment/Envelope class for Parsoid
 *
 * Carries around the SiteConfig and PageConfig during an operation
 * and provides certain other services.
 */
class Env {

	/** @var SiteConfig */
	private $siteConfig;

	/** @var PageConfig */
	private $pageConfig;

	/** @var DataAccess */
	private $dataAccess;

	/**
	 * The top-level frame for this conversion.  This largely wraps the
	 * PageConfig.
	 *
	 * In the future we may replace PageConfig with the Frame, and add
	 * a
	 * @var Frame
	 */
	public $topFrame;
	// XXX In the future, perhaps replace PageConfig with the Frame, and
	// add $this->currentFrame (relocated from TokenTransformManager) if/when
	// we've removed async parsing.

	/**
	 * @var bool Are data accesses disabled?
	 *
	 * FIXME: This can probably moved to a NoDataAccess instance, rather than
	 * being an explicit mode of Parsoid.  See T229469
	 */
	private $noDataAccess;

	/**
	 * @var bool Are we using native template expansion?
	 *
	 * Parsoid implements native template expansion, which is currently
	 * only used during parser tests; in production, template expansion
	 * is done via MediaWiki's legacy preprocessor.
	 *
	 * FIXME: Hopefully this distinction can be removed when we're entirely
	 * in PHP land.
	 */
	private $nativeTemplateExpansion;

	/** @phan-var array<string,int> */
	private $wt2htmlUsage = [];

	/** @phan-var array<string,int> */
	private $html2wtUsage = [];

	/** @var DOMDocument[] */
	private $liveDocs = [];

	/** @var bool */
	private $wrapSections = true;

	/** @var string */
	private $requestOffsetType = 'byte';

	/** @var string */
	private $currentOffsetType = 'byte';

	/** @var array<string,mixed> */
	private $behaviorSwitches = [];

	/**
	 * Maps fragment id to the fragment forest (array of DOMNodes).
	 * @var array<string,DOMNode[]>
	 */
	private $fragmentMap = [];

	/**
	 * @var int used to generate fragment ids as needed during parse
	 */
	private $fid = 1;

	/** @var int used to generate uids as needed during this parse */
	private $uid = 1;

	/** @var array[] Lints recorded */
	private $lints = [];

	/** @var bool logLinterData */
	public $logLinterData = false;

	/** @var bool[] */
	private $traceFlags;

	/** @var bool[] */
	private $dumpFlags;

	/** @var bool[] */
	private $debugFlags;

	/** @var ParsoidLogger */
	private $parsoidLogger;

	/** @var float */
	public $startTime;

	/** @var bool */
	private $scrubWikitext = false;

	/**
	 * The default content version that Parsoid assumes it's serializing or
	 * updating in the pb2pb endpoints
	 *
	 * @var string
	 */
	private $inputContentVersion;

	/**
	 * The default content version that Parsoid will generate.
	 *
	 * @var string
	 */
	private $outputContentVersion;

	/**
	 * If non-null, the language variant used for Parsoid HTML;
	 * we convert to this if wt2html, or from this if html2wt.
	 * @var string
	 */
	private $htmlVariantLanguage;

	/**
	 * If non-null, the language variant to be used for wikitext.
	 * If null, heuristics will be used to identify the original wikitext variant
	 * in wt2html mode, and in html2wt mode new or edited HTML will be left unconverted.
	 * @var string
	 */
	private $wtVariantLanguage;

	/** @var ParserPipelineFactory */
	private $pipelineFactory;

	/**
	 * FIXME Used in DedupeStyles::dedupe()
	 * @var array
	 */
	public $styleTagKeys = [];

	/** @var bool */
	public $pageBundle = false;

	/** @var bool */
	public $discardDataParsoid = false;

	/** @var DOMNode */
	private $origDOM;

	/** @var DOMDocument */
	private $domDiff;

	/**
	 * Page properties (module resources primarily) that need to be output
	 * @var array
	 */
	private $outputProps = [];

	/**
	 * PORT-FIXME: public currently
	 * Cache of wikitext source for a title
	 * @var array
	 */
	public $pageCache = [];

	/**
	 * PORT-FIXME: public currently
	 * HTML Cache of expanded transclusions to support
	 * reusing expansions from HTML of previous revision.
	 * @var array
	 */
	public $transclusionCache = [];

	/**
	 * PORT-FIXME: public currently
	 * HTML Cache of expanded media wikiext to support
	 * reusing expansions from HTML of previous revision.
	 * @var array
	 */
	public $mediaCache = [];

	/**
	 * PORT-FIXME: public currently
	 * HTML Cache of expanded extension tags to support
	 * reusing expansions from HTML of previous revision.
	 * @var array
	 */
	public $extensionCache = [];

	/**
	 * @param SiteConfig $siteConfig
	 * @param PageConfig $pageConfig
	 * @param DataAccess $dataAccess
	 * @param array|null $options
	 *  - wrapSections: (bool) Whether `<section>` wrappers should be added.
	 *  - pageBundle: (bool) Sets ids on nodes and stores data-* attributes in a JSON blob.
	 *  - scrubWikitext: (bool) Indicates emit "clean" wikitext.
	 *  - traceFlags: (array) Flags indicating which components need to be traced
	 *  - dumpFlags: (bool[]) Dump flags
	 *  - debugFlags: (bool[]) Debug flags
	 *  - noDataAccess: boolean
	 *  - nativeTemplateExpansion: boolean
	 *  - discardDataParsoid: boolean
	 *  - offsetType: 'byte' (default), 'ucs2', 'char'
	 *                See `Parsoid\Wt2Html\PP\Processors\ConvertOffsets`.
	 *  - logLinterData: (bool) Should we log linter data if linting is enabled?
	 *  - htmlVariantLanguage: string|null
	 *      If non-null, the language variant used for Parsoid HTML;
	 *      we convert to this if wt2html, or from this if html2wt.
	 *  - wtVariantLanguage: string|null
	 *      If non-null, the language variant to be used for wikitext.
	 *      If null, heuristics will be used to identify the original
	 *      wikitext variant in wt2html mode, and in html2wt mode new
	 *      or edited HTML will be left unconverted.
	 *  - logLevels: (string[]) Levels to log
	 */
	public function __construct(
		SiteConfig $siteConfig, PageConfig $pageConfig, DataAccess $dataAccess, array $options = null
	) {
		$options = $options ?? [];
		$this->siteConfig = $siteConfig;
		$this->pageConfig = $pageConfig;
		$this->dataAccess = $dataAccess;
		$this->topFrame = new PageConfigFrame( $this, $pageConfig, $siteConfig );
		if ( isset( $options['scrubWikitext'] ) ) {
			$this->scrubWikitext = !empty( $options['scrubWikitext'] );
		}
		if ( isset( $options['wrapSections'] ) ) {
			$this->wrapSections = !empty( $options['wrapSections'] );
		}
		if ( isset( $options['pageBundle'] ) ) {
			$this->pageBundle = !empty( $options['pageBundle'] );
		}
		$this->pipelineFactory = new ParserPipelineFactory( $this );
		$defaultContentVersion = Parsoid::defaultHTMLVersion();
		$this->inputContentVersion = $options['inputContentVersion'] ?? $defaultContentVersion;
		// FIXME: We should have a check for the supported input content versions as well.
		// That will require a separate constant.
		$this->outputContentVersion = $options['outputContentVersion'] ?? $defaultContentVersion;
		if ( !in_array( $this->outputContentVersion, Parsoid::AVAILABLE_VERSIONS, true ) ) {
			throw new \UnexpectedValueException(
				$this->outputContentVersion . ' is not an available content version.' );
		}
		$this->htmlVariantLanguage = $options['htmlVariantLanguage'] ?? null;
		$this->wtVariantLanguage = $options['wtVariantLanguage'] ?? null;
		$this->noDataAccess = !empty( $options['noDataAccess'] );
		$this->nativeTemplateExpansion = !empty( $options['nativeTemplateExpansion'] );
		$this->discardDataParsoid = !empty( $options['discardDataParsoid'] );
		$this->requestOffsetType = $options['offsetType'] ?? 'byte';
		$this->logLinterData = !empty( $options['logLinterData'] );
		$this->traceFlags = $options['traceFlags'] ?? [];
		$this->dumpFlags = $options['dumpFlags'] ?? [];
		$this->debugFlags = $options['debugFlags'] ?? [];
		$this->parsoidLogger = new ParsoidLogger( $this->siteConfig->getLogger(), [
			'logLevels' => $options['logLevels'] ?? [ 'fatal', 'error', 'warn', 'info' ],
			'debugFlags' => $this->debugFlags,
			'dumpFlags' => $this->dumpFlags,
			'traceFlags' => $this->traceFlags
		] );
	}

	/**
	 * @return bool
	 */
	public function hasTraceFlags(): bool {
		return !empty( $this->traceFlags );
	}

	/**
	 * Test which trace information to log
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public function hasTraceFlag( string $flag ): bool {
		return isset( $this->traceFlags[$flag] );
	}

	/**
	 * @return bool
	 */
	public function hasDumpFlags(): bool {
		return !empty( $this->dumpFlags );
	}

	/**
	 * Test which state to dump
	 *
	 * @param string $flag Flag name.
	 * @return bool
	 */
	public function hasDumpFlag( string $flag ): bool {
		return isset( $this->dumpFlags[$flag] );
	}

	/**
	 * Get the site config
	 * @return SiteConfig
	 */
	public function getSiteConfig(): SiteConfig {
		return $this->siteConfig;
	}

	/**
	 * Get the page config
	 * @return PageConfig
	 */
	public function getPageConfig(): PageConfig {
		return $this->pageConfig;
	}

	/**
	 * Get the data access object
	 * @return DataAccess
	 */
	public function getDataAccess(): DataAccess {
		return $this->dataAccess;
	}

	public function noDataAccess(): bool {
		return $this->noDataAccess;
	}

	public function nativeTemplateExpansionEnabled(): bool {
		return $this->nativeTemplateExpansion;
	}

	/**
	 * Get the current uid counter value
	 * @return int
	 */
	public function getUID(): int {
		return $this->uid;
	}

	/**
	 * Get the current fragment id counter value
	 * @return int
	 */
	public function getFID(): int {
		return $this->fid;
	}

	/**
	 * Whether `<section>` wrappers should be added.
	 * @todo Does this actually belong here? Should it be a behavior switch?
	 * @return bool
	 */
	public function getWrapSections(): bool {
		return $this->wrapSections;
	}

	public function getPipelineFactory(): ParserPipelineFactory {
		return $this->pipelineFactory;
	}

	/**
	 * Return the external format of character offsets in source ranges.
	 * Internally we always keep DomSourceRange and SourceRange information
	 * as UTF-8 byte offsets for efficiency (matches the native string
	 * representation), but for external use we can convert these to
	 * other formats when we output wt2html or input for html2wt.
	 *
	 * @see Parsoid\Wt2Html\PP\Processors\ConvertOffsets
	 * @return string 'byte', 'ucs2', or 'char'
	 */
	public function getRequestOffsetType(): string {
		return $this->requestOffsetType;
	}

	/**
	 * Return the current format of character offsets in source ranges.
	 * This allows us to track whether the internal byte offsets have
	 * been converted to the external format (as returned by
	 * `getRequestOffsetType`) yet.
	 *
	 * @see Parsoid\Wt2Html\PP\Processors\ConvertOffsets
	 * @return string 'byte', 'ucs2', or 'char'
	 */
	public function getCurrentOffsetType(): string {
		return $this->currentOffsetType;
	}

	/**
	 * Update the current offset type. Only
	 * Parsoid\Wt2Html\PP\Processors\ConvertOffsets should be doing this.
	 * @param string $offsetType 'byte', 'ucs2', or 'char'
	 */
	public function setCurrentOffsetType( string $offsetType ) {
		$this->currentOffsetType = $offsetType;
	}

	/**
	 * Resolve strings that are page-fragments or subpage references with
	 * respect to the current page name.
	 *
	 * TODO: Handle namespaces relative links like [[User:../../]] correctly, they
	 * shouldn't be treated like links at all.
	 *
	 * @param string $str Page fragment or subpage reference. Not URL encoded.
	 * @param bool $resolveOnly If true, only trim and add the current title to
	 *  lone fragments. TODO: This parameter seems poorly named.
	 * @return string Resolved title
	 */
	public function resolveTitle( string $str, bool $resolveOnly = false ): string {
		$origName = $str;
		$str = trim( $str ); // PORT-FIXME: Care about non-ASCII whitespace?

		$pageConfig = $this->getPageConfig();

		// Resolve lonely fragments (important if the current page is a subpage,
		// otherwise the relative link will be wrong)
		if ( $str !== '' && $str[0] === '#' ) {
			$str = $pageConfig->getTitle() . $str;
		}

		// Default return value
		$titleKey = $str;
		if ( $this->getSiteConfig()->namespaceHasSubpages( $pageConfig->getNs() ) ) {
			// Resolve subpages
			$reNormalize = false;
			if ( preg_match( '!^(?:\.\./)+!', $str, $relUp ) ) {
				$levels = strlen( $relUp[0] ) / 3;  // Levels are indicated by '../'.
				$titleBits = explode( '/', $pageConfig->getTitle() );
				if ( count( $titleBits ) <= $levels ) {
					// Too many levels -- invalid relative link
					return $origName;
				}
				$newBits = array_slice( $titleBits, 0, -$levels );
				if ( $str !== $relUp[0] ) {
					$newBits[] = substr( $str, $levels * 3 );
				}
				$str = implode( '/', $newBits );
				$reNormalize = true;
			} elseif ( $str !== '' && $str[0] === '/' ) {
				// Resolve absolute subpage links
				$str = $pageConfig->getTitle() . $str;
				$reNormalize = true;
			}

			if ( $reNormalize && !$resolveOnly ) {
				// Remove final slashes if present.
				// See https://gerrit.wikimedia.org/r/173431
				$str = rtrim( $str, '/' );
				$titleKey = (string)$this->normalizedTitleKey( $str );
			}
		}

		// Strip leading ':'
		if ( $titleKey !== '' && $titleKey[0] === ':' && !$resolveOnly ) {
			$titleKey = substr( $titleKey, 1 );
		}
		return $titleKey;
	}

	/**
	 * Convert a Title to a string
	 * @param Title $title
	 * @param bool $ignoreFragment
	 * @return string
	 */
	private function titleToString( Title $title, bool $ignoreFragment = false ): string {
		$ret = $title->getPrefixedDBKey();
		if ( !$ignoreFragment ) {
			$fragment = $title->getFragment() ?? '';
			if ( $fragment !== '' ) {
				$ret .= '#' . $fragment;
			}
		}
		return $ret;
	}

	/**
	 * Get normalized title key for a title string.
	 *
	 * @param string $str Should be in url-decoded format.
	 * @param bool $noExceptions Return null instead of throwing exceptions.
	 * @param bool $ignoreFragment Ignore the fragment, if any.
	 * @return string|null Normalized title key for a title string (or null for invalid titles).
	 */
	public function normalizedTitleKey(
		string $str, bool $noExceptions = false, bool $ignoreFragment = false
	): ?string {
		$title = $this->makeTitleFromURLDecodedStr( $str, 0, $noExceptions );
		if ( !$title ) {
			return null;
		}
		return $this->titleToString( $title, $ignoreFragment );
	}

	/**
	 * Normalize and resolve the page title
	 * @deprecated Just use $this->getPageConfig()->getTitle() directly
	 * @return string
	 */
	public function normalizeAndResolvePageTitle(): string {
		return $this->getPageConfig()->getTitle();
	}

	/**
	 * Create a Title object
	 * @param string $text URL-decoded text
	 * @param int|TitleNamespace $defaultNs
	 * @param bool $noExceptions
	 * @return Title|null
	 */
	private function makeTitle( string $text, $defaultNs = 0, bool $noExceptions = false ): ?Title {
		try {
			if ( preg_match( '!^(?:[#/]|\.\./)!', $text ) ) {
				$defaultNs = $this->getPageConfig()->getNs();
			}
			$text = $this->resolveTitle( $text );
			return Title::newFromText( $text, $this->getSiteConfig(), $defaultNs );
		} catch ( TitleException $e ) {
			if ( $noExceptions ) {
				return null;
			}
			throw $e;
		}
	}

	/**
	 * Create a Title object
	 * @see Title::newFromURL in MediaWiki
	 * @param string $str URL-encoded text
	 * @param int|TitleNamespace $defaultNs
	 * @param bool $noExceptions
	 * @return Title|null
	 */
	public function makeTitleFromText(
		string $str, $defaultNs = 0, bool $noExceptions = false
	): ?Title {
		return $this->makeTitle( Utils::decodeURIComponent( $str ), $defaultNs, $noExceptions );
	}

	/**
	 * Create a Title object
	 * @see Title::newFromText in MediaWiki
	 * @param string $str URL-decoded text
	 * @param int|TitleNamespace $defaultNs
	 * @param bool $noExceptions
	 * @return Title|null
	 */
	public function makeTitleFromURLDecodedStr(
		string $str, $defaultNs = 0, bool $noExceptions = false
	): ?Title {
		return $this->makeTitle( $str, $defaultNs, $noExceptions );
	}

	/**
	 * Make a link to a Title
	 * @param Title $title
	 * @return string
	 */
	public function makeLink( Title $title ): string {
		return Sanitizer::sanitizeTitleURI(
			$this->getSiteConfig()->relativeLinkPrefix() . $this->titleToString( $title ),
			false
		);
	}

	/**
	 * Test if an href attribute value could be a valid link target
	 * @param string|(Token|string)[] $href
	 * @return bool
	 */
	public function isValidLinkTarget( $href ): bool {
		$href = TokenUtils::tokensToString( $href );

		// decode percent-encoding so that we can reliably detect
		// bad page title characters
		$hrefToken = Utils::decodeURIComponent( $href );
		return $this->normalizedTitleKey( $this->resolveTitle( $hrefToken, true ), true ) !== null;
	}

	/**
	 * Generate a new uid
	 * @return int
	 */
	public function generateUID(): int {
		return $this->uid++;
	}

	/**
	 * Generate a new object id
	 * @return string
	 */
	public function newObjectId(): string {
		return "mwt" . $this->generateUID();
	}

	/**
	 * Generate a new about id
	 * @return string
	 */
	public function newAboutId(): string {
		return "#" . $this->newObjectId();
	}

	/**
	 * Store reference to original DOM (body)
	 * @param DOMElement $domBody
	 */
	public function setOrigDOM( DOMElement $domBody ): void {
		$this->origDOM = $domBody;
	}

	/**
	 * Return reference to original DOM (body)
	 * @return DOMElement
	 */
	public function getOrigDOM(): DOMElement {
		return $this->origDOM;
	}

	/**
	 * Store reference to DOM diff document
	 * @param DOMDocument $doc
	 */
	public function setDOMDiff( $doc ): void {
		$this->domDiff = $doc;
	}

	/**
	 * Return reference to DOM diff document
	 * @return DOMDocument|null
	 */
	public function getDOMDiff(): ?DOMDocument {
		return $this->domDiff;
	}

	/**
	 * Generate a new fragment id
	 * @return string
	 */
	public function newFragmentId(): string {
		return "mwf" . (string)$this->fid++;
	}

	/**
	 * FIXME: This function could be given a better name to reflect what it does.
	 *
	 * @param DOMDocument $doc
	 * @param DataBag|null $bag
	 */
	public function referenceDataObject( DOMDocument $doc, ?DataBag $bag = null ): void {
		// `bag` is a deliberate dynamic property; see DOMDataUtils::getBag()
		// @phan-suppress-next-line PhanUndeclaredProperty dynamic property
		$doc->bag = $bag ?? new DataBag();

		// Prevent GC from collecting the PHP wrapper around the libxml doc
		$this->liveDocs[] = $doc;
	}

	/**
	 * @param string $html
	 * @return DOMDocument
	 */
	public function createDocument( string $html = '' ): DOMDocument {
		$doc = DOMUtils::parseHTML( $html );
		// Cache the head and body.
		DOMCompat::getHead( $doc );
		DOMCompat::getBody( $doc );
		$this->referenceDataObject( $doc );
		return $doc;
	}

	/**
	 * BehaviorSwitchHandler support function that adds a property named by
	 * $variable and sets it to $state
	 *
	 * @deprecated Use setBehaviorSwitch() instead.
	 * @param string $variable
	 * @param mixed $state
	 */
	public function setVariable( string $variable, $state ): void {
		$this->setBehaviorSwitch( $variable, $state );
	}

	/**
	 * Record a behavior switch.
	 *
	 * @todo Does this belong here, or on some equivalent to MediaWiki's ParserOutput?
	 * @param string $switch Switch name
	 * @param mixed $state Relevant state data to record
	 */
	public function setBehaviorSwitch( string $switch, $state ): void {
		$this->behaviorSwitches[$switch] = $state;
	}

	/**
	 * Fetch the state of a previously-recorded behavior switch.
	 *
	 * @todo Does this belong here, or on some equivalent to MediaWiki's ParserOutput?
	 * @param string $switch Switch name
	 * @param mixed|null $default Default value if the switch was never set
	 * @return mixed State data that was previously passed to setBehaviorSwitch(), or $default
	 */
	public function getBehaviorSwitch( string $switch, $default = null ) {
		return $this->behaviorSwitches[$switch] ?? $default;
	}

	/**
	 * @return array<string,DOMNode[]>
	 */
	public function getDOMFragmentMap(): array {
		return $this->fragmentMap;
	}

	/**
	 * @param string $id Fragment id
	 * @return DOMNode[]
	 */
	public function getDOMFragment( string $id ): array {
		return $this->fragmentMap[$id];
	}

	/**
	 * @param string $id Fragment id
	 * @param DOMNode[] $forest DOM forest (contiguous array of DOM trees)
	 *   to store against the fragment id
	 */
	public function setDOMFragment( string $id, array $forest ): void {
		$this->fragmentMap[$id] = $forest;
	}

	/**
	 * Record a lint
	 * @param string $type Lint type key
	 * @param array $lintData Data for the lint.
	 *  - dsr: (SourceRange)
	 *  - params: (array)
	 *  - templateInfo: (array|null)
	 */
	public function recordLint( string $type, array $lintData ): void {
		// Parsoid-JS tests don't like getting null properties where JS had undefined.
		$lintData = array_filter( $lintData, function ( $v ) {
			return $v !== null;
		} );

		if ( empty( $lintData['dsr'] ) ) {
			$this->log( 'error/lint', "Missing DSR; msg=", $lintData );
			return;
		}

		// This will always be recorded as a native 'byte' offset
		$lintData['dsr'] = $lintData['dsr']->jsonSerialize();

		// Ensure a "params" array
		if ( !isset( $lintData['params'] ) ) {
			$lintData['params'] = [];
		}

		$this->lints[] = [ 'type' => $type ] + $lintData;
	}

	/**
	 * Retrieve recorded lints
	 * @return array[]
	 */
	public function getLints(): array {
		return $this->lints;
	}

	/**
	 * Init lints to the passed array.
	 *
	 * FIXME: This is currently needed to reset lints after converting
	 * DSR offsets because of ordering of DOM passes. So, in reality,
	 * there should be no real use case for setting this anywhere else
	 * but from that single callsite.
	 *
	 * @param array $lints
	 */
	public function setLints( array $lints ): void {
		$this->lints = $lints;
	}

	/**
	 * @param mixed ...$args
	 */
	public function log( ...$args ): void {
		$this->parsoidLogger->log( ...$args );
	}

	/**
	 * Update a profile timer.
	 *
	 * @param string $resource
	 * @param mixed $time
	 * @param mixed $cat
	 */
	public function bumpTimeUse( string $resource, $time, $cat ): void {
		// --trace ttm:* trip on this if we throw an exception
		// throw new \BadMethodCallException( 'not yet ported' );
	}

	/**
	 * Update a profile counter.
	 *
	 * @param string $resource
	 * @param int $n The amount to increment the counter; defaults to 1.
	 */
	public function bumpCount( string $resource, int $n = 1 ): void {
		throw new \BadMethodCallException( 'not yet ported' );
	}

	/**
	 * Bump usage of some limited parser resource
	 * (ex: tokens, # transclusions, # list items, etc.)
	 *
	 * @param string $resource
	 * @param int $count How much of the resource is used?
	 * @throws ResourceLimitExceededException
	 */
	public function bumpWt2HtmlResourceUse( string $resource, int $count = 1 ): void {
		$n = $this->wt2htmlUsage[$resource] ?? 0;
		$n += $count;
		$this->wt2htmlUsage[$resource] = $n;
		$wt2htmlLimits = $this->siteConfig->getWt2HtmlLimits();
		if (
			isset( $wt2htmlLimits[$resource] ) &&
			$n > $wt2htmlLimits[$resource]
		) {
			// TODO: re-evaluate whether throwing an exception is really
			// the right failure strategy when Parsoid is integrated into MW
			// (T221238)
			throw new ResourceLimitExceededException( "wt2html: $resource limit exceeded: $n" );
		}
	}

	/**
	 * Bump usage of some limited serializer resource
	 * (ex: html size)
	 *
	 * @param string $resource
	 * @param int $count How much of the resource is used? (defaults to 1)
	 * @throws ResourceLimitExceededException
	 */
	public function bumpHtml2WtResourceUse( string $resource, int $count = 1 ): void {
		$n = $this->html2wtUsage[$resource] ?? 0;
		$n += $count;
		$this->html2wtUsage[$resource] = $n;
		$html2wtLimits = $this->siteConfig->getHtml2WtLimits();
		if (
			isset( $html2wtLimits[$resource] ) &&
			$n > $html2wtLimits[$resource]
		) {
			throw new ResourceLimitExceededException( "html2wt: $resource limit exceeded: $n" );
		}
	}

	/**
	 * Get an appropriate content handler, given a contentmodel.
	 *
	 * @param string|null &$contentmodel An optional content model which
	 *   will override whatever the source specifies.  It gets set to the
	 *   handler which is used.
	 * @return ContentModelHandler An appropriate content handler
	 */
	public function getContentHandler(
		?string &$contentmodel = null
	): ContentModelHandler {
		$contentmodel = $contentmodel ?? $this->pageConfig->getContentModel();
		$handler = $this->siteConfig->getContentModelHandler( $contentmodel );
		if ( !$handler ) {
			$this->log( 'warn', "Unknown contentmodel $contentmodel" );
			$contentmodel = 'wikitext';
			$handler = $this->siteConfig->getContentModelHandler( $contentmodel );
		}
		return $handler;
	}

	/**
	 * Is the language converter enabled on this page?
	 *
	 * @return bool
	 */
	public function langConverterEnabled(): bool {
		return $this->siteConfig->langConverterEnabledForLanguage(
			$this->pageConfig->getPageLanguage()
		);
	}

	/**
	 * Indicates emit "clean" wikitext compared to what we would if we didn't normalize HTML
	 * @return bool
	 */
	public function shouldScrubWikitext(): bool {
		return $this->scrubWikitext;
	}

	/**
	 * The HTML content version of the input document (for html2wt and html2html conversions).
	 * @see https://www.mediawiki.org/wiki/Parsoid/API#Content_Negotiation
	 * @see https://www.mediawiki.org/wiki/Specs/HTML/2.1.0#Versioning
	 * @return string A semver version number
	 */
	public function getInputContentVersion(): string {
		return $this->inputContentVersion;
	}

	/**
	 * The HTML content version of the input document (for html2wt and html2html conversions).
	 * @see https://www.mediawiki.org/wiki/Parsoid/API#Content_Negotiation
	 * @see https://www.mediawiki.org/wiki/Specs/HTML/2.1.0#Versioning
	 * @return string A semver version number
	 */
	public function getOutputContentVersion(): string {
		return $this->outputContentVersion;
	}

	/**
	 * If non-null, the language variant used for Parsoid HTML; we convert
	 * to this if wt2html, or from this (if html2wt).
	 *
	 * @return string|null
	 */
	public function getHtmlVariantLanguage(): ?string {
		return $this->htmlVariantLanguage;
	}

	/**
	 * If non-null, the language variant to be used for wikitext.  If null,
	 * heuristics will be used to identify the original wikitext variant
	 * in wt2html mode, and in html2wt mode new or edited HTML will be left
	 * unconverted.
	 *
	 * @return string|null
	 */
	public function getWtVariantLanguage(): ?string {
		return $this->wtVariantLanguage;
	}

	/**
	 * Update K=[V1,V2,...] that might need to be output as part of the
	 * generated HTML.  Ex: module styles, modules scripts, ...
	 *
	 * @param string $key
	 * @param array $value
	 */
	public function addOutputProperty( string $key, array $value ): void {
		if ( !isset( $this->outputProps[$key] ) ) {
			$this->outputProps[$key] = [];
		}
		$this->outputProps[$key] = array_merge( $this->outputProps[$key], $value );
	}

	/**
	 * @return array
	 */
	public function getOutputProperties(): array {
		return $this->outputProps;
	}

	/**
	 * Determine appropriate vary headers for the HTML form of this page.
	 * @return string
	 */
	public function htmlVary(): string {
		$varies = [ 'Accept' ]; // varies on Content-Type
		if ( $this->langConverterEnabled() ) {
			$varies[] = 'Accept-Language';
		}

		sort( $varies );
		return implode( ', ', $varies );
	}

	/**
	 * Determine an appropriate content-language for the HTML form of this page.
	 * @return string
	 */
	public function htmlContentLanguage(): string {
		// PageConfig::htmlVariant is set iff we do variant conversion on the
		// HTML
		return $this->pageConfig->getVariant() ??
			$this->pageConfig->getPageLanguage();
	}
}
