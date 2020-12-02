<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Utils;

use Wikimedia\IPUtils;
use Wikimedia\Parsoid\Config\SiteConfig;

class Title {

	/** @var int */
	private $namespaceId;

	/** @var string */
	private $namespaceName;

	/** @var string */
	private $dbkey;

	/** @var string|null */
	private $fragment;

	/** @var TitleNamespace */
	private $namespace;

	/**
	 * @param string $key Page DBkey (with underscores, not spaces)
	 * @param int|TitleNamespace $ns
	 * @param SiteConfig $siteConfig
	 * @param string|null $fragment
	 */
	public function __construct( string $key, $ns, SiteConfig $siteConfig, ?string $fragment = null ) {
		$this->dbkey = $key;
		if ( $ns instanceof TitleNamespace ) {
			$this->namespaceId = $ns->getId();
			$this->namespace = $ns;
		} else {
			$this->namespaceId = (int)$ns;
			$this->namespace = new TitleNamespace( $this->namespaceId, $siteConfig );
		}
		$this->namespaceName = $siteConfig->namespaceName( $this->namespaceId );
		$this->fragment = $fragment;
	}

	/**
	 * @param string $title
	 * @param SiteConfig $siteConfig
	 * @param int|TitleNamespace $defaultNs
	 * @return Title
	 */
	public static function newFromText(
		string $title, SiteConfig $siteConfig, $defaultNs = 0
	): Title {
		if ( $defaultNs === null ) {
			$defaultNs = 0;
		}

		$origTitle = $title;

		if ( !mb_check_encoding( $title, 'UTF-8' ) ) {
			throw new TitleException( "Bad UTF-8 in title \"$title\"", 'title-invalid-utf8', $title );
		}

		// Strip Unicode bidi override characters.
		$title = preg_replace( '/[\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', $title );
		// Clean up whitespace
		$title = preg_replace(
			'/[ _\x{00A0}\x{1680}\x{180E}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+/u',
			'_', $title
		);
		// Trim _ from beginning and end
		$title = trim( $title, '_' );

		if ( strpos( $title, \UtfNormal\Constants::UTF8_REPLACEMENT ) !== false ) {
			throw new TitleException( "Bad UTF-8 in title \"$title\"", 'title-invalid-utf8', $title );
		}

		// Initial colon indicates main namespace rather than specified default
		// but should not create invalid {ns,title} pairs such as {0,Project:Foo}
		if ( $title !== '' && $title[0] === ':' ) {
			$title = ltrim( substr( $title, 1 ), '_' );
			$defaultNs = 0;
		}

		if ( $title === '' ) {
			throw new TitleException( 'Empty title', 'title-invalid-empty', $title );
		}

		if ( $defaultNs instanceof TitleNamespace ) {
			$defaultNs = $defaultNs->getId();
		}

		// phpcs:ignore MediaWiki.ControlStructures.AssignmentInControlStructures.AssignmentInControlStructures
		if ( preg_match( '/^(.+?)_*:_*(.*)$/D', $title, $m ) && (
			( $nsId = $siteConfig->canonicalNamespaceId( $m[1] ) ) !== null ||
			( $nsId = $siteConfig->namespaceId( $m[1] ) ) !== null
		) ) {
			$ns = $nsId;
			$title = $m[2];
		} else {
			$ns = $defaultNs;
		}

		// Disallow Talk:File:x type titles.
		if ( $ns === $siteConfig->canonicalNamespaceId( 'talk' ) &&
			preg_match( '/^(.+?)_*:_*(.*)$/D', $title, $m ) &&
			$siteConfig->namespaceId( $m[1] ) !== null
		) {
			throw new TitleException(
				"Invalid Talk namespace title \"$origTitle\"", 'title-invalid-talk-namespace', $title
			);
		}

		$fragment = null;
		$fragmentIndex = strpos( $title, '#' );
		if ( $fragmentIndex !== false ) {
			$fragment = substr( $title, $fragmentIndex + 1 );
			$title = rtrim( substr( $title, 0, $fragmentIndex ), '_' );
		}

		$illegalCharsRe = '/[^' . $siteConfig->legalTitleChars() . ']'
			// URL percent encoding sequences interfere with the ability
			// to round-trip titles -- you can't link to them consistently.
			. '|%[0-9A-Fa-f]{2}'
			// XML/HTML character references produce similar issues.
			. '|&[A-Za-z0-9\x80-\xff]+;'
			. '|&#[0-9]+;'
			. '|&#x[0-9A-Fa-f]+;/';
		if ( preg_match( $illegalCharsRe, $title ) ) {
			throw new TitleException(
				"Invalid characters in title \"$origTitle\"", 'title-invalid-characters', $title
			);
		}

		// Pages with "/./" or "/../" appearing in the URLs will often be
		// unreachable due to the way web browsers deal with 'relative' URLs.
		// Also, they conflict with subpage syntax. Forbid them explicitly.
		if ( strpos( $title, '.' ) !== false && (
			$title === '.' || $title === '..' ||
			strpos( $title, './' ) === 0 ||
			strpos( $title, '../' ) === 0 ||
			strpos( $title, '/./' ) !== false ||
			strpos( $title, '/../' ) !== false ||
			substr( $title, -2 ) === '/.' ||
			substr( $title, -3 ) === '/..'
		) ) {
			throw new TitleException(
				"Title \"$origTitle\" contains relative path components", 'title-invalid-relative', $title
			);
		}

		// Magic tilde sequences? Nu-uh!
		if ( strpos( $title, '~~~' ) !== false ) {
			throw new TitleException(
				"Title \"$origTitle\" contains ~~~", 'title-invalid-magic-tilde', $title
			);
		}

		$maxLength = $ns === $siteConfig->canonicalNamespaceId( 'special' ) ? 512 : 255;
		if ( strlen( $title ) > $maxLength ) {
			throw new TitleException(
				"Title \"$origTitle\" is too long", 'title-invalid-too-long', $title
			);
		}

		if ( $siteConfig->namespaceCase( $ns ) === 'first-letter' ) {
			$title = $siteConfig->ucfirst( $title );
		}

		// Allow "#foo" as a title, which comes in as namespace 0.
		// TODO: But should this exclude "_#foo" and the like?
		if ( $title === '' && $ns !== $siteConfig->canonicalNamespaceId( '' ) ) {
			throw new TitleException( 'Empty title', 'title-invalid-empty', $title );
		}

		if ( $ns === $siteConfig->canonicalNamespaceId( 'user' ) ||
			$ns === $siteConfig->canonicalNamespaceId( 'user_talk' )
		) {
			$title = IPUtils::sanitizeIP( $title );
		}

		// This is not in core's splitTitleString but matches
		// mediawiki-title's newFromText.
		if ( $ns === $siteConfig->canonicalNamespaceId( 'special' ) ) {
			$title = self::fixSpecialName( $siteConfig, $title );
		}

		return new self( $title, $ns, $siteConfig, $fragment );
	}

