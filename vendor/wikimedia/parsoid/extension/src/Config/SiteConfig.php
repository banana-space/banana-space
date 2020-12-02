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

namespace MWParsoid\Config;

// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic

use Config;
use ExtensionRegistry;
use FakeConverter;
use Language;
use LanguageConverter;
use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MagicWordArray;
use MagicWordFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MutableConfig;
use Psr\Log\LoggerInterface;
use Title;
use User;
use Wikimedia\Parsoid\Config\SiteConfig as ISiteConfig;

/**
 * Site-level configuration for Parsoid
 *
 * This includes both global configuration and wiki-level configuration.
 *
 * @todo This belongs in MediaWiki, not Parsoid. We'll move it there when we
 *  get to the point of integrating the two.
 */
class SiteConfig extends ISiteConfig {

	/**
	 * Regular expression fragment for matching wikitext comments.
	 * Meant for inclusion in other regular expressions.
	 */
	protected const COMMENT_REGEXP_FRAGMENT = '<!--(?>[\s\S]*?-->)';

	/** @var Config MediaWiki configuration object */
	private $config;

	/** @var array Parsoid-specific options array from $config */
	private $parsoidSettings;

	/** @var Language */
	private $contLang;

	/** @var LoggerInterface|null */
	private $traceLogger, $dumpLogger;

	/** @var string|null */
	private $baseUri, $relativeLinkPrefix;

	/** @var array|null */
	private $interwikiMap, $variants;

	/** @var array */
	private $extensionTags;

	public function __construct() {
		parent::__construct();

		$services = MediaWikiServices::getInstance();
		$this->config = $services->getMainConfig();
		$this->parsoidSettings = $services->getMainConfig()->get( 'ParsoidSettings' );
		$this->contLang = $services->getContentLanguage();
		// Override parent default
		if ( isset( $this->parsoidSettings['rtTestMode'] ) ) {
			// @todo: Add this setting to MW's DefaultSettings.php
			$this->rtTestMode = $this->parsoidSettings['rtTestMode'];
		}
		// Override parent default
		if ( isset( $this->parsoidSettings['linting'] ) ) {
			// @todo: Add this setting to MW's DefaultSettings.php
			$this->linterEnabled = $this->parsoidSettings['linting'];
		}

		if ( isset( $this->parsoidSettings['wt2htmlLimits'] ) ) {
			$this->wt2htmlLimits = array_merge(
				$this->wt2htmlLimits, $this->parsoidSettings['wt2htmlLimits']
			);
		}
		if ( isset( $this->parsoidSettings['html2wtLimits'] ) ) {
			$this->html2wtLimits = array_merge(
				$this->html2wtLimits, $this->parsoidSettings['html2wtLimits']
			);
		}
		// Register extension modules
		$parsoidModules = ExtensionRegistry::getInstance()->getAttribute( 'ParsoidModules' );
		foreach ( $parsoidModules as $configOrSpec ) {
			$this->registerExtensionModule( $configOrSpec );
		}
	}

	/** @inheritDoc */
	public function getLogger(): LoggerInterface {
		if ( $this->logger === null ) {
			$this->logger = LoggerFactory::getInstance( 'Parsoid' );
		}
		return $this->logger;
	}

	public function metrics(): ?StatsdDataFactoryInterface {
		static $prefixedMetrics = null;
		if ( $prefixedMetrics === null ) {
			$prefixedMetrics = new \PrefixingStatsdDataFactoryProxy(
				// Our stats will also get prefixed with 'MediaWiki.'
				MediaWikiServices::getInstance()->getStatsdDataFactory(),
				$this->parsoidSettings['metricsPrefix'] ?? 'Parsoid.'
			);
		}
		return $prefixedMetrics;
	}

	public function nativeGalleryEnabled(): bool {
		return false;
	}

	public function galleryOptions(): array {
		return $this->config->get( 'GalleryOptions' );
	}

	public function allowedExternalImagePrefixes(): array {
		if ( $this->config->get( 'AllowExternalImages' ) ) {
			return [ '' ];
		} else {
			$allowFrom = $this->config->get( 'AllowExternalImagesFrom' );
			return $allowFrom ? (array)$allowFrom : [];
		}
	}

