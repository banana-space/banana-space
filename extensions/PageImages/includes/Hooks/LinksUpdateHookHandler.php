<?php

namespace PageImages\Hooks;

use DerivativeContext;
use Exception;
use File;
use FormatMetadata;
use Http;
use IDBAccessObject;
use LinksUpdate;
use MediaWiki\MediaWikiServices;
use MediaWiki\Storage\SlotRecord;
use PageImages\PageImages;
use RuntimeException;
use Title;

/**
 * Handler for the "LinksUpdate" hook.
 *
 * @license WTFPL
 * @author Max Semenik
 * @author Thiemo Kreuz
 */
class LinksUpdateHookHandler {

	/**
	 * LinksUpdate hook handler, sets at most 2 page properties depending on images on page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinksUpdate
	 *
	 * @param LinksUpdate $linksUpdate the LinksUpdate object that this hook is parsing
	 */
	public static function onLinksUpdate( LinksUpdate $linksUpdate ) {
		$handler = new self();
		$handler->doLinksUpdate( $linksUpdate );
	}

	/**
	 * Returns a list of page image candidates for consideration
	 * for scoring algorithm.
	 * @param LinksUpdate $linksUpdate LinksUpdate object used to determine what page
	 * to get page images for
	 * @return array[]|null $image Associative array describing an image
	 */
	public function getPageImageCandidates( LinksUpdate $linksUpdate ) {
		global $wgPageImagesLeadSectionOnly;
		$po = false;

		if ( $wgPageImagesLeadSectionOnly ) {
			$revRecord = null;

			if ( method_exists( $linksUpdate, 'getRevisionRecord' ) ) {
				// MW 1.35+
				$revRecord = $linksUpdate->getRevisionRecord();
			} else {
				$rev = $linksUpdate->getRevision();
				if ( $rev ) {
					$revRecord = $rev->getRevisionRecord();
				}
			}

			if ( $revRecord === null ) {
				// Use READ_LATEST (T221763)
				$revRecord = MediaWikiServices::getInstance()
					->getRevisionLookup()
					->getRevisionByTitle( $linksUpdate->getTitle(), 0,
						IDBAccessObject::READ_LATEST );
			}

			if ( $revRecord ) {
				$content = $revRecord->getContent( SlotRecord::MAIN );
				if ( $content ) {
					$section = $content->getSection( 0 );

					// Certain content types e.g. AbstractContent return null if sections do not apply
					if ( $section ) {
						$po = $section->getParserOutput( $linksUpdate->getTitle() );
					}
				}
			}
		} else {
			$po = $linksUpdate->getParserOutput();
		}

		return $po ? $po->getExtensionData( 'pageImages' ) : [];
	}

	/**
	 * @param LinksUpdate $linksUpdate the LinksUpdate object that was passed to the handler
	 */
	public function doLinksUpdate( LinksUpdate $linksUpdate ) {
		$images = $this->getPageImageCandidates( $linksUpdate );

		if ( $images === null ) {
			return;
		}

		$scores = [];
		$counter = 0;

		foreach ( $images as $image ) {
			$fileName = $image['filename'];

			if ( !isset( $scores[$fileName] ) ) {
				$scores[$fileName] = -1;
			}

			$scores[$fileName] = max( $scores[$fileName], $this->getScore( $image, $counter++ ) );
		}

		$image = false;
		$free_image = false;

		foreach ( $scores as $name => $score ) {
			if ( $score > 0 ) {
				if ( !$image || $score > $scores[$image] ) {
					$image = $name;
				}
				if ( ( !$free_image || $score > $scores[$free_image] ) && $this->isImageFree( $name ) ) {
					$free_image = $name;
				}
			}
		}

		if ( $free_image ) {
			$linksUpdate->mProperties[PageImages::getPropName( true )] = $free_image;
		}

		// Only store the image if it's not free. Free image (if any) has already been stored above.
		if ( $image && $image !== $free_image ) {
			$linksUpdate->mProperties[PageImages::getPropName( false )] = $image;
		}
	}

	/**
	 * Returns score for image, the more the better, if it is less than zero,
	 * the image shouldn't be used for anything
	 *
	 * @param array $image Associative array describing an image
	 * @param int $position Image order on page
	 *
	 * @return float
	 */
	protected function getScore( array $image, $position ) {
		global $wgPageImagesScores;

		if ( isset( $image['handler'] ) ) {
			// Standalone image
			$score = $this->scoreFromTable( $image['handler']['width'], $wgPageImagesScores['width'] );
		} else {
			// From gallery
			$score = $this->scoreFromTable( $image['fullwidth'], $wgPageImagesScores['galleryImageWidth'] );
		}

		if ( isset( $wgPageImagesScores['position'][$position] ) ) {
			$score += $wgPageImagesScores['position'][$position];
		}

		$ratio = intval( $this->getRatio( $image ) * 10 );
		$score += $this->scoreFromTable( $ratio, $wgPageImagesScores['ratio'] );

		$blacklist = $this->getBlacklist();
		if ( isset( $blacklist[$image['filename']] ) ) {
			$score = -1000;
		}

		return $score;
	}

