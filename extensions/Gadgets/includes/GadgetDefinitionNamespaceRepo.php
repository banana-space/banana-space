<?php

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use Wikimedia\Rdbms\Database;

/**
 * GadgetRepo implementation where each gadget has a page in
 * the Gadget definition namespace, and scripts and styles are
 * located in the Gadget namespace.
 */
class GadgetDefinitionNamespaceRepo extends GadgetRepo {
	/**
	 * How long in seconds the list of gadget ids and
	 * individual gadgets should be cached for (1 day)
	 */
	private const CACHE_TTL = 86400;

	/**
	 * @var WANObjectCache
	 */
	private $wanCache;

	/**
	 * @var RevisionLookup
	 */
	private $revLookup;

	public function __construct() {
		$services = MediaWikiServices::getInstance();
		$this->wanCache = $services->getMainWANObjectCache();
		$this->revLookup = $services->getRevisionLookup();
	}

	/**
	 * Get a list of gadget ids from cache/database
	 *
	 * @return string[]
	 */
	public function getGadgetIds() {
		$key = $this->getGadgetIdsKey();

		$fname = __METHOD__;
		return $this->wanCache->getWithSetCallback(
			$key,
			self::CACHE_TTL,
			function ( $oldValue, &$ttl, array &$setOpts ) use ( $fname ) {
				$dbr = wfGetDB( DB_REPLICA );
				$setOpts += Database::getCacheSetOptions( $dbr );

				return $dbr->selectFieldValues(
					'page',
					'page_title',
					[ 'page_namespace' => NS_GADGET_DEFINITION ],
					$fname
				);
			},
			[
				'checkKeys' => [ $key ],
				'pcTTL' => WANObjectCache::TTL_PROC_SHORT,
				'lockTSE' => 30
			]
		);
	}

	/**
	 * @inheritDoc
	 */
	public function handlePageUpdate( LinkTarget $target ) {
		if ( $target->inNamespace( NS_GADGET_DEFINITION ) ) {
			$this->purgeGadgetEntry( $target->getText() );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function handlePageCreation( LinkTarget $target ) {
		if ( $target->inNamespace( NS_GADGET_DEFINITION ) ) {
			$this->purgeGadgetIdsList();
		}
	}

	/**
	 * @inheritDoc
	 */
	public function handlePageDeletion( LinkTarget $target ) {
		if ( $target->inNamespace( NS_GADGET_DEFINITION ) ) {
			$this->purgeGadgetIdsList();
			$this->purgeGadgetEntry( $target->getText() );
		}
	}

	/**
	 * Purge the list of gadget ids when a page is deleted or if a new page is created
	 */
	public function purgeGadgetIdsList() {
		$this->wanCache->touchCheckKey( $this->getGadgetIdsKey() );
	}

	/**
	 * @param string $id
	 * @throws InvalidArgumentException
	 * @return Gadget
	 */
	public function getGadget( $id ) {
		$key = $this->getGadgetCacheKey( $id );
		$gadget = $this->wanCache->getWithSetCallback(
			$key,
			self::CACHE_TTL,
			function ( $old, &$ttl, array &$setOpts ) use ( $id ) {
				$setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );
				$title = Title::makeTitleSafe( NS_GADGET_DEFINITION, $id );
				if ( !$title ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}

				$revRecord = $this->revLookup->getRevisionByTitle( $title );
				if ( !$revRecord ) {
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}

				$content = $revRecord->getContent( SlotRecord::MAIN );
				if ( !$content instanceof GadgetDefinitionContent ) {
					// Uhm...
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					return null;
				}

				return Gadget::newFromDefinitionContent( $id, $content );
			},
			[
				'checkKeys' => [ $key ],
				'pcTTL' => WANObjectCache::TTL_PROC_SHORT,
				'lockTSE' => 30
			]
		);

		if ( $gadget === null ) {
			throw new InvalidArgumentException( "No gadget registered for '$id'" );
		}

		return $gadget;
	}

	/**
	 * Update the cache for a specific Gadget whenever it is updated
	 *
	 * @param string $id
	 */
	public function purgeGadgetEntry( $id ) {
		$this->wanCache->touchCheckKey( $this->getGadgetCacheKey( $id ) );
	}

	/**
	 * @return string
	 */
	private function getGadgetIdsKey() {
		return $this->wanCache->makeKey( 'gadgets', 'namespace', 'ids' );
	}

	/**
	 * @param string $id
	 * @return string
	 */
	private function getGadgetCacheKey( $id ) {
		return $this->wanCache->makeKey(
			'gadgets', 'object', md5( $id ), Gadget::GADGET_CLASS_VERSION );
	}
}
