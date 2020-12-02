<?php

namespace TextExtracts;

use ApiBase;
use ApiMain;
use ApiQueryBase;
use ApiUsageException;
use Config;
use FauxRequest;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use ParserOptions;
use Title;
use User;
use WANObjectCache;
use WikiPage;

/**
 * @license GPL-2.0-or-later
 */
class ApiQueryExtracts extends ApiQueryBase {

	/**
	 * Bump when memcache needs clearing
	 */
	private const CACHE_VERSION = 2;

	private const PREFIX = 'ex';

	private $params;
	/**
	 * @var Config
	 */
	private $config;
	/**
	 * @var WANObjectCache
	 */
	private $cache;

	// TODO: Allow extensions to hook into this to opt-in.
	// This is partly for security reasons; see T107170.
	/**
	 * @var array
	 */
	private $supportedContentModels = [ 'wikitext' ];

	/**
	 * @param \ApiQuery $query API query module object
	 * @param string $moduleName Name of this query module
	 * @param Config $conf MediaWiki configuration
	 * @param WANObjectCache $cache
	 */
	public function __construct( $query, $moduleName, Config $conf, WANObjectCache $cache ) {
		parent::__construct( $query, $moduleName, self::PREFIX );
		$this->config = $conf;
		$this->cache = $cache;
	}

