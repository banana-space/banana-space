<?php

namespace CirrusSearch\Search\Rescore;

use CirrusSearch\Elastica\LtrQuery;
use CirrusSearch\Profile\SearchProfileService;
use CirrusSearch\Search\SearchContext;
use Elastica\Query\AbstractQuery;

/**
 * Set of rescore builders
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
 * Builds a rescore queries by reading a rescore profile.
 */
class RescoreBuilder {

	/**
	 * @var int Maximum number of rescore profile fallbacks
	 */
	const FALLBACK_LIMIT = 4;

	/**
	 * List of allowed rescore params
	 * @todo: refactor to const with php 5.6
	 *
	 * @var string[] $rescoreMainParams
	 */
	private static $rescoreMainParams = [
		'query_weight',
		'rescore_query_weight',
		'score_mode'
	];

	const FUNCTION_SCORE_TYPE = "function_score";
	const LTR_TYPE = "ltr";
	const PHRASE = "phrase";

	/**
	 * @var SearchContext
	 */
	private $context;

	/**
	 * @var array|string a rescore profile
	 */
	private $profile;

	/**
	 * @param SearchContext $context
	 * @param string|null $profile
	 */
	public function __construct( SearchContext $context, $profile = null ) {
		$this->context = $context;
		if ( $profile === null ) {
			$profile = $context->getRescoreProfile();
		}
		$this->profile = $this->getSupportedProfile( $profile );
	}

	/**
	 * @return array[] List of rescore queries
	 */
	public function build() {
		$rescores = [];
		foreach ( $this->profile['rescore'] as $rescoreDef ) {
			$windowSize = $this->windowSize( $rescoreDef );
			if ( $windowSize <= 0 ) {
				continue;
			}
			$rescore = [
				'window_size' => $windowSize,
			];

			$rescore['query'] = $this->prepareQueryParams( $rescoreDef );
			$rescoreQuery = $this->buildRescoreQuery( $rescoreDef );
			if ( $rescoreQuery === null ) {
				continue;
			}
			$rescore['query']['rescore_query'] = $rescoreQuery;
			$rescores[] = $rescore;
		}
		return $rescores;
	}

	/**
	 * builds the 'query' attribute by reading type
	 *
	 * @param array $rescoreDef
	 * @return AbstractQuery|null the rescore query
	 * @throws InvalidRescoreProfileException
	 */
	private function buildRescoreQuery( array $rescoreDef ) {
		switch ( $rescoreDef['type'] ) {
		case self::FUNCTION_SCORE_TYPE:
			$funcChain = new FunctionScoreChain( $this->context, $rescoreDef['function_chain'],
				$rescoreDef['function_chain_overrides'] ?? [] );
			return $funcChain->buildRescoreQuery();
		case self::LTR_TYPE:
			return $this->buildLtrQuery( $rescoreDef['model'] );
		case self::PHRASE:
			return $this->context->getPhraseRescoreQuery();
		default:
			throw new InvalidRescoreProfileException(
				"Unsupported rescore query type: " . $rescoreDef['type'] );
		}
	}

	/**
	 * @param string $model Name of the sltr model to use
	 * @return AbstractQuery
	 */
	private function buildLtrQuery( $model ) {
		// This is a bit fragile, and makes the bold assumption
		// only a single level of rescore will be used. This is
		// strictly for debugging/testing before shipping a model
		// live so shouldn't be a big deal.
		$override = $this->context->getDebugOptions()->getCirrusMLRModel();
		if ( $override ) {
			$model = $override;
		}

		$bool = new \Elastica\Query\BoolQuery();
		// the ltr query can return negative scores, which mucks with elasticsearch
		// sorting as that will put these results below documents set to 0. Fix
		// that up by adding a large constant boost.
		$constant = new \Elastica\Query\ConstantScore( new \Elastica\Query\MatchAll );
		$constant->setBoost( 100000 );
		$bool->addShould( $constant );
		$bool->addShould( new LtrQuery( $model, [
				// TODO: These params probably shouldn't be hard coded
				'query_string' => $this->context->getCleanedSearchTerm(),
			] ) );

		return $bool;
	}

	/**
	 * @param array $rescore
	 * @return int the window size defined in the profile
	 * or the value from config if window_size_override is set.
	 */
	private function windowSize( array $rescore ) {
		if ( isset( $rescore['window_size_override'] ) ) {
			$windowSize = $this->context->getConfig()->get( $rescore['window_size_override'] );
			if ( $windowSize !== null ) {
				return $windowSize;
			}
		}
		return $rescore['window'];
	}

