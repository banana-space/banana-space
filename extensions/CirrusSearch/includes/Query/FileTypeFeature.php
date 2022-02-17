<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Config;
use Elastica\Query;
use Elastica\Query\AbstractQuery;

/**
 * File type features:
 *  filetype:bitmap
 * Types can be OR'd together:
 *  filetype:bitmap|png
 * Selects only files of these specified features.
 */
class FileTypeFeature extends SimpleKeywordFeature implements FilterQueryFeature {
	const MAX_CONDITIONS = 20;

	/** @var string[] Map of aliases from user provided term to term to search for */
	private $aliases;

	public function __construct( Config $config ) {
		$this->aliases = $config->get( 'CirrusSearchFiletypeAliases' );
	}

	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'filetype' ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		return CrossSearchStrategy::allWikisStrategy();
	}

	/**
	 * @param SearchContext $context
	 * @param string $key The keyword
	 * @param string $value The value attached to the keyword with quotes stripped
	 * @param string $quotedValue The original value in the search string, including quotes
	 *     if used
	 * @param bool $negated Is the search negated? Not used to generate the returned
	 *     AbstractQuery, that will be negated as necessary. Used for any other building/context
	 *     necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$parsed = $this->parseValue( $key, $value, $quotedValue, '', '', $context );
		'@phan-var array $parsed';
		$query = $this->matchFileType( array_merge( $parsed['aliased'], $parsed['user_types'] ) );

		return [ $query, false ];
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array|false|null
	 */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector ) {
		// TODO: The explode/count/warn is repeated elsewhere and should be generalized?
		$types = explode( '|', $value );
		if ( count( $types ) > self::MAX_CONDITIONS ) {
			$warningCollector->addWarning(
				'cirrussearch-feature-too-many-conditions',
				$key,
				self::MAX_CONDITIONS
			);
			$types = array_slice(
				$types,
				0,
				self::MAX_CONDITIONS
			);
		}

		$aliased = [];
		foreach ( $types as $type ) {
			$lcType = mb_strtolower( $type );
			if ( isset( $this->aliases[$lcType] ) ) {
				$aliased[] = $this->aliases[$lcType];
			}
		}

		return [
			'user_types' => $types,
			'aliased' => $aliased,
		];
	}

	/**
	 * @param string[] $types
	 * @return Query\BoolQuery|Query\MatchQuery|null
	 */
	protected function matchFileType( $types ) {
		$queries = [];
		foreach ( $types as $type ) {
			$queries[] = new Query\MatchQuery( 'file_media_type', [ 'query' => $type ] );
		}

		return Filters::booleanOr( $queries, false );
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		$parsed = $node->getParsedValue();
		'@phan-var array $parsed';
		return $this->matchFileType( array_merge( $parsed['aliased'], $parsed['user_types'] ) );
	}
}
