<?php

namespace Flow\Utils;

use BatchRowIterator;
use EchoCallbackIterator;
use Iterator;
use IteratorAggregate;
use RecursiveIteratorIterator;
use Title;
use Wikimedia\Rdbms\IDatabase;

/**
 * Iterates over all titles within the specified namespace. Batches
 * queries into 500 titles at a time starting with the lowest page id.
 */
class NamespaceIterator implements IteratorAggregate {
	/**
	 * @var IDatabase A wiki database to read from
	 */
	protected $db;

	/**
	 * @var int An NS_* namespace to iterate over
	 */
	protected $namespace;

	/**
	 * @param IDatabase $db A wiki database to read from
	 * @param int $namespace An NS_* namespace to iterate over
	 */
	public function __construct( IDatabase $db, $namespace ) {
		$this->db = $db;
		$this->namespace = $namespace;
	}

	/**
	 * @return Iterator<Title>
	 */
	public function getIterator() {
		$it = new BatchRowIterator(
			$this->db,
			/* tables */ [ 'page' ],
			/* pk */ 'page_id',
			/* rows per batch */ 500
		);
		$it->addConditions( [
			'page_namespace' => $this->namespace,
		] );
		$it->setFetchColumns( [ 'page_title' ] );
		$it = new RecursiveIteratorIterator( $it );

		$namespace = $this->namespace;
		return new EchoCallbackIterator( $it, function ( $row ) use ( $namespace ) {
			return Title::makeTitle( $namespace, $row->page_title );
		} );
	}
}
