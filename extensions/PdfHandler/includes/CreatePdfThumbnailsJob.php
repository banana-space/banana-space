<?php

class CreatePdfThumbnailsJob extends Job {
	/**
	 * Flags for thumbnail jobs
	 */
	const BIG_THUMB = 1;
	const SMALL_THUMB = 2;

	/**
	 * Construct a thumbnail job
	 *
	 * @param Title $title Title object
	 * @param array $params Associative array of options:
	 *     page:    page number for which the thumbnail will be created
	 *     jobtype: CreatePDFThumbnailsJob::BIG_THUMB or CreatePDFThumbnailsJob::SMALL_THUMB
	 *              BIG_THUMB will create a thumbnail visible for full thumbnail view,
	 *              SMALL_THUMB will create a thumbnail shown in "previous page"/"next page" boxes
	 */
	public function __construct( $title, $params ) {
		parent::__construct( 'createPdfThumbnailsJob', $title, $params );
	}

	/**
	 * Run a thumbnail job on a given PDF file.
	 * @return bool true
	 */
	public function run() {
		if ( !isset( $this->params['page'] ) ) {
			wfDebugLog( 'thumbnails', 'A page for thumbnails job of ' . $this->title->getText() .
				' was not specified! That should never happen!' );
			return true; // no page set? that should never happen
		}

		$file = wfLocalFile( $this->title ); // we just want a local file
		if ( !$file ) {
			return true; // Just silently fail, perhaps the file was already deleted, don't bother
		}

		switch ( $this->params['jobtype'] ) {
			case self::BIG_THUMB:
				global $wgImageLimits;
				// Ignore user preferences, do default thumbnails
				// everything here shamelessy copied and reused from includes/ImagePage.php
				$sizeSel = User::getDefaultOption( 'imagesize' );

				// The user offset might still be incorrect, specially if
				// $wgImageLimits got changed (see bug #8858).
				if ( !isset( $wgImageLimits[$sizeSel] ) ) {
					// Default to the first offset in $wgImageLimits
					$sizeSel = 0;
				}
				$max = $wgImageLimits[$sizeSel];
				$maxWidth = $max[0];
				$maxHeight = $max[1];

				$width_orig = $file->getWidth( $this->params['page'] );
				$width = $width_orig;
				$height_orig = $file->getHeight( $this->params['page'] );
				$height = $height_orig;
				if ( $width > $maxWidth || $height > $maxHeight ) {
					// Calculate the thumbnail size.
					// First case, the limiting factor is the width, not the height.
					if ( $width / $height >= $maxWidth / $maxHeight ) {
						// $height = round( $height * $maxWidth / $width );
						$width = $maxWidth;
						// Note that $height <= $maxHeight now.
					} else {
						$newwidth = floor( $width * $maxHeight / $height );
						// $height = round( $height * $newwidth / $width );
						$width = $newwidth;
						// Note that $height <= $maxHeight now, but might not be identical
						// because of rounding.
					}
					$transformParams = [ 'page' => $this->params['page'], 'width' => $width ];
					$file->transform( $transformParams );
				}
				break;

			case self::SMALL_THUMB:
				Linker::makeThumbLinkObj( $this->title, $file, '', '', 'none',
					[ 'page' => $this->params['page'] ] );
				break;
		}

		return true;
	}

	/**
	 * @param UploadBase $upload
	 * @param string $mime
	 * @param string &$error
	 * @return bool
	 */
	public static function insertJobs( $upload, $mime, &$error ) {
		global $wgPdfCreateThumbnailsInJobQueue;
		if ( !$wgPdfCreateThumbnailsInJobQueue ) {
			return true;
		}
		$magic = MediaWiki\MediaWikiServices::getInstance()->getMimeAnalyzer();
		if ( !$magic->isMatchingExtension( 'pdf', $mime ) ) {
			return true; // not a PDF, abort
		}

		$title = $upload->getTitle();
		$uploadFile = $upload->getLocalFile();
		if ( is_null( $uploadFile ) ) {
			wfDebugLog( 'thumbnails', '$uploadFile seems to be null, should never happen...' );
			return true; // should never happen, but it's better to be secure
		}

		$metadata = $uploadFile->getMetadata();
		$unserialized = unserialize( $metadata );
		$pages = intval( $unserialized['Pages'] );

		$jobs = [];
		for ( $i = 1; $i <= $pages; $i++ ) {
			$jobs[] = new CreatePdfThumbnailsJob(
				$title,
				[ 'page' => $i, 'jobtype' => self::BIG_THUMB ]
			);
			$jobs[] = new CreatePdfThumbnailsJob(
				$title,
				[ 'page' => $i, 'jobtype' => self::SMALL_THUMB ]
			);
		}
		JobQueueGroup::singleton()->push( $jobs );
		return true;
	}
}
