<?php
/**
 * Parsoid/RESTBase+MediaWiki API wrapper.
 *
 * @file
 * @ingroup Extensions
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license MIT
 */

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

class ApiVisualEditorEdit extends ApiBase {

	use ApiParsoidTrait;

	const MAX_CACHE_RECENT = 2;
	const MAX_CACHE_TTL = 900;

	/**
	 * @inheritDoc
	 */
	public function __construct( ApiMain $main, $name ) {
		parent::__construct( $main, $name );
		$this->setLogger( LoggerFactory::getInstance( 'VisualEditor' ) );
	}

	/**
	 * Attempt to save a given page's wikitext to MediaWiki's storage layer via its API
	 *
	 * @param Title $title The title of the page to write
	 * @param string $wikitext The wikitext to write
	 * @param array $params The edit parameters
	 * @return mixed The result of the save attempt
	 */
	protected function saveWikitext( Title $title, $wikitext, $params ) {
		$apiParams = [
			'action' => 'edit',
			'title' => $title->getPrefixedDBkey(),
			'text' => $wikitext,
			'summary' => $params['summary'],
			'basetimestamp' => $params['basetimestamp'],
			'starttimestamp' => $params['starttimestamp'],
			'token' => $params['token'],
			'watchlist' => $params['watchlist'],
			'tags' => $params['tags'],
			'section' => $params['section'],
			'sectiontitle' => $params['sectiontitle'],
			'captchaid' => $params['captchaid'],
			'captchaword' => $params['captchaword'],
			'errorformat' => 'html',
		];

		if ( $params['minor'] !== null ) {
			$apiParams['minor'] = true;
		} else {
			$apiParams['notminor'] = true;
		}

		// Pass any unrecognized query parameters to the internal action=edit API request. This is
		// necessary to support extensions that add extra stuff to the edit form (e.g. FlaggedRevs)
		// and allows passing any other query parameters to be used for edit tagging (e.g. T209132).
		// Exclude other known params from here and ApiMain.
		// TODO: This doesn't exclude params from the formatter
		$allParams = $this->getRequest()->getValues();
		$knownParams = array_keys( $this->getAllowedParams() + $this->getMain()->getAllowedParams() );
		foreach ( $knownParams as $knownParam ) {
			unset( $allParams[ $knownParam ] );
		}

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams + $allParams,
				/* was posted? */ true
			),
			/* enable write? */ true
		);

		$api->execute();

		return $api->getResult()->getResultData();
	}

	/**
	 * Load into an array the output of MediaWiki's parser for a given revision
	 *
	 * @param int $newRevId The revision to load
	 * @return array|false The parsed of the save attempt
	 */
	protected function parseWikitext( $newRevId ) {
		$apiParams = [
			'action' => 'parse',
			'oldid' => $newRevId,
			'prop' => 'text|revid|categorieshtml|displaytitle|modules|jsconfigvars',
		];
		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams,
				/* was posted? */ false
			),
			/* enable write? */ true
		);

		$api->execute();
		$result = $api->getResult()->getResultData( null, [
			/* Transform content nodes to '*' */ 'BC' => [],
			/* Add back-compat subelements */ 'Types' => [],
			/* Remove any metadata keys from the links array */ 'Strip' => 'all',
		] );
		$content = $result['parse']['text']['*'] ?? false;
		$categorieshtml = $result['parse']['categorieshtml']['*'] ?? false;
		$displaytitle = $result['parse']['displaytitle'] ?? false;
		$modules = array_merge(
			$result['parse']['modules'] ?? [],
			$result['parse']['modulestyles'] ?? []
		);
		$jsconfigvars = $result['parse']['jsconfigvars'] ?? [];

		if (
			$content === false ||
			// TODO: Is this check still needed?
			( strlen( $content ) && MediaWikiServices::getInstance()
				->getRevisionLookup()
				->getRevisionById( $result['parse']['revid'] ) === null
			)
		) {
			return false;
		}

		if ( $displaytitle !== false ) {
			// Escape entities as in OutputPage::setPageTitle()
			$displaytitle = Sanitizer::normalizeCharReferences(
				Sanitizer::removeHTMLtags( $displaytitle ) );
		}

		return [
			'content' => $content,
			'categorieshtml' => $categorieshtml,
			'displayTitleHtml' => $displaytitle,
			'modules' => $modules,
			'jsconfigvars' => $jsconfigvars
		];
	}

	/**
	 * Create and load the parsed wikitext of an edit, or from the serialisation cache if available.
	 *
	 * @param Title $title The title of the page
	 * @param array $params The edit parameters
	 * @param array $parserParams The parser parameters
	 * @return string The wikitext of the edit
	 */
	protected function getWikitext( Title $title, $params, $parserParams ) {
		if ( $params['cachekey'] !== null ) {
			$wikitext = $this->trySerializationCache( $params['cachekey'] );
			if ( !is_string( $wikitext ) ) {
				$this->dieWithError( 'apierror-visualeditor-badcachekey', 'badcachekey' );
			}
		} else {
			$wikitext = $this->getWikitextNoCache( $title, $params, $parserParams );
		}
		'@phan-var string $wikitext';
		return $wikitext;
	}

	/**
	 * Create and load the parsed wikitext of an edit, ignoring the serialisation cache.
	 *
	 * @param Title $title The title of the page
	 * @param array $params The edit parameters
	 * @param array $parserParams The parser parameters
	 * @return string The wikitext of the edit
	 */
	protected function getWikitextNoCache( Title $title, $params, $parserParams ) {
		$this->requireOnlyOneParameter( $params, 'html' );
		if ( Deflate::isDeflated( $params['html'] ) ) {
			$status = Deflate::inflate( $params['html'] );
			if ( !$status->isGood() ) {
				$this->dieWithError( 'deflate-invaliddeflate', 'invaliddeflate' );
			}
			$html = $status->getValue();
		} else {
			$html = $params['html'];
		}
		$wikitext = $this->postHTML(
			$title, $html, $parserParams, $params['etag']
		);
		return $wikitext;
	}

	/**
	 * Load the parsed wikitext of an edit into the serialisation cache.
	 *
	 * @param Title $title The title of the page
	 * @param string $wikitext The wikitext of the edit
	 * @return string|false The key of the wikitext in the serialisation cache
	 */
	protected function storeInSerializationCache( Title $title, $wikitext ) {
		if ( $wikitext === false ) {
			return false;
		}

		$cache = ObjectCache::getLocalClusterInstance();

		$services = MediaWikiServices::getInstance();
		$statsd = $services->getStatsdDataFactory();
		$editStash = $services->getPageEditStash();

		// Store the corresponding wikitext, referenceable by a new key
		$hash = md5( $wikitext );
		$key = $cache->makeKey( 'visualeditor', 'serialization', $hash );
		$ok = $cache->set( $key, $wikitext, self::MAX_CACHE_TTL );
		if ( $ok ) {
			$this->pruneExcessStashedEntries( $cache, $this->getUser(), $key );
		}

		$status = $ok ? 'ok' : 'failed';
		$statsd->increment( "editstash.ve_serialization_cache.set_" . $status );

		// Also parse and prepare the edit in case it might be saved later
		$page = WikiPage::factory( $title );
		$content = ContentHandler::makeContent( $wikitext, $title, CONTENT_MODEL_WIKITEXT );

		$status = $editStash->parseAndCache( $page, $content, $this->getUser(), '' );
		if ( $status === $editStash::ERROR_NONE ) {
			$logger = LoggerFactory::getInstance( 'StashEdit' );
			$logger->debug( "Cached parser output for VE content key '$key'." );
		}
		$statsd->increment( "editstash.ve_cache_stores.$status" );

		return $hash;
	}

	/**
	 * @param BagOStuff $cache
	 * @param User $user
	 * @param string $newKey
	 */
	private function pruneExcessStashedEntries( BagOStuff $cache, User $user, $newKey ) {
		$key = $cache->makeKey( 'visualeditor-serialization-recent', $user->getName() );

		$keyList = $cache->get( $key ) ?: [];
		if ( count( $keyList ) >= self::MAX_CACHE_RECENT ) {
			$oldestKey = array_shift( $keyList );
			$cache->delete( $oldestKey );
		}

		$keyList[] = $newKey;
		$cache->set( $key, $keyList, 2 * self::MAX_CACHE_TTL );
	}

	/**
	 * Load some parsed wikitext of an edit from the serialisation cache.
	 *
	 * @param string $hash The key of the wikitext in the serialisation cache
	 * @return string|null The wikitext
	 */
	protected function trySerializationCache( $hash ) {
		$cache = ObjectCache::getLocalClusterInstance();
		$key = $cache->makeKey( 'visualeditor', 'serialization', $hash );
		$value = $cache->get( $key );

		$status = ( $value !== false ) ? 'hit' : 'miss';
		$statsd = MediaWikiServices::getInstance()->getStatsdDataFactory();
		$statsd->increment( "editstash.ve_serialization_cache.get_$status" );

		return $value;
	}

	/**
	 * Calculate the different between the wikitext of an edit and an existing revision.
	 *
	 * @param Title $title The title of the page
	 * @param int $fromId The existing revision of the page to compare with
	 * @param string $wikitext The wikitext to compare against
	 * @param int|null $section Whether the wikitext refers to a given section or the whole page
	 * @return array The comparison, or `[ 'result' => 'nochanges' ]` if there are none
	 */
	protected function diffWikitext( Title $title, $fromId, $wikitext, $section = null ) {
		$apiParams = [
			'action' => 'compare',
			'prop' => 'diff',
			'fromtitle' => $title->getPrefixedDBkey(),
			'fromrev' => $fromId,
			'fromsection' => $section,
			'totext' => $wikitext,
			'topst' => true,
		];

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams,
				/* was posted? */ false
			),
			/* enable write? */ false
		);
		$api->execute();
		$result = $api->getResult()->getResultData( null, [
			/* Transform content nodes to '*' */ 'BC' => [],
			/* Add back-compat subelements */ 'Types' => [],
		] );

		if ( !isset( $result['compare']['*'] ) ) {
			$this->dieWithError( 'apierror-visualeditor-difffailed', 'difffailed' );
		}
		$diffRows = $result['compare']['*'];

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $title );
		$engine = new DifferenceEngine( $context );
		return [
			'result' => 'success',
			'diff' => $diffRows ? $engine->addHeader(
				$diffRows,
				$context->msg( 'currentrev' )->parse(),
				$context->msg( 'yourtext' )->parse()
			) : ''
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$user = $this->getUser();
		$params = $this->extractRequestParams();
		$title = Title::newFromText( $params['page'] );
		if ( $title && $title->isSpecial( 'CollabPad' ) ) {
			// Convert Special:CollabPad/MyPage to MyPage so we can serialize properly
			$title = SpecialCollabPad::getSubPage( $title );
		}
		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ] );
		}
		'@phan-var Title $title';

		$parserParams = [];
		if ( isset( $params['oldid'] ) ) {
			$parserParams['oldid'] = $params['oldid'];
		}

		if ( isset( $params['wikitext'] ) ) {
			$wikitext = str_replace( "\r\n", "\n", $params['wikitext'] );
		} else {
			$wikitext = $this->getWikitext( $title, $params, $parserParams );
		}

		if ( $params['paction'] === 'serialize' ) {
			$result = [ 'result' => 'success', 'content' => $wikitext ];
		} elseif ( $params['paction'] === 'serializeforcache' ) {
			$key = $this->storeInSerializationCache(
				$title,
				$wikitext
			);
			$result = [ 'result' => 'success', 'cachekey' => $key ];
		} elseif ( $params['paction'] === 'diff' ) {
			$section = $params['section'] ?? null;
			$result = $this->diffWikitext( $title, $params['oldid'], $wikitext, $section );
		} elseif ( $params['paction'] === 'save' ) {
			$saveresult = $this->saveWikitext( $title, $wikitext, $params );
			$editStatus = $saveresult['edit']['result'];

			// Error
			if ( $editStatus !== 'Success' ) {
				$result = [
					'result' => 'error',
					'edit' => $saveresult['edit']
				];

			// Success
			} else {
				if ( isset( $saveresult['edit']['newrevid'] ) ) {
					$newRevId = intval( $saveresult['edit']['newrevid'] );
				} else {
					$newRevId = $title->getLatestRevId();
				}

				// Return result of parseWikitext instead of saveWikitext so that the
				// frontend can update the page rendering without a refresh.
				$result = $this->parseWikitext( $newRevId );
				if ( $result === false ) {
					$this->dieWithError( 'apierror-visualeditor-docserver', 'docserver' );
				}

				$result['isRedirect'] = (string)$title->isRedirect();

				if ( ExtensionRegistry::getInstance()->isLoaded( 'FlaggedRevs' ) ) {
					$view = FlaggablePageView::singleton();

					$originalContext = $view->getContext();
					$originalTitle = RequestContext::getMain()->getTitle();

					$newContext = new DerivativeContext( $originalContext );
					// Defeat !$this->isPageView( $request ) || $request->getVal( 'oldid' ) check in setPageContent
					$newRequest = new DerivativeRequest(
						$this->getRequest(),
						[
							'diff' => null,
							'oldid' => '',
							'title' => $title->getPrefixedText(),
							'action' => 'view'
						] + $this->getRequest()->getValues()
					);
					$newContext->setRequest( $newRequest );
					$newContext->setTitle( $title );
					$view->setContext( $newContext );
					RequestContext::getMain()->setTitle( $title );

					// The two parameters here are references but we don't care
					// about what FlaggedRevs does with them.
					$outputDone = null;
					$useParserCache = null;
					// @phan-suppress-next-line PhanTypeMismatchArgument
					$view->setPageContent( $outputDone, $useParserCache );
					$view->displayTag();
					$view->setContext( $originalContext );
					RequestContext::getMain()->setTitle( $originalTitle );
				}

				$context = new RequestContext;
				$context->setTitle( $title );
				$tempOut = new OutputPage( $context );
				$tempOut->setArticleFlag( true );

				$subpagestr = $this->getSkin()->subPageSubtitle( $tempOut );
				if ( $subpagestr !== '' ) {
					$subpagestr = '<span class="subpages">' . $subpagestr . '</span>';
				}
				$result['contentSub'] = $subpagestr . $this->getOutput()->getSubtitle();

				$lang = $this->getLanguage();

				if ( isset( $saveresult['edit']['newtimestamp'] ) ) {
					$ts = $saveresult['edit']['newtimestamp'];

					$result['lastModified'] = [
						'date' => $lang->userDate( $ts, $user ),
						'time' => $lang->userTime( $ts, $user )
					];
				}

				if ( isset( $saveresult['edit']['newrevid'] ) ) {
					$result['newrevid'] = intval( $saveresult['edit']['newrevid'] );
				}

				$result['watched'] = $saveresult['edit']['watched'] ?? false;
				$result['watchlistexpiry'] = $saveresult['edit']['watchlistexpiry'] ?? null;
				$result['result'] = 'success';
			}
		}

		// @phan-suppress-next-line PhanPossiblyUndeclaredVariable False positive
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'paction' => [
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_TYPE => [
					'serialize',
					'serializeforcache',
					'diff',
					'save',
				],
			],
			'page' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'token' => [
				ApiBase::PARAM_REQUIRED => true,
			],
			'wikitext' => [
				ApiBase::PARAM_TYPE => 'text',
				ApiBase::PARAM_DFLT => null,
			],
			'section' => null,
			'sectiontitle' => null,
			'basetimestamp' => null,
			'starttimestamp' => null,
			'oldid' => null,
			'minor' => null,
			'watchlist' => null,
			'html' => [
				ApiBase::PARAM_TYPE => 'text',
				ApiBase::PARAM_DFLT => null,
			],
			'etag' => null,
			'summary' => null,
			'captchaid' => null,
			'captchaword' => null,
			'cachekey' => null,
			'tags' => [
				ApiBase::PARAM_ISMULTI => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
	}

	/**
	 * @inheritDoc
	 */
	public function mustBePosted() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return true;
	}
}