	/**
	 * Get the DBkey
	 * @return string
	 */
	public function getKey(): string {
		return $this->dbkey;
	}

	/**
	 * Get the prefixed DBkey
	 * @return string
	 */
	public function getPrefixedDBKey(): string {
		if ( $this->namespaceName === '' ) {
			return $this->dbkey;
		}
		return strtr( $this->namespaceName, ' ', '_' ) . ':' . $this->dbkey;
	}

	/**
	 * Get the prefixed text
	 * @return string
	 */
	public function getPrefixedText(): string {
		$ret = strtr( $this->dbkey, '_', ' ' );
		if ( $this->namespaceName !== '' ) {
			$ret = $this->namespaceName . ':' . $ret;
		}
		return $ret;
	}

	/**
	 * Get the fragment, if any
	 * @return string|null
	 */
	public function getFragment(): ?string {
		return $this->fragment;
	}

	/**
	 * @deprecated Use namespace IDs and SiteConfig methods instead.
	 * @return TitleNamespace
	 */
	public function getNamespace(): TitleNamespace {
		return $this->namespace;
	}

	/**
	 * Get the namespace ID
	 * @return int
	 */
	public function getNamespaceId(): int {
		return $this->namespaceId;
	}

	/**
	 * Compare with another title.
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function equals( Title $title ) {
		return $this->getNamespaceId() === $title->getNamespaceId() &&
			$this->getKey() === $title->getKey();
	}

	/**
	 * Use the default special page alias.
	 *
	 * @param SiteConfig $siteConfig
	 * @param string $title
	 * @return string
	 */
	public static function fixSpecialName(
		SiteConfig $siteConfig, string $title
	): string {
		$parts = explode( '/', $title, 2 );
		$specialName = $siteConfig->specialPageLocalName( $parts[0] );
		if ( $specialName !== null ) {
			$parts[0] = $specialName;
			$title = implode( '/', $parts );
		}
		return $title;
	}
}
