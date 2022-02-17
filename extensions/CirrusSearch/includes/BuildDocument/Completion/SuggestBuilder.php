<?php

namespace CirrusSearch\BuildDocument\Completion;

use CirrusSearch\Connection;
use Elastica\Multi\Search as MultiSearch;
use Elastica\Search;
use LinkBatch;
use Title;

/**
 * Build a doc ready for the titlesuggest index.
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

/**
 * Builder used to create suggester docs
 * NOTE: Experimental
 */
class SuggestBuilder {
	/**
	 * We limit the input to 50 chars the search requests
	 * It'll be used when searching to trim the input query
	 * and when determining close redirects
	 */
	const MAX_INPUT_LENGTH = 50;

	/**
	 * The acceptable edit distance to group similar strings
	 */
	const GROUP_ACCEPTABLE_DISTANCE = 2;

	/**
	 * Discount suggestions based on redirects
	 */
	const REDIRECT_DISCOUNT = 0.1;

	/**
	 * Discount suggestions based on cross namespace redirects
	 */
	const CROSSNS_DISCOUNT = 0.005;

	/**
	 * Redirect suggestion type
	 */
	const REDIRECT_SUGGESTION = 'r';

	/**
	 * Title suggestion type
	 */
	const TITLE_SUGGESTION = 't';

	/**
	 * Number of common prefix chars a redirect must share with the title to be
	 * promoted as a title suggestion.
	 * This is useful not to promote Eraq as a title suggestion for Iraq
	 * Less than 3 can lead to weird results like oba => Osama Bin Laden
	 */
	const REDIRECT_COMMON_PREFIX_LEN = 3;

	/**
	 * @var SuggestScoringMethod the scoring function
	 */
	private $scoringMethod;

	/**
	 * @var integer batch id
	 */
	private $batchId;

	/**
	 * @var ExtraSuggestionsBuilder[]
	 */
	private $extraBuilders;

	/**
	 * NOTE: Currently a fixed value because the completion suggester does not support
	 * multi namespace suggestion.
	 *
	 * @var int
	 */
	private $targetNamespace = NS_MAIN; // @phan-suppress-current-line PhanUndeclaredConstant NS_MAIN is defined

	/**
	 * @param SuggestScoringMethod $scoringMethod the scoring function to use
	 * @param ExtraSuggestionsBuilder[] $extraBuilders set of extra builders
	 */
	public function __construct( SuggestScoringMethod $scoringMethod, array $extraBuilders = [] ) {
		$this->scoringMethod = $scoringMethod;
		$this->extraBuilders = $extraBuilders;
		$this->batchId = time();
	}

	/**
	 * @param Connection $connection
	 * @param string|null $scoreMethodName
	 * @param string|null $indexBaseName
	 * @return SuggestBuilder
	 * @throws \Exception
	 */
	public static function create( Connection $connection, $scoreMethodName = null, $indexBaseName = null ): SuggestBuilder {
		$config  = $connection->getConfig();
		$scoreMethodName = $scoreMethodName ?: $config->get( 'CirrusSearchCompletionDefaultScore' );
		$scoreMethod = SuggestScoringMethodFactory::getScoringMethod( $scoreMethodName );

		$extraBuilders = [];
		if ( $config->get( 'CirrusSearchCompletionSuggesterUseDefaultSort' ) ) {
			$extraBuilders[] = new DefaultSortSuggestionsBuilder();
		}
		$subPhrasesConfig = $config->get( 'CirrusSearchCompletionSuggesterSubphrases' );
		if ( $subPhrasesConfig['build'] ) {
			$extraBuilders[] = NaiveSubphrasesSuggestionsBuilder::create( $subPhrasesConfig );
		}
		$scoreMethod->setMaxDocs( self::fetchMaxDoc( $connection, $indexBaseName ) );
		return new SuggestBuilder( $scoreMethod, $extraBuilders );
	}

