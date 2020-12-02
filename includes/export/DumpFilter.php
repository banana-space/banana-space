<?php
/**
 * Dump output filter class.
 * This just does output filtering and streaming; XML formatting is done
 * higher up, so be careful in what you do.
 *
 * Copyright © 2003, 2005, 2006 Brion Vibber <brion@pobox.com>
 * https://www.mediawiki.org/
 *
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

/**
 * @ingroup Dump
 */
class DumpFilter {
	/**
	 * @var DumpOutput
	 * FIXME will need to be made protected whenever legacy code
	 * is updated.
	 */
	public $sink;

	/**
	 * @var bool
	 */
	protected $sendingThisPage;

	/**
	 * @param DumpOutput &$sink
	 */
	public function __construct( &$sink ) {
		$this->sink =& $sink;
	}

	/**
	 * @param string $string
	 */
	public function writeOpenStream( $string ) {
		$this->sink->writeOpenStream( $string );
	}

	/**
	 * @param string $string
	 */
	public function writeCloseStream( $string ) {
		$this->sink->writeCloseStream( $string );
	}

	/**
	 * @param object $page
	 * @param string $string
	 */
	public function writeOpenPage( $page, $string ) {
		$this->sendingThisPage = $this->pass( $page );
		if ( $this->sendingThisPage ) {
			$this->sink->writeOpenPage( $page, $string );
		}
	}

	/**
	 * @param string $string
	 */
	public function writeClosePage( $string ) {
		if ( $this->sendingThisPage ) {
			$this->sink->writeClosePage( $string );
			$this->sendingThisPage = false;
		}
	}

	/**
	 * @param object $rev
	 * @param string $string
	 */
	public function writeRevision( $rev, $string ) {
		if ( $this->sendingThisPage ) {
			$this->sink->writeRevision( $rev, $string );
		}
	}

	/**
	 * @param object $rev
	 * @param string $string
	 */
	public function writeLogItem( $rev, $string ) {
		$this->sink->writeRevision( $rev, $string );
	}

	/**
	 * @see DumpOutput::closeRenameAndReopen()
	 * @param string|string[] $newname
	 */
	public function closeRenameAndReopen( $newname ) {
		$this->sink->closeRenameAndReopen( $newname );
	}

	/**
	 * @see DumpOutput::closeAndRename()
	 * @param string|string[] $newname
	 * @param bool $open
	 */
	public function closeAndRename( $newname, $open = false ) {
		$this->sink->closeAndRename( $newname, $open );
	}

	/**
	 * @return array
	 */
	public function getFilenames() {
		return $this->sink->getFilenames();
	}

	/**
	 * Override for page-based filter types.
	 * @param object $page
	 * @return bool
	 */
	protected function pass( $page ) {
		return true;
	}
}
