<?php

namespace CirrusSearch\Search;

use CirrusSearch\Searcher;
use Elastica\ResultSet as ElasticaResultSet;
use MediaWiki\Logger\LoggerFactory;

/**
 * Returns titles categorized based on how they matched - redirect or name.
 */
class FancyTitleResultsType extends TitleResultsType {
	/** @var string */
	private $matchedAnalyzer;

	/**
	 * Build result type.   The matchedAnalyzer is required to detect if the match
	 * was from the title or a redirect (and is kind of a leaky abstraction.)
	 *
	 * @param string $matchedAnalyzer the analyzer used to match the title
	 * @param TitleHelper|null $titleHelper
	 */
	public function __construct( $matchedAnalyzer, TitleHelper $titleHelper = null ) {
		parent::__construct( $titleHelper );
		$this->matchedAnalyzer = $matchedAnalyzer;
	}

	public function getSourceFiltering() {
		return [ 'namespace', 'title', 'namespace_text', 'wiki', 'redirect' ];
	}

	/**
	 * @param array $extraHighlightFields
	 * @return array|null
	 */
	public function getHighlightingConfiguration( array $extraHighlightFields = [] ) {
		global $wgCirrusSearchUseExperimentalHighlighter;

		if ( $wgCirrusSearchUseExperimentalHighlighter ) {
			// This is much less esoteric then the plain highlighter based
			// invocation but does the same thing.  The magic is that the none
			// fragmenter still fragments on multi valued fields.
			$entireValue = [
				'type' => 'experimental',
				'fragmenter' => 'none',
				'number_of_fragments' => 1,
			];
			$manyValues = [
				'type' => 'experimental',
				'fragmenter' => 'none',
				'order' => 'score',
			];
		} else {
			// This is similar to the FullTextResults type but against the near_match and
			// with the plain highlighter.  Near match because that is how the field is
			// queried.  Plain highlighter because we don't want to add the FVH's space
			// overhead for storing extra stuff and we don't need it for combining fields.
			$entireValue = [
				'type' => 'plain',
				'number_of_fragments' => 0,
			];
			$manyValues = [
				'type' => 'plain',
				'fragment_size' => 10000,   // We want the whole value but more than this is crazy
				'order' => 'score',
			];
		}
		$manyValues[ 'number_of_fragments' ] = 30;
		return [
			'pre_tags' => [ Searcher::HIGHLIGHT_PRE ],
			'post_tags' => [ Searcher::HIGHLIGHT_POST ],
			'fields' => [
				"title.$this->matchedAnalyzer" => $entireValue,
				"title.{$this->matchedAnalyzer}_asciifolding" => $entireValue,
				"redirect.title.$this->matchedAnalyzer" => $manyValues,
				"redirect.title.{$this->matchedAnalyzer}_asciifolding" => $manyValues,
			],
		];
	}

	/**
	 * Convert the results to titles.
	 *
	 * @param ElasticaResultSet $resultSet
	 * @return array[] Array of arrays, each with optional keys:
	 *   titleMatch => a title if the title matched
	 *   redirectMatches => an array of redirect matches, one per matched redirect
	 */
	public function transformElasticsearchResult( ElasticaResultSet $resultSet ) {
		$results = [];
		foreach ( $resultSet->getResults() as $r ) {
			$results[] = $this->transformOneElasticResult( $r );
		}
		return $results;
	}

	/**
	 * Finds best title or redirect
	 * @param array $match array returned by self::transformOneElasticResult
	 * @return \Title|false choose best
	 */
	public static function chooseBestTitleOrRedirect( array $match ) {
		if ( isset( $match['titleMatch'] ) ) {
			return $match['titleMatch'];
		} else {
			if ( isset( $match['redirectMatches'][0] ) ) {
				// TODO maybe dig around in the redirect matches and find the best one?
				return $match['redirectMatches'][0];
			}
		}

		return false;
	}

	/**
	 * @return array
	 */
	public function createEmptyResult() {
		return [];
	}

