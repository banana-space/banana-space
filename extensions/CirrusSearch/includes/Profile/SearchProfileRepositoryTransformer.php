<?php

namespace CirrusSearch\Profile;

/**
 * Transforms profile content based on a transformer implementation.
 */
class SearchProfileRepositoryTransformer implements SearchProfileRepository {

	/**
	 * @var SearchProfileRepository
	 */
	private $repository;

	/**
	 * @var ArrayPathSetter
	 */
	private $transformer;

	/**
	 * @param SearchProfileRepository $repository
	 * @param array|ArrayPathSetter $transformer
	 */
	public function __construct( SearchProfileRepository $repository, $transformer ) {
		if ( is_array( $transformer ) ) {
			$transformer = new ArrayPathSetter( $transformer );
		} elseif ( !$transformer instanceof ArrayPathSetter ) {
			throw new \InvalidArgumentException( '$transformer must be array|ArrayPathSetter, got ' .
				( is_object( $transformer ) ? get_class( $transformer ) : gettype( $transformer ) ) );
		}
		$this->repository = $repository;
		$this->transformer = $transformer;
	}

	/**
	 * The repository type
	 * @return string
	 */
	public function repositoryType() {
		return $this->repository->repositoryType();
	}

	/**
	 * The repository name
	 * @return string
	 */
	public function repositoryName() {
		return $this->repository->repositoryName();
	}

	/**
	 * Load a profile named $name
	 * @param string $name
	 * @return array|null the profile data or null if not found
	 */
	public function getProfile( $name ) {
		return $this->transform( $this->repository->getProfile( $name ) );
	}

	/**
	 * Check if a profile named $name exists in this repository
	 * @param string $name
	 * @return bool
	 */
	public function hasProfile( $name ) {
		return $this->repository->hasProfile( $name );
	}

	/**
	 * Get the list of profiles that we want to expose to the user.
	 *
	 * @return array[] list of profiles indexed by name
	 */
	public function listExposedProfiles() {
		return array_map( [ $this, 'transform' ], $this->repository->listExposedProfiles() );
	}

	/**
	 * Transform the profile
	 * @param array|null $profile
	 * @return array|null
	 */
	private function transform( array $profile = null ) {
		if ( $profile === null ) {
			return null;
		}
		return $this->transformer->transform( $profile );
	}
}
