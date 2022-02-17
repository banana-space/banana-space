<?php

namespace CirrusSearch\Profile;

/**
 * Override the default profile.
 */
interface SearchProfileOverride {
	/**
	 * Default priority for uri param overrides
	 */
	const URI_PARAM_PRIO = 100;

	/**
	 * Default priority for user pref overrides
	 */
	const USER_PREF_PRIO = 200;

	/**
	 * Default prority for contextual overrides
	 */
	const CONTEXTUAL_PRIO = 300;

	/**
	 * Default priority for config overrides
	 */
	const CONFIG_PRIO = 400;

	/**
	 * Get the overridden name or null if it cannot be overridden.
	 * @param string[] $contextParams Arbitrary parameters describing the context
	 *  provided by the profile requestor.
	 * @return string|null
	 */
	public function getOverriddenName( array $contextParams );

	/**
	 * The priority of this override, lower wins
	 * @return int
	 */
	public function priority();

	/**
	 * Returns some explanation of the features stored in this overrider.
	 * @return array the explanation (must only contains primitives types arrays/numbers/strings
	 * so that it's easily serializable)
	 */
	public function explain(): array;
}
