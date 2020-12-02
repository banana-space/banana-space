<?php

namespace PageImages\Hooks;

use File;
use ImageGalleryBase;
use MediaWiki\MediaWikiServices;
use Parser;
use Title;

/**
 * Handler for the "ParserMakeImageParams" and "AfterParserFetchFileAndTitle" hooks.
 *
 * @license WTFPL
 * @author Max Semenik
 * @author Thiemo Kreuz
 */
class ParserFileProcessingHookHandlers {

	/**
	 * ParserMakeImageParams hook handler, saves extended information about images used on page
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserMakeImageParams
	 *
	 * @param Title $title The title of the image
	 * @param File|bool $file The file name of the image
	 * @param array[] &$params The parameters used to generate the image
	 * @param Parser $parser Parser that is parsing this image
	 */
	public static function onParserMakeImageParams(
		Title $title,
		$file,
		array &$params,
		Parser $parser
	) {
		$handler = new self();
		$handler->doParserMakeImageParams( $title, $file, $params, $parser );
	}

	/**
	 * AfterParserFetchFileAndTitle hook handler, saves information about gallery images
	 *
	 * @param Parser $parser Parser that is parsing the gallery
	 * @param ImageGalleryBase $gallery Object representing the gallery being created
	 */
	public static function onAfterParserFetchFileAndTitle(
		Parser $parser, ImageGalleryBase $gallery
	) {
		$handler = new self();
		$handler->doAfterParserFetchFileAndTitle( $parser, $gallery );
	}

	/**
	 * @param Title $title The title of the image
	 * @param File|bool $file The file name of the image
	 * @param array[] &$params The parameters used to generate the image
	 * @param Parser $parser Parser that called the hook
	 */
	public function doParserMakeImageParams(
		Title $title,
		$file,
		array &$params,
		Parser $parser
	) {
		$this->processFile( $parser, $file, $params );
	}

	/**
	 * @param Parser $parser Parser that is parsing the gallery
	 * @param ImageGalleryBase $gallery Object representing the gallery being created
	 */
	public function doAfterParserFetchFileAndTitle( Parser $parser, ImageGalleryBase $gallery ) {
		foreach ( $gallery->getImages() as $image ) {
			$this->processFile( $parser, $image[0], null );
		}
	}

	/**
	 * @param Parser $parser
	 * @param File|Title|null $file
	 * @param array[]|null $handlerParams
	 */
	private function processFile( Parser $parser, $file, $handlerParams ) {
		if ( !$file || !$this->processThisTitle( $parser->getTitle() ) ) {
			return;
		}

		if ( !( $file instanceof File ) ) {
			$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $file );
			// Non-image files (e.g. audio files) from a <gallery> can end here
			if ( !$file || !$file->canRender() ) {
				return;
			}
		}

		if ( is_array( $handlerParams ) ) {
			$myParams = $handlerParams;
			$this->calcWidth( $myParams, $file );
		} else {
			$myParams = [];
		}

		$myParams['filename'] = $file->getTitle()->getDBkey();
		$myParams['fullwidth'] = $file->getWidth();
		$myParams['fullheight'] = $file->getHeight();

		$out = $parser->getOutput();
		$pageImages = $out->getExtensionData( 'pageImages' ) ?: [];
		$pageImages[] = $myParams;
		$out->setExtensionData( 'pageImages', $pageImages );
	}

	/**
	 * Returns true if data for this title should be saved
	 *
	 * @param Title $title
	 *
	 * @return bool
	 */
	private function processThisTitle( Title $title ) {
		global $wgPageImagesNamespaces;
		static $flipped = false;

		if ( $flipped === false ) {
			$flipped = array_flip( $wgPageImagesNamespaces );
		}

		return isset( $flipped[$title->getNamespace()] );
	}

	/**
	 * Estimates image size as displayed if not explicitly provided. We don't follow the core size
	 * calculation algorithm precisely because it's not required and editor's intentions are more
	 * important than the precise number.
	 *
	 * @param array[] &$params
	 * @param File $file
	 */
	private function calcWidth( array &$params, File $file ) {
		global $wgThumbLimits, $wgDefaultUserOptions;

		if ( isset( $params['handler']['width'] ) ) {
			return;
		}

		if ( isset( $params['handler']['height'] ) && $file->getHeight() > 0 ) {
			$params['handler']['width'] =
				$file->getWidth() * ( $params['handler']['height'] / $file->getHeight() );
		} elseif ( isset( $params['frame']['thumbnail'] )
			|| isset( $params['frame']['thumb'] )
			|| isset( $params['frame']['frameless'] )
		) {
			$params['handler']['width'] = $wgThumbLimits[$wgDefaultUserOptions['thumbsize']]
				?? 250;
		} else {
			$params['handler']['width'] = $file->getWidth();
		}
	}

}