	/**
	 * Evaluates the parameters, performs the requested extraction of text,
	 * and sets up the result
	 * @return null
	 */
	public function execute() {
		$titles = $this->getPageSet()->getGoodTitles();
		if ( $titles === [] ) {
			return;
		}
		$isXml = $this->getMain()->isInternalMode()
			|| $this->getMain()->getPrinter()->getFormat() == 'XML';
		$result = $this->getResult();
		$params = $this->params = $this->extractRequestParams();
		$this->requireMaxOneParameter( $params, 'chars', 'sentences' );
		$continue = 0;
		$limit = intval( $params['limit'] );
		if ( $limit > 1 && !$params['intro'] && count( $titles ) > 1 ) {
			$limit = 1;
			$this->addWarning( [ 'apiwarn-textextracts-limit', $limit ] );
		}
		if ( isset( $params['continue'] ) ) {
			$continue = intval( $params['continue'] );
			$this->dieContinueUsageIf( $continue < 0 || $continue > count( $titles ) );
			$titles = array_slice( $titles, $continue, null, true );
		}
		$count = 0;
		$titleInFileNamespace = false;
		/** @var Title $t */
		foreach ( $titles as $id => $t ) {
			if ( ++$count > $limit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}

			if ( $t->inNamespace( NS_FILE ) ) {
				$text = '';
				$titleInFileNamespace = true;
			} else {
				$params = $this->params;
				$text = $this->getExtract( $t );
				$text = $this->truncate( $text );
				if ( $params['plaintext'] ) {
					$text = $this->doSections( $text );
				} else {
					if ( $params['sentences'] ) {
						$this->addWarning( $this->msg( 'apiwarn-textextracts-sentences-and-html', self::PREFIX ) );
					}
					$this->addWarning( 'apiwarn-textextracts-malformed-html' );
				}
			}

			if ( $isXml ) {
				$fit = $result->addValue( [ 'query', 'pages', $id ], 'extract', [ '*' => $text ] );
			} else {
				$fit = $result->addValue( [ 'query', 'pages', $id ], 'extract', $text );
			}
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $continue + $count - 1 );
				break;
			}
		}
		if ( $titleInFileNamespace ) {
			$this->addWarning( 'apiwarn-textextracts-title-in-file-namespace' );
		}
	}

	/**
	 * @param array $params Ignored parameters
	 * @return string
	 */
	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * Returns a processed, but not trimmed extract
	 * @param Title $title
	 * @return string
	 */
	private function getExtract( Title $title ) {
		$contentModel = $title->getContentModel();
		if ( !in_array( $contentModel, $this->supportedContentModels, true ) ) {
			$this->addWarning( [
				'apiwarn-textextracts-unsupportedmodel',
				wfEscapeWikiText( $title->getPrefixedText() ),
				$contentModel
			] );
			return '';
		}

		$page = WikiPage::factory( $title );

		$introOnly = $this->params['intro'];
		$text = $this->getFromCache( $page, $introOnly );
		// if we need just first section, try retrieving full page and getting first section out of it
		if ( $text === false && $introOnly ) {
			$text = $this->getFromCache( $page, false );
			if ( $text !== false ) {
				$text = $this->getFirstSection( $text, $this->params['plaintext'] );
			}
		}
		if ( $text === false ) {
			$text = $this->parse( $page );
			$text = $this->convertText( $text );
			$this->setCache( $page, $text );
		}
		return $text;
	}

	private function cacheKey( WANObjectCache $cache, WikiPage $page, $introOnly ) {
		return $cache->makeKey( 'textextracts', self::CACHE_VERSION,
			$page->getId(), $page->getTouched(),
			$page->getTitle()->getPageLanguage()->getPreferredVariant(),
			$this->params['plaintext'] ? 'plaintext' : 'html',
			$introOnly ? 'intro' : 'full'
		);
	}

	private function getFromCache( WikiPage $page, $introOnly ) {
		$cache = $this->cache;
		// @TODO: replace with getWithSetCallback()
		$key = $this->cacheKey( $cache, $page, $introOnly );
		return $cache->get( $key );
	}

	private function setCache( WikiPage $page, $text ) {
		$cache = $this->cache;
		// @TODO: replace with getWithSetCallback()
		$key = $this->cacheKey( $cache, $page, $this->params['intro'] );
		$cache->set( $key, $text, $this->getConfig()->get( 'ParserCacheExpireTime' ) );
	}

	private function getFirstSection( $text, $plainText ) {
		if ( $plainText ) {
			$regexp = '/^(.*?)(?=' . ExtractFormatter::SECTION_MARKER_START . ')/s';
		} else {
			$regexp = '/^(.*?)(?=<h[1-6]\b)/s';
		}
		if ( preg_match( $regexp, $text, $matches ) ) {
			$text = $matches[0];
		}
		return $text;
	}

	/**
	 * Returns page HTML
	 * @param WikiPage $page
	 * @return string|null
	 * @throws ApiUsageException
	 */
	private function parse( WikiPage $page ) {
		$apiException = null;
		$parserOptions = new ParserOptions( new User() );

		// first try finding full page in parser cache
		if ( $page->shouldCheckParserCache( $parserOptions, 0 ) ) {
			$pout = MediaWikiServices::getInstance()->getParserCache()->get( $page, $parserOptions );
			if ( $pout ) {
				$text = $pout->getText( [ 'unwrap' => true ] );
				if ( $this->params['intro'] ) {
					$text = $this->getFirstSection( $text, false );
				}
				return $text;
			}
		}
		$request = [
			'action' => 'parse',
			'page' => $page->getTitle()->getPrefixedText(),
			'prop' => 'text',
			// Invokes special handling when using partial wikitext (T168743)
			'sectionpreview' => 1,
			'wrapoutputclass' => '',
		];
		if ( $this->params['intro'] ) {
			$request['section'] = 0;
		}
		// in case of cache miss, render just the needed section
		$api = new ApiMain( new FauxRequest( $request ) );
		try {
			$api->execute();
			$data = $api->getResult()->getResultData( null, [
				'BC' => [],
				'Types' => [],
			] );
		} catch ( ApiUsageException $e ) {
			$apiException = $e->__toString();
			if ( $e->getStatusValue()->hasMessage( 'apierror-nosuchsection' ) ) {
				// Looks like we tried to get the intro to a page without
				// sections!  Lets just grab what we can get.
				unset( $request['section'] );
				$api = new ApiMain( new FauxRequest( $request ) );
				$api->execute();
				$data = $api->getResult()->getResultData( null, [
					'BC' => [],
					'Types' => [],
				] );
			} else {
				// Some other unexpected error - lets just report it to the user
				// on the off chance that is the right thing.
				throw $e;
			}
		}
		if ( !array_key_exists( 'parse', $data ) ) {
			LoggerFactory::getInstance( 'textextracts' )->warning(
				'API Parse request failed while generating text extract', [
					'title' => $page->getTitle()->getFullText(),
					'url' => $this->getRequest()->getFullRequestURL(),
					'exception' => $apiException,
					'request' => $request
			] );
			return null;
		}

		return $data['parse']['text']['*'];
	}

	/**
	 * @param \ApiQuery $query API query module
	 * @param string $name Name of this query module
	 * @return ApiQueryExtracts
	 */
	public static function factory( $query, $name ) {
		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'textextracts' );
		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		return new self( $query, $name, $config, $cache );
	}

	/**
	 * Converts page HTML into an extract
	 * @param string $text
	 * @return string
	 */
	private function convertText( $text ) {
		$fmt = new ExtractFormatter( $text, $this->params['plaintext'] );
		$fmt->remove( $this->config->get( 'ExtractsRemoveClasses' ) );
		$text = $fmt->getText();
		return $text;
	}

	/**
	 * Truncate the given text to a certain number of characters or sentences
	 * @param string $text The text to truncate
	 * @return string
	 */
	private function truncate( $text ) {
		if ( !$this->params['plaintext'] ) {
			$truncator = new TextTruncator( true );
		} else {
			$truncator = new TextTruncator( false );
		}

		if ( $this->params['chars'] ) {
			$text = $truncator->getFirstChars( $text, $this->params['chars'] ) .
				$this->msg( 'ellipsis' )->text();
		} elseif ( $this->params['sentences'] ) {
			$text = $truncator->getFirstSentences( $text, $this->params['sentences'] );
		}
		return $text;
	}

	private function doSections( $text ) {
		$pattern = '/' .
			ExtractFormatter::SECTION_MARKER_START . '(\d)' .
			ExtractFormatter::SECTION_MARKER_END . '(.*)/';

		switch ( $this->params['sectionformat'] ) {
			case 'raw':
				return $text;

			case 'wiki':
				return preg_replace_callback( $pattern, function ( $matches ) {
					$bars = str_repeat( '=', $matches[1] );
					return "\n$bars " . trim( $matches[2] ) . " $bars";
				}, $text );

			case 'plain':
				return preg_replace_callback( $pattern, function ( $matches ) {
					return "\n" . trim( $matches[2] );
				}, $text );

			default:
				throw new \LogicException( 'Invalid sectionformat' );
		}
	}

	/**
	 * Return an array describing all possible parameters to this module
	 * @return array
	 */
	public function getAllowedParams() {
		return [
			'chars' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 1200,
			],
			'sentences' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 10,
			],
			'limit' => [
				ApiBase::PARAM_DFLT => 20,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => 20,
				ApiBase::PARAM_MAX2 => 20,
			],
			'intro' => false,
			'plaintext' => false,
			'sectionformat' => [
				ApiBase::PARAM_TYPE => [ 'plain', 'wiki', 'raw' ],
				ApiBase::PARAM_DFLT => 'wiki',
			],
			'continue' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
		];
	}

	/**
	 * @see ApiBase::getExamplesMessages()
	 * @return array
	 */
	protected function getExamplesMessages() {
		return [
			'action=query&prop=extracts&exchars=175&titles=Therion'
				=> 'apihelp-query+extracts-example-1',
		];
	}

	/**
	 * @see ApiBase::getHelpUrls()
	 * @return string
	 */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:TextExtracts#API';
	}
}
