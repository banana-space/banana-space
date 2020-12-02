<?php
/**
 * @file
 * @author Niklas Laxström
 * @license GPL-2.0-or-later
 */

namespace LocalisationUpdate;

/**
 * Interface for classes which provide list of components, which should be
 * included for l10n updates.
 */
class Finder {
	/**
	 * @var array
	 */
	private $json;

	/**
	 * @var string
	 */
	private $core;

	/**
	 * @param array $json See $wgMessagesDirs
	 * @param string $core Absolute path to MediaWiki core
	 */
	public function __construct( $json, $core ) {
		$this->json = $json;
		$this->core = $core;
	}

	/**
	 * @return array[]
	 */
	public function getComponents() {
		$components = [];

		foreach ( $this->json as $key => $value ) {
			foreach ( (array)$value as $subkey => $subvalue ) {
				// Mediawiki core files
				$matches = [];
				if ( preg_match( '~/(?P<path>(?:includes|languages|resources)/.*)$~', $subvalue, $matches ) ) {
					$components["$key-$subkey"] = [
						'repo' => 'mediawiki',
						'orig' => "file://$value/*.json",
						'path' => "{$matches['path']}/*.json",
					];
					continue;
				}

				$item = $this->getItem( 'extensions', $subvalue );
				if ( $item !== null ) {
					$item['repo'] = 'extension';
					$components["$key-$subkey"] = $item;
					continue;
				}

				$item = $this->getItem( 'skins', $subvalue );
				if ( $item !== null ) {
					$item['repo'] = 'skin';
					$components["$key-$subkey"] = $item;
					continue;
				}
			}
		}

		return $components;
	}

	/**
	 * @param string $dir extensions or skins
	 * @param string $subvalue
	 * @return array|null
	 */
	private function getItem( $dir, $subvalue ) {
		// This ignores magic, alias etc. non message files
		$matches = [];
		if ( !preg_match( "~/$dir/(?P<name>[^/]+)/(?P<path>.*)$~", $subvalue, $matches ) ) {
			return null;
		}

		return [
			'name' => $matches['name'],
			'orig' => "file://$subvalue/*.json",
			'path' => "{$matches['path']}/*.json",
		];
	}
}
