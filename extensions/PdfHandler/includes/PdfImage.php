<?php
/**
 *
 * Copyright © 2007 Xarax <jodeldi@gmx.de>
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
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Shell\Shell;
use UtfNormal\Validator;

/**
 * inspired by djvuimage from Brion Vibber
 * modified and written by xarax
 */

class PdfImage {

	/**
	 * @var string
	 */
	private $mFilename;

	/**
	 * @param string $filename
	 */
	function __construct( $filename ) {
		$this->mFilename = $filename;
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return true;
	}

	/**
	 * @return array|bool
	 */
	public function getImageSize() {
		$data = $this->retrieveMetadata();
		$size = self::getPageSize( $data, 1 );

		if ( $size ) {
			$width = $size['width'];
			$height = $size['height'];
			return [ $width, $height, 'Pdf',
				"width=\"$width\" height=\"$height\"" ];
		}
		return false;
	}

	/**
	 * @param array $data
	 * @param int $page
	 * @return array|bool
	 */
	public static function getPageSize( $data, $page ) {
		global $wgPdfHandlerDpi;

		if ( isset( $data['pages'][$page]['Page size'] ) ) {
			$o = $data['pages'][$page]['Page size'];
		} elseif ( isset( $data['Page size'] ) ) {
			$o = $data['Page size'];
		} else {
			$o = false;
		}

		if ( $o ) {
			if ( isset( $data['pages'][$page]['Page rot'] ) ) {
				$r = $data['pages'][$page]['Page rot'];
			} elseif ( isset( $data['Page rot'] ) ) {
				$r = $data['Page rot'];
			} else {
				$r = 0;
			}
			$size = explode( 'x', $o, 2 );

			if ( $size ) {
				$width  = intval( trim( $size[0] ) / 72 * $wgPdfHandlerDpi );
				$height = explode( ' ', trim( $size[1] ), 2 );
				$height = intval( trim( $height[0] ) / 72 * $wgPdfHandlerDpi );
				if ( ( $r / 90 ) & 1 ) {
					// Swap width and height for landscape pages
					$t = $width;
					$width = $height;
					$height = $t;
				}

				return [
					'width' => $width,
					'height' => $height
				];
			}
		}

		return false;
	}

	/**
	 * @return array|bool|null
	 */
	public function retrieveMetaData() {
		global $wgPdfInfo, $wgPdftoText;

		if ( $wgPdfInfo ) {
			// Note in poppler 0.26 the -meta and page data options worked together,
			// but as of poppler 0.48 they must be queried separately.
			// https://bugs.freedesktop.org/show_bug.cgi?id=96801
			$cmdMeta = [
				$wgPdfInfo,
				'-enc', 'UTF-8', # Report metadata as UTF-8 text...
				'-meta',         # Report XMP metadata
				$this->mFilename,
			];
			$resultMeta = Shell::command( $cmdMeta )
				->execute();

			$cmdPages = [
				$wgPdfInfo,
				'-enc', 'UTF-8', # Report metadata as UTF-8 text...
				'-l', '9999999', # Report page sizes for all pages
				$this->mFilename,
			];
			$resultPages = Shell::command( $cmdPages )
				->execute();

			$dump = $resultMeta->getStdout() . $resultPages->getStdout();
			$data = $this->convertDumpToArray( $dump );
		} else {
			$data = null;
		}

		// Read text layer
		if ( isset( $wgPdftoText ) ) {
			$cmd = [ $wgPdftoText,  $this->mFilename, '-' ];
			$result = Shell::command( $cmd )
				->execute();
			$retval = $result->getExitCode();
			$txt = $result->getStdout();
			if ( $retval == 0 ) {
				$txt = str_replace( "\r\n", "\n", $txt );
				$pages = explode( "\f", $txt );
				foreach ( $pages as $page => $pageText ) {
					// Get rid of invalid UTF-8, strip control characters
					// Note we need to do this per page, as \f page feed would be stripped.
					$pages[$page] = Validator::cleanUp( $pageText );
				}
				$data['text'] = $pages;
			}
		}
		return $data;
	}

