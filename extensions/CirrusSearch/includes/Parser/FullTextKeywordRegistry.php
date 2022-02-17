<?php

namespace CirrusSearch\Parser;

use CirrusSearch\Query\BoostTemplatesFeature;
use CirrusSearch\Query\ContentModelFeature;
use CirrusSearch\Query\DeepcatFeature;
use CirrusSearch\Query\FileNumericFeature;
use CirrusSearch\Query\FileTypeFeature;
use CirrusSearch\Query\HasTemplateFeature;
use CirrusSearch\Query\InCategoryFeature;
use CirrusSearch\Query\InSourceFeature;
use CirrusSearch\Query\InTitleFeature;
use CirrusSearch\Query\KeywordFeature;
use CirrusSearch\Query\LanguageFeature;
use CirrusSearch\Query\LegacyKeywordFeature;
use CirrusSearch\Query\LinksToFeature;
use CirrusSearch\Query\LocalFeature;
use CirrusSearch\Query\MoreLikeFeature;
use CirrusSearch\Query\MoreLikeThisFeature;
use CirrusSearch\Query\PreferRecentFeature;
use CirrusSearch\Query\PrefixFeature;
use CirrusSearch\Query\SimpleKeywordFeature;
use CirrusSearch\Query\SubPageOfFeature;
use CirrusSearch\Query\TextFieldFilterFeature;
use CirrusSearch\SearchConfig;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Sparql\SparqlClient;

/**
 * Registry of keywords suited for fulltext searches
 */
class FullTextKeywordRegistry implements KeywordRegistry {
	/**
	 * @var (LegacyKeywordFeature|KeywordFeature)[]
	 */
	private $features;

	/**
	 * FullTextKeywordRegistry constructor.
	 * @param SearchConfig $config
	 * @param NamespacePrefixParser|null $namespacePrefixParser
	 * @param SparqlClient|null $client
	 * @throws \FatalError
	 * @throws \MWException
	 */
	public function __construct( SearchConfig $config, NamespacePrefixParser $namespacePrefixParser = null, SparqlClient $client = null ) {
		$this->features = [
			// Handle morelike keyword (greedy). Kept for BC reasons with existing clients.
			// The morelikethis keyword should be preferred.
			new MoreLikeFeature( $config ),
			// Handle title prefix notation (greedy). Kept for BC reasons with existing clients.
			// The subpageof keyword should be preferred.
			new PrefixFeature( $namespacePrefixParser ),
			// Handle prefer-recent keyword
			new PreferRecentFeature( $config ),
			// Handle local keyword
			new LocalFeature(),
			// Handle boost-templates keyword
			new BoostTemplatesFeature(),
			// Handle hastemplate keyword
			new HasTemplateFeature(),
			// Handle linksto keyword
			new LinksToFeature(),
			// Handle incategory keyword
			new InCategoryFeature( $config ),
			// Handle non-regex insource keyword
			new InSourceFeature( $config ),
			// Handle intitle keyword
			new InTitleFeature( $config ),
			// inlanguage keyword
			new LanguageFeature(),
			// File types
			new FileTypeFeature( $config ),
			// File mime types
			new TextFieldFilterFeature( 'filemime', 'file_mime' ),
			// File numeric characteristics - size, resolution, etc.
			new FileNumericFeature(),
			// Content model feature
			new ContentModelFeature(),
			// subpageof keyword
			new SubPageOfFeature(),
			// deepcat feature
			new DeepcatFeature( $config, $client ),
			// morelikethis feature: a non-greedy version of the morelike keyword.
			new MoreLikeThisFeature( $config )
		];

		$extraFeatures = [];
		\Hooks::run( 'CirrusSearchAddQueryFeatures', [ $config, &$extraFeatures ] );
		// @phan-suppress-next-line PhanEmptyForeach May be set by hook
		foreach ( $extraFeatures as $extra ) {
			if ( $extra instanceof SimpleKeywordFeature ) {
				$this->features[] = $extra;
			} else {
				LoggerFactory::getInstance( 'CirrusSearch' )
					->warning( 'Skipped invalid feature of class ' . get_class( $extra ) .
							   ' - should be instanceof SimpleKeywordFeature' );
			}
		}
	}

	/**
	 * @return KeywordFeature[]
	 */
	public function getKeywords() {
		return $this->features;
	}
}
