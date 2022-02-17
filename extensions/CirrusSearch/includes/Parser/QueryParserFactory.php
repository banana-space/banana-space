<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Parser\QueryStringRegex\QueryStringRegexParser;
use CirrusSearch\Search\Escaper;
use CirrusSearch\SearchConfig;
use MediaWiki\Sparql\SparqlClient;

/**
 * Simple factory to create QueryParser instance based on the host wiki config.
 * @see QueryParser
 */
class QueryParserFactory {

	/**
	 * Get the default fulltext parser.
	 * @param SearchConfig $config the host wiki config
	 * @param NamespacePrefixParser $namespacePrefix
	 * @param SparqlClient|null $client
	 * @return QueryParser
	 * @throws ParsedQueryClassifierException
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public static function newFullTextQueryParser(
		SearchConfig $config,
		NamespacePrefixParser $namespacePrefix,
		SparqlClient $client = null
	) {
		$escaper = new Escaper( $config->get( 'LanguageCode' ), $config->get( 'CirrusSearchAllowLeadingWildcard' ) );
		$repository = new FTQueryClassifiersRepository( $config );
		return new QueryStringRegexParser( new FullTextKeywordRegistry( $config, $namespacePrefix, $client ),
			$escaper, $config->get( 'CirrusSearchStripQuestionMarks' ), $repository, $namespacePrefix,
			$config->get( "CirrusSearchMaxFullTextQueryLength" ) );
	}

}