	/**
	 * @param string $dump
	 * @return array|bool
	 */
	protected function convertDumpToArray( $dump ) {
		if ( strval( $dump ) == '' ) {
			return false;
		}

		$lines = explode( "\n", $dump );
		$data = [];

		// Metadata is always the last item, and spans multiple lines.
		$inMetadata = false;

		// Basically this loop will go through each line, splitting key value
		// pairs on the colon, until it gets to a "Metadata:\n" at which point
		// it will gather all remaining lines into the xmp key.
		foreach ( $lines as $line ) {
			if ( $inMetadata ) {
				// Handle XMP differently due to diffence in line break
				$data['xmp'] .= "\n$line";
				continue;
			}
			$bits = explode( ':', $line, 2 );
			if ( count( $bits ) > 1 ) {
				$key = trim( $bits[0] );
				if ( $key === 'Metadata' ) {
					$inMetadata = true;
					$data['xmp'] = '';
					continue;
				}
				$value = trim( $bits[1] );
				$matches = [];
				// "Page xx rot" will be in poppler 0.20's pdfinfo output
				// See https://bugs.freedesktop.org/show_bug.cgi?id=41867
				if ( preg_match( '/^Page +(\d+) (size|rot)$/', $key, $matches ) ) {
					$data['pages'][$matches[1]][$matches[2] == 'size' ? 'Page size' : 'Page rot'] = $value;
				} else {
					$data[$key] = $value;
				}
			}
		}
		$data = $this->postProcessDump( $data );
		return $data;
	}

	/**
	 * Postprocess the metadata (convert xmp into useful form, etc)
	 *
	 * This is used to generate the metadata table at the bottom
	 * of the image description page.
	 *
	 * @param array $data metadata
	 * @return array post-processed metadata
	 */
	protected function postProcessDump( array $data ) {
		$meta = new BitmapMetadataHandler();
		$items = [];
		foreach ( $data as $key => $val ) {
			switch ( $key ) {
				case 'Title':
					$items['ObjectName'] = $val;
					break;
				case 'Subject':
					$items['ImageDescription'] = $val;
					break;
				case 'Keywords':
					// Sometimes we have empty keywords. This seems
					// to be a product of how pdfinfo deals with keywords
					// with spaces in them. Filter such empty keywords
					$keyList = array_filter( explode( ' ', $val ) );
					if ( count( $keyList ) > 0 ) {
						$items['Keywords'] = $keyList;
					}
					break;
				case 'Author':
					$items['Artist'] = $val;
					break;
				case 'Creator':
					// Program used to create file.
					// Different from program used to convert to pdf.
					$items['Software'] = $val;
					break;
				case 'Producer':
					// Conversion program
					$items['pdf-Producer'] = $val;
					break;
				case 'ModTime':
					$timestamp = wfTimestamp( TS_EXIF, $val );
					if ( $timestamp ) {
						// 'if' is just paranoia
						$items['DateTime'] = $timestamp;
					}
					break;
				case 'CreationTime':
					$timestamp = wfTimestamp( TS_EXIF, $val );
					if ( $timestamp ) {
						$items['DateTimeDigitized'] = $timestamp;
					}
					break;
				// These last two (version and encryption) I was unsure
				// if we should include in the table, since they aren't
				// all that useful to editors. I leaned on the side
				// of including. However not including if file
				// is optimized/linearized since that is really useless
				// to an editor.
				case 'PDF version':
					$items['pdf-Version'] = $val;
					break;
				case 'Encrypted':
					// @todo: The value isn't i18n-ised. The appropriate
					// place to do that is in FormatMetadata.php
					// should add a hook a there.
					// For reference, if encrypted this fields value looks like:
					// "yes (print:yes copy:no change:no addNotes:no)"
					$items['pdf-Encrypted'] = $val;
					break;
				// Note 'pages' and 'Pages' are different keys (!)
				case 'pages':
					// A pdf document can have multiple sized pages in it.
					// (However 95% of the time, all pages are the same size)
					// get a list of all the unique page sizes in document.
					// This doesn't do anything with rotation as of yet,
					// mostly because I am unsure of what a good way to
					// present that information to the user would be.
					$pageSizes = [];
					foreach ( $val as $page ) {
						if ( isset( $page['Page size'] ) ) {
							$pageSizes[$page['Page size']] = true;
						}
					}

					$pageSizeArray = array_keys( $pageSizes );
					if ( count( $pageSizeArray ) > 0 ) {
						$items['pdf-PageSize'] = $pageSizeArray;
					}
					break;
			}

		}
		$meta->addMetadata( $items, 'native' );

		if ( isset( $data['xmp'] ) && function_exists( 'xml_parser_create_ns' ) ) {
			// func exists verifies that the xml extension required for XMPReader
			// is present (Almost always is present)
			// @todo: This only handles generic xmp properties. Would be improved
			// by handling pdf xmp properties (pdf and pdfx) via XMPInfo hook.
			$xmp = new XMPReader( LoggerFactory::getInstance( 'XMP' ) );
			$xmp->parse( $data['xmp'] );
			$xmpRes = $xmp->getResults();
			foreach ( $xmpRes as $type => $xmpSection ) {
				$meta->addMetadata( $xmpSection, $type );
			}
		}
		unset( $data['xmp'] );
		$data['mergedMetadata'] = $meta->getMetadataArray();
		return $data;
	}
}
