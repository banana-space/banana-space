<?php

namespace CirrusSearch\Search;

use CirrusSearch\SearchConfig;
use SearchIndexField;

/**
 * Index field representing keyword.
 * Keywords use special analyzer.
 * @package CirrusSearch
 */
class KeywordIndexField extends CirrusIndexField {
	/**
	 * Using text type here since it's better for our purposes than native
	 * keyword type.
	 * @var string
	 */
	protected $typeName = 'text';
	/**
	 * @var bool
	 */
	private $caseSensitiveSubfield;

	public function __construct( $name, $type, SearchConfig $config, bool $caseSensitiveSubfield = false ) {
		parent::__construct( $name, $type, $config );
		if ( $caseSensitiveSubfield ) {
			$this->setFlag( SearchIndexField::FLAG_CASEFOLD );
		}
		$this->caseSensitiveSubfield = $caseSensitiveSubfield;
	}

	/**
	 * Maximum number of characters allowed in keyword terms.
	 */
	const KEYWORD_IGNORE_ABOVE = 5000;

	public function getMapping( \SearchEngine $engine ) {
		$config = parent::getMapping( $engine );
		$config['analyzer'] =
			$this->checkFlag( self::FLAG_CASEFOLD ) ? 'lowercase_keyword' : 'keyword';
		$config += [
			'norms' => false,
			// Omit the length norm because there is only even one token
			'index_options' => 'docs',
		];
		if ( $this->caseSensitiveSubfield ) {
			$config['fields']['keyword'] = [
				'type' => 'text',
				'analyzer' => 'keyword',
				'index_options' => 'docs',
				'norms' => false,
			];
		}
		return $config;
	}
}
