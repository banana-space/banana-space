<?php

namespace CirrusSearch\Maintenance;

use CirrusSearch\CirrusDebugOptions;
use CirrusSearch\CirrusSearch;
use CirrusSearch\HashSearchConfig;
use CirrusSearch\Search\CirrusSearchResultSet;
use CirrusSearch\SearchConfig;
use MediaWiki\MediaWikiServices;
use OrderedStreamingForkController;
use PageArchive;
use SearchSuggestionSet;
use Status;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Run search queries provided on stdin
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

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";
require_once __DIR__ . '/../includes/Maintenance/Maintenance.php';

class RunSearch extends Maintenance {

	/**
	 * @var string
	 */
	protected $indexBaseName;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Run one or more searches against the specified cluster. ' .
			'search queries are read from stdin.' );
		$this->addOption( 'baseName', 'What basename to use for all indexes, ' .
			'defaults to wiki id', false, true );
		$this->addOption( 'type', 'What type of search to run, prefix, suggest, archive or full_text. ' .
			'defaults to full_text.', false, true );
		$this->addOption( 'options', 'A JSON object mapping from global variable to ' .
			'its test value', false, true );
		$this->addOption( 'fork', 'Fork multiple processes to run queries from.' .
			'defaults to false.', false, true );
		$this->addOption( 'decode', 'urldecode() queries before running them', false, false );
		$this->addOption( 'explain', 'Include lucene explanation in the results', false, false );
		$this->addOption( 'limit', 'Set the max number of results returned by query (defaults to 10)', false, true );
	}

	public function finalSetup() {
		parent::finalSetup();
		$this->applyGlobals();
	}

	public function execute() {
		$this->disablePoolCountersAndLogging();
		$this->indexBaseName = $this->getOption( 'baseName', $this->getSearchConfig()->get( SearchConfig::INDEX_BASE_NAME ) );

		$callback = [ $this, 'consume' ];
		$forks = $this->getOption( 'fork', false );
		$forks = ctype_digit( $forks ) ? (int)$forks : 0;
		$controller = new OrderedStreamingForkController( $forks, $callback, STDIN, STDOUT );
		$controller->start();

		return true;
	}

	/**
	 * To keep life sane this shouldn't be able to set completely arbitrary configuration, only
	 * the options that change search ranking.  CirrusSearch has so many variables that enumerating
	 * them and maintaining extra lists of them would be a tedious process.
	 *
	 * @return array Changeable global variables represented as the keys for an array, for
	 *  use with isset().
	 */
	private function loadGlobalsWhitelist(): array {
		// WARNING: The autoloader isn't available yet, you can't use any mw/cirrus classes
		$config = json_decode( file_get_contents( __DIR__ . '/../extension.json' ), true );
		if ( !is_array( $config ) ) {
			throw new \RuntimeException( 'Could not load extension.json for whitelist' );
		}
		$whitelist = [];
		foreach ( array_keys( $config['config'] ) as $key ) {
			$whitelist['wg' . $key] = true;
		}
		return $whitelist;
	}

	/**
	 * Applies global variables provided as the options CLI argument
	 * to override current settings.
	 */
	protected function applyGlobals() {
		$optionsData = $this->getOption( 'options', 'false' );
		if ( substr_compare( $optionsData, 'B64://', 0, strlen( 'B64://' ) ) === 0 ) {
			$optionsData = base64_decode( substr( $optionsData, strlen( 'B64://' ) ) );
		}
		$options = json_decode( $optionsData, true );
		$whitelist = $this->loadGlobalsWhitelist();

		if ( $options ) {
			foreach ( $options as $key => $value ) {
				if ( strpos( $key, '.' ) !== false ) {
					// key path
					$path = explode( '.', $key );
					if ( !isset( $whitelist[$path[0]] ) ) {
						$this->error( "\nERROR: $key is not a whitelisted global variable\n" );
					}

					$cur =& $GLOBALS;
					foreach ( $path as $pathel ) {
						if ( !array_key_exists( $pathel, $cur ) ) {
							$this->error( "\nERROR: $key is not a valid global variable path\n" );
							exit();
						}
						$cur =& $cur[$pathel];
					}
					$cur = $value;
				} elseif ( isset( $whitelist[$key] ) ) {
					// This is different from the keypath case above in that this can set
					// variables that haven't been loaded yet. In particular at this point
					// in the MW load process explicitly configured variables are
					// available, but defaults from extension.json have not yet been
					// loaded.
					$GLOBALS[$key] = $value;
				} else {
					$this->error( "\nERROR: $key is not a whitelisted global variable\n" );
					exit();
				}
			}
		}
	}

	/**
	 * Transform the search request into a JSON string representing the
	 * search result.
	 *
	 * @param string $query
	 * @return string JSON object
	 */
	public function consume( $query ) {
		if ( $this->getOption( 'decode' ) ) {
			$query = urldecode( $query );
		}
		$data = [ 'query' => $query ];
		$status = $this->searchFor( $query );
		if ( $status->isOK() ) {
			$value = $status->getValue();
			if ( $value instanceof IResultWrapper ) {
				// Archive search results
				$data += $this->processArchiveResult( $value );
			} elseif ( $value instanceof CirrusSearchResultSet ) {
				$data += $this->processResultSet( $value, $query );
			} elseif ( $value instanceof SearchSuggestionSet ) {
				// these are suggestion results
				$data += $this->processSuggestionSet( $value );
			} else {
				throw new \RuntimeException(
					'Unknown result type: '
					. ( is_object( $value ) ? get_class( $value ) : gettype( $value ) )
				);
			}
		} else {
			$data['error'] = $status->getMessage()->text();
		}
		return json_encode( $data );
	}

	/**
	 * Extract data from a search result set.
	 * @param CirrusSearchResultSet $value
	 * @param string $query
	 * @return array
	 */
	protected function processResultSet( CirrusSearchResultSet $value, $query ) {
		// these are prefix or full text results
		$rows = [];
		foreach ( $value as $result ) {
			/** @var CirrusSearch\Search\CirrusSearchResult $result */
			$row = [
				// use getDocId() rather than asking the title to allow this script
				// to work when a production index has been imported to a test es instance
				'docId' => $result->getDocId(),
				'title' => $result->getTitle()->getPrefixedText(),
				'score' => $result->getScore(),
				'snippets' => [
					'text' => $result->getTextSnippet(),
					'title' => $result->getTitleSnippet(),
					'redirect' => $result->getRedirectSnippet(),
					'section' => $result->getSectionSnippet(),
					'category' => $result->getCategorySnippet(),
				],
				'explanation' => $result->getExplanation(),
				'extra' => $result->getExtensionData(),
			];
			$img = $result->getFile() ?: MediaWikiServices::getInstance()->getRepoGroup()
				->findFile( $result->getTitle() );
			if ( $img ) {
				$thumb = $img->transform( [ 'width' => 120, 'height' => 120 ] );
				if ( $thumb ) {
					$row['thumb_url'] = $thumb->getUrl();
				}
			}
			$rows[] = $row;
		}
		return [
			'totalHits' => $value->getTotalHits(),
			'rows' => $rows,
		];
	}

	/**
	 * Extract data from a search suggestions set.
	 * @param SearchSuggestionSet $value
	 * @return array
	 */
	protected function processSuggestionSet( SearchSuggestionSet $value ) {
		$rows = [];
		foreach ( $value->getSuggestions() as $suggestion ) {
			$rows[] = [
				'pageId' => $suggestion->getSuggestedTitleID(),
				'title' => $suggestion->getSuggestedTitle()->getPrefixedText(),
				'snippets' => [],
			];
		}
		return [
			'totalHits' => $value->getSize(),
			'rows' => $rows,
		];
	}

	/**
	 * Extract data from archive search results.
	 * @param IResultWrapper $value
	 * @return array
	 */
	protected function processArchiveResult( IResultWrapper $value ) {
		$rows = [];
		foreach ( $value as $row ) {
			$rows[] = [
				'title' => $row->ar_title,
				'namespace' => $row->ar_namespace,
				'count' => $row->count,
			];
		}
		return [
			'totalHits' => $value->numRows(),
			'rows' => $rows,
		];
	}

	/**
	 * Search for term in the archive.
	 * @param string $query
	 * @return Status<IResultWrapper>
	 */
	protected function searchArchive( $query ) {
		$result = PageArchive::listPagesBySearch( $query );
		return Status::newGood( $result );
	}

	/**
	 * Transform the search request into a Status object representing the
	 * search result. Varies based on CLI input argument `type`.
	 *
	 * @param string $query
	 * @return Status<CirrusSearch\Search\CirrusSearchResultSet|SearchSuggestionSet|IResultWrapper>
	 */
	protected function searchFor( $query ) {
		$searchType = $this->getOption( 'type', 'full_text' );

		if ( $searchType === 'archive' ) {
			// Archive has its own engine so go directly there
			return $this->searchArchive( $query );
		}

		$limit = $this->getOption( 'limit', 10 );
		$options = CirrusDebugOptions::forRelevanceTesting(
			$this->getOption( 'explain', false ) ? 'raw' : null
		);

		$config = new HashSearchConfig( [ SearchConfig::INDEX_BASE_NAME => $this->indexBaseName ],
			[ HashSearchConfig::FLAG_INHERIT ] );
		$engine = new CirrusSearch( $config, $options );
		$namespaces = array_keys( $engine->getConfig()->get( 'NamespacesToBeSearchedDefault' ), true );
		$engine->setNamespaces( $namespaces );

		$engine->setConnection( $this->getConnection() );
		$engine->setLimitOffset( $limit );

		switch ( $searchType ) {
		case 'full_text':
			// @todo pass through $this->getConnection() ?
			$result = $engine->searchText( $query );
			if ( $result instanceof Status ) {
				return $result;
			} else {
				return Status::newGood( $result );
			}

		case 'prefix':
			$titles = $engine->defaultPrefixSearch( $query );
			$resultSet = SearchSuggestionSet::fromTitles( $titles );
			return Status::newGood( $resultSet );

		case 'suggest':
			$result = $engine->completionSearch( $query );
			if ( $result instanceof Status ) {
				return $result;
			} else {
				return Status::newGood( $result );
			}

		default:
			$this->fatalError( "\nERROR: Unknown search type $searchType\n" );
		}
	}
}

$maintClass = RunSearch::class;
require_once RUN_MAINTENANCE_IF_MAIN;
