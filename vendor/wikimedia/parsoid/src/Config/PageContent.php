<?php

namespace Wikimedia\Parsoid\Config;

use InvalidArgumentException;

/**
 * Page content data object
 */
interface PageContent {

	/**
	 * Return the roles available in this page
	 * @return string[]
	 */
	public function getRoles(): array;

	/**
	 * Determine whether the page contains a role
	 * @param string $role
	 * @return bool
	 */
	public function hasRole( string $role ): bool;

	/**
	 * Fetch the content model for a role
	 * @param string $role
	 * @return string
	 * @throws InvalidArgumentException if the role doesn't exist
	 */
	public function getModel( string $role ): string;

	/**
	 * Fetch the content format for a role
	 * @param string $role
	 * @return string
	 * @throws InvalidArgumentException if the role doesn't exist
	 */
	public function getFormat( string $role ): string;

	/**
	 * Fetch the content for a role
	 * @param string $role
	 * @return string
	 * @throws InvalidArgumentException if the role doesn't exist
	 */
	public function getContent( string $role ): string;

	/**
	 * If the PageContent represents a redirect, return the target
	 * of that redirect as a title string. Otherwise return null.
	 * @return string|null
	 */
	public function getRedirectTarget(): ?string;

}
