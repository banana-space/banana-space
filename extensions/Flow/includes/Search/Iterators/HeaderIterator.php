<?php

namespace Flow\Search\Iterators;

class HeaderIterator extends AbstractIterator {
	/**
	 * @inheritDoc
	 */
	protected function query() {
		// get the current (=most recent, =max) revision id for all headers
		return $this->dbr->select(
			[ 'flow_revision', 'flow_workflow' ],
			[ 'rev_id' => 'MAX(rev_id)', 'rev_type' ],
			$this->conditions,
			__METHOD__,
			[
				'ORDER BY' => 'rev_id ASC',
				'GROUP BY' => 'rev_type_id',
			],
			[
				'flow_workflow' => [
					'INNER JOIN',
					[ 'workflow_id = rev_type_id' , 'rev_type' => 'header' ]
				],
			]
		);
	}
}
