<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;
use Wikimedia\Assert\Assert;

class FuzzyNode extends ParsedNode {

	/**
	 * @var string the word
	 */
	private $word;

	/**
	 * @var int $fuzziness 0, 1 or 2. -1 meaning that it was not provided by the user
	 */
	private $fuzziness;

	/**
	 * @param int $start
	 * @param int $end
	 * @param string $word
	 * @param int $fuzziness
	 */
	public function __construct( $start, $end, $word, $fuzziness ) {
		parent::__construct( $start, $end );
		Assert::parameter( is_int( $fuzziness ) && $fuzziness >= -1 && $fuzziness < 3,
			'fuzziness', ' must be an integer in the [-1,2] range' );
		$this->word = $word;
		$this->fuzziness = $fuzziness;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [ 'fuzzy' => array_merge( parent::baseParams(), [
			'word' => $this->word,
			'fuzziness' => $this->fuzziness,
		] ) ];
	}

	/**
	 * @return string the fuzzy word to search
	 */
	public function getWord() {
		return $this->word;
	}

	/**
	 * @return int
	 */
	public function getFuzziness() {
		return $this->fuzziness;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitFuzzyNode( $this );
	}
}
