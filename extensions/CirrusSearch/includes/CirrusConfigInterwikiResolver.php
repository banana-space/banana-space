<?php

namespace CirrusSearch;

/**
 * InterwikiResolver suited for custom cirrus config:
 * - wgCirrusSearchLanguageToWikiMap: a map of language code to wiki db prefixes
 * - wgCirrusSearchInterwikiSources: a map of cross project interwiki prefixes to wikiId
 * - wgCirrusSearchWikiToNameMap: a map of language interwiki prefixes to wikiId
 */

class CirrusConfigInterwikiResolver extends BaseInterwikiResolver {
	/**
	 * @param SearchConfig $config
	 * @return bool true if this resolver can run with the specified config
	 */
	public static function accepts( SearchConfig $config ) {
		if ( !empty( $config->get( 'CirrusSearchInterwikiSources' ) ) ) {
			return true;
		}
		if ( !empty( $config->get( 'CirrusSearchWikiToNameMap' ) ) ) {
			return true;
		}
		return false;
	}

	protected function loadMatrix() {
		$sisterProjects = $this->config->get( 'CirrusSearchInterwikiSources' );
		if ( $sisterProjects === null ) {
			$sisterProjects = [];
		}
		$languageMap = $this->config->get( 'CirrusSearchLanguageToWikiMap' );
		if ( $languageMap === null ) {
			$languageMap = [];
		}
		$crossLanguage = $this->config->get( 'CirrusSearchWikiToNameMap' );
		if ( $crossLanguage === null ) {
			$crossLanguage = [];
		}
		$crossLanguage = array_filter( $crossLanguage, function ( $entry ) {
			return $entry !== $this->config->getWikiId();
		} );
		$prefixesByWiki = array_flip( $sisterProjects ) + array_flip( $crossLanguage );
		return [
			'sister_projects' => $sisterProjects,
			'language_map' => $languageMap,
			'cross_language' => $crossLanguage,
			'prefixes_by_wiki' => $prefixesByWiki,
		];
	}
}