	/**
	 * @param array[] $inputDocs a batch of docs to build
	 * @param bool $explain
	 * @return \Elastica\Document[] a set of suggest documents
	 */
	public function build( $inputDocs, $explain = false ) {
		// Cross namespace titles
		$crossNsTitles = [];
		$docs = [];
		foreach ( $inputDocs as $sourceDoc ) {
			$inputDoc = $sourceDoc['source'];
			$docId = $sourceDoc['id'];
			// a bit of a hack but it's convenient to carry
			// the id around
			$inputDoc['id'] = $docId;
			if ( !isset( $inputDoc['namespace'] ) ) {
				// Bad doc, nothing to do here.
				continue;
			}
			if ( $inputDoc['namespace'] == $this->targetNamespace ) {
				if ( !isset( $inputDoc['title'] ) ) {
					// Bad doc, nothing to do here.
					continue;
				}
				$docs = array_merge( $docs, $this->buildNormalSuggestions( $docId, $inputDoc, $explain ) );
			} else {
				if ( !isset( $inputDoc['redirect'] ) ) {
					// Bad doc, nothing to do here.
					continue;
				}

				foreach ( $inputDoc['redirect'] as $redir ) {
					if ( !isset( $redir['namespace'] ) || !isset( $redir['title'] ) ) {
						continue;
					}
					if ( $redir['namespace'] != $this->targetNamespace ) {
						continue;
					}
					$score = $this->scoringMethod->score( $inputDoc );
					// Discount the score of these suggestions.
					$score = (int)( $score * self::CROSSNS_DISCOUNT );
					$explainDetails = null;
					if ( $explain ) {
						// TODO: add explanation of the crossns discount
						$explainDetails = $this->scoringMethod->explain( $inputDoc );
					}

					$title = Title::makeTitle( $redir['namespace'], $redir['title'] );
					$crossNsTitles[] = [
						'title' => $title,
						'score' => $score,
						'text' => $redir['title'],
						'inputDoc' => $inputDoc,
						'explain' => $explainDetails,
					];
				}
			}
		}

		// Build cross ns suggestions
		if ( !empty( $crossNsTitles ) ) {
			$titles = [];
			foreach ( $crossNsTitles as $data ) {
				$titles[] = $data['title'];
			}
			$lb = new LinkBatch( $titles );
			$lb->setCaller( __METHOD__ );
			$lb->execute();
			// This is far from perfect:
			// - we won't try to group similar redirects since we don't know which one
			// is the official one
			// - we will certainly suggest multiple times the same pages
			// - we must not run a second pass at query time: no redirect suggestion
			foreach ( $crossNsTitles as $data ) {
				$suggestion = [
					'text' => $data['text'],
					'variants' => []
				];
				$docs[] = $this->buildTitleSuggestion( (string)$data['title']->getArticleID(), $suggestion,
					$data['score'], $data['inputDoc'], $data['explain'] );
			}
		}
		return $docs;
	}

	/**
	 * Build classic suggestion
	 *
	 * @param string $docId
	 * @param array $inputDoc
	 * @param bool $explain
	 * @return \Elastica\Document[] a set of suggest documents
	 */
	private function buildNormalSuggestions( $docId, array $inputDoc, $explain = false ) {
		if ( !isset( $inputDoc['title'] ) ) {
			// Bad doc, nothing to do here.
			return [];
		}

		$score = $this->scoringMethod->score( $inputDoc );
		$explainDetails = null;
		if ( $explain ) {
			$explainDetails = $this->scoringMethod->explain( $inputDoc );
		}

		$suggestions = $this->extractTitleAndSimilarRedirects( $inputDoc );

		$docs = [ $this->buildTitleSuggestion( $docId, $suggestions['group'], $score, $inputDoc, $explainDetails ) ];
		if ( !empty( $suggestions['candidates'] ) ) {
			$docs[] = $this->buildRedirectsSuggestion( $docId, $suggestions['candidates'], $score, $inputDoc, $explainDetails );
		}
		return $docs;
	}

	/**
	 * The fields needed to build and score documents.
	 *
	 * @return string[] the list of fields
	 */
	public function getRequiredFields() {
		$fields = $this->scoringMethod->getRequiredFields();
		$fields = array_merge( $fields, [ 'title', 'redirect', 'namespace' ] );
		foreach ( $this->extraBuilders as $extraBuilder ) {
			$fields = array_merge( $fields, $extraBuilder->getRequiredFields() );
		}
		return array_values( array_unique( $fields ) );
	}

	/**
	 * Builds the 'title' suggestion.
	 *
	 * @param string $docId the page id
	 * @param array $title the title in 'text' and an array of similar redirects in 'variants'
	 * @param int $score the weight of the suggestion
	 * @param mixed[] $inputDoc
	 * @param array|null $scoreExplanation
	 * @return \Elastica\Document the suggestion document
	 */
	private function buildTitleSuggestion( $docId, array $title, $score, array $inputDoc, array $scoreExplanation = null ) {
		$inputs = [ $title['text'] ];
		foreach ( $title['variants'] as $variant ) {
			$inputs[] = $variant;
		}
		return $this->buildSuggestion(
			self::TITLE_SUGGESTION,
			$docId,
			$inputs,
			$score,
			$inputDoc,
			$scoreExplanation
		);
	}

