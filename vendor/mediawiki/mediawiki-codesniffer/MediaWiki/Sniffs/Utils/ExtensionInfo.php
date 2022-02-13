<?php
/**
 * Copyright (C) 2017 Kunal Mehta <legoktm@member.fsf.org>
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

namespace MediaWiki\Sniffs\Utils;

use Composer\Semver\Constraint\Constraint;
use Composer\Semver\VersionParser;
use PHP_CodeSniffer\Files\File;

class ExtensionInfo {

	/**
	 * @var string Extension root path
	 */
	private $dir;

	/**
	 * @var array|false|null Parsed extension.json
	 */
	private $info = null;

	/** @var bool[] */
	private $supportCache = [];

	/**
	 * @param File $phpcsFile
	 *
	 * @return ExtensionInfo
	 */
	public static function newFromFile( File $phpcsFile ) {
		static $instances = [];
		// The first standard path will be .phpcs.xml in the extension root
		$dir = dirname( $phpcsFile->config->standards[0] );
		if ( !isset( $instances[$dir] ) ) {
			$instances[$dir] = new self( $dir );
		}

		return $instances[$dir];
	}

	/**
	 * @param string $dir Path of extension
	 */
	private function __construct( $dir ) {
		$this->dir = $dir;
	}

	/**
	 * @param string $version Version to see if it is still supported
	 *
	 * @return bool
	 */
	public function supportsMediaWiki( $version ) {
		if ( isset( $this->supportCache[$version] ) ) {
			return $this->supportCache[$version];
		}

		$info = $this->readInfo();
		if ( !$info ) {
			// Default behavior is that we assume they're following master
			return false;
		}

		if ( !isset( $info['requires']['MediaWiki'] ) ) {
			return false;
		}

		$versionParser = new VersionParser();
		$ourVersion = new Constraint( '==', $versionParser->normalize( $version ) );
		$ourVersion->setPrettyString( $version );
		$matches = $versionParser
			->parseConstraints( $info['requires']['MediaWiki'] )
			->matches( $ourVersion );
		$this->supportCache[$version] = $matches;
		return $matches;
	}

	private function readInfo() {
		if ( $this->info !== null ) {
			return $this->info;
		}

		$found = false;
		foreach ( [ 'extension', 'skin' ] as $type ) {
			$path = "{$this->dir}/$type.json";
			if ( file_exists( $path ) ) {
				$found = true;
				break;
			}
		}

		if ( !$found ) {
			$this->info = false;
			return $this->info;
		}

		$this->info = json_decode( file_get_contents( $path ), true );
		return $this->info;
	}

}
