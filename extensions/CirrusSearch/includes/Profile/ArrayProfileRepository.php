<?php

namespace CirrusSearch\Profile;

/**
 * Simple profile repository backed by a PHP array
 */
class ArrayProfileRepository implements SearchProfileRepository {

	/**
	 * @var string
	 */
	private $name;

	/**
	 * @var string
	 */
	private $type;

	/**
	 * @var array[]|null
	 */
	private $profiles;

	/**
	 * @var callable|null
	 */
	private $callback;

	/**
	 * @param string $repoType
	 * @param string $repoName
	 * @param array $profiles
	 * @return ArrayProfileRepository
	 */
	public static function fromArray( $repoType, $repoName, array $profiles ) {
		return new self( $repoType, $repoName, $profiles );
	}

	/**
	 * Lazy loaded array using a callback
	 * @param string $repoType
	 * @param string $repoName
	 * @param callable $loader
	 * @return ArrayProfileRepository
	 */
	public static function lazyLoaded( $repoType, $repoName, callable $loader ) {
		return new self( $repoType, $repoName, $loader );
	}

	/**
	 * Lazy loaded array by including a php file.
	 *
	 * <b>NOTE:</b> $phpFile will be loaded using PHP's require function
	 *
	 * @param string $repoType
	 * @param string $repoName
	 * @param string $phpFile
	 * @return ArrayProfileRepository
	 */
	public static function fromFile( $repoType, $repoName, $phpFile ) {
		return self::lazyLoaded( $repoType, $repoName, function () use ( $phpFile ) {
			return require $phpFile;
		} );
	}

	/**
	 * @param string $repoType
	 * @param string $repoName
	 * @param array[]|callable $profilesOrCallback
	 */
	private function __construct( $repoType, $repoName, $profilesOrCallback ) {
		$this->type = $repoType;
		$this->name = $repoName;
		if ( is_callable( $profilesOrCallback ) ) {
			$this->callback = $profilesOrCallback;
		} else {
			$this->profiles = $profilesOrCallback;
		}
	}

	/**
	 * The repository type
	 * @return string
	 */
	public function repositoryType() {
		return $this->type;
	}

	/**
	 * The repository name
	 * @return string
	 */
	public function repositoryName() {
		return $this->name;
	}

	/**
	 * Load a profile named $name
	 * @param string $name
	 * @return array|null the profile data or null if not found
	 */
	public function getProfile( $name ) {
		if ( $this->hasProfile( $name ) ) {
			return $this->getProfiles()[$name];
		}
		return null;
	}

	/**
	 * Check if a profile named $name exists in this repository
	 * @param string $name
	 * @return bool
	 */
	public function hasProfile( $name ) {
		return isset( $this->getProfiles()[$name] );
	}

	/**
	 * @return array[]
	 */
	public function listExposedProfiles() {
		return $this->getProfiles();
	}

	private function getProfiles() {
		if ( $this->profiles === null ) {
			$profiles = call_user_func( $this->callback );
			if ( !is_array( $profiles ) ) {
				throw new SearchProfileException( "Loader callback for repository " .
					$this->name . " of type " . $this->type .
					" resolved to " . gettype( $profiles ) . " but expected an array." );
			}
			$this->profiles = $profiles;
		}
		return $this->profiles;
	}
}
