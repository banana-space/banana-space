<?php

namespace Cite\ResourceLoader;

use MediaWiki\MediaWikiServices;

/**
 * ResourceLoaderFileModule for adding the content language Cite CSS
 *
 * @copyright 2011-2018 VisualEditor Team's Cite sub-team and others; see AUTHORS.txt
 * @license MIT
 */
class CiteCSSFileModule extends \ResourceLoaderFileModule {

	/**
	 * @inheritDoc
	 */
	public function __construct(
		array $options = [],
		$localBasePath = null,
		$remoteBasePath = null
	) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		parent::__construct( $options, $localBasePath, $remoteBasePath );

		// Get the content language code, and all the fallbacks. The first that
		// has a ext.cite.style.<lang code>.css file present will be used.
		$langCodes = array_merge(
			[ $contLang->getCode() ],
			$contLang->getFallbackLanguages()
		);
		foreach ( $langCodes as $lang ) {
			$langStyleFile = 'ext.cite.style.' . $lang . '.css';
			$localPath = $this->getLocalPath( $langStyleFile );
			if ( file_exists( $localPath ) ) {
				$this->styles[] = $langStyleFile;
				break;
			}
		}
	}

}
