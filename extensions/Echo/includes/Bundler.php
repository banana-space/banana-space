<?php

class Bundler {

	private function sort( &$array ) {
		// We have to ignore the error here (use @usort)
		// otherwise this code fails when executed by unit tests
		// See: https://bugs.php.net/bug.php?id=50688

		// @codingStandardsIgnoreStart
		@usort( $array, function( Bundleable $a, Bundleable $b ) {
			return strcmp( $b->getSortingKey(), $a->getSortingKey() );
		} );
		// @codingStandardsIgnoreEnd
	}

	/**
	 * Bundle bundleable elements that can be bundled by their bundling keys
	 *
	 * @param Bundleable[] $bundleables
	 * @return Bundleable[] Grouped notifications sorted by timestamp DESC
	 */
	public function bundle( array $bundleables ) {
		$groups = [];
		$bundled = [];

		/** @var Bundleable $element */
		foreach ( $bundleables as $element ) {
			if ( $element->canBeBundled() && $element->getBundlingKey() ) {
				$groups[ $element->getBundlingKey() ][] = $element;
			} else {
				$bundled[] = $element;
			}
		}

		foreach ( $groups as $bundlingKey => $group ) {
			$this->sort( $group );
			/** @var Bundleable $base */
			$base = array_shift( $group );
			$base->setBundledElements( $group );
			$bundled[] = $base;
		}

		$this->sort( $bundled );
		return $bundled;
	}

}
