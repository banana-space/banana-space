<?php

use MediaWiki\Linker\LinkTarget;

abstract class GadgetRepo {

	/**
	 * @var GadgetRepo|null
	 */
	private static $instance;

	/**
	 * Get the ids of the gadgets provided by this repository
	 *
	 * It's possible this could be out of sync with what
	 * getGadget() will return due to caching
	 *
	 * @return string[]
	 */
	abstract public function getGadgetIds();

	/**
	 * Get the Gadget object for a given gadget id
	 *
	 * @param string $id
	 * @throws InvalidArgumentException
	 * @return Gadget
	 */
	abstract public function getGadget( $id );

	/**
	 * Given that the provided page was updated, invalidate
	 * caches if necessary
	 *
	 * @param LinkTarget $target
	 *
	 * @return void
	 */
	public function handlePageUpdate( LinkTarget $target ) {
	}

	/**
	 * Given that the provided page was created, invalidate
	 * caches if necessary
	 *
	 * @param LinkTarget $target
	 *
	 * @return void
	 */
	public function handlePageCreation( LinkTarget $target ) {
	}

	/**
	 * Given that the provided page was updated, invalidate
	 * caches if necessary
	 *
	 * @param LinkTarget $target
	 *
	 * @return void
	 */
	public function handlePageDeletion( LinkTarget $target ) {
	}

	/**
	 * Get a list of gadgets sorted by category
	 *
	 * @return array [ 'category' => [ 'name' => $gadget ] ]
	 */
	public function getStructuredList() {
		$list = [];
		foreach ( $this->getGadgetIds() as $id ) {
			try {
				$gadget = $this->getGadget( $id );
			} catch ( InvalidArgumentException $e ) {
				continue;
			}
			$list[$gadget->getCategory()][$gadget->getName()] = $gadget;
		}

		return $list;
	}

	/**
	 * Get the configured default GadgetRepo.
	 *
	 * @return GadgetRepo
	 */
	public static function singleton() {
		if ( self::$instance === null ) {
			global $wgGadgetsRepoClass; // @todo use Config here
			self::$instance = new $wgGadgetsRepoClass();
		}
		return self::$instance;
	}

	/**
	 * Should only be used by unit tests
	 *
	 * @param GadgetRepo|null $repo
	 */
	public static function setSingleton( $repo = null ) {
		self::$instance = $repo;
	}
}
