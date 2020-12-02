<?php

namespace Wikimedia\Parsoid\Config;

/**
 * MediaWiki data access interface for Parsoid
 */
interface DataAccess {

	/**
	 * Return target data for formatting links.
	 *
	 * Replaces Batcher.getPageProps()
	 *
	 * @param PageConfig $pageConfig
	 * @param string[] $titles
	 * @return array [ string Title => array ], where the array contains
	 *  - pageId: (int|null) Page ID
	 *  - revId: (int|null) Current revision of the page
	 *  - missing: (bool) Whether the page is missing
	 *  - known: (bool) Whether the special page is known
	 *  - redirect: (bool) Whether the page is a redirect
	 *  - disambiguation: (bool) Whether the page is a disambiguation page
	 */
	public function getPageInfo( PageConfig $pageConfig, array $titles ): array;

	/**
	 * Return information about files (images)
	 *
	 * This replaces ImageInfoRequest and Batcher.imageinfo()
	 *
	 * @param PageConfig $pageConfig
	 * @param array $files [ string Name => array Dims ]. The array may contain
	 *  - width: (int) Requested thumbnail width
	 *  - height: (int) Requested thumbnail height
	 *  - page: (int) Requested thumbnail page number
	 *  - seek: (int) Requested thumbnail time offset
	 * @return array [ string Title => array|null ], where the array contains
	 *  - width: (int|false) File width, false if unknown
	 *  - height: (int|false) File height, false if unknown
	 *  - size: (int|false) File size in bytes, false if unknown
	 *  - mediatype: (string) File media type
	 *  - mime: (string) File MIME type
	 *  - url: (string) File URL
	 *  - mustRender: (bool) False if the file can be directly rendered by browsers
	 *  - badFile: (bool) Whether the file is on the "bad image list"
	 *  - duration: (float, optional) Duration of the media in seconds
	 *  - thumberror: (string, optional) Error text if thumbnailing failed. Ugh.
	 *  - responsiveUrls: (string[], optional) Map of display densities to URLs.
	 *  - thumbdata: (mixed, optional) MediaWiki File->getAPIData()
	 *  - thumburl: (string, optional) Thumbnail URL
	 *  - thumbwidth: (int, optional) Thumbnail width
	 *  - thumbheight: (int, optional) Thumbnail height
	 */
	public function getFileInfo( PageConfig $pageConfig, array $files ): array;

	/**
	 * Perform a pre-save transform on wikitext
	 *
	 * This replaces PHPParseRequest with onlypst = true
	 *
	 * @todo Parsoid should be able to do this itself.
	 * @param PageConfig $pageConfig
	 * @param string $wikitext
	 * @return string Processed wikitext
	 */
	public function doPst( PageConfig $pageConfig, string $wikitext ): string;

	/**
	 * Perform a parse on wikitext
	 *
	 * This replaces PHPParseRequest with onlypst = false, and Batcher.parse()
	 *
	 * @todo Parsoid should be able to do this itself.
	 * @todo ParsoidBatchAPI also returns page properties, but they don't seem to be used in Parsoid?
	 * @param PageConfig $pageConfig
	 * @param string $wikitext
	 * @return array
	 *  - html: (string) Output HTML.
	 *  - modules: (string[]) ResourceLoader module names
	 *  - modulescripts: (string[]) ResourceLoader module names to load scripts-only
	 *  - modulestyles: (string[]) ResourceLoader module names to load styles-only
	 *  - categories: (array) [ Category name => sortkey ]
	 */
	public function parseWikitext( PageConfig $pageConfig, string $wikitext ): array;

	/**
	 * Preprocess wikitext
	 *
	 * This replaces PreprocessorRequest and Batcher.preprocess()
	 *
	 * @todo Parsoid should be able to do this itself.
	 * @todo ParsoidBatchAPI also returns page properties, but they don't seem to be used in Parsoid?
	 * @param PageConfig $pageConfig
	 * @param string $wikitext
	 * @return array
	 *  - wikitext: (string) Expanded wikitext
	 *  - modules: (string[]) ResourceLoader module names
	 *  - modulescripts: (string[]) ResourceLoader module names to load scripts-only
	 *  - modulestyles: (string[]) ResourceLoader module names to load styles-only
	 *  - categories: (array) [ Category name => sortkey ]
	 */
	public function preprocessWikitext( PageConfig $pageConfig, string $wikitext ): array;

	/**
	 * Fetch page content, e.g. for transclusion
	 *
	 * This replaces TemplateRequest
	 *
	 * @todo TemplateRequest also returns a bunch of other data, but seems to never use it except for
	 *   TemplateRequest.setPageSrcInfo() which is replaced by PageConfig.
	 * @param PageConfig $pageConfig
	 * @param string $title Title of the page to fetch
	 * @param int $oldid Revision ID to fetch. Set 0 for the current revision
	 * @return PageContent|null
	 */
	public function fetchPageContent(
		PageConfig $pageConfig, string $title, int $oldid = 0
	): ?PageContent;

	/**
	 * Fetch templatedata for a title
	 *
	 * This replaces TemplateDataRequest
	 *
	 * @param PageConfig $pageConfig
	 * @param string $title
	 * @return array|null
	 */
	public function fetchTemplateData( PageConfig $pageConfig, string $title ): ?array;

	/**
	 * Log linter data.
	 *
	 * @param PageConfig $pageConfig
	 * @param array $lints
	 */
	public function logLinterData( PageConfig $pageConfig, array $lints ): void;
}
