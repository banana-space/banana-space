<?php

namespace MediaWiki\Hook;

use SpecialTrackingCategories;

// phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
/**
 * @stable to implement
 * @ingroup Hooks
 */
interface SpecialTrackingCategories__preprocessHook {
	/**
	 * This hook is called after LinkBatch on Special:TrackingCategories
	 *
	 * @since 1.35
	 *
	 * @param SpecialTrackingCategories $specialPage The SpecialTrackingCategories object
	 * @param array $trackingCategories Array of data from Special:TrackingCategories with msg and
	 *   cats
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onSpecialTrackingCategories__preprocess( $specialPage,
		$trackingCategories
	);
}
