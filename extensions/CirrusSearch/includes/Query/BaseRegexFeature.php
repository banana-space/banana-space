<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Extra\Query\SourceRegex;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\Fetch\FetchPhaseConfigBuilder;
use CirrusSearch\Search\Fetch\HighlightedField;
use CirrusSearch\Search\Fetch\HighlightFieldGenerator;
use CirrusSearch\Search\Filters;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\SearchConfig;
use CirrusSearch\WarningCollector;
use Elastica\Query\AbstractQuery;
use Wikimedia\Assert\Assert;

/**
 * Base class supporting regex searches. Works best when combined with the
 * wikimedia-extra plugin for elasticsearch, but can also fallback to a groovy
 * based implementation. Can be really expensive, but mostly ok if you have the
 * extra plugin enabled.
 *
 * Examples:
 *   insource:/abc?/
 *
 * @see SourceRegex
 */
abstract class BaseRegexFeature extends SimpleKeywordFeature implements FilterQueryFeature, HighlightingFeature {
	/**
	 * @var string[] Elasticsearch field(s) to search against
	 */
	private $fields;

	/**
	 * @var bool Is this feature enabled?
	 */
	private $enabled;

	/**
	 * @var string Locale used for case conversions. It's important that this
	 *  matches the locale used for lowercasing in the ngram index.
	 */
	private $languageCode;

	/**
	 * @var string[] Configuration flags for the regex plugin
	 */
	private $regexPlugin;

	/**
	 * @var int The maximum number of automaton states that Lucene's regex
	 * compilation can expand to (even temporarily). Provides protection
	 * against overloading the search cluster. Only works when using the
	 * extra plugin, groovy based execution is unbounded.
	 */
	private $maxDeterminizedStates;

	/**
	 * @var string $shardTimeout timeout for regex queries
	 * with the extra plugin
	 */
	private $shardTimeout;

	/**
	 * @param SearchConfig $config
	 * @param string[] $fields
	 */
	public function __construct( SearchConfig $config, array $fields ) {
		$this->enabled = $config->get( 'CirrusSearchEnableRegex' );
		$this->languageCode = $config->get( 'LanguageCode' );
		$this->regexPlugin = $config->getElement( 'CirrusSearchWikimediaExtraPlugin', 'regex' );
		$this->maxDeterminizedStates = $config->get( 'CirrusSearchRegexMaxDeterminizedStates' );
		Assert::precondition( $fields !== [], 'must have at least one field' );
		$this->fields = $fields;
		$this->shardTimeout = $config->getElement( 'CirrusSearchSearchShardTimeout', 'regex' );
	}

	/**
	 * @return string[][]
	 */
	public function getValueDelimiters() {
		return [
			[
				// simple search
				'delimiter' => '"'
			],
			[
				// regex searches
				'delimiter' => '/',
				// optional case insensitive suffix
				'suffixes' => 'i'
			]
		];
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
		if ( $valueDelimiter === '/' ) {
			if ( !$this->enabled ) {
				$warningCollector->addWarning( 'cirrussearch-feature-not-available', "$key regex" );
			}
			return [
				'type' => 'regex',
				'pattern' => trim( $quotedValue, '/' ),
				'insensitive' => $suffix === 'i',
			];
		}
		return parent::parseValue( $key, $value, $quotedValue, $valueDelimiter, $suffix, $warningCollector );
	}

