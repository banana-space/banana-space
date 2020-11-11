<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;

/**
 * Gadgets repo powered by MediaWiki:Gadgets-definition
 */
class MediaWikiGadgetsDefinitionRepo extends GadgetRepo {
	const CACHE_VERSION = 2;

	private $definitionCache;

	/**
	 * @param string $id
	 *
	 * @return Gadget
	 * @throws InvalidArgumentException
	 */
	public function getGadget( $id ) {
		$gadgets = $this->loadGadgets();
		if ( !isset( $gadgets[$id] ) ) {
			throw new InvalidArgumentException( "No gadget registered for '$id'" );
		}

		return $gadgets[$id];
	}

	public function getGadgetIds() {
		$gadgets = $this->loadGadgets();
		if ( $gadgets ) {
			return array_keys( $gadgets );
		} else {
			return [];
		}
	}

	public function handlePageUpdate( LinkTarget $target ) {
		if ( $target->getNamespace() == NS_MEDIAWIKI && $target->getText() == 'Gadgets-definition' ) {
			$this->purgeDefinitionCache();
		}
	}

	/**
	 * Purge the definitions cache, for example if MediaWiki:Gadgets-definition
	 * was edited.
	 */
	private function purgeDefinitionCache() {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$cache->touchCheckKey( $this->getCheckKey() );
	}

	private function getCheckKey() {
		return wfMemcKey( 'gadgets-definition', Gadget::GADGET_CLASS_VERSION, self::CACHE_VERSION );
	}

	/**
	 * Loads list of gadgets and returns it as associative array of sections with gadgets
	 * e.g. [ 'sectionnname1' => [ $gadget1, $gadget2 ],
	 *             'sectionnname2' => [ $gadget3 ] ];
	 * @return array|bool Gadget array or false on failure
	 */
	protected function loadGadgets() {
		if ( $this->definitionCache !== null ) {
			return $this->definitionCache; // process cache hit
		}

		// Ideally $t1Cache is APC, and $wanCache is memcached
		$t1Cache = ObjectCache::getLocalServerInstance( 'hash' );
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		$key = $this->getCheckKey();

		// (a) Check the tier 1 cache
		$value = $t1Cache->get( $key );
		// Check if it passes a blind TTL check (avoids I/O)
		if ( $value && ( microtime( true ) - $value['time'] ) < 10 ) {
			$this->definitionCache = $value['gadgets']; // process cache
			return $this->definitionCache;
		}
		// Cache generated after the "check" time should be up-to-date
		$ckTime = $wanCache->getCheckKeyTime( $key ) + WANObjectCache::HOLDOFF_TTL;
		if ( $value && $value['time'] > $ckTime ) {
			$this->definitionCache = $value['gadgets']; // process cache
			return $this->definitionCache;
		}

		// (b) Fetch value from WAN cache or regenerate if needed.
		// This is hit occasionally and more so when the list changes.
		$us = $this;
		$value = $wanCache->getWithSetCallback(
			$key,
			Gadget::CACHE_TTL,
			function ( $old, &$ttl, &$setOpts ) use ( $us ) {
				$setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );

				$now = microtime( true );
				$gadgets = $us->fetchStructuredList();
				if ( $gadgets === false ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
				}

				return [ 'gadgets' => $gadgets, 'time' => $now ];
			},
			[ 'checkKeys' => [ $key ], 'lockTSE' => 300 ]
		);

		// Update the tier 1 cache as needed
		if ( $value['gadgets'] !== false && $value['time'] > $ckTime ) {
			// Set a modest TTL to keep the WAN key in cache
			$t1Cache->set( $key, $value, mt_rand( 300, 600 ) );
		}

		$this->definitionCache = $value['gadgets'];

