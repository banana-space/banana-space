<?php

namespace Flow\Import\LiquidThreadsApi;

use Flow\Import\ArchiveNameHelper;
use Flow\Import\IConversionStrategy;
use Flow\Import\Postprocessor\LqtNotifications;
use Flow\Import\Postprocessor\LqtRedirector;
use Flow\Import\Postprocessor\ProcessorGroup;
use Flow\Import\SourceStore\SourceStoreInterface;
use Flow\Notifications\Controller;
use Flow\UrlGenerator;
use LqtDispatch;
use MediaWiki\MediaWikiServices;
use MWTimestamp;
use Title;
use User;
use Wikimedia\Rdbms\IDatabase;
use WikitextContent;

/**
 * Converts LiquidThreads pages on a wiki to Flow. This converter is idempotent
 * when used with an appropriate SourceStoreInterface, and may be run many times
 * without worry for duplicate imports.
 *
 * Pages with the LQT magic word will be moved to a subpage of their original location
 * named 'LQT Archive N' with N increasing starting at 1 looking for the first empty page.
 * On successful import of an entire page the LQT magic word will be stripped from the
 * archive version of the page.
 */
class ConversionStrategy implements IConversionStrategy {
	/**
	 * @var IDatabase Master database for the current wiki
	 */
	protected $dbw;

	/**
	 * @var SourceStoreInterface
	 */
	protected $sourceStore;

	/**
	 * @var ApiBackend
	 */
	public $api;

	/**
	 * @var UrlGenerator
	 */
	protected $urlGenerator;

	/**
	 * @var User
	 */
	protected $talkpageUser;

	/**
	 * @var Controller
	 */
	protected $notificationController;

	public function __construct(
		IDatabase $dbw,
		SourceStoreInterface $sourceStore,
		ApiBackend $api,
		UrlGenerator $urlGenerator,
		User $talkpageUser,
		Controller $notificationController
	) {
		$this->dbw = $dbw;
		$this->sourceStore = $sourceStore;
		$this->api = $api;
		$this->urlGenerator = $urlGenerator;
		$this->talkpageUser = $talkpageUser;
		$this->notificationController = $notificationController;
	}

	public function getSourceStore() {
		return $this->sourceStore;
	}

	public function getMoveComment( Title $from, Title $to ) {
		return "Conversion of LQT to Flow from: {$from->getPrefixedText()}";
	}

	public function getCleanupComment( Title $from, Title $to ) {
		return "LQT to Flow conversion";
	}

	public function isConversionFinished( Title $title, Title $movedFrom = null ) {
		if ( LqtDispatch::isLqtPage( $title ) ) {
			return false;
		} else {
			return true;
		}
	}

	public function createImportSource( Title $title ) {
		return new ImportSource( $this->api, $title->getPrefixedText(), $this->talkpageUser );
	}

	/**
	 * Flow does not support viewing the history of the wikitext pages it takes
	 * over, so those need to be moved out the way. This method decides that
	 * destination. The archived revisions include the headers displayed with
	 * lqt and potentially any pre-lqt wikitext talk page content.
	 *
	 * @param Title $source
	 * @return Title
	 */
	public function decideArchiveTitle( Title $source ) {
		$archiveNameHelper = new ArchiveNameHelper();
		return $archiveNameHelper->decideArchiveTitle( $source, [
			'%s/LQT Archive %d',
		] );
	}

	/**
	 * Creates a new revision that ensures the LQT magic word is there and turning LQT off.
	 * It also adds a template about the move.
	 * effectively no longer be LQT pages.
	 *
	 * @param WikitextContent $content
	 * @param Title $title
	 * @return WikitextContent
	 */
	public function createArchiveCleanupRevisionContent( WikitextContent $content, Title $title ) {
		// cleanup existing text
		$existing = $content->getText();
		$existing = self::removeLqtMagicWord( $existing );
		$existing = $this->removePrefixText( $existing );

		// prefix the existing text with some additional info related to the conversion
		$text = $this->getPrefixText( $content, $title ) . "\n\n";
		$text .= self::getDisableLqtMagicWord() . "\n\n";
		$text .= $existing;

		return new WikitextContent( $text );
	}

	public function getPostprocessor() {
		$group = new ProcessorGroup;
		$group->add( new LqtRedirector( $this->urlGenerator, $this->talkpageUser ) );
		$group->add( new LqtNotifications( $this->notificationController, $this->dbw ) );

		return $group;
	}

	/**
	 * @inheritDoc
	 */
	public function shouldConvert( Title $sourceTitle ) {
		// The expensive part of this (user-override checking) is cached by LQT.
		return LqtDispatch::isLqtPage( $sourceTitle );
	}

	/**
	 * Gets rid of any "This page is an archived page..." prefix that may have
	 * been added in an earlier conversion run.
	 *
	 * @param string $content
	 * @return string
	 */
	protected function removePrefixText( $content ) {
		$template = wfMessage( 'flow-importer-lqt-converted-archive-template' )->inContentLanguage()->plain();
		return preg_replace( "{{{$template}\\|[^\\}]+}}", '', $content );
	}

	/**
	 * Generates a "This page is an archived page..." text to add to the
	 * existing content.
	 *
	 * @param WikitextContent $content
	 * @param Title $title
	 * @return string
	 */
	protected function getPrefixText( WikitextContent $content, Title $title ) {
		$arguments = implode( '|', [
			'from=' . $title->getPrefixedText(),
			'date=' . MWTimestamp::getInstance()->timestamp->format( 'Y-m-d' ),
		] );

		$template = wfMessage( 'flow-importer-lqt-converted-archive-template' )->inContentLanguage()->plain();

		return "{{{$template}|$arguments}}\n\n" . self::getDisableLqtMagicWord() . "\n\n";
	}

	/**
	 * Remove the LQT magic word or its localized version
	 * @param string $content
	 * @return string
	 */
	public static function removeLqtMagicWord( $content ) {
		$magicWord = MediaWikiServices::getInstance()->getMagicWordFactory()->
			get( 'useliquidthreads' );
		$patterns = array_map(
			// delete any status: enabled or disabled doesn't matter (we're
			// adding disabled magic word anyway and having it twice is messy)
			function ( $word ) {
				return '/{{\\s*#' . preg_quote( $word ) . ':\\s*[01]*\\s*}}/i';
			},
			[ 'useliquidthreads' ] + $magicWord->getSynonyms() );

		return preg_replace( $patterns, '', $content );
	}

	/**
	 * @return string The localized magic word to disable LQT on a page
	 */
	public static function getDisableLqtMagicWord() {
		$wordObj = MediaWikiServices::getInstance()->getMagicWordFactory()->
			get( 'useliquidthreads' );
		$magicWord = strtolower( $wordObj->getSynonym( 0 ) );
		return "{{#$magicWord:0}}";
	}
}