	/**
	 * @param string $key
	 * @param string $valueDelimiter
	 * @return string
	 */
	public function getFeatureName( $key, $valueDelimiter ) {
		if ( $valueDelimiter === '/' ) {
			return 'regex';
		}
		return parent::getFeatureName( $key, $valueDelimiter );
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @return CrossSearchStrategy
	 */
	public function getCrossSearchStrategy( KeywordFeatureNode $node ) {
		if ( $node->getDelimiter() === '/' ) {
			return CrossSearchStrategy::hostWikiOnlyStrategy();
		} else {
			return CrossSearchStrategy::allWikisStrategy();
		}
	}

	/**
	 * @param SearchContext $context
	 * @param string $key
	 * @param string $value
	 * @param string $quotedValue
	 * @param bool $negated
	 * @param string $delimiter
	 * @param string $suffix
	 * @return array
	 */
	public function doApplyExtended( SearchContext $context, $key, $value, $quotedValue, $negated, $delimiter, $suffix ) {
		$parsedValue = $this->parseValue( $key, $value, $quotedValue, $delimiter, $suffix, $context );
		if ( $this->isRegexQuery( $parsedValue ) ) {
			if ( !$this->enabled ) {
				return [ null, false ];
			}
			'@phan-var array $parsedValue';
			$pattern = $parsedValue['pattern'];
			$insensitive = $parsedValue['insensitive'];

			$filter = $this->buildRegexQuery( $pattern, $insensitive );
			if ( !$negated ) {
				$this->configureHighlighting( $pattern, $insensitive, $context->getFetchPhaseBuilder() );
			}
			return [ $filter, false ];
		} else {
			return $this->doApply( $context, $key, $value, $quotedValue, $negated );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		$parsedValue = $node->getParsedValue();
		if ( $this->isRegexQuery( $parsedValue ) ) {
			if ( !$this->enabled ) {
				return null;
			}
			'@phan-var array $parsedValue';
			$pattern = $parsedValue['pattern'];
			$insensitive = $parsedValue['insensitive'];
			return $this->buildRegexQuery( $pattern, $insensitive );
		} else {
			return $this->getNonRegexFilterQuery( $node, $context );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function buildHighlightFields( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		$parsedValue = $node->getParsedValue();
		if ( $this->isRegexQuery( $parsedValue ) ) {
			if ( !$this->enabled ) {
				return [];
			}
			'@phan-var array $parsedValue';
			$pattern = $parsedValue['pattern'];
			$insensitive = $parsedValue['insensitive'];
			return $this->doGetRegexHLFields( $context->getHighlightFieldGenerator(), $pattern, $insensitive );
		}
		return $this->buildNonRegexHLFields( $node, $context );
	}

	/**
	 * Obtain the filter when the keyword is used in non regex mode.
	 * This method will be called on syntax like keyword:word or keyword:"phrase"
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	abstract protected function getNonRegexFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context );

	/**
	 * @param string $pattern
	 * @param bool $insensitive
	 * @return AbstractQuery
	 */
	private function buildRegexQuery( $pattern, $insensitive ) {
		return $this->regexPlugin && in_array( 'use', $this->regexPlugin )
			? $this->buildRegexWithPlugin( $pattern, $insensitive )
			: $this->buildRegexWithGroovy( $pattern, $insensitive );
	}

	/**
	 * @param string $pattern
	 * @param bool $insensitive
	 * @param FetchPhaseConfigBuilder $fetchPhaseConfigBuilder
	 */
	private function configureHighlighting( $pattern, $insensitive, FetchPhaseConfigBuilder $fetchPhaseConfigBuilder ) {
		foreach ( $this->doGetRegexHLFields( $fetchPhaseConfigBuilder, $pattern, $insensitive ) as $f ) {
			$fetchPhaseConfigBuilder->addHLField( $f );
		}
	}

	/**
	 * @param HighlightFieldGenerator $generator
	 * @param string $pattern
	 * @param bool $insensitive
	 * @return HighlightedField[]
	 */
	private function doGetRegexHLFields( HighlightFieldGenerator $generator, $pattern, $insensitive ) {
		$fields = [];
		if ( !$generator->supportsRegexFields() ) {
			return $fields;
		}
		foreach ( $this->fields as $field => $hlTarget ) {
			$fields[] = $generator->newRegexField( "$field.plain", $hlTarget,
				$pattern, $insensitive, HighlightedField::COSTLY_EXPERT_SYNTAX_PRIORITY );
		}
		return $fields;
	}

	/**
	 * Builds a regular expression query using the wikimedia-extra plugin.
	 *
	 * @param string $pattern The regular expression to match
	 * @param bool $insensitive Should the match be case insensitive?
	 * @return AbstractQuery Regular expression query
	 */
	private function buildRegexWithPlugin( $pattern, $insensitive ) {
		$filters = [];
		// TODO: Update plugin to accept multiple values for the field property
		// so that at index time we can create a single trigram index with
		// copy_to instead of creating multiple queries.
		foreach ( $this->fields as $field => $hlTarget ) {
			$filter = new SourceRegex( $pattern, $field, $field . '.trigram' );
			// set some defaults
			$filter->setMaxDeterminizedStates( $this->maxDeterminizedStates );
			if ( isset( $this->regexPlugin['max_ngrams_extracted'] ) && is_numeric( $this->regexPlugin['max_ngrams_extracted'] ) ) {
				$filter->setMaxNgramsExtracted( (int)$this->regexPlugin['max_ngrams_extracted'] );
			}
			if ( isset( $this->regexPlugin['max_ngram_clauses'] ) && is_numeric( $this->regexPlugin['max_ngram_clauses'] ) ) {
				$filter->setMaxNgramClauses( (int)$this->regexPlugin['max_ngram_clauses'] );
			}
			$filter->setCaseSensitive( !$insensitive );
			$filter->setLocale( $this->languageCode );

			$filters[] = $filter;
		}

		return Filters::booleanOr( $filters );
	}

	/**
	 * Builds a regular expression query using groovy. It's significantly less
	 * good than the wikimedia-extra plugin, but it's something.
	 *
	 * @param string $pattern The regular expression to match
	 * @param bool $insensitive Should the match be case insensitive?
	 * @return AbstractQuery Regular expression query
	 */
	private function buildRegexWithGroovy( $pattern, $insensitive ) {
		$filters = [];
		foreach ( $this->fields as $field ) {
			$script = <<<GROOVY
import org.apache.lucene.util.automaton.*;
sourceText = _source.get("{$field}");
if (sourceText == null) {
    false;
} else {
    if (automaton == null) {
        if (insensitive) {
            locale = new Locale(language);
            pattern = pattern.toLowerCase(locale);
        }
        regexp = new RegExp(pattern, RegExp.ALL ^ RegExp.AUTOMATON);
        automaton = new CharacterRunAutomaton(regexp.toAutomaton());
    }
    if (insensitive) {
        sourceText = sourceText.toLowerCase(locale);
    }
    automaton.run(sourceText);
}

GROOVY;

			$filters[] = new \Elastica\Query\Script( new \Elastica\Script\Script(
				$script,
				[
					'pattern' => '.*(' . $pattern . ').*',
					'insensitive' => $insensitive,
					'language' => $this->languageCode,
					// The null here creates a slot in which the script will shove
					// an automaton while executing.
					'automaton' => null,
					'locale' => null,
				],
				'groovy'
			) );
		}

		return Filters::booleanOr( $filters );
	}

	abstract public function buildNonRegexHLFields( KeywordFeatureNode $node, QueryBuildingContext $context );

	/**
	 * @param array|null $parsedValue
	 * @return bool
	 */
	private function isRegexQuery( array $parsedValue = null ) {
		return is_array( $parsedValue ) && isset( $parsedValue['type'] ) &&
			   $parsedValue['type'] === 'regex';
	}
}
