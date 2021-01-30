<?php

namespace Flow\Import;

use Flow\Repository\TitleRepository;
use Title;

class ArchiveNameHelper {

	/**
	 * Helper method decides on an archive title based on a set of printf formats.
	 * Each format should first have a %s for the base page name and a %d for the
	 * archive page number. Example:
	 *
	 *   %s/Archive %d
	 *
	 * It will iterate through the formats looking for an existing format.  If no
	 * formats are currently in use the first format will be returned with n=1.
	 * If a format is currently in used we will look for the first unused page
	 * >= to n=1 and <= to n=20.
	 *
	 * @param Title $source
	 * @param string[] $formats
	 * @param TitleRepository|null $titleRepo
	 * @return Title
	 * @throws ImportException
	 */
	public function decideArchiveTitle( Title $source, array $formats, TitleRepository $titleRepo = null ) {
		$info = self::findLatestArchiveInfo( $source, $formats, $titleRepo );
		$format = $info ? $info['format'] : $formats[0];
		$counter = $info ? $info['counter'] + 1 : 1;
		$text = $source->getPrefixedText();
		return Title::newFromText( sprintf( $format, $text, $counter ) );
	}

	/**
	 * @param Title $source
	 * @param string[] $formats
	 * @param TitleRepository|null $titleRepo
	 * @return bool|mixed
	 */
	public function findLatestArchiveTitle( Title $source, array $formats, TitleRepository $titleRepo = null ) {
		$info = self::findLatestArchiveInfo( $source, $formats, $titleRepo );
		return $info ? $info['title'] : false;
	}

	/**
	 * @param Title $source
	 * @param string[] $formats
	 * @param TitleRepository|null $titleRepo
	 * @return bool|array
	 */
	protected function findLatestArchiveInfo( Title $source, array $formats, TitleRepository $titleRepo = null ) {
		if ( $titleRepo === null ) {
			$titleRepo = new TitleRepository();
		}

		$format = false;
		$n = 1;
		$text = $source->getPrefixedText();
		foreach ( $formats as $potential ) {
			$title = Title::newFromText( sprintf( $potential, $text, $n ) );
			if ( $title && $titleRepo->exists( $title ) ) {
				$format = $potential;
				break;
			}
		}
		if ( $format === false ) {
			// no archive page matches any format
			return false;
		}

		$latestArchiveInfo = false;
		for ( $n = 1; $n <= 20; ++$n ) {
			$title = Title::newFromText( sprintf( $format, $text, $n ) );
			if ( !$title || !$titleRepo->exists( $title ) ) {
				break;
			}
			$latestArchiveInfo = [
				'title' => $title,
				'format' => $format,
				'counter' => $n
			];
		}
		return $latestArchiveInfo;
	}

}
