<?php

namespace Flow\Collection;

use Flow\Exception\InvalidDataException;
use Flow\Model\AbstractRevision;
use Flow\Model\UUID;

/**
 * LocalBufferedCache saves all data that has been requested in an internal
 * cache (in memory, per request). This provides the opportunity of (trying to)
 * be smart about what results we fetch.
 * The class extends the default AbstractCollection to make sure not all
 * revisions are loaded unless we really need them. It could very well be that
 * perhaps 5 recent revisions have already been loaded in other parts of the
 * code, and we only need the 3rd most recent, in which case we shouldn't
 * try to fetch all of them.
 */
abstract class LocalCacheAbstractCollection extends AbstractCollection {
	/**
	 * Returns all revisions.
	 *
	 * @return AbstractRevision[]
	 */
	public function getAllRevisions() {
		// if we have not yet loaded everything, just clear what we have and
		// fetch from cache
		if ( !$this->loaded() ) {
			$this->revisions = [];
		}

		return parent::getAllRevisions();
	}

	/**
	 * Returns the revision with the given id.
	 *
	 * @param UUID $uuid
	 * @return AbstractRevision|null null if there is no such revision
	 */
	public function getRevision( UUID $uuid ) {
		// check if fetching last already res
		if ( isset( $this->revisions[$uuid->getAlphadecimal() ] ) ) {
			return $this->revisions[$uuid->getAlphadecimal() ];
		}

		/*
		 * The strategy here is to avoid having to call getAllRevisions(), which
		 * is most likely to have to load (fresh) data that is not yet in
		 * LocalBufferedCache's internal cache.
		 * To do so, we'll build the $this->revisions array by hand. Starting at
		 * the most recent revision and going up 1 revision at a time, checking
		 * if it is already in LocalBufferedCache's cache.
		 * If, however, we can't find the requested revisions (or one of the
		 * revisions on our way to the requested revision) in the internal cache
		 * of LocalBufferedCache, we'll just bail and load all revisions after
		 * all: if we do have to fetch data, might as well do it all in 1 go!
		 */
		while ( !$this->loaded() ) {
			// fetch current oldest revision
			$oldest = $this->getOldestLoaded();

			// fetch that one's preceding revision id
			$previousId = $oldest->getPrevRevisionId();

			// check if it's in local storage already
			if ( $previousId && self::getStorage()->got( $previousId ) ) {
				$revision = self::getStorage()->get( $previousId );

				// add this revision to revisions array
				$this->revisions[$previousId->getAlphadecimal()] = $revision;

				// stop iterating if we've found the one we wanted
				if ( $uuid->equals( $previousId ) ) {
					break;
				}
			} else {
				// revision not found in local storage: load all revisions
				$this->getAllRevisions();
				break;
			}
		}

		if ( !isset( $this->revisions[$uuid->getAlphadecimal()] ) ) {
			return null;
		}

		return $this->revisions[$uuid->getAlphadecimal()];
	}

	/**
	 * Returns the most recent revision.
	 *
	 * @return AbstractRevision
	 * @throws InvalidDataException When no revision can be located
	 */
	public function getLastRevision() {
		// if $revisions is not empty, it will always have the last revision,
		// at the beginning of the array
		if ( $this->revisions ) {
			return reset( $this->revisions );
		}

		$attributes = [ 'rev_type_id' => $this->uuid ];
		$options = [ 'sort' => 'rev_id', 'limit' => 1, 'order' => 'DESC' ];

		if ( self::getStorage()->found( $attributes, $options ) ) {
			// if last revision is already known in local cache, fetch it
			$revision = self::getStorage()->find( $attributes, $options );
			if ( !$revision ) {
				throw new InvalidDataException(
					'Last revision for ' . $this->uuid->getAlphadecimal() . ' could not be found',
					'invalid-type-id'
				);
			}
			$revision = reset( $revision );
			$this->revisions[$revision->getRevisionId()->getAlphadecimal()] = $revision;
			return $revision;

		} else {
			// otherwise, might as well fetch all previous revisions while we're at
			// it - saves roundtrips to cache/db
			$this->getAllRevisions();
			return reset( $this->revisions );
		}
	}

	/**
	 * Given a certain revision, returns the next revision.
	 *
	 * @param AbstractRevision $revision
	 * @return AbstractRevision|null null if there is no next revision
	 */
	public function getNextRevision( AbstractRevision $revision ) {
		// make sure the given revision is loaded
		$this->getRevision( $revision->getRevisionId() );

		// find requested id, based on given revision
		$ids = array_keys( $this->revisions );
		$current = array_search( $revision->getRevisionId()->getAlphadecimal(), $ids );
		$next = $current - 1;

		if ( $next < 0 ) {
			return null;
		}

		return $this->getRevision( UUID::create( $ids[$next] ) );
	}

	/**
	 * Returns true if all revisions have been loaded into $this->revisions.
	 *
	 * @return bool
	 */
	public function loaded() {
		$first = end( $this->revisions );
		return $first && $first->getPrevRevisionId() === null;
	}

	/**
	 * Returns the oldest revision that has already been fetched via this class.
	 *
	 * @return AbstractRevision
	 */
	public function getOldestLoaded() {
		if ( !$this->revisions ) {
			return $this->getLastRevision();
		}

		return end( $this->revisions );
	}
}
