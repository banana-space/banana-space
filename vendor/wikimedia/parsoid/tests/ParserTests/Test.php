<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\ParserTests;

use Wikimedia\Parsoid\Utils\PHPUtils;

/**
 * Represents a parser test
 */
class Test extends Item {
	/* --- These are test properties from the test file --- */
	/** @var array */
	public $options = [];

	/** @var array */
	public $changes = [];

	/** @var string This is the test name, not page title for the test */
	public $title = null;

	/** @var string */
	public $wikitext = null;

	/** @var string */
	public $html = null;

	/** @var array */
	public $altHtmlSections = [];

	/** @var array */
	public $altWtSections = [];

	/* --- The rest below are computed while running tests -- */

	/** @var string */
	private $pageName;

	/** @var int */
	private $pageNs;

	/** @var array */
	public $selserChangeTrees = [];

	/** @var array */
	public $changetree = null;

	/** @var bool */
	public $duplicateChange = false;

	/** @var string */
	public $seed = null;

	/** @var string */
	public $resultWT = null;

	/** @var bool */
	public $wt2wtPassed = null;

	/** @var string */
	public $wt2wtResult = null;

	/** @var string */
	public $selser = null;

	/** @var string */
	public $changedHTMLStr = null;

	/** @var string */
	public $cachedBODYstr = null;

	/** @var string */
	public $cachedNormalizedHTML = null;

	/** @var array */
	public $time = [];

	private const ALT_WT_KEYS = [ 'wikitext/edited' ];
	private const ALT_HTML_KEYS = [
		'html/*', 'html/*+tidy', 'html+tidy', 'html/parsoid', 'html/parsoid+langconv'
	];

	/**
	 * @param array $testProperties key-value mapping of properties
	 */
	public function __construct( array $testProperties ) {
		parent::__construct( $testProperties['type'] );
		foreach ( $testProperties as $key => $value ) {
			if ( in_array( $key, self::ALT_HTML_KEYS, true ) ) {
				$this->altHtmlSections[$key] = $value;
			} elseif ( in_array( $key, self::ALT_WT_KEYS, true ) ) {
				$this->altWtSections[$key] = $value;
			} else {
				$this->$key = $value;
			}
		}

		if ( isset( $this->options['parsoid'] ) ) {
			$this->options['parsoid'] =
				PHPUtils::jsonDecode( PHPUtils::jsonEncode( $this->options['parsoid'] ) );
		}
	}

	/**
	 * @param array $testFilter Test Filter as set in TestRunner
	 * @return bool if test matches the filter
	 */
	public function matchesFilter( $testFilter ): bool {
		if ( !$testFilter ) {
			return true; // Trivial match
		}

		if ( !empty( $testFilter['regex'] ) ) {
			return (bool)preg_match( '/' . $testFilter['raw'] . '/', $this->title );
		}

		if ( !empty( $testFilter['string'] ) ) {
			return strpos( $this->title, $testFilter['raw'] ) !== false;
		}

		return true; // Trivial match because of a bad test filter
	}

	/**
	 * @return string
	 */
	public function pageName(): string {
		if ( !$this->pageName ) {
			$this->pageName = $this->options['title'] ?? 'Parser test';
			if ( is_array( $this->pageName ) ) {
				$this->pageName = 'Parser test';
			}
		}

		return $this->pageName;
	}

}
