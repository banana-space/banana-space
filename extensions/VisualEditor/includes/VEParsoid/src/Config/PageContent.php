<?php
/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

namespace VEParsoid\Config;

use InvalidArgumentException;
use MediaWiki\Revision\RevisionRecord;

use Wikimedia\Parsoid\Config\PageContent as IPageContent;

/**
 * PageContent implementation for MediaWiki
 *
 * @todo This belongs in MediaWiki, not Parsoid. We'll move it there when we
 *  get to the point of integrating the two.
 */
class PageContent implements IPageContent {

	/** @var RevisionRecord */
	private $rev;

	/**
	 * @param RevisionRecord $rev
	 */
	public function __construct( RevisionRecord $rev ) {
		$this->rev = $rev;
	}

	/** @inheritDoc */
	public function getRoles(): array {
		return $this->rev->getSlotRoles();
	}

	/** @inheritDoc */
	public function hasRole( string $role ): bool {
		return $this->rev->hasSlot( $role );
	}

	/**
	 * Throw if the revision doesn't have the named role
	 * @param string $role
	 * @throws InvalidArgumentException
	 */
	private function checkRole( string $role ): void {
		if ( !$this->rev->hasSlot( $role ) ) {
			throw new InvalidArgumentException( "PageContent does not have role '$role'" );
		}
	}

	/** @inheritDoc */
	public function getModel( string $role ): string {
		$this->checkRole( $role );
		return $this->rev->getContent( $role )->getModel();
	}

	/** @inheritDoc */
	public function getFormat( string $role ): string {
		$this->checkRole( $role );
		return $this->rev->getContent( $role )->getDefaultFormat();
	}

	/** @inheritDoc */
	public function getContent( string $role ): string {
		$this->checkRole( $role );
		return $this->rev->getContent( $role )->serialize();
	}

	/** @inheritDoc */
	public function getRedirectTarget(): ?string {
		$content = $this->rev->getContent( 'main' );
		$target = $content ? $content->getRedirectTarget() : null;
		return $target ? $target->getPrefixedDBkey() : null;
	}

}
