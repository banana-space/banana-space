<?php

use MediaWiki\MediaWikiServices;

/**
 * Copyright © 2007 Martin Seidel (Xarax) <jodeldi@gmx.de>
 *
 * Inspired by djvuhandler from Tim Starling
 * Modified and written by Xarax
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

class PdfHandler extends ImageHandler {
	public static $messages = [
		'main' => 'pdf-file-page-warning',
		'header' => 'pdf-file-page-warning-header',
		'info' => 'pdf-file-page-warning-info',
		'footer' => 'pdf-file-page-warning-footer',
	];

	/**
	 * @return bool
	 */
	public function isEnabled() {
		global $wgPdfProcessor, $wgPdfPostProcessor, $wgPdfInfo;

		if ( !isset( $wgPdfProcessor ) || !isset( $wgPdfPostProcessor ) || !isset( $wgPdfInfo ) ) {
			wfDebug( "PdfHandler is disabled, please set the following\n" );
			wfDebug( "variables in LocalSettings.php:\n" );
			wfDebug( "\$wgPdfProcessor, \$wgPdfPostProcessor, \$wgPdfInfo\n" );
			return false;
		}
		return true;
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public function mustRender( $file ) {
		return true;
	}

	/**
	 * @param File $file
	 * @return bool
	 */
	public function isMultiPage( $file ) {
		return true;
	}

	/**
	 * @param string $name
	 * @param string $value
	 * @return bool
	 */
	public function validateParam( $name, $value ) {
		if ( $name === 'page' && trim( $value ) !== (string)intval( $value ) ) {
			// Extra junk on the end of page, probably actually a caption
			// e.g. [[File:Foo.pdf|thumb|Page 3 of the document shows foo]]
			return false;
		}
		if ( in_array( $name, [ 'width', 'height', 'page' ] ) ) {
			return ( $value > 0 );
		}
		return false;
	}

	/**
	 * @param array $params
	 * @return bool|string
	 */
	public function makeParamString( $params ) {
		$page = $params['page'] ?? 1;
		if ( !isset( $params['width'] ) ) {
			return false;
		}
		return "page{$page}-{$params['width']}px";
	}

	/**
	 * @param string $str
	 * @return array|bool
	 */
	public function parseParamString( $str ) {
		$m = [];

		if ( preg_match( '/^page(\d+)-(\d+)px$/', $str, $m ) ) {
			return [ 'width' => $m[2], 'page' => $m[1] ];
		}

		return false;
	}

	/**
	 * @param array $params
	 * @return array
	 */
	public function getScriptParams( $params ) {
		return [
			'width' => $params['width'],
			'page' => $params['page'],
		];
	}

	/**
	 * @return array
	 */
	public function getParamMap() {
		return [
			'img_width' => 'width',
			'img_page' => 'page',
		];
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @param string $msg
	 * @return MediaTransformError
	 */
	protected function doThumbError( $width, $height, $msg ) {
		return new MediaTransformError( 'thumbnail_error',
			$width, $height, wfMessage( $msg )->inContentLanguage()->text() );
	}

	/**
	 * @param File $image
	 * @param string $dstPath
	 * @param string $dstUrl
	 * @param array $params
	 * @param int $flags
	 * @return MediaTransformError|MediaTransformOutput|ThumbnailImage|TransformParameterError
	 */
	public function doTransform( $image, $dstPath, $dstUrl, $params, $flags = 0 ) {
		global $wgPdfProcessor, $wgPdfPostProcessor, $wgPdfHandlerDpi, $wgPdfHandlerJpegQuality;

		if ( !$this->normaliseParams( $image, $params ) ) {
			return new TransformParameterError( $params );
		}

		$width = (int)$params['width'];
		$height = (int)$params['height'];
		$page = (int)$params['page'];

		if ( $page > $this->pageCount( $image ) ) {
			return $this->doThumbError( $width, $height, 'pdf_page_error' );
		}

		if ( $flags & self::TRANSFORM_LATER ) {
			return new ThumbnailImage( $image, $dstUrl, false, [
				'width' => $width,
				'height' => $height,
				'page' => $page,
			] );
		}

		if ( !wfMkdirParents( dirname( $dstPath ), null, __METHOD__ ) ) {
			return $this->doThumbError( $width, $height, 'thumbnail_dest_directory' );
		}

		// Thumbnail extraction is very inefficient for large files.
		// Provide a way to pool count limit the number of downloaders.
		if ( $image->getSize() >= 1e7 ) { // 10MB
			$work = new PoolCounterWorkViaCallback( 'GetLocalFileCopy', sha1( $image->getName() ),
				[
					'doWork' => function () use ( $image ) {
						return $image->getLocalRefPath();
					}
				]
			);
			$srcPath = $work->execute();
		} else {
			$srcPath = $image->getLocalRefPath();
		}

		if ( $srcPath === false ) { // could not download original
			return $this->doThumbError( $width, $height, 'filemissing' );
		}

		$cmd = '(' . wfEscapeShellArg(
			$wgPdfProcessor,
			"-sDEVICE=jpeg",
			"-sOutputFile=-",
			"-sstdout=%stderr",
			"-dFirstPage={$page}",
			"-dLastPage={$page}",
			"-dSAFER",
			"-r{$wgPdfHandlerDpi}",
			"-dBATCH",
			"-dNOPAUSE",
			"-q",
			$srcPath
		);
		$cmd .= " | " . wfEscapeShellArg(
			$wgPdfPostProcessor,
			"-depth",
			"8",
			"-quality",
			$wgPdfHandlerJpegQuality,
			"-resize",
			$width,
			"-",
			$dstPath
		);
		$cmd .= ")";

		wfDebug( __METHOD__ . ": $cmd\n" );
		$retval = '';
		$err = wfShellExecWithStderr( $cmd, $retval );

		$removed = $this->removeBadFile( $dstPath, $retval );

		if ( $retval != 0 || $removed ) {
			wfDebugLog( 'thumbnail',
				sprintf( 'thumbnail failed on %s: error %d "%s" from "%s"',
				wfHostname(), $retval, trim( $err ), $cmd ) );
			return new MediaTransformError( 'thumbnail_error', $width, $height, $err );
		} else {
			return new ThumbnailImage( $image, $dstUrl, $dstPath, [
				'width' => $width,
				'height' => $height,
				'page' => $page,
			] );
		}
	}

	/**
	 * @param File $image
	 * @param string $path
	 * @return PdfImage
	 * @suppress PhanUndeclaredProperty
	 */
	private function getPdfImage( $image, $path ) {
		if ( !$image ) {
			$pdfimg = new PdfImage( $path );
		} elseif ( !isset( $image->pdfImage ) ) {
			$pdfimg = $image->pdfImage = new PdfImage( $path );
		} else {
			$pdfimg = $image->pdfImage;
		}

		return $pdfimg;
	}

	/**
	 * @param File $image
	 * @return bool|array
	 */
	private function getMetaArray( $image ) {
		if ( isset( $image->pdfMetaArray ) ) {
			return $image->pdfMetaArray;
		}

		$metadata = $image->getMetadata();

		if ( !$this->isMetadataValid( $image, $metadata ) ) {
			wfDebug( "Pdf metadata is invalid or missing, should have been fixed in upgradeRow\n" );
			return false;
		}

		$work = new PoolCounterWorkViaCallback(
			'PdfHandler-unserialize-metadata',
			$image->getName(),
			[
				/**
				 * @suppress PhanUndeclaredProperty
				 */
				'doWork' => function () use ( $image, $metadata ) {
					Wikimedia\suppressWarnings();
					$image->pdfMetaArray = unserialize( $metadata );
					Wikimedia\restoreWarnings();
				},
			]
		);
		$work->execute();

		return $image->pdfMetaArray;
	}

	/**
	 * @param File $image
	 * @param string $path
	 * @return array|bool
	 */
	public function getImageSize( $image, $path ) {
		return $this->getPdfImage( $image, $path )->getImageSize();
	}

	/**
	 * @param string $ext
	 * @param string $mime
	 * @param null $params
	 * @return array
	 */
	public function getThumbType( $ext, $mime, $params = null ) {
		global $wgPdfOutputExtension;
		static $mime;

		if ( !isset( $mime ) ) {
			$magic = MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer();
			$mime = $magic->guessTypesForExtension( $wgPdfOutputExtension );
		}
		return [ $wgPdfOutputExtension, $mime ];
	}

	/**
	 * @param File $image
	 * @param string $path
	 * @return string
	 */
	public function getMetadata( $image, $path ) {
		return serialize( $this->getPdfImage( $image, $path )->retrieveMetaData() );
	}

	/**
	 * @param File $image
	 * @param string $metadata
	 * @return bool
	 */
	public function isMetadataValid( $image, $metadata ) {
		if ( !$metadata || $metadata === serialize( [] ) ) {
			return self::METADATA_BAD;
		} elseif ( strpos( $metadata, 'mergedMetadata' ) === false ) {
			return self::METADATA_COMPATIBLE;
		}
		return self::METADATA_GOOD;
	}

	/**
	 * @param File $image
	 * @param bool|IContextSource $context Context to use (optional)
	 * @return bool|array
	 */
	public function formatMetadata( $image, $context = false ) {
		$meta = $image->getMetadata();

		if ( !$meta ) {
			return false;
		}
		Wikimedia\suppressWarnings();
		$meta = unserialize( $meta );
		Wikimedia\restoreWarnings();

		if ( !isset( $meta['mergedMetadata'] )
			|| !is_array( $meta['mergedMetadata'] )
			|| count( $meta['mergedMetadata'] ) < 1
		) {
			return false;
		}

		// Inherited from MediaHandler.
		return $this->formatMetadataHelper( $meta['mergedMetadata'], $context );
	}

	/**
	 * @param File $image
	 * @return bool|int
	 */
	public function pageCount( File $image ) {
		$info = $this->getDimensionInfo( $image );

		return $info ? $info['pageCount'] : false;
	}

	/**
	 * @param File $image
	 * @param int $page
	 * @return array|bool
	 */
	public function getPageDimensions( File $image, $page ) {
		$index = $page; // MW starts pages at 1, as they are stored here

		$info = $this->getDimensionInfo( $image );
		if ( $info && isset( $info['dimensionsByPage'][$index] ) ) {
			return $info['dimensionsByPage'][$index];
		}

		return false;
	}

	protected function getDimensionInfo( File $file ) {
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return $cache->getWithSetCallback(
			$cache->makeKey( 'file-pdf', 'dimensions', $file->getSha1() ),
			$cache::TTL_INDEFINITE,
			function () use ( $file ) {
				$data = $this->getMetaArray( $file );
				if ( !$data || !isset( $data['Pages'] ) ) {
					return false;
				}
				unset( $data['text'] ); // lower peak RAM

				$dimsByPage = [];
				$count = intval( $data['Pages'] );
				for ( $i = 1; $i <= $count; $i++ ) {
					$dimsByPage[$i] = PdfImage::getPageSize( $data, $i );
				}

				return [ 'pageCount' => $count, 'dimensionsByPage' => $dimsByPage ];
			},
			[ 'pcTTL' => $cache::TTL_INDEFINITE ]
		);
	}

	/**
	 * @param File $image
	 * @param int $page
	 * @return bool
	 */
	public function getPageText( File $image, $page ) {
		$data = $this->getMetaArray( $image );
		if ( !$data || !isset( $data['text'] ) || !isset( $data['text'][$page - 1] ) ) {
			return false;
		}
		return $data['text'][$page - 1];
	}

	/**
	 * Adds a warning about PDFs being potentially dangerous to the file
	 * page. Multiple messages with this base will be used.
	 * @param File $file
	 * @return array
	 */
	public function getWarningConfig( $file ) {
		return [
			'messages' => self::$messages,
			'link' => '//www.mediawiki.org/wiki/Special:MyLanguage/Help:Security/PDF_files',
			'module' => 'pdfhandler.messages',
		];
	}

	/**
	 * Register a module with the warning messages in it.
	 * @param ResourceLoader &$resourceLoader
	 */
	public static function registerWarningModule( &$resourceLoader ) {
		$resourceLoader->register( 'pdfhandler.messages', [
			'messages' => array_values( self::$messages ),
		] );
	}
}
