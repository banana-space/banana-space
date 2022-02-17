<?php

namespace CirrusSearch\BuildDocument;

use CirrusSearch\Connection;
use CirrusSearch\Search\CirrusIndexField;
use CirrusSearch\SearchConfig;
use Elastica\Document;
use Hooks;
use IDatabase;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionStore;
use ParserCache;
use ParserOutput;
use Wikimedia\Assert\Assert;
use WikiPage;

/**
 * Orchestrate the process of building an elasticsearch document out of a
 * WikiPage. Document building is performed in two stages, and all properties
 * are provided by PagePropertyBuilder instances chosen by a set of provided
 * flags.
 *
 * The first stage, called initialize, sets up the basic document properties.
 * This stage is executed one time per update and the results are shared
 * between all retry attempts and clusters to be written to. The results of the
 * initialize stage may be written to the job queue, so we try to keep the size
 * of these documents reasonable small. The initialize stage supports batching
 * initialization by the PagePropertyBuilder instances.
 *
 * The second stage of document building, finalize, is called on each attempt
 * to send a document to an elasticsearch cluster. This stage loads the bulk
 * content, potentially megabytes, from mediawiki ParserOutput into the
 * documents.
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
class BuildDocument {
	const HINT_FLAGS = 'BuildDocument_flags';

	// Bit field parameters for constructor et al.
	const INDEX_EVERYTHING = 0;
	const INDEX_ON_SKIP = 1;
	const SKIP_PARSE = 2;
	const SKIP_LINKS = 4;
	const FORCE_PARSE = 8;

	/** @var SearchConfig */
	private $config;
	/** @var Connection */
	private $connection;
	/** @var IDatabase */
	private $db;
	/** @var ParserCache */
	private $parserCache;
	/** @var RevisionStore */
	private $revStore;

	/**
	 * @param Connection $connection Cirrus connection to read page properties from
	 * @param IDatabase $db Wiki database connection to read page properties from
	 * @param ParserCache $parserCache Cache to read parser output from
	 * @param RevisionStore $revStore Store for retrieving revisions by id
	 */
	public function __construct(
		Connection $connection,
		IDatabase $db,
		ParserCache $parserCache,
		RevisionStore $revStore
	) {
		$this->config = $connection->getConfig();
		$this->connection = $connection;
		$this->db = $db;
		$this->parserCache = $parserCache;
		$this->revStore = $revStore;
	}

	/**
	 * @param \WikiPage[] $pages List of pages to build documents for. These
	 *  pages must represent concrete pages with content. It is expected that
	 *  redirects and non-existent pages have been resolved.
	 * @param int $flags Bitfield of class constants
	 * @return \Elastica\Document[] List of created documents indexed by page id.
	 */
	public function initialize( array $pages, int $flags ): array {
		$documents = [];
		$builders = $this->createBuilders( $flags );
		foreach ( $pages as $page ) {
			if ( !$page->exists() ) {
				LoggerFactory::getInstance( 'CirrusSearch' )->warning(
					'Attempted to build a document for a page that doesn\'t exist.  This should be caught ' .
					"earlier but wasn't.  Page: {title}",
					[ 'title' => (string)$page->getTitle() ]
				);
				continue;
			}

			$documents[$page->getId()] = $this->initializeDoc( $page, $builders, $flags );

			// Use of this hook is deprecated, integration should happen through content handler
			// interfaces.
			Hooks::run( 'CirrusSearchBuildDocumentParse', [
				$documents[$page->getId()],
				$page->getTitle(),
				$page->getContent(),
				// Intentionally pass a bogus parser output, restoring this
				// hook is a temporary hack for WikibaseMediaInfo, which does
				// not use the parser output.
				new ParserOutput( null ),
				$this->connection
			] );
		}

		foreach ( $builders as $builder ) {
			$builder->finishInitializeBatch();
		}

		return $documents;
	}

	/**
	 * Finalize building a page document.
	 *
	 * Called on every attempt to write the document to elasticsearch, meaning
	 * every cluster and every retry. Any bulk data that needs to be loaded
	 * should happen here.
	 *
	 * @param Document $doc
	 * @return bool True when the document update can proceed
	 */
	public function finalize( Document $doc ): bool {
		$flags = CirrusIndexField::getHint( $doc, self::HINT_FLAGS );
		if ( $flags !== null ) {
			try {
				$title = $this->revStore->getTitle( null, $doc->get( 'version' ) );
			} catch ( RevisionAccessException $e ) {
				$title = null;
			}
			if ( $title === null || $title->getLatestRevID() !== $doc->get( 'version' ) ) {
				// Something has changed since the job was enqueued, this is no longer
				// a valid update.
				return false;
			}
			$builders = $this->createBuilders( $flags );
			foreach ( $builders as $builder ) {
				$builder->finalize( $doc, $title );
			}
		}
		return true;
	}

	/**
	 * Construct PagePropertyBuilder instances suitable for provided flags
	 *
	 * Visible for testing. Should be private.
	 *
	 * @param int $flags Bitfield of class constants
	 * @return PagePropertyBuilder[]
	 */
	protected function createBuilders( int $flags ): array {
		$skipLinks = $flags & self::SKIP_LINKS;
		$skipParse = $flags & self::SKIP_PARSE;
		$forceParse = $flags & self::FORCE_PARSE;
		$builders = [ new DefaultPageProperties( $this->db ) ];
		if ( !$skipParse ) {
			$builders[] = new ParserOutputPageProperties( $this->parserCache, (bool)$forceParse );
		}
		if ( !$skipLinks ) {
			$builders[] = new RedirectsAndIncomingLinks( $this->connection );
		}
		return $builders;
	}

	/**
	 * Everything is sent as an update to prevent overwriting fields maintained in other processes
	 * like OtherIndex::updateOtherIndex.
	 *
	 * But we need a way to index documents that don't already exist.  We're willing to upsert any
	 * full documents or any documents that we've been explicitly told it is ok to index when they
	 * aren't full. This is typically just done during the first phase of the initial index build.
	 * A quick note about docAsUpsert's merging behavior:  It overwrites all fields provided by doc
	 * unless they are objects in both doc and the indexed source.  We're ok with this because all of
	 * our fields are either regular types or lists of objects and lists are overwritten.
	 *
	 * @param int $flags Bitfield of class constants
	 * @return bool True when upsert is allowed with the provided flags
	 */
	private function canUpsert( int $flags ): bool {
		$skipParse = $flags & self::SKIP_PARSE;
		$skipLinks = $flags & self::SKIP_LINKS;
		$indexOnSkip = $flags & self::INDEX_ON_SKIP;
		$fullDocument = !( $skipParse || $skipLinks );
		return $fullDocument || $indexOnSkip;
	}

	/**
	 * Perform initial building of a page document. This is called
	 * once when starting an update and is shared between all clusters
	 * written to. This doc may be written to the jobqueue multiple
	 * times and should not contain any large values.
	 *
	 * @param WikiPage $page
	 * @param PagePropertyBuilder[] $builders
	 * @param int $flags
	 * @return Document
	 */
	private function initializeDoc( WikiPage $page, array $builders, int $flags ): Document {
		$docId = $this->config->makeId( $page->getId() );
		$doc = new \Elastica\Document( $docId, [] );
		// allow self::finalize to recreate the same set of builders
		CirrusIndexField::setHint( $doc, self::HINT_FLAGS, $flags );
		$doc->setDocAsUpsert( $this->canUpsert( $flags ) );
		// While it would make plenty of sense for a builder to provide the version (revision id),
		// we need to use it in self::finalize to ensure the revision is still the latest.
		Assert::precondition( (bool)$page->getLatest(), "Must have a latest revision" );
		$doc->set( 'version', $page->getLatest() );
		CirrusIndexField::addNoopHandler(
			$doc, 'version', 'documentVersion' );

		foreach ( $builders as $builder ) {
			$builder->initialize( $doc, $page );
		}

		return $doc;
	}
}
