<?php

namespace CirrusSearch\Query;

use CirrusSearch\CrossSearchStrategy;
use CirrusSearch\Parser\AST\KeywordFeatureNode;
use CirrusSearch\Query\Builder\QueryBuildingContext;
use CirrusSearch\Search\SearchContext;
use CirrusSearch\WarningCollector;
use Elastica\Query;
use Elastica\Query\AbstractQuery;
use Wikimedia\Assert\Assert;

/**
 * File features:
 *  filebits:16  - bit depth
 *  filesize:>300 - size >= 300 kb
 *  filew:100,300 - search of 100 <= file_width <= 300
 * Selects only files of these specified features.
 */
class FileNumericFeature extends SimpleKeywordFeature implements FilterQueryFeature {
	/**
	 * @return string[]
	 */
	protected function getKeywords() {
		return [ 'filesize', 'filebits', 'fileh', 'filew', 'fileheight', 'filewidth', 'fileres' ];
	}

	/**
	 * Map from feature names to keys
	 * @var string[]
	 */
	private $keyTable = [
		'filesize' => 'file_size',
		'filebits' => 'file_bits',
		'fileh' => 'file_height',
		'filew' => 'file_width',
		'fileheight' => 'file_height',
		'filewidth' => 'file_width',
		'fileres' => 'file_resolution',
	];

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
		$query = $this->doGetFilterQuery( $key,
			$this->parseValue( $key, $value, $quotedValue, '', '', $context ) );
		if ( $query === null ) {
			$context->setResultsPossible( false );
		}

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
		$parsedValue = [];

		$field = $this->keyTable[$key];
		$parsedValue['field'] = $field;
		list( $sign, $number ) = $this->extractSign( $value );
		// filesize treats no sign as >, since exact file size matches make no sense
		if ( !$sign && $key === 'filesize' && strpos( $number, ',' ) === false ) {
			$sign = 1;
		}

		$parsedValue['sign'] = $sign;

		if ( $sign && strpos( $number, ',' ) !== false ) {
			$warningCollector->addWarning(
				'cirrussearch-file-numeric-feature-multi-argument-w-sign',
				$key,
				$number
			);
			return null;
		} elseif ( $sign || strpos( $number, ',' ) === false ) {
			if ( !is_numeric( $number ) ) {
				$this->nanWarning( $warningCollector, $key, empty( $number ) ? $value : $number );
				return null;
			}
			$parsedValue['value'] = intval( $number );
		} else {
			$numbers = explode( ',', $number, 2 );
			$valid = true;
			if ( !is_numeric( $numbers[0] ) ) {
				$this->nanWarning( $warningCollector, $key, $numbers[0] );
				$valid = false;
			}

			if ( !is_numeric( $numbers[1] ) ) {
				$this->nanWarning( $warningCollector, $key, $numbers[1] );
				$valid = false;
			}
			if ( !$valid ) {
				return null;
			}
			$parsedValue['range'] = [ intval( $numbers[0] ), intval( $numbers[1] ) ];
		}

		return $parsedValue;
	}

	/**
	 * Extract sign prefix which can be < or > or nothing.
	 * @param string $value
	 * @param int $default
	 * @return array Two element array, first the sign: 0 is equal, 1 is more, -1 is less,
	 *  then the number to be compared.
	 */
	protected function extractSign( $value, $default = 0 ) {
		if ( $value[0] == '>' || $value[0] == '<' ) {
			$sign = ( $value[0] == '>' ) ? 1 : - 1;
			return [ $sign, substr( $value, 1 ) ];
		} else {
			return [ $default, $value ];
		}
	}

	/**
	 * Adds a warning to the search context that the $key keyword
	 * was provided with the invalid value $notANumber.
	 *
	 * @param WarningCollector $warningCollector
	 * @param string $key
	 * @param string $notANumber
	 */
	protected function nanWarning( WarningCollector $warningCollector, $key, $notANumber ) {
		$warningCollector->addWarning(
			'cirrussearch-file-numeric-feature-not-a-number',
			$key,
			$notANumber
		);
	}

	/**
	 * @param string $field
	 * @param int $from
	 * @param int $to
	 * @param int $multiplier
	 * @return Query\AbstractQuery
	 */
	private function buildBoundedIntervalQuery( $field, $from, $to, $multiplier = 1 ) {
		return new Query\Range( $field, [
			'gte' => $from * $multiplier,
			'lte' => $to * $multiplier
		] );
	}

	/**
	 * @param string $field
	 * @param int $sign
	 * @param int $value
	 * @param int $multiplier
	 * @return Query\AbstractQuery
	 */
	private function buildIntervalQuery( $field, $sign, $value, $multiplier = 1 ) {
		Assert::parameter( $sign != 0, 'sign', 'sign must be non zero' );
		if ( $sign > 0 ) {
			$range = [ 'gte' => $value * $multiplier ];
		} else {
			$range = [ 'lte' => $value * $multiplier ];
		}
		return new Query\Range( $field, $range );
	}

	/**
	 * @param string $field
	 * @param int $value
	 * @param int $multiplier
	 * @return Query\AbstractQuery
	 */
	private function buildMatchQuery( $field, $value, $multiplier = 1 ) {
		$query = new Query\MatchQuery();
		$query->setFieldQuery( $field, (string)( $value * $multiplier ) );
		return $query;
	}

	/**
	 * @param string $key
	 * @param array $parsedValue
	 * @return Query\AbstractQuery|null
	 */
	protected function doGetFilterQuery( $key, $parsedValue ) {
		if ( $parsedValue === null ) {
			return null;
		}
		$field = $parsedValue['field'];
		$sign = $parsedValue['sign'];
		$multiplier = ( $key === 'filesize' ) ? 1024 : 1;

		if ( isset( $parsedValue['range'] ) ) {
			$query =
				$this->buildBoundedIntervalQuery( $parsedValue['field'], $parsedValue['range'][0],
					$parsedValue['range'][1], $multiplier );
		} elseif ( $sign === 0 ) {
			$query = $this->buildMatchQuery( $field, $parsedValue['value'], $multiplier );
		} else {
			$query = $this->buildIntervalQuery( $field, $sign, $parsedValue['value'], $multiplier );
		}

		return $query;
	}

	/**
	 * @param KeywordFeatureNode $node
	 * @param QueryBuildingContext $context
	 * @return AbstractQuery|null
	 */
	public function getFilterQuery( KeywordFeatureNode $node, QueryBuildingContext $context ) {
		return $this->doGetFilterQuery( $node->getKey(), $node->getParsedValue() );
	}
}
