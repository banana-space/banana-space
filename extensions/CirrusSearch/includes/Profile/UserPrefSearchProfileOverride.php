<?php

namespace CirrusSearch\Profile;

use User;

/**
 * Overrider based on user preference.
 */
class UserPrefSearchProfileOverride implements SearchProfileOverride {
	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var string name of the preference
	 */
	private $preference;

	/**
	 * @var int
	 */
	private $priority;

	/**
	 * @param User $user
	 * @param string $preference
	 * @param int $priority
	 */
	public function __construct( User $user, $preference, $priority = SearchProfileOverride::USER_PREF_PRIO ) {
		$this->user = $user;
		$this->preference = $preference;
		$this->priority = $priority;
	}

	/**
	 * Get the overridden name or null if it cannot be overridden.
	 * @param string[] $contextParams
	 * @return string|null
	 */
	public function getOverriddenName( array $contextParams ) {
		// Only check user options if the user is logged to avoid loading
		// default user options.
		if ( $this->user->getId() === 0 ) {
			return null;
		}
		return $this->user->getOption( $this->preference );
	}

	/**
	 * The priority of this override, lower wins
	 * @return int
	 */
	public function priority() {
		return $this->priority;
	}

	/**
	 * @return array
	 */
	public function explain(): array {
		return [
			'type' => 'userPreference',
			'priority' => $this->priority(),
			'userPreference' => $this->preference
		];
	}
}