	/**
	 * Determine the article base URI and relative prefix
	 *
	 * Populates `$this->baseUri` and `$this->relativeLinkPrefix` based on
	 * `$wgServer` and `$wgArticlePath`, by splitting it at the last '/' in the
	 * path portion.
	 */
	private function determineArticlePath(): void {
		$url = $this->config->get( 'Server' ) . $this->config->get( 'ArticlePath' );

		if ( substr( $url, -2 ) !== '$1' ) {
			throw new \UnexpectedValueException( "Article path '$url' does not have '$1' at the end" );
		}
		$url = substr( $url, 0, -2 );

		$bits = wfParseUrl( $url );
		if ( !$bits ) {
			throw new \UnexpectedValueException( "Failed to parse article path '$url'" );
		}

		if ( empty( $bits['path'] ) ) {
			$path = '/';
		} else {
			$path = wfRemoveDotSegments( $bits['path'] );
		}

		$relParts = [ 'query' => true, 'fragment' => true ];
		$base = array_diff_key( $bits, $relParts );
		$rel = array_intersect_key( $bits, $relParts );

		$i = strrpos( $path, '/' );
		$base['path'] = substr( $path, 0, $i + 1 );
		$rel['path'] = '.' . substr( $path, $i );

		$this->baseUri = wfAssembleUrl( $base );
		$this->relativeLinkPrefix = wfAssembleUrl( $rel );
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

	/**
	 * This is very similar to MagicWordArray::getBaseRegex() except we
	 * don't emit the named grouping constructs, which can cause havoc
	 * when embedded in other regexps with grouping constructs.
	 *
	 * @param MagicWordFactory $factory
	 * @param MagicWordArray $magicWordArray
	 * @param string $delimiter
	 * @return string
	 */
	private static function mwaToRegex(
		MagicWordFactory $factory,
		MagicWordArray $magicWordArray,
		string $delimiter = '/'
	): string {
		$regex = [ 0 => [], 1 => [] ];
		foreach ( $magicWordArray->getNames() as $name ) {
			$magic = $factory->get( $name );
			$case = $magic->isCaseSensitive() ? 1 : 0;
			foreach ( $magic->getSynonyms() as $syn ) {
				$regex[$case][] = preg_quote( $syn, $delimiter );
			}
		}
		'@phan-var array<int,string[]> $regex'; /** @var array<int,string[]> $regex */
		$result = [];
		if ( count( $regex[1] ) > 0 ) {
			$result[] = implode( '|', $regex[1] );
		}
		if ( count( $regex[0] ) > 0 ) {
			$result[] = '(?i:' . implode( '|', $regex[0] ) . ')';
		}
		return count( $result ) ? implode( '|', $result ) : '(?!)';
	}

	public function redirectRegexp(): string {
		$mwFactory = MediaWikiServices::getInstance()->getMagicWordFactory();
		$redirect = self::mwaToRegex(
			$mwFactory, $mwFactory->newArray( [ 'redirect' ] ), '@'
		);
		return "@$redirect@";
	}

	public function categoryRegexp(): string {
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		$canon = $namespaceInfo->getCanonicalName( NS_CATEGORY );
		$result = [ $canon ];
		foreach ( $this->contLang->getNamespaceAliases() as $alias => $ns ) {
			if ( $ns === NS_CATEGORY && $alias !== $canon ) {
				$result[] = $alias;
			}
		}
		$category = implode( '|', array_map( function ( $v ) {
			return $this->quoteTitleRe( $v, '@' );
		}, $result ) );
		return "@(?i:$category)@";
	}

	public function bswRegexp(): string {
		$mwFactory = MediaWikiServices::getInstance()->getMagicWordFactory();
		$words = $mwFactory->getDoubleUnderscoreArray();
		$bsw = self::mwaToRegex(
			$mwFactory, $mwFactory->getDoubleUnderscoreArray(), '@'
		);
		return "@$bsw@";
	}

	/** @inheritDoc */
	public function canonicalNamespaceId( string $name ): ?int {
		$ret = MediaWikiServices::getInstance()->getNamespaceInfo()->getCanonicalIndex( $name );
		return $ret === false ? null : $ret;
	}

	/** @inheritDoc */
	public function namespaceId( string $name ): ?int {
		$ret = $this->contLang->getNsIndex( $name );
		return $ret === false ? null : $ret;
	}

	/** @inheritDoc */
	public function namespaceName( int $ns ): ?string {
		$ret = $this->contLang->getFormattedNsText( $ns );
		return $ret === '' && $ns !== NS_MAIN ? null : $ret;
	}

	/** @inheritDoc */
	public function namespaceHasSubpages( int $ns ): bool {
		return MediaWikiServices::getInstance()->getNamespaceInfo()->hasSubpages( $ns );
	}

	/** @inheritDoc */
	public function namespaceCase( int $ns ): string {
		$nsInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		return $nsInfo->isCapitalized( $ns ) ? 'first-letter' : 'case-sensitive';
	}

	/** @inheritDoc */
	public function namespaceIsTalk( int $ns ): bool {
		return MediaWikiServices::getInstance()->getNamespaceInfo()->isTalk( $ns );
	}

	/** @inheritDoc */
	public function ucfirst( string $str ): string {
		return $this->contLang->ucfirst( $str );
	}

	/** @inheritDoc */
	public function specialPageLocalName( string $alias ): ?string {
		$specialPageFactory = MediaWikiServices::getInstance()->getSpecialPageFactory();
		$aliases = $specialPageFactory->resolveAlias( $alias );
		return $aliases[0] !== null ? $specialPageFactory->getLocalNameFor( ...$aliases ) : $alias;
	}

	public function interwikiMagic(): bool {
		return $this->config->get( 'InterwikiMagic' );
	}

	public function interwikiMap(): array {
		// Unfortunate that this mostly duplicates \ApiQuerySiteinfo::appendInterwikiMap()
		if ( $this->interwikiMap === null ) {
			$this->interwikiMap = [];

			$getPrefixes = MediaWikiServices::getInstance()->getInterwikiLookup()->getAllPrefixes();
			$langNames = Language::fetchLanguageNames();
			$extraLangPrefixes = $this->config->get( 'ExtraInterlanguageLinkPrefixes' );
			$localInterwikis = $this->config->get( 'LocalInterwikis' );

			foreach ( $getPrefixes as $row ) {
				$prefix = $row['iw_prefix'];
				$val = [];
				$val['prefix'] = $prefix;
				$val['url'] = wfExpandUrl( $row['iw_url'], PROTO_CURRENT );

				// Fix up broken interwiki hrefs that are missing a $1 placeholder
				// Just append the placeholder at the end.
				// This makes sure that the interwikiMatcher adds one match
				// group per URI, and that interwiki links work as expected.
				if ( strpos( $val['url'], '$1' ) === false ) {
					$val['url'] .= '$1';
				}

				if ( substr( $row['iw_url'], 0, 2 ) == '//' ) {
					$val['protorel'] = true;
				}
				if ( isset( $row['iw_local'] ) && $row['iw_local'] == '1' ) {
					$val['local'] = true;
				}
				if ( isset( $langNames[$prefix] ) ) {
					$val['language'] = true;
				}
				if ( in_array( $prefix, $localInterwikis, true ) ) {
					$val['localinterwiki'] = true;
				}
				if ( in_array( $prefix, $extraLangPrefixes, true ) ) {
					$val['extralanglink'] = true;

					$linktext = wfMessage( "interlanguage-link-$prefix" );
					if ( !$linktext->isDisabled() ) {
						$val['linktext'] = $linktext->text();
					}
				}

				$this->interwikiMap[$prefix] = $val;
			}
		}
		return $this->interwikiMap;
	}

	public function iwp(): string {
		return wfWikiID();
	}

	public function legalTitleChars() : string {
		return Title::legalChars();
	}

	public function linkPrefixRegex(): ?string {
		if ( !$this->contLang->linkPrefixExtension() ) {
			return null;
		}
		return '/[' . $this->contLang->linkPrefixCharset() . ']+$/Du';
	}

	/** @inheritDoc */
	protected function linkTrail(): string {
		return $this->contLang->linkTrail();
	}

	public function lang(): string {
		return $this->config->get( 'LanguageCode' );
	}

	public function mainpage(): string {
		return Title::newMainPage()->getPrefixedText();
	}

	public function responsiveReferences(): array {
		// @todo This is from the Cite extension, which shouldn't be known about by core
		return [
			'enabled' => $this->config->has( 'CiteResponsiveReferences' ) ?
				$this->config->get( 'CiteResponsiveReferences' ) : false,
			'threshold' => 10,
		];
	}

	public function rtl(): bool {
		return $this->contLang->isRTL();
	}

	/** @inheritDoc */
	public function langConverterEnabled( string $lang ): bool {
		try {
			return !$this->config->get( 'DisableLangConversion' ) &&
				in_array( $lang, LanguageConverter::$languagesWithVariants, true ) &&
				!Language::factory( $lang )->getConverter() instanceof FakeConverter;
		} catch ( \MWException $ex ) {
			// Probably a syntactically invalid language code
			return false;
		}
	}

	public function script(): string {
		return $this->config->get( 'Script' );
	}

	public function scriptpath(): string {
		return $this->config->get( 'ScriptPath' );
	}

	public function server(): string {
		return $this->config->get( 'Server' );
	}

	public function getModulesLoadURI(): string {
		return $this->config->get( 'LoadScript' );
	}

	public function timezoneOffset(): int {
		return $this->config->get( 'LocalTZoffset' );
	}

	public function variants(): array {
		if ( $this->variants === null ) {
			$this->variants = [];

			$langNames = LanguageConverter::$languagesWithVariants;
			if ( $this->config->get( 'DisableLangConversion' ) ) {
				// Ensure result is empty if language conversion is disabled.
				$langNames = [];
			}

			foreach ( $langNames as $langCode ) {
				$lang = Language::factory( $langCode );
				if ( $lang->getConverter() instanceof FakeConverter ) {
					// Only languages which do not return instances of
					// FakeConverter implement language conversion.
					continue;
				}

				$variants = $lang->getVariants();
				foreach ( $variants as $v ) {
					$fallbacks = $lang->getConverter()->getVariantFallbacks( $v );
					if ( !is_array( $fallbacks ) ) {
						$fallbacks = [ $fallbacks ];
					}
					$this->variants[$v] = [
						'base' => $langCode,
						'fallbacks' => $fallbacks,
					];
				}
			}
		}
		return $this->variants;
	}

	public function widthOption(): int {
		return $this->config->get( 'ThumbLimits' )[User::getDefaultOption( 'thumbsize' )];
	}

	/** @inheritDoc */
	protected function getVariableIDs(): array {
		return MediaWikiServices::getInstance()->getMagicWordFactory()->getVariableIDs();
	}

	/** @inheritDoc */
	protected function getFunctionHooks(): array {
		return MediaWikiServices::getInstance()->getParser()->getFunctionHooks();
	}

	/** @inheritDoc */
	protected function getMagicWords(): array {
		return MediaWikiServices::getInstance()->getContentLanguage()->getMagicWords();
	}

	public function getMagicWordMatcher( string $id ): string {
		return MediaWikiServices::getInstance()->getMagicWordFactory()
			->get( $id )->getRegexStartToEnd();
	}

	/** @inheritDoc */
	public function getParameterizedAliasMatcher( array $words ): callable {
		// PORT-FIXME: this should be combined with
		// getMediaPrefixParameterizedAliasMatcher; see PORT-FIXME comment
		// in that method.
		// Filter out timedmedia-* unless that extension is loaded, so Parsoid
		// doesn't have a hard dependency on an extension.
		if ( !\ExtensionRegistry::getInstance()->isLoaded( 'TimedMediaHandler' ) ) {
			$words = preg_grep( '/^timedmedia_/', $words, PREG_GREP_INVERT );
		}
		$words = MediaWikiServices::getInstance()->getMagicWordFactory()
			->newArray( $words );
		return function ( $text ) use ( $words ) {
			$ret = $words->matchVariableStartToEnd( $text );
			if ( $ret[0] === false || $ret[1] === false ) {
				return null;
			} else {
				return [ 'k' => $ret[0], 'v' => $ret[1] ];
			}
		};
	}

	private function populateExtensionTags(): void {
		$parser = MediaWikiServices::getInstance()->getParser();
		$this->extensionTags = array_fill_keys( $parser->getTags(), true );
	}

	/** @inheritDoc */
	protected function getNonNativeExtensionTags(): array {
		if ( $this->extensionTags === null ) {
			$this->populateExtensionTags();
		}
		return $this->extensionTags;
	}

	public function getMaxTemplateDepth(): int {
		return (int)$this->config->get( 'MaxTemplateDepth' );
	}

	/**
	 * Overrides the max template depth in the MediaWiki configuration.
	 * @param int $depth
	 */
	public function setMaxTemplateDepth( int $depth ): void {
		if ( $this->config instanceof MutableConfig ) {
			$this->config->set( 'MaxTemplateDepth', $depth );
		} else {
			// Fall back on global variable (hopefully we're using
			// a GlobalVarConfig and this will work)
			$GLOBALS['wgMaxTemplateDepth'] = $depth;
		}
	}

	/** @inheritDoc */
	protected function getSpecialNSAliases(): array {
		$nsAliases = [
			'Special',
			$this->quoteTitleRe( $this->contLang->getNsText( NS_SPECIAL ) )
		];
		foreach (
			array_merge( $this->config->get( 'NamespaceAliases' ), $this->contLang->getNamespaceAliases() )
			as $name => $ns
		) {
			if ( $ns === NS_SPECIAL ) {
				$nsAliases[] = $this->quoteTitleRe( $name );
			}
		}

		return $nsAliases;
	}

	/** @inheritDoc */
	protected function getSpecialPageAliases( string $specialPage ): array {
		return array_merge( [ $specialPage ],
			$this->contLang->getSpecialPageAliases()[$specialPage] ?? []
		);
	}

	/** @inheritDoc */
	protected function getProtocols(): array {
		return $this->config->get( 'UrlProtocols' );
	}
}
