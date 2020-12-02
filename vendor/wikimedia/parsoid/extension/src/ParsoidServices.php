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
declare( strict_types = 1 );

namespace MWParsoid;

use MediaWiki\MediaWikiServices;
use MWParsoid\Config\PageConfigFactory;
use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\SiteConfig;

// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
class ParsoidServices {

	/** @var MediaWikiServices */
	private $services;

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	public function getParsoidSiteConfig(): SiteConfig {
		return $this->services->get( 'ParsoidSiteConfig' );
	}

	public function getParsoidPageConfigFactory(): PageConfigFactory {
		return $this->services->get( 'ParsoidPageConfigFactory' );
	}

	public function getParsoidDataAccess(): DataAccess {
		return $this->services->get( 'ParsoidDataAccess' );
	}

}
