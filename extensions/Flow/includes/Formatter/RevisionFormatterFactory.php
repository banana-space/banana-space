<?php
/**
 * Copyright (C) 2020 Kunal Mehta <legoktm@member.fsf.org>
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
 *
 */

namespace Flow\Formatter;

use Flow\Repository\UserNameBatch;
use Flow\RevisionActionPermissions;
use Flow\Templating;

/**
 * The RevisionFormatter holds internal state like
 * contentType of output and if it should include history
 * properties.  To prevent different code using the formatter
 * from causing problems we need to create a new RevisionFormatter
 * every time it is requested.
 */
class RevisionFormatterFactory {

	/** @var RevisionActionPermissions */
	private $permissions;
	/** @var Templating */
	private $templating;
	/** @var UserNameBatch */
	private $repositoryUsername;
	/** @var int */
	private $maxThreadingDepth;

	public function __construct(
		RevisionActionPermissions $permissions, Templating $templating,
		UserNameBatch $repositoryUsername, $maxThreadingDepth
	) {
		$this->permissions = $permissions;
		$this->templating = $templating;
		$this->repositoryUsername = $repositoryUsername;
		$this->maxThreadingDepth = $maxThreadingDepth;
	}

	public function create(): RevisionFormatter {
		return new RevisionFormatter(
			$this->permissions,
			$this->templating,
			$this->repositoryUsername,
			$this->maxThreadingDepth
		);
	}
}