	/**
	 * Builds the 'redirects' suggestion.
	 * The score will be discounted by the REDIRECT_DISCOUNT factor.
	 * NOTE: the client will have to fetch the doc redirects when searching
	 * and choose the best one to display. This is because we are unable
	 * to make this decision at index time.
	 *
	 * @param string $docId the elasticsearch document id
	 * @param string[] $redirects
	 * @param int $score the weight of the suggestion
	 * @param mixed[] $inputDoc
	 * @param array|null $scoreExplanation
	 * @return \Elastica\Document the suggestion document
	 */
	private function buildRedirectsSuggestion( $docId, array $redirects, $score, array $inputDoc, array $scoreExplanation = null ) {
		$inputs = [];
		foreach ( $redirects as $redirect ) {
			$inputs[] = $redirect;
		}
		// TODO: add redirect discount explanation
		$score = (int)( $score * self::REDIRECT_DISCOUNT );
		return $this->buildSuggestion( self::REDIRECT_SUGGESTION, $docId, $inputs,
			$score, $inputDoc, $scoreExplanation );
	}

	/**
	 * Builds a suggestion document.
	 *
	 * @param string $suggestionType suggestion type (title or redirect)
	 * @param string $docId The document id
	 * @param string[] $inputs the suggestion inputs
	 * @param int $score the weight of the suggestion
	 * @param mixed[] $inputDoc
	 * @param array|null $scoreExplanation
	 * @return \Elastica\Document a doc ready to be indexed in the completion suggester
	 */
	private function buildSuggestion( $suggestionType, $docId, array $inputs, $score, array $inputDoc, array $scoreExplanation = null ) {
		$doc = [
			'batch_id' => $this->batchId,
			'source_doc_id' => $inputDoc['id'],
			'target_title' => [
				'title' => $inputDoc['title'],
				'namespace' => $inputDoc['namespace'],
			],
			'suggest' => [
				'input' => $inputs,
				'weight' => $score
			],
			'suggest-stop' => [
				'input' => $inputs,
				'weight' => $score
			]
		];

		$suggestDoc = new \Elastica\Document( self::encodeDocId( $suggestionType, $docId ), $doc );
		foreach ( $this->extraBuilders as $builder ) {
			$builder->build( $inputDoc, $suggestionType, $score, $suggestDoc, $this->targetNamespace );
		}
		if ( $scoreExplanation !== null ) {
			$suggestDoc->set( 'score_explanation', $scoreExplanation );
		}
		return $suggestDoc;
	}

	/**
	 * @param string $input A page title
	 * @return string A page title short enough to not cause indexing
	 *  issues.
	 */
	public function trimForDistanceCheck( $input ) {
		if ( mb_strlen( $input ) > self::MAX_INPUT_LENGTH ) {
			$input = mb_substr( $input, 0, self::MAX_INPUT_LENGTH );
		}
		return $input;
	}

	/**
	 * Extracts title with redirects that are very close.
	 * It will allow to make one suggestion with title as the
	 * output and title + similar redirects as the inputs.
	 * It can be useful to avoid displaying redirects created to
	 * to handle typos.
	 *
	 * e.g. :
	 *   title: Giraffe
	 *   redirects: Girafe, Girraffe, Mating Giraffes
	 * will output
	 *   - 'group' : { 'text': 'Giraffe', 'variants': ['Girafe', 'Girraffe'] }
	 *   - 'candidates' : ['Mating Giraffes']
	 *
	 * It would be nice to do this for redirects but we have no way to decide
	 * which redirect is a typo and this technique would simply take the first
	 * redirect in the list.
	 *
	 * @param array $doc
	 * @return array mixed 'group' key contains the group with the
	 *         lead and its variants and 'candidates' contains the remaining
	 *         candidates that were not close enough to $groupHead.
	 */
	public function extractTitleAndSimilarRedirects( array $doc ) {
		$redirects = [];
		if ( isset( $doc['redirect'] ) ) {
			foreach ( $doc['redirect'] as $redir ) {
				// Avoid suggesting/displaying non existent titles
				// in the target namespace
				if ( $redir['namespace'] == $this->targetNamespace ) {
					$redirects[] = $redir['title'];
				}
			}
		}
		return $this->extractSimilars( $doc['title'], $redirects, true );
	}

