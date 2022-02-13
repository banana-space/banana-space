<?php

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;

/**
 * Get the returned things of a method
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
class GetReturnObjsVisitor extends PluginAwarePostAnalysisVisitor {
	use TaintednessBaseVisitor;

	/**
	 * @inheritDoc
	 */
	public function __construct( CodeBase $code_base, Context $context ) {
		parent::__construct( $code_base, $context );
		$this->plugin = SecurityCheckPlugin::$pluginInstance;
	}

	/**
	 * @param Node $node
	 * @return array Phan objects related to elements in return line
	 * @suppress PhanParamSignatureMismatch
	 */
	public function visit( Node $node ) {
		$results = [];
		foreach ( $node->children as $child ) {
			if ( !$child instanceof Node ) {
				continue;
			}
			// Note, this does not adjust the $context object...
			// @todo, this could be made more efficient by
			// recursing only when neccessary.
			$results = array_merge( $results, $this( $child ) );
		}
		return $results;
	}

	/**
	 * @param Node $node
	 * @return array
	 */
	public function visitReturn( Node $node ) {
		if ( $node->children['expr'] instanceof Node ) {
			return $this->getPhanObjsForNode( $node->children['expr'] );
		} else {
			// Returning a literal e.g. return 'foo';
			return [];
		}
	}
}
