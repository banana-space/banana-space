<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Rescore\ByKeywordTemplateBoostFunction;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;

/**
 * Handles the boost-templates keyword in full text search. Allows user
 * to specify a percentage to increase or decrease a search result by based
 * on the templates included in the page. Templates can be specified with
 * spaces or underscores. Multiple templates can be specified. Any value
 * including a space must be quoted.
 *
 * Examples:
 *  boost-templates:Main_article|250%
 *  boost-templates:"Featured sound|150%"
 *  boost-templates:"Main_article|250% List_of_lists|10%"
 */
class BoostTemplatesFeature extends SimpleKeywordFeature implements BoostFunctionFeature {
	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'boost-templates' ];
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
	 * @param string $quotedValue The original value in the search string, including quotes if used
	 * @param bool $negated Is the search negated? Not used to generate the returned AbstractQuery,
	 *  that will be negated as necessary. Used for any other building/context necessary.
	 * @return array Two element array, first an AbstractQuery or null to apply to the
	 *  query. Second a boolean indicating if the quotedValue should be kept in the search
	 *  string.
	 */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$context->addCustomRescoreComponent(
			$this->buildBoostFunction( [ 'boost-templates' => self::parseBoostTemplates( $value ) ] )
		);

		return [ null, false ];
	}

	/**
	 * Parse boosted templates.  Parse failures silently return no boosted templates.
	 * Matches a template name followed by a | then a positive integer followed by a %.
	 * Multiple templates can be specified separated by a space.
	 *
	 * Examples:
	 *   Featured_article|150%
	 *   List of lists|10% Featured_sound|200%
	 *
	 * @param string $text text representation of boosted templates
	 * @return float[] map of boosted templates (key is the template, value is a float).
	 */
	public static function parseBoostTemplates( $text ) {
		$boostTemplates = [];
		$templateMatches = [];
		if ( preg_match_all( '/([^|]+)\|(\d+)% ?/', $text, $templateMatches, PREG_SET_ORDER ) ) {
			foreach ( $templateMatches as $templateMatch ) {
				// templates field is populated with Title::getPrefixedText
				// which will replace _ to ' '. We should do the same here.
				$template = strtr( $templateMatch[ 1 ], '_', ' ' );
				$boostTemplates[ $template ] = floatval( $templateMatch[ 2 ] ) / 100;
			}
		}
		return $boostTemplates;
	}

	/**
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param string $valueDelimiter
	 * @param string $suffix
	 * @param WarningCollector $warningCollector
	 * @return array|false|float[]|null
	 */
	public function parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector ) {
		return [ 'boost-templates' => self::parseBoostTemplates( $value ) ];
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return \CirrusSearch\Search\Rescore\BoostFunctionBuilder|null
	 */
	public function getBoostFunctionBuilder( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->buildBoostFunction( $node->getParsedValue() );
	}

	/**
	 * @param array $parsedValue
	 * @return ByKeywordTemplateBoostFunction
	 */
	private function buildBoostFunction( array $parsedValue ) {
		return new ByKeywordTemplateBoostFunction( $parsedValue['boost-templates'] );
	}
}