	/**
	 * Extracts from $candidates the values that are "similar" to $groupHead
	 *
	 * @param string $groupHead string the group "head"
	 * @param string[] $candidates array of string the candidates
	 * @param bool $checkVariants if the candidate does not match the groupHead try to match a variant
	 * @return array 'group' key contains the group with the
	 *         head and its variants and 'candidates' contains the remaining
	 *         candidates that were not close enough to $groupHead.
	 */
	private function extractSimilars( $groupHead, array $candidates, $checkVariants = false ) {
		$group = [
			'text' => $groupHead,
			'variants' => []
		];
		$newCandidates = [];
		foreach ( $candidates as $c ) {
			$distance = $this->distance( $groupHead, $c );
			if ( $distance > self::GROUP_ACCEPTABLE_DISTANCE && $checkVariants ) {
				// Run a second pass over the variants
				foreach ( $group['variants'] as $v ) {
					$distance = $this->distance( $v, $c );
					if ( $distance <= self::GROUP_ACCEPTABLE_DISTANCE ) {
						break;
					}
				}
			}
			if ( $distance <= self::GROUP_ACCEPTABLE_DISTANCE ) {
				$group['variants'][] = $c;
			} else {
				$newCandidates[] = $c;
			}
		}

		return [
			'group' => $group,
			'candidates' => $newCandidates
		];
	}

	/**
	 * Computes the edit distance between $a and $b.
	 *
	 * @param string $a
	 * @param string $b
	 * @return int the edit distance between a and b
	 */
	private function distance( $a, $b ) {
		$a = $this->trimForDistanceCheck( $a );
		$b = $this->trimForDistanceCheck( $b );
		$a = mb_strtolower( $a );
		$b = mb_strtolower( $b );

		$aLength = mb_strlen( $a );
		$bLength = mb_strlen( $b );

		$commonPrefixLen = self::REDIRECT_COMMON_PREFIX_LEN;

		if ( $aLength < $commonPrefixLen ) {
			$commonPrefixLen = $aLength;
		}
		if ( $bLength < $commonPrefixLen ) {
			$commonPrefixLen = $bLength;
		}

		// check the common prefix
		if ( mb_substr( $a, 0, $commonPrefixLen ) != mb_substr( $b, 0, $commonPrefixLen ) ) {
			return PHP_INT_MAX;
		}

		// TODO: switch to a ratio instead of raw distance would help to group
		// longer strings
		return levenshtein( $a, $b );
	}

	/**
	 * Encode the suggestion doc id
	 * @param string $suggestionType
	 * @param string $docId
	 * @return string
	 */
	public static function encodeDocId( $suggestionType, $docId ) {
		return $docId . $suggestionType;
	}

	/**
	 * Encode possible docIds used by the completion suggester index
	 *
	 * @param string $docId
	 * @return string[] list of docIds
	 */
	public static function encodePossibleDocIds( $docId ) {
		return [
			self::encodeDocId( self::TITLE_SUGGESTION, $docId ),
			self::encodeDocId( self::REDIRECT_SUGGESTION, $docId ),
		];
	}

	/**
	 * @return int the batchId
	 */
	public function getBatchId() {
		return $this->batchId;
	}

	/**
	 * @return int the target namespace
	 */
	public function getTargetNamespace() {
		return $this->targetNamespace;
	}

	/**
	 * @param Connection $connection
	 * @param string|null $indexBaseName
	 * @return int
	 */
	private static function fetchMaxDoc( Connection $connection, $indexBaseName = null ) {
		// Indices to use for counting max_docs used by scoring functions
		// Since we work mostly on the content namespace it seems OK to count
		// only docs in the CONTENT index.
		$countIndices = [ Connection::CONTENT_INDEX_TYPE ];

		$indexBaseName = $indexBaseName ?: $connection->getConfig()->get( 'CirrusSearchIndexBaseName' );

		// Run a first query to count the number of docs.
		// This is needed for the scoring methods that need
		// to normalize values against wiki size.
		$mSearch = new MultiSearch( $connection->getClient() );
		foreach ( $countIndices as $sourceIndexType ) {
			$search = new Search( $connection->getClient() );
			$search->addIndex(
				$connection->getIndex( $indexBaseName, $sourceIndexType )
			);
			$search->getQuery()->setSize( 0 );
			$mSearch->addSearch( $search );
		}

		$mSearchRes = $mSearch->search();
		$total = 0;
		foreach ( $mSearchRes as $res ) {
			$total += $res->getTotalHits();
		}
		return $total;
	}
}
