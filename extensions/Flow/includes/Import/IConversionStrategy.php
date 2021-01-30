<?php

namespace Flow\Import;

use Flow\Import\Postprocessor\Postprocessor;
use Flow\Import\SourceStore\SourceStoreInterface;
use Title;
use WikitextContent;

/**
 * Interface between the Converter and an implementation of IImportSource.
 * Informs conversion behavior and which pages should be converted.
 */
interface IConversionStrategy {
	/**
	 * @return SourceStoreInterface This should consistently return the
	 *  same store between conversion runs from the same source to
	 *  guarantee idempotent imports (without duplicate content).
	 */
	public function getSourceStore();

	/**
	 * @param Title $from The original location of the page
	 * @param Title $to The archive location of the page
	 * @return string A reason for moving the page to an archive location.
	 */
	public function getMoveComment( Title $from, Title $to );

	/**
	 * @param Title $from The original location of the page
	 * @param Title $to The archive location of the page
	 * @return string A reason for performing an edit to the
	 *  archive location.
	 */
	public function getCleanupComment( Title $from, Title $to );

	/**
	 * @param Title $title The current location of the page
	 * @param Title|null $movedFrom The location this was moved from
	 *  in a prior run of the converter.
	 * @return bool True when the conversion is complete and nothing
	 *  more can be done
	 */
	public function isConversionFinished( Title $title, Title $movedFrom = null );

	/**
	 * Create an ImportSource implementation for the provided Title.
	 * This provides a consistent interface to the headers, topics,
	 * summaries and posts to be imported.
	 *
	 * @param Title $title The page to import from
	 * @return IImportSource
	 */
	public function createImportSource( Title $title );

	/**
	 * Flow does not support viewing the history of the wikitext pages
	 * it takes over, so those need to be moved out the way. This method
	 * decides that destination.
	 *
	 * @param Title $source The title to be archived
	 * @return Title The title to archive $source to
	 * @throws ImportException When no title can be decided upon
	 */
	public function decideArchiveTitle( Title $source );

	/**
	 * Creates the content for an edit to the archived page content. When
	 * null is returned no edit is performed. This edit is performed by
	 * an administrative user provided to the Converter.
	 *
	 * @param WikitextContent $content
	 * @param Title $title
	 * @return WikitextContent|null
	 */
	public function createArchiveCleanupRevisionContent( WikitextContent $content, Title $title );

	/**
	 * Gets any postprocessors used for this type of conversion
	 * @return Postprocessor|null
	 */
	public function getPostprocessor();

	/**
	 * Checks whether the title should be converted.
	 * This is a secondary filter in addition to the original list (which might be a
	 * single title, a namespace, etc.) passed into convert/convertAll.
	 *
	 * @param Title $sourceTitle
	 * @return bool True if and only if it should be converted
	 */
	public function shouldConvert( Title $sourceTitle );
}