	/**
	 * Returns score based on table of ranges
	 *
	 * @param int $value The number that the various bounds are compared against
	 * to calculate the score
	 * @param float[] $scores Table of scores for different ranges of $value
	 *
	 * @return float
	 */
	protected function scoreFromTable( $value, array $scores ) {
		$lastScore = 0;

		// The loop stops at the *first* match, and therefore *requires* the input array keys to be
		// in increasing order.
		ksort( $scores, SORT_NUMERIC );
		foreach ( $scores as $upperBoundary => $score ) {
			$lastScore = $score;

			if ( $value <= $upperBoundary ) {
				break;
			}
		}

		if ( !is_numeric( $lastScore ) ) {
			wfLogWarning( 'The PageImagesScores setting must only contain numeric values!' );
		}

		return (float)$lastScore;
	}

	/**
	 * Check whether image's copyright allows it to be used freely.
	 *
	 * @param string $fileName Name of the image file
	 * @return bool
	 */
	protected function isImageFree( $fileName ) {
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $fileName );
		if ( $file ) {
			// Process copyright metadata from CommonsMetadata, if present.
			// Image is considered free if the value is '0' or unset.
			return empty( $this->fetchFileMetadata( $file )['NonFree']['value'] );
		}
		return true;
	}

	/**
	 * Fetch file metadata
	 *
	 * @param File $file File to fetch metadata from
	 * @return array
	 */
	protected function fetchFileMetadata( $file ) {
		$format = new FormatMetadata;
		$context = new DerivativeContext( $format->getContext() );
		// we don't care about the language, and specifying singleLanguage is slightly faster
		$format->setSingleLanguage( true );
		// we don't care about the language, so avoid splitting the cache by selecting English
		$context->setLanguage( 'en' );
		$format->setContext( $context );
		return $format->fetchExtendedMetadata( $file );
	}

	/**
	 * Returns width/height ratio of an image as displayed or 0 is not available
	 *
	 * @param array $image Array representing the image to get the aspect ratio from
	 *
	 * @return float|int
	 */
	protected function getRatio( array $image ) {
		$width = $image['fullwidth'];
		$height = $image['fullheight'];

		if ( !$width || !$height ) {
			return 0;
		}

		return $width / $height;
	}

	/**
	 * Returns a list of images blacklisted from influencing this extension's output
	 *
	 * @return int[] Flipped associative array in format "image BDB key" => int
	 * @throws Exception
	 */
	protected function getBlacklist() {
		global $wgPageImagesBlacklistExpiry;

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->getWithSetCallback(
			$cache->makeKey( 'pageimages-blacklist' ),
			$wgPageImagesBlacklistExpiry,
			function () {
				global $wgPageImagesBlacklist;

				$list = [];
				foreach ( $wgPageImagesBlacklist as $source ) {
					switch ( $source['type'] ) {
						case 'db':
							$list = array_merge(
								$list,
								$this->getDbBlacklist( $source['db'], $source['page'] )
							);
							break;
						case 'url':
							$list = array_merge(
								$list,
								$this->getUrlBlacklist( $source['url'] )
							);
							break;
						default:
							throw new RuntimeException(
								"unrecognized image blacklist type '{$source['type']}'"
							);
					}
				}

				return array_flip( $list );
			}
		);
	}

	/**
	 * Returns list of images linked by the given blacklist page
	 *
	 * @param string|bool $dbName Database name or false for current database
	 * @param string $page
	 *
	 * @return string[]
	 */
	private function getDbBlacklist( $dbName, $page ) {
		$dbr = wfGetDB( DB_REPLICA, [], $dbName );
		$title = Title::newFromText( $page );
		$list = [];

		$id = $dbr->selectField(
			'page',
			'page_id',
			[ 'page_namespace' => $title->getNamespace(), 'page_title' => $title->getDBkey() ],
			__METHOD__
		);

		if ( $id ) {
			$res = $dbr->select( 'pagelinks',
				'pl_title',
				[ 'pl_from' => $id, 'pl_namespace' => NS_FILE ],
				__METHOD__
			);
			foreach ( $res as $row ) {
				$list[] = $row->pl_title;
			}
		}

		return $list;
	}

	/**
	 * Returns list of images on given remote blacklist page.
	 * Not quite 100% bulletproof due to localised namespaces and so on.
	 * Though if you beat people if they add bad entries to the list... :)
	 *
	 * @param string $url
	 *
	 * @return string[]
	 */
	private function getUrlBlacklist( $url ) {
		global $wgFileExtensions;

		$list = [];
		$text = Http::get( $url, [ 'timeout' => 3 ], __METHOD__ );
		$regex = '/\[\[:([^|\#]*?\.(?:' . implode( '|', $wgFileExtensions ) . '))/i';

		if ( $text && preg_match_all( $regex, $text, $matches ) ) {
			foreach ( $matches[1] as $s ) {
				$t = Title::makeTitleSafe( NS_FILE, $s );

				if ( $t ) {
					$list[] = $t->getDBkey();
				}
			}
		}

		return $list;
	}

}
