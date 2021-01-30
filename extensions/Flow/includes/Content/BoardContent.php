<?php

namespace Flow\Content;

use Content;
use DerivativeContext;
use FauxRequest;
use Flow\Container;
use Flow\LinksTableUpdater;
use Flow\Model\UUID;
use Flow\View;
use Flow\WorkflowLoaderFactory;
use Hooks;
use MediaWiki\MediaWikiServices;
use OutputPage;
use ParserOptions;
use ParserOutput;
use RequestContext;
use Title;
use User;
use WikiPage;

class BoardContent extends \AbstractContent {
	/** @var UUID|null */
	protected $workflowId;

	public function __construct( $contentModel = CONTENT_MODEL_FLOW_BOARD, UUID $workflowId = null ) {
		parent::__construct( $contentModel );
		$this->workflowId = $workflowId;
	}

	/**
	 * @since 1.21
	 *
	 * @return string A string representing the content in a way useful for
	 *   building a full text search index. If no useful representation exists,
	 *   this method returns an empty string.
	 *
	 * @todo Test that this actually works
	 * @todo Make sure this also works with LuceneSearch / WikiSearch
	 */
	public function getTextForSearchIndex() {
		return '';
	}

	/**
	 * @since 1.21
	 *
	 * @return string The wikitext to include when another page includes this
	 * content, or false if the content is not includable in a wikitext page.
	 *
	 * @todo Allow native handling, bypassing wikitext representation, like
	 *  for includable special pages.
	 * @todo Allow transclusion into other content models than Wikitext!
	 * @todo Used in WikiPage and MessageCache to get message text. Not so
	 *  nice. What should we use instead?!
	 */
	public function getWikitextForTransclusion() {
		return '<span class="error">' . wfMessage( 'flow-embedding-unsupported' )->plain() . '</span>';
	}

	/**
	 * Returns a textual representation of the content suitable for use in edit
	 * summaries and log messages.
	 *
	 * @since 1.21
	 *
	 * @param int $maxLength Maximum length of the summary text.
	 *
	 * @return string The summary text.
	 */
	public function getTextForSummary( $maxLength = 250 ) {
		return '[Flow board ' . $this->getWorkflowId()->getAlphadecimal() . ']';
	}

	/**
	 * Returns native representation of the data. Interpretation depends on
	 * the data model used, as given by getDataModel().
	 *
	 * @since 1.21
	 *
	 * @return UUID|null The native representation of the content. Could be a
	 *    string, a nested array structure, an object, a binary blob...
	 *    anything, really.
	 *
	 * @note Caller must be aware of content model!
	 */
	public function getNativeData() {
		return $this->getWorkflowId();
	}

	/**
	 * Returns the content's nominal size in bogo-bytes.
	 *
	 * @return int
	 */
	public function getSize() {
		return 1;
	}

	/**
	 * Return a copy of this Content object. The following must be true for the
	 * object returned:
	 *
	 * if $copy = $original->copy()
	 *
	 * - get_class($original) === get_class($copy)
	 * - $original->getModel() === $copy->getModel()
	 * - $original->equals( $copy )
	 *
	 * If and only if the Content object is immutable, the copy() method can and
	 * should return $this. That is, $copy === $original may be true, but only
	 * for immutable content objects.
	 *
	 * @since 1.21
	 *
	 * @return Content A copy of this object
	 */
	public function copy() {
		return $this;
	}

	/**
	 * Returns true if this content is countable as a "real" wiki page, provided
	 * that it's also in a countable location (e.g. a current revision in the
	 * main namespace).
	 *
	 * @since 1.21
	 *
	 * @param bool|null $hasLinks If it is known whether this content contains
	 *    links, provide this information here, to avoid redundant parsing to
	 *    find out.
	 *
	 * @return bool
	 */
	public function isCountable( $hasLinks = null ) {
		return true;
	}

	/**
	 * Parse the Content object and generate a ParserOutput from the result.
	 * $result->getText() can be used to obtain the generated HTML. If no HTML
	 * is needed, $generateHtml can be set to false; in that case,
	 * $result->getText() may return null.
	 *
	 * @note To control which options are used in the cache key for the
	 *       generated parser output, implementations of this method
	 *       may call ParserOutput::recordOption() on the output object.
	 *
	 * @param Title $title The page title to use as a context for rendering.
	 * @param int|null $revId Optional revision ID being rendered.
	 * @param ParserOptions|null $options Any parser options.
	 * @param bool $generateHtml Whether to generate HTML (default: true). If false,
	 *        the result of calling getText() on the ParserOutput object returned by
	 *        this method is undefined.
	 *
	 * @since 1.21
	 *
	 * @return ParserOutput
	 */
	public function getParserOutput(
		Title $title,
		$revId = null,
		ParserOptions $options = null,
		$generateHtml = true
	) {
		// TODO: This should also call the "ContentGetParserOutput" hook
		if ( $generateHtml ) {
			try {
				global $wgUser;
				$user = $options ? $options->getUser() : $wgUser;
				$parserOutput = $this->generateHtml( $title, $user );
			} catch ( \Exception $e ) {
				// Workflow does not yet exist (may be in the process of being created)
				$parserOutput = new ParserOutput();
			}
		} else {
			$parserOutput = new ParserOutput();
		}

		$parserOutput->updateCacheExpiry( 0 );

		if ( $revId === null ) {
			$wikiPage = WikiPage::factory( $title );
			$timestamp = $wikiPage->getTimestamp();
		} else {
			$timestamp = MediaWikiServices::getInstance()->getRevisionLookup()
				->getTimestampFromId( $revId );
		}

		$parserOutput->setTimestamp( $timestamp );

		/** @var LinksTableUpdater $updater */
		$updater = Container::get( 'reference.updater.links-tables' );
		$updater->mutateParserOutput( $title, $parserOutput );

		Hooks::run( 'ContentAlterParserOutput', [ $this, $title, $parserOutput ] );

		return $parserOutput;
	}

	/**
	 * @param Title $title
	 * @param User $user
	 * @return ParserOutput
	 */
	protected function generateHtml( Title $title, User $user ) {
		// Set up a derivative context (which inherits the current request)
		// to hold the output modules + text
		$childContext = new DerivativeContext( RequestContext::getMain() );
		$childContext->setOutput( new OutputPage( $childContext ) );
		$childContext->setRequest( new FauxRequest );
		$childContext->setUser( $user );

		// Create a View set up to output to our derivative context
		$view = new View(
			Container::get( 'url_generator' ),
			Container::get( 'lightncandy' ),
			$childContext->getOutput(),
			Container::get( 'flow_actions' )
		);

		$loader = $this->getWorkflowLoader( $title );
		$view->show( $loader, 'view' );

		// Extract data from derivative context
		$parserOutput = new ParserOutput();
		$parserOutput->setText( $childContext->getOutput()->getHTML() );
		$parserOutput->addModules( $childContext->getOutput()->getModules() );
		$parserOutput->addModuleStyles( $childContext->getOutput()->getModuleStyles() );

		return $parserOutput;
	}

	/**
	 * @param Title $title
	 * @return \Flow\WorkflowLoader
	 * @throws \Flow\Exception\CrossWikiException
	 * @throws \Flow\Exception\InvalidInputException
	 */
	protected function getWorkflowLoader( Title $title ) {
		/** @var WorkflowLoaderFactory $factory */
		$factory = Container::get( 'factory.loader.workflow' );
		return $factory->createWorkflowLoader( $title, $this->getWorkflowId() );
	}

	/**
	 * @return UUID|null
	 */
	public function getWorkflowId() {
		return $this->workflowId;
	}
}
