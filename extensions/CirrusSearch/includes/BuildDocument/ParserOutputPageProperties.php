<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\CirrusSearch;
use CirrusSearch\Search\CirrusIndexField;
use Elastica\Document;
use MediaWiki\Logger\LoggerFactory;
use ParserCache;
use ParserOutput;
use Sanitizer;
use Title;
use WikiPage;

/**
 * Extract searchable properties from the MediaWiki ParserOutput
 */
class ParserOutputPageProperties implements PagePropertyBuilder {
	/** @var ParserCache */
	private $parserCache;
	/** @var bool */
	private $forceParse;

	/**
	 * @param ParserCache $cache Cache to retrieve ParserOutput from
	 * @param bool $forceParse When true ignore the cache and re-parse
	 *  wikitext.
	 */
	public function __construct( ParserCache $cache, bool $forceParse ) {
		$this->parserCache = $cache;
		$this->forceParse = $forceParse;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc The document to be populated
	 * @param WikiPage $page The page to scope operation to
	 */
	public function initialize( Document $doc, WikiPage $page ): void {
		// NOOP
	}

	/**
	 * {@inheritDoc}
	 */
	public function finishInitializeBatch(): void {
		// NOOP
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Document $doc
	 * @param Title $title
	 */
	public function finalize( Document $doc, Title $title ): void {
		$page = new \WikiPage( $title );
		// TODO: If parserCache is null here then we will parse for every
		// cluster and every retry.  Maybe instead of forcing a parse, we could
		// force a parser cache update during self::initialize?
		$cache = $this->forceParse ? null : $this->parserCache;
		$this->finalizeReal( $doc, $page, $cache, new CirrusSearch );
	}

	/**
	 * Visible for testing. Much simpler to test with all objects resolved.
	 *
	 * @param Document $doc Document to finalize
	 * @param WikiPage $page WikiPage to scope operation to
	 * @param ?ParserCache $parserCache Cache to fetch parser output from. When null the
	 *  wikitext parser will be invoked.
	 * @param CirrusSearch $engine SearchEngine implementation
	 */
	public function finalizeReal( Document $doc, WikiPage $page, ?ParserCache $parserCache, CirrusSearch $engine ): void {
		$contentHandler = $page->getContentHandler();
		// TODO: Should see if we can change content handler api to avoid
		// the WikiPage god object, but currently parser cache is still
		// tied to WikiPage as well.
		$output = $contentHandler->getParserOutputForIndexing( $page, $parserCache );

		$fieldDefinitions = $contentHandler->getFieldsForSearchIndex( $engine );
		$fieldContent = $contentHandler->getDataForSearchIndex( $page, $output, $engine );
		$fieldContent = self::fixAndFlagInvalidUTF8InSource( $fieldContent, $page->getId() );
		foreach ( $fieldContent as $field => $fieldData ) {
			$doc->set( $field, $fieldData );
			if ( isset( $fieldDefinitions[$field] ) ) {
				$hints = $fieldDefinitions[$field]->getEngineHints( $engine );
				CirrusIndexField::addIndexingHints( $doc, $field, $hints );
			}
		}

		$doc->set( 'display_title', self::extractDisplayTitle( $page->getTitle(), $output ) );
	}

	/**
	 * @param Title $title
	 * @param ParserOutput $output
	 * @return string|null
	 */
	private static function extractDisplayTitle( Title $title, ParserOutput $output ): ?string {
		$titleText = $title->getText();
		$titlePrefixedText = $title->getPrefixedText();

		$raw = $output->getDisplayTitle();
		if ( $raw === false ) {
			return null;
		}
		$clean = Sanitizer::stripAllTags( $raw );
		// Only index display titles that differ from the normal title
		if ( self::isSameString( $clean, $titleText ) ||
			self::isSameString( $clean, $titlePrefixedText )
		) {
			return null;
		}
		if ( $title->getNamespace() === 0 || false === strpos( $clean, ':' ) ) {
			return $clean;
		}
		// There is no official way that namespaces work in display title, it
		// is an arbitrary string. Even so some use cases, such as the
		// Translate extension, will translate the namespace as well. Here
		// `Help:foo` will have a display title of `Aide:bar`. If we were to
		// simply index as is the autocomplete and near matcher would see
		// Help:Aide:bar, which doesn't seem particularly useful.
		// The strategy here is to see if the portion before the : is a valid namespace
		// in either the language of the wiki or the language of the page. If it is
		// then we strip it from the display title.
		list( $maybeNs, $maybeDisplayTitle ) = explode( ':', $clean, 2 );
		$cleanTitle = Title::newFromText( $clean );
		if ( $cleanTitle === null ) {
			// The title is invalid, we cannot extract the ns prefix
			return $clean;
		}
		if ( $cleanTitle->getNamespace() == $title->getNamespace() ) {
			// While it doesn't really matter, $cleanTitle->getText() may
			// have had ucfirst() applied depending on settings so we
			// return the unmodified $maybeDisplayTitle.
			return $maybeDisplayTitle;
		}

		$docLang = $title->getPageLanguage();
		$nsIndex = $docLang->getNsIndex( $maybeNs );
		if ( $nsIndex !== $title->getNamespace() ) {
			// Valid namespace but not the same as the actual page.
			// Keep the namespace in the display title.
			return $clean;
		}

		return self::isSameString( $maybeDisplayTitle, $titleText )
			? null
			: $maybeDisplayTitle;
	}

	private static function isSameString( string $a, string $b ): bool {
		$a = mb_strtolower( strtr( $a, '_', ' ' ) );
		$b = mb_strtolower( strtr( $b, '_', ' ' ) );
		return $a === $b;
	}

	/**
	 * Find invalid UTF-8 sequence in the source text.
	 * Fix them and flag the doc with the CirrusSearchInvalidUTF8 template.
	 *
	 * Temporary solution to help investigate/fix T225200
	 *
	 * Visible for testing only
	 * @param array $fieldDefinitions
	 * @param int $pageId
	 * @return array
	 */
	public static function fixAndFlagInvalidUTF8InSource( array $fieldDefinitions, int $pageId ): array {
		if ( isset( $fieldDefinitions['source_text'] ) ) {
			$fixedVersion = mb_convert_encoding( $fieldDefinitions['source_text'], 'UTF-8', 'UTF-8' );
			if ( $fixedVersion !== $fieldDefinitions['source_text'] ) {
				LoggerFactory::getInstance( 'CirrusSearch' )
					->warning( 'Fixing invalid UTF-8 sequences in source text for page id {page_id}',
						[ 'page_id' => $pageId ] );
				$fieldDefinitions['source_text'] = $fixedVersion;
				$fieldDefinitions['template'][] = Title::makeTitle( NS_TEMPLATE, 'CirrusSearchInvalidUTF8' )->getPrefixedText();
			}
		}
		return $fieldDefinitions;
	}

}
