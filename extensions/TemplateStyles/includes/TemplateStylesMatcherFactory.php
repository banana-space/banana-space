<?php
/**
 * @file
 * @license GPL-2.0-or-later
 */

use Wikimedia\CSS\Grammar\TokenMatcher;
use Wikimedia\CSS\Grammar\UrlMatcher;
use Wikimedia\CSS\Objects\Token;

/**
 * Extend the standard factory for TemplateStyles-specific matchers
 */
class TemplateStylesMatcherFactory extends \Wikimedia\CSS\Grammar\MatcherFactory {

	/** @var array URL validation regexes */
	protected $allowedDomains;

	/**
	 * @param array $allowedDomains See $wgTemplateStylesAllowedUrls
	 */
	public function __construct( array $allowedDomains ) {
		$this->allowedDomains = $allowedDomains;
	}

	/**
	 * Check a URL for safety
	 * @param string $type
	 * @param string $url
	 * @return bool
	 */
	protected function checkUrl( $type, $url ) {
		// Undo unnecessary percent encoding
		$url = preg_replace_callback( '/%[2-7][0-9A-Fa-f]/', function ( $m ) {
			$char = urldecode( $m[0] );
			/** @phan-suppress-next-line PhanParamSuspiciousOrder */
			if ( strpos( '"#%<>[\]^`{|}/?&=+;', $char ) === false ) {
				# Unescape it
				return $char;
			}
			return $m[0];
		}, $url );

		// Don't allow unescaped \ or /../ in the non-query part of the URL
		$tmp = preg_replace( '<[#?].*$>', '', $url );
		if ( strpos( $tmp, '\\' ) !== false || preg_match( '<(?:^|/|%2[fF])\.+(?:/|%2[fF]|$)>', $tmp ) ) {
			return false;
		}

		// Run it through the whitelist
		$regexes = $this->allowedDomains[$type] ?? [];
		foreach ( $regexes as $regex ) {
			if ( preg_match( $regex, $url ) ) {
				return true;
			}
		}

		return false;
	}

	public function urlstring( $type ) {
		$key = __METHOD__ . ':' . $type;
		if ( !isset( $this->cache[$key] ) ) {
			$this->cache[$key] = new TokenMatcher( Token::T_STRING, function ( Token $t ) use ( $type ) {
				return $this->checkUrl( $type, $t->value() );
			} );
		}
		return $this->cache[$key];
	}

	public function url( $type ) {
		$key = __METHOD__ . ':' . $type;
		if ( !isset( $this->cache[$key] ) ) {
			$this->cache[$key] = new UrlMatcher( function ( $url, $modifiers ) use ( $type ) {
				return !$modifiers && $this->checkUrl( $type, $url );
			} );
		}
		return $this->cache[$key];
	}
}
