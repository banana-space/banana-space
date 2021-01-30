<?php

namespace Flow\Import\Wikitext;

use DateTime;
use DateTimeZone;
use ExtensionRegistry;
use Flow\Import\ArchiveNameHelper;
use Flow\Import\IConversionStrategy;
use Flow\Import\SourceStore\SourceStoreInterface;
use LinkBatch;
use Parser;
use Psr\Log\LoggerInterface;
use StubObject;
use Title;
use User;
use WikitextContent;

/**
 * Does not really convert. Archives wikitext pages out of the way and puts
 * a new flow board in place. We take either the entire page, or the page up
 * to the first section and put it into the header of the flow board. We
 * additionally edit both the flow header and the archived page to include
 * a localized template containing the reciprocal title and the conversion
 * date in GMT.
 *
 * It is plausible something with the EchoDiscussionParser could be worked up
 * to do an import of topics and posts. We know it wont work for everything,
 * but we don't know if it works for 90%, 99%, or 99.99% of topics. We know
 * for sure that it does not currently understand anything about editing an
 * existing comment.
 */
class ConversionStrategy implements IConversionStrategy {
	/**
	 * @var LoggerInterface
	 */
	protected $logger;

	/**
	 * @var SourceStoreInterface
	 */
	protected $sourceStore;

	/**
	 * @var Parser|StubObject
	 */
	protected $parser;

	/**
	 * @var array
	 */
	protected $archiveTitleSuggestions;

	/**
	 * @var string
	 */
	protected $headerSuffix;

	/** @var User User doing the conversion actions (e.g. initial description, wikitext
	 *    archive edit).  However, actions will be attributed to the original user when
	 *    possible (e.g. the user who did the original LQT reply)
	 *
	 */
	protected $user;

	/**
	 * @var Title[]
	 */
	private $noConvertTemplates;

	/**
	 * @param Parser|StubObject $parser
	 * @param SourceStoreInterface $sourceStore
	 * @param LoggerInterface $logger
	 * @param User $user User to take conversion actions are (applicable for actions
	 *   where if there is no 'original' user)
	 * @param Title[] $noConvertTemplates List of templates that flag pages that
	 *  shouldn't be converted (optional)
	 * @param string|null $headerSuffix Wikitext to add to the end of the header (optional)
	 */
	public function __construct(
		$parser,
		SourceStoreInterface $sourceStore,
		LoggerInterface $logger,
		User $user,
		array $noConvertTemplates = [],
		$headerSuffix = null
	) {
		$this->parser = $parser;
		$this->sourceStore = $sourceStore;
		$this->logger = $logger;
		$this->user = $user;
		$this->noConvertTemplates = $noConvertTemplates;
		$this->headerSuffix = $headerSuffix;

		$archiveFormat = wfMessage( 'flow-conversion-archive-page-name-format' )->inContentLanguage()->plain();
		if ( strpos( $archiveFormat, "\n" ) === false ) {
			$this->archiveTitleSuggestions = [ $archiveFormat ];
		} else {
			$this->archiveTitleSuggestions = explode( "\n", $archiveFormat );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getSourceStore() {
		return $this->sourceStore;
	}

	/**
	 * @inheritDoc
	 */
	public function getMoveComment( Title $from, Title $to ) {
		return wfMessage( 'flow-talk-conversion-move-reason', $from->getPrefixedText() )->plain();
	}

	/**
	 * @inheritDoc
	 */
	public function getCleanupComment( Title $from, Title $to ) {
		return wfMessage( 'flow-talk-conversion-archive-edit-reason' )->plain();
	}

	/**
	 * @inheritDoc
	 */
	public function isConversionFinished( Title $title, Title $movedFrom = null ) {
		if ( $title->getContentModel() === CONTENT_MODEL_FLOW_BOARD ) {
			// page is a flow board already
			return true;
		} elseif ( $movedFrom ) {
			// page was moved out of the way by import - leave it alone
			return true;
		} else {
			return false;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function createImportSource( Title $title ) {
		return new ImportSource( $title, $this->parser, $this->user, $this->headerSuffix );
	}

	/**
	 * @inheritDoc
	 */
	public function decideArchiveTitle( Title $source ) {
		$archiveNameHelper = new ArchiveNameHelper();
		return $archiveNameHelper->decideArchiveTitle( $source, $this->archiveTitleSuggestions );
	}

	/**
	 * @inheritDoc
	 */
	public function getPostprocessor() {
		return null;
	}

	/**
	 * @inheritDoc
	 */
	public function createArchiveCleanupRevisionContent( WikitextContent $content, Title $title ) {
		$now = new DateTime( "now", new DateTimeZone( "GMT" ) );
		$arguments = implode( '|', [
			'from=' . $title->getPrefixedText(),
			'date=' . $now->format( 'Y-m-d' ),
		] );

		$template = wfMessage( 'flow-importer-wt-converted-archive-template' )->inContentLanguage()->plain();
		$newWikitext = "{{{$template}|$arguments}}" . "\n\n" . $content->getText();

		return new WikitextContent( $newWikitext );
	}

	// Public only for unit testing

	/**
	 * Checks whether it meets the applicable subpage rules.  Meant to be overriden by
	 * subclasses that do not have the same requirements
	 *
	 * @param Title $sourceTitle Title to check
	 * @return bool Whether it meets the applicable subpage requirements
	 */
	public function meetsSubpageRequirements( Title $sourceTitle ) {
		// Don't allow conversion of sub pages unless it is
		// a talk page with matching subject page. For example
		// we will convert User_talk:Foo/bar only if User:Foo/bar
		// exists, and we will never convert User:Baz/bang.
		if ( $sourceTitle->isSubpage() &&
			( !$sourceTitle->isTalkPage() || !$sourceTitle->getSubjectPage()->exists() )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Check whether the given title has one of the templates that should protect it from
	 * being converted.
	 * @param Title $sourceTitle Title to check
	 * @return bool Whether the title has such a template
	 */
	protected function hasNoConvertTemplate( Title $sourceTitle ) {
		if ( count( $this->noConvertTemplates ) === 0 ) {
			return false;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$batch = new LinkBatch( $this->noConvertTemplates );
		$result = $dbr->select(
			'templatelinks',
			'tl_from',
			[
				'tl_from' => $sourceTitle->getArticleID(),
				$batch->constructSet( 'tl', $dbr )
			],
			__METHOD__,
			[ 'LIMIT' => 1 ]
		);
		return $dbr->numRows( $result ) > 0;
	}

	/**
	 * @inheritDoc
	 */
	public function shouldConvert( Title $sourceTitle ) {
		// If we have LiquidThreads filter out any pages with that enabled.  They should
		// be converted separately.
		if ( ExtensionRegistry::getInstance()->isLoaded( 'Liquid Threads' ) ) {
			if ( \LqtDispatch::isLqtPage( $sourceTitle ) ) {
				$this->logger->info( "Skipping LQT enabled page, conversion must be done with " .
					"convertLqtPagesWithProp.php or convertLqtPageOnLocalWiki.php: $sourceTitle" );
				return false;
			}
		}

		if ( !$this->meetsSubpageRequirements( $sourceTitle ) ||
			$this->hasNoConvertTemplate( $sourceTitle )
		) {
			return false;
		}

		return true;
	}
}
