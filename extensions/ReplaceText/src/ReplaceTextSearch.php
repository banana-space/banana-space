<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IResultWrapper;

class ReplaceTextSearch {

	/**
	 * @param string $search
	 * @param array $namespaces
	 * @param string $category
	 * @param string $prefix
	 * @param bool $use_regex
	 * @return IResultWrapper Resulting rows
	 */
	public static function doSearchQuery(
		$search, $namespaces, $category, $prefix, $use_regex = false
	) {
		$dbr = wfGetDB( DB_REPLICA );
		$tables = [ 'page', 'revision', 'text' ];
		$vars = [ 'page_id', 'page_namespace', 'page_title', 'old_text' ];
		if ( $use_regex ) {
			$comparisonCond = self::regexCond( $dbr, 'old_text', $search );
		} else {
			$any = $dbr->anyString();
			$comparisonCond = 'old_text ' . $dbr->buildLike( $any, $search, $any );
		}
		$conds = [
			$comparisonCond,
			'page_namespace' => $namespaces,
			'rev_id = page_latest',
			'rev_text_id = old_id'
		];

		self::categoryCondition( $category, $tables, $conds );
		self::prefixCondition( $prefix, $conds );
		$options = [
			'ORDER BY' => 'page_namespace, page_title',
			// 250 seems like a reasonable limit for one screen.
			// @TODO - should probably be a setting.
			'LIMIT' => 250
		];

		return $dbr->select( $tables, $vars, $conds, __METHOD__, $options );
	}

	/**
	 * @param string $category
	 * @param array &$tables
	 * @param array &$conds
	 */
	public static function categoryCondition( $category, &$tables, &$conds ) {
		if ( strval( $category ) !== '' ) {
			$category = Title::newFromText( $category )->getDbKey();
			$tables[] = 'categorylinks';
			$conds[] = 'page_id = cl_from';
			$conds['cl_to'] = $category;
		}
	}

	/**
	 * @param string $prefix
	 * @param array &$conds
	 */
	public static function prefixCondition( $prefix, &$conds ) {
		if ( strval( $prefix ) === '' ) {
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$title = Title::newFromText( $prefix );
		if ( !is_null( $title ) ) {
			$prefix = $title->getDbKey();
		}
		$any = $dbr->anyString();
		$conds[] = 'page_title ' . $dbr->buildLike( $prefix, $any );
	}

	/**
	 * @param Database $dbr
	 * @param string $column
	 * @param string $regex
	 * @return string query condition for regex
	 */
	public static function regexCond( $dbr, $column, $regex ) {
		if ( $dbr->getType() == 'postgres' ) {
			$op = '~';
		} else {
			$op = 'REGEXP';
		}
		return "$column $op " . $dbr->addQuotes( $regex );
	}
}