		return $this->definitionCache;
	}

	/**
	 * Fetch list of gadgets and returns it as associative array of sections with gadgets
	 * e.g. [ $name => $gadget1, etc. ]
	 * @param string $forceNewText Injected text of MediaWiki:gadgets-definition [optional]
	 * @return array|bool
	 */
	public function fetchStructuredList( $forceNewText = null ) {
		if ( $forceNewText === null ) {
			// T157210: avoid using wfMessage() to avoid staleness due to cache layering
			$title = Title::makeTitle( NS_MEDIAWIKI, 'Gadgets-definition' );
			$rev = Revision::newFromTitle( $title );
			if ( !$rev || !$rev->getContent() || $rev->getContent()->isEmpty() ) {
				return false; // don't cache
			}

			$g = $rev->getContent()->getNativeData();
		} else {
			$g = $forceNewText;
		}

		$gadgets = $this->listFromDefinition( $g );
		if ( !count( $gadgets ) ) {
			return false; // don't cache; Bug 37228
		}

		$source = $forceNewText !== null ? 'input text' : 'MediaWiki:Gadgets-definition';
		wfDebug( __METHOD__ . ": $source parsed, cache entry should be updated\n" );

		return $gadgets;
	}

	/**
	 * Generates a structured list of Gadget objects from a definition
	 *
	 * @param string $definition
	 * @return Gadget[] List of Gadget objects indexed by the gadget's name.
	 */
	private function listFromDefinition( $definition ) {
		$definition = preg_replace( '/<!--.*?-->/s', '', $definition );
		$lines = preg_split( '/(\r\n|\r|\n)+/', $definition );

		$gadgets = [];
		$section = '';

		foreach ( $lines as $line ) {
			$m = [];
			if ( preg_match( '/^==+ *([^*:\s|]+?)\s*==+\s*$/', $line, $m ) ) {
				$section = $m[1];
			} else {
				$gadget = $this->newFromDefinition( $line, $section );
				if ( $gadget ) {
					$gadgets[$gadget->getName()] = $gadget;
				}
			}
		}

		return $gadgets;
	}

	/**
	 * Creates an instance of this class from definition in MediaWiki:Gadgets-definition
	 * @param string $definition Gadget definition
	 * @param string $category
	 * @return Gadget|bool Instance of Gadget class or false if $definition is invalid
	 */
	public function newFromDefinition( $definition, $category ) {
		$m = [];
		if ( !preg_match(
			'/^\*+ *([a-zA-Z](?:[-_:.\w\d ]*[a-zA-Z0-9])?)(\s*\[.*?\])?\s*((\|[^|]*)+)\s*$/',
			$definition,
			$m
		) ) {
			return false;
		}
		// NOTE: the gadget name is used as part of the name of a form field,
		// and must follow the rules defined in https://www.w3.org/TR/html4/types.html#type-cdata
		// Also, title-normalization applies.
		$info = [ 'category' => $category ];
		$info['name'] = trim( str_replace( ' ', '_', $m[1] ) );
		// If the name is too long, then RL will throw an MWException when
		// we try to register the module
		if ( !Gadget::isValidGadgetID( $info['name'] ) ) {
			return false;
		}
		$info['definition'] = $definition;
		$options = trim( $m[2], ' []' );

		foreach ( preg_split( '/\s*\|\s*/', $options, -1, PREG_SPLIT_NO_EMPTY ) as $option ) {
			$arr = preg_split( '/\s*=\s*/', $option, 2 );
			$option = $arr[0];
			if ( isset( $arr[1] ) ) {
				$params = explode( ',', $arr[1] );
				$params = array_map( 'trim', $params );
			} else {
				$params = [];
			}

			switch ( $option ) {
				case 'ResourceLoader':
					$info['resourceLoaded'] = true;
					break;
				case 'dependencies':
					$info['dependencies'] = $params;
					break;
				case 'peers':
					$info['peers'] = $params;
					break;
				case 'rights':
					$info['requiredRights'] = $params;
					break;
				case 'hidden':
					$info['hidden'] = true;
					break;
				case 'skins':
					$info['requiredSkins'] = $params;
					break;
				case 'default':
					$info['onByDefault'] = true;
					break;
				case 'targets':
					$info['targets'] = $params;
					break;
				case 'type':
					// Single value, not a list
					$info['type'] = isset( $params[0] ) ? $params[0] : '';
					break;
			}
		}

		foreach ( preg_split( '/\s*\|\s*/', $m[3], -1, PREG_SPLIT_NO_EMPTY ) as $page ) {
			$page = "MediaWiki:Gadget-$page";

			if ( preg_match( '/\.js/', $page ) ) {
				$info['scripts'][] = $page;
			} elseif ( preg_match( '/\.css/', $page ) ) {
				$info['styles'][] = $page;
			}
		}

		return new Gadget( $info );
	}
}