	/**
	 * Assemble query params in the rescore block
	 * Only self::$rescoreMainParams are allowed.
	 * @param array $settings
	 * @return array
	 */
	private function prepareQueryParams( array $settings ) {
		$def = [];
		foreach ( self::$rescoreMainParams as $param ) {
			if ( !isset( $settings[$param] ) ) {
				continue;
			}
			$value = $settings[$param];
			if ( isset( $settings[$param . '_override'] ) ) {
				$oValue = $this->context->getConfig()->get( $settings[$param . '_override'] );
				if ( $oValue !== null ) {
					$value = $oValue;
				}
			}
			$def[$param] = $value;
		}
		return $def;
	}

	/**
	 * Inspect requested namespaces and return the supported profile
	 *
	 * @param string $profileName
	 * @return array the supported rescore profile.
	 * @throws InvalidRescoreProfileException
	 */
	private function getSupportedProfile( $profileName ) {
		$profile = $this->context->getConfig()
			->getProfileService()
			->loadProfileByName( SearchProfileService::RESCORE, $profileName );
		if ( !is_array( $profile ) ) {
			throw new InvalidRescoreProfileException(
				"Invalid fallback profile, must be array: $profileName" );
		}

		$seen = [];
		while ( true ) {
			$seen[$profileName] = true;
			if ( count( $seen ) > self::FALLBACK_LIMIT ) {
				throw new InvalidRescoreProfileException(
					"Fell back more than " . self::FALLBACK_LIMIT . " times"
				);
			}

			if ( !$this->isProfileNamespaceSupported( $profile )
				|| !$this->isProfileSyntaxSupported( $profile )
			) {
				if ( !isset( $profile['fallback_profile'] ) ) {
					throw new InvalidRescoreProfileException(
						"Invalid rescore profile: fallback_profile is mandatory "
						. "if supported_namespaces is not 'all' or "
						. "unsupported_syntax is not null."
					);
				}
				$profileName = $profile['fallback_profile'];
				if ( isset( $seen[$profileName] ) ) {
					$chain = implode( '->', array_keys( $seen ) ) . "->$profileName";
					throw new InvalidRescoreProfileException( "Cycle in rescore fallbacks: $chain" );
				}

				$profile = $this->context->getConfig()
					->getProfileService()
					->loadProfileByName( SearchProfileService::RESCORE,  $profileName );
				if ( !is_array( $profile ) ) {
					throw new InvalidRescoreProfileException(
						"Invalid fallback profile, must be array: $profileName" );
				}
				continue;
			}
			return $profile;
		}
	}

	/**
	 * Check if a given profile supports the namespaces used by the current
	 * search request.
	 *
	 * @param array $profile Profile to check
	 * @return bool True is the profile supports current namespaces
	 */
	private function isProfileNamespaceSupported( array $profile ) {
		if ( !is_array( $profile['supported_namespaces'] ) ) {
			switch ( $profile['supported_namespaces'] ) {
			case 'all':
				return true;
			case 'content':
				$profileNs = $this->context->getConfig()->get( 'ContentNamespaces' );
				// Default search namespaces are also considered content
				$defaultSearch = $this->context->getConfig()->get( 'NamespacesToBeSearchedDefault' );
				foreach ( $defaultSearch as $ns => $isDefault ) {
					if ( $isDefault ) {
						$profileNs[] = $ns;
					}
				}
				break;
			default:
				throw new InvalidRescoreProfileException( "Invalid rescore profile: supported_namespaces " .
					"should be 'all', 'content' or an array of namespaces" );
			}
		} else {
			$profileNs = $profile['supported_namespaces'];
		}

		$queryNs = $this->context->getNamespaces();

		if ( !$queryNs ) {
			// According to comments in Searcher if namespaces is
			// not set we run the query on all namespaces
			// @todo: verify comments.
			return false;
		}

		foreach ( $queryNs as $ns ) {
			if ( !in_array( $ns, $profileNs ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if the given profile supports the syntax used by the
	 * current search request.
	 *
	 * @param array $profile
	 * @return bool
	 */
	private function isProfileSyntaxSupported( array $profile ) {
		if ( !isset( $profile['unsupported_syntax'] ) ) {
			return true;
		}

		foreach ( $profile['unsupported_syntax'] as $reject ) {
			if ( $this->context->isSyntaxUsed( $reject ) ) {
				return false;
			}
		}

		return true;
	}
}
