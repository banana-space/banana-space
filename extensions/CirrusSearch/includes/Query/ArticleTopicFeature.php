<?php

namespace CirrusSearch\Query;

use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use CirrusSearch\Wikimedia\ORESArticleTopicsHooks;
use Elastica\Query\DisMax;
use Elastica\Query\Term;
use Message;

/**
 * Finds pages based on how well they match a given topic, based on scores provided by the
 * (Wikimedia-specific) articletopic ORES model.
 * @package CirrusSearch\Wikimedia
 * @see ORESArticleTopicsHooks
 * @see https://www.mediawiki.org/wiki/Help:CirrusSearch#Articletopic
 */
class ArticleTopicFeature extends SimpleKeywordFeature {

	public const TERMS_TO_LABELS = [
		'biography' => 'Culture.Biography.Biography*',
		'women' => 'Culture.Biography.Women',
		'food-and-drink' => 'Culture.Food and drink',
		'internet-culture' => 'Culture.Internet culture',
		'linguistics' => 'Culture.Linguistics',
		'literature' => 'Culture.Literature',
		'books' => 'Culture.Media.Books',
		'entertainment' => 'Culture.Media.Entertainment',
		'films' => 'Culture.Media.Films',
		'media' => 'Culture.Media.Media*',
		'music' => 'Culture.Media.Music',
		'radio' => 'Culture.Media.Radio',
		'software' => 'Culture.Media.Software',
		'television' => 'Culture.Media.Television',
		'video-games' => 'Culture.Media.Video games',
		'performing-arts' => 'Culture.Performing arts',
		'philosophy-and-religion' => 'Culture.Philosophy and religion',
		'sports' => 'Culture.Sports',
		'architecture' => 'Culture.Visual arts.Architecture',
		'comics-and-anime' => 'Culture.Visual arts.Comics and Anime',
		'fashion' => 'Culture.Visual arts.Fashion',
		'visual-arts' => 'Culture.Visual arts.Visual arts*',
		'geographical' => 'Geography.Geographical',
		'africa' => 'Geography.Regions.Africa.Africa*',
		'central-africa' => 'Geography.Regions.Africa.Central Africa',
		'eastern-africa' => 'Geography.Regions.Africa.Eastern Africa',
		'northern-africa' => 'Geography.Regions.Africa.Northern Africa',
		'southern-africa' => 'Geography.Regions.Africa.Southern Africa',
		'western-africa' => 'Geography.Regions.Africa.Western Africa',
		'central-america' => 'Geography.Regions.Americas.Central America',
		'north-america' => 'Geography.Regions.Americas.North America',
		'south-america' => 'Geography.Regions.Americas.South America',
		'asia' => 'Geography.Regions.Asia.Asia*',
		'central-asia' => 'Geography.Regions.Asia.Central Asia',
		'east-asia' => 'Geography.Regions.Asia.East Asia',
		'north-asia' => 'Geography.Regions.Asia.North Asia',
		'south-asia' => 'Geography.Regions.Asia.South Asia',
		'southeast-asia' => 'Geography.Regions.Asia.Southeast Asia',
		'west-asia' => 'Geography.Regions.Asia.West Asia',
		'eastern-europe' => 'Geography.Regions.Europe.Eastern Europe',
		'europe' => 'Geography.Regions.Europe.Europe*',
		'northern-europe' => 'Geography.Regions.Europe.Northern Europe',
		'southern-europe' => 'Geography.Regions.Europe.Southern Europe',
		'western-europe' => 'Geography.Regions.Europe.Western Europe',
		'oceania' => 'Geography.Regions.Oceania',
		'business-and-economics' => 'History and Society.Business and economics',
		'education' => 'History and Society.Education',
		'history' => 'History and Society.History',
		'military-and-warfare' => 'History and Society.Military and warfare',
		'politics-and-government' => 'History and Society.Politics and government',
		'society' => 'History and Society.Society',
		'transportation' => 'History and Society.Transportation',
		'biology' => 'STEM.Biology',
		'chemistry' => 'STEM.Chemistry',
		'computing' => 'STEM.Computing',
		'earth-and-environment' => 'STEM.Earth and environment',
		'engineering' => 'STEM.Engineering',
		'libraries-and-information' => 'STEM.Libraries & Information',
		'mathematics' => 'STEM.Mathematics',
		'medicine-and-health' => 'STEM.Medicine & Health',
		'physics' => 'STEM.Physics',
		'stem' => 'STEM.STEM*',
		'space' => 'STEM.Space',
		'technology' => 'STEM.Technology',
	];

	/**
	 * Helper method for turning raw ORES score data (as stored in the Cirrus document) into
	 * search terms, for analytics/debugging.
	 * @param array $rawTopicData The contents of the document's ores_articletopics field
	 * @return array corresponding search term => ORES score (rounded to three decimals)
	 */
	public static function getTopicScores( array $rawTopicData ): array {
		$labelsToTerms = array_flip( self::TERMS_TO_LABELS );
		$topicScores = [];
		foreach ( $rawTopicData as $rawTopic ) {
			list( $oresLabel, $scaledScore ) = explode( '|', $rawTopic );
			$topicId = $labelsToTerms[$oresLabel];
			$topicScores[$topicId] = (int)$scaledScore / 1000;
		}
		return $topicScores;
	}

	/**
	 * @inheritDoc
	 * @phan-return array{topics:string[]}
	 */
	public function parseValue(
		$key, $value, $quotedValue, $valueDelimiter, $suffix, WarningCollector $warningCollector
	) {
		$topics = explode( '|', $value );
		$invalidTopics = array_diff( $topics, array_keys( self::TERMS_TO_LABELS ) );
		$validTopics = array_filter( array_map( function ( $topic ) {
			return self::TERMS_TO_LABELS[$topic];
		}, array_diff( $topics, $invalidTopics ) ) );

		if ( $invalidTopics ) {
			$warningCollector->addWarning( 'cirrussearch-articletopic-invalid-topic',
				Message::listParam( $invalidTopics, 'comma' ), count( $invalidTopics ) );
		}
		return [ 'topics' => $validTopics ];
	}

	/** @inheritDoc */
	protected function getKeywords() {
		return [ 'articletopic' ];
	}

	/** @inheritDoc */
	protected function doApply( SearchContext $context, $key, $value, $quotedValue, $negated ) {
		$topics = $this->parseValue( $key, $value, $quotedValue, '', '', $context )['topics'];
		if ( $topics === [] ) {
			$context->setResultsPossible( false );
			return [ null, true ];
		}

		$query = new DisMax();
		foreach ( $topics as $topic ) {
			$topicQuery = new Term();
			$topicQuery->setTerm( ORESArticleTopicsHooks::FIELD_NAME, $topic );
			$query->addQuery( $topicQuery );
		}

		if ( !$negated ) {
			$context->addNonTextQuery( $query );
			return [ null, false ];
		} else {
			return [ $query, false ];
		}
	}

}
