<?php

namespace Flow\Data\Pager;

/**
 * Represents a single page of data loaded via Flow\Data\Pager\Pager
 */
class PagerPage {
	/**
	 * @var array
	 */
	protected $results;

	/**
	 * @var array[]
	 */
	protected $pagingLinkOptions;

	/**
	 * @var Pager
	 */
	protected $pager;

	/**
	 * @param array $results
	 * @param array[] $pagingLinkOptions
	 * @param Pager $pager
	 */
	public function __construct( array $results, array $pagingLinkOptions, Pager $pager ) {
		$this->results = $results;
		$this->pagingLinkOptions = $pagingLinkOptions;
		$this->pager = $pager;
	}

	/**
	 * @return Pager
	 */
	public function getPager() {
		return $this->pager;
	}

	/**
	 * @return array
	 */
	public function getResults() {
		return $this->results;
	}

	/**
	 * @return array[]
	 */
	public function getPagingLinksOptions() {
		return $this->pagingLinkOptions;
	}
}
