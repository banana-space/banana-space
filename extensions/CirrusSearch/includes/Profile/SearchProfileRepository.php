<?php

namespace CirrusSearch\Profile;

/**
 * A repository of search profiles
 *
 */
interface SearchProfileRepository {

	/**
	 * The repository type
	 * @return string
	 */
	public function repositoryType();

	/**
	 * The repository name
	 * @return string
	 */
	public function repositoryName();

	/**
	 * Load a profile named $name
	 * @param string $name
	 * @return array|null the profile data or null if not found
	 */
	public function getProfile( $name );

	/**
	 * Check if a profile named $name exists in this repository
	 * @param string $name
	 * @return bool
	 */
	public function hasProfile( $name );

	/**
	 * Get the list of profiles that we want to expose to the user.
	 *
	 * @return array[] list of profiles indexed by name
	 */
	public function listExposedProfiles();
}
