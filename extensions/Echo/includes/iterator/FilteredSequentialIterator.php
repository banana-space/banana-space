<?php

/**
 * Allows building a single iterator out of multiple iterators
 * and filtering the results.  Accepts plain arrays for the simple
 * use case, also accepts Iterator instances for anything more complex.
 *
 * This exists so that EchoUserLocator implementations can return iterators
 * that return potentially thousands of users without having to grab
 * them all in one giant query.
 *
 * Usage:
 *   $users = new EchoFilteredSequentialIterator;
 *   $users->add( array( $userA, $userB, $userC ) );
 *
 *   $it = new BatchRowIterator( ... );
 *   ...
 *   $it = new RecursiveIteratorIterator( $it );
 *   $users->add( new EchoCallbackIterator( $it, function( $row ) {
 *    ...
 *    return $user;
 *   } ) );
 *
 *   foreach ( $users as $user ) {
 *    ...
 *   }
 *
 * By default the BatchRowIterator returns an array of rows, this class
 * expects a stream of user objects.  To bridge that gap the
 * RecursiveIteratorIterator is used to flatten and the EchoCallbackIterator
 * is used to transform each database $row into a User object.
 *
 * @todo name?
 */
class EchoFilteredSequentialIterator implements IteratorAggregate {
	/**
	 * @var Iterator[]
	 */
	protected $iterators = [];

	/**
	 * @var callable[]
	 */
	protected $filters = [];

	/**
	 * @param Iterator|IteratorAggregate|array $users
	 * @throws MWException
	 */
	public function add( $users ) {
		if ( is_array( $users ) ) {
			$it = new ArrayIterator( $users );
		} elseif ( $users instanceof Iterator ) {
			$it = $users;
		} elseif ( $users instanceof IteratorAggregate ) {
			$it = $users->getIterator();
		} else {
			throw new MWException( 'Expected array, Iterator or IteratorAggregate but received:' .
				( is_object( $users ) ? get_class( $users ) : gettype( $users ) )
			);
		}

		$this->iterators[] = $it;
	}

	/**
	 * @param callable $callable
	 */
	public function addFilter( $callable ) {
		$this->filters[] = $callable;
	}

	/**
	 * Satisfies IteratorAggregate interface
	 *
	 * @return Iterator
	 */
	public function getIterator() {
		$it = $this->createIterator();
		if ( $this->filters ) {
			$it = new CallbackFilterIterator( $it, $this->createFilter() );
		}

		return $it;
	}

	/**
	 * @return Iterator
	 */
	protected function createIterator() {
		switch ( count( $this->iterators ) ) {
			case 0:
				return new EmptyIterator;

			case 1:
				return reset( $this->iterators );

			default:
				return new RecursiveIteratorIterator( new EchoMultipleIterator( $this->iterators ) );
		}
	}

	/**
	 * @return callable
	 */
	protected function createFilter() {
		switch ( count( $this->filters ) ) {
			case 0:
				return function () {
					return true;
				};

			case 1:
				return reset( $this->filters );

			default:
				$filters = $this->filters;

				return function ( $user ) use ( $filters ) {
					foreach ( $filters as $filter ) {
						if ( !call_user_func( $filter, $user ) ) {
							return false;
						}
					}

					return true;
				};
		}
	}
}
