<?php

namespace CirrusSearch\Parser\AST;

use CirrusSearch\Parser\AST\Visitor\Visitor;
use Wikimedia\Assert\Assert;

/**
 * A "namespace header" in the query.
 * Queries can be prefixed with a namespace header that allows to bypass
 * namespaces selection made with API params or Special:Search UI.
 * e.g.:
 * - help:foobar
 *
 * will search foobar in NS_HELP no matter what is selected previously.
 */
class NamespaceHeaderNode extends ParsedNode {
	/**
	 * @var string|int "all" or a int
	 */
	private $namespace;

	/**
	 * @param int $startOffset
	 * @param int $endOffset
	 * @param int|string $namespace "all" or a int.
	 */
	public function __construct( $startOffset, $endOffset, $namespace ) {
		parent::__construct( $startOffset, $endOffset );
		Assert::parameter( is_int( $namespace ) || $namespace === 'all',
			'$namespace', 'must be null, an integer or a string equals to "all"' );
		$this->namespace = $namespace;
	}

	/**
	 * @return array
	 */
	public function toArray() {
		return [
			'namespaceHeader' => array_merge( parent::baseParams(), [
				'namespace' => $this->namespace
			] )
		];
	}

	/**
	 * @return int|string
	 */
	public function getNamespace() {
		return $this->namespace;
	}

	/**
	 * @param Visitor $visitor
	 */
	public function accept( Visitor $visitor ) {
		$visitor->visitNamespaceHeader( $this );
	}
}