	/**
	 * Transform a result from elastic into an array of Titles.
	 *
	 * @param \Elastica\Result $r
	 * @param int[] $namespaces Prefer
	 * @return \Title[] with the following keys :
	 *   titleMatch => a title if the title matched
	 *   redirectMatches => an array of redirect matches, one per matched redirect
	 */
	public function transformOneElasticResult( \Elastica\Result $r, array $namespaces = [] ) {
		$title = $this->getTitleHelper()->makeTitle( $r );
		$highlights = $r->getHighlights();
		$resultForTitle = [];

		// Now we have to use the highlights to figure out whether it was the title or the redirect
		// that matched.  It is kind of a shame we can't really give the highlighting to the client
		// though.
		if ( isset( $highlights["title.$this->matchedAnalyzer"] ) ) {
			$resultForTitle['titleMatch'] = $title;
		} elseif ( isset( $highlights["title.{$this->matchedAnalyzer}_asciifolding"] ) ) {
			$resultForTitle['titleMatch'] = $title;
		}
		$redirectHighlights = [];

		if ( isset( $highlights["redirect.title.$this->matchedAnalyzer"] ) ) {
			$redirectHighlights = $highlights["redirect.title.$this->matchedAnalyzer"];
		}
		if ( isset( $highlights["redirect.title.{$this->matchedAnalyzer}_asciifolding"] ) ) {
			$redirectHighlights =
				array_merge( $redirectHighlights,
					$highlights["redirect.title.{$this->matchedAnalyzer}_asciifolding"] );
		}
		if ( $redirectHighlights !== [] ) {
			$source = $r->getSource();
			$docRedirects = [];
			if ( isset( $source['redirect'] ) ) {
				foreach ( $source['redirect'] as $docRedir ) {
					$docRedirects[$docRedir['title']][] = $docRedir;
				}
			}
			foreach ( $redirectHighlights as $redirectTitleString ) {
				$resultForTitle['redirectMatches'][] = $this->resolveRedirectHighlight(
					$r, $redirectTitleString, $docRedirects, $namespaces );
			}
		}
		if ( $resultForTitle === [] ) {
			// We're not really sure where the match came from so lets just pretend it was the title.
			LoggerFactory::getInstance( 'CirrusSearch' )
				->warning( "Title search result type hit a match but we can't " .
					"figure out what caused the match: {namespace}:{title}",
					[ 'namespace' => $r->namespace, 'title' => $r->title ] );
			$resultForTitle['titleMatch'] = $title;
		}

		return $resultForTitle;
	}

	/**
	 * @param \Elastica\Result $r Elasticsearch result
	 * @param string $redirectTitleString Highlighted string returned from elasticsearch
	 * @param array $docRedirects Map from title string to list of redirects from elasticsearch source document
	 * @param int[] $namespaces Prefered namespaces to source redirects from
	 * @return \Title
	 */
	private function resolveRedirectHighlight( \Elastica\Result $r, $redirectTitleString, array $docRedirects, $namespaces ) {
		// The match was against a redirect so we should replace the $title with one that
		// represents the redirect.
		// The first step is to strip the actual highlighting from the title.
		$redirectTitleString = str_replace( [ Searcher::HIGHLIGHT_PRE, Searcher::HIGHLIGHT_POST ],
			'', $redirectTitleString );

		if ( !isset( $docRedirects[$redirectTitleString] ) ) {
			// Instead of getting the redirect's real namespace we're going to just use the namespace
			// of the title.  This is not great.
			// TODO: Should we just bail at this point?
			return $this->getTitleHelper()->makeRedirectTitle( $r, $redirectTitleString, $r->namespace );
		}

		$redirs = $docRedirects[$redirectTitleString];
		if ( count( $redirs ) === 1 ) {
			// may or may not be the right namespace, but we don't seem to have any other options.
			return $this->getTitleHelper()->makeRedirectTitle( $r, $redirectTitleString, $redirs[0]['namespace'] );
		}

		if ( $namespaces ) {
			foreach ( $redirs as $redir ) {
				if ( array_search( $redir['namespace'], $namespaces ) ) {
					return $this->getTitleHelper()->makeRedirectTitle( $r, $redirectTitleString, $redir['namespace'] );
				}
			}
		}
		// Multiple redirects with same text from different namespaces, but none of them match the requested namespaces. What now?
		return $this->getTitleHelper()->makeRedirectTitle( $r, $redirectTitleString, $redirs[0]['namespace'] );
	}
}
