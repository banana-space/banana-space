<?php
/**
 * This is phan plugin to provide security static analysis checks for php
 *
 * If your project has functions/methods whose output you
 * specifically need to mark tainted, then you probably
 * want to make your own subclass of SecurityCheckPlugin
 * and override getCustomFuncTaint().
 *
 * See MediaWikiSecurityCheckPlugin for an example of that.
 *
 * To use, add this file to the list of your phan plugins.
 *
 * Copyright (C) 2017  Brian Wolff <bawolff@gmail.com>
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
require_once __DIR__ . "/src/SecurityCheckPlugin.php";

class GenericSecurityCheckPlugin extends SecurityCheckPlugin {
	/**
	 * @inheritDoc
	 */
	public static function getPostAnalyzeNodeVisitorClassName(): string {
		return TaintednessVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	public static function getPreAnalyzeNodeVisitorClassName(): string {
		return PreTaintednessVisitor::class;
	}

	/**
	 * @inheritDoc
	 */
	protected function getCustomFuncTaints() : array {
		return [];
	}
}

return new GenericSecurityCheckPlugin;
