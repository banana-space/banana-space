<?php

namespace Flow\Formatter;

use Flow\Data\Listener\RecentChangesListener;
use Flow\Data\ManagerGroup;
use Flow\Exception\FlowException;
use Flow\FlowActions;
use Flow\Model\UUID;
use Flow\Repository\TreeRepository;
use MediaWiki\Logger\LoggerFactory;
use RecentChange;

class ChangesListQuery extends AbstractQuery {

	/**
	 * Check if the most recent action for an entity has been displayed already
	 *
	 * @var array
	 */
	protected $displayStatus = [];

	/**
	 * @var FlowActions
	 */
	protected $actions;

	/**
	 * @var bool
	 */
	protected $extendWatchlist = false;

	public function __construct( ManagerGroup $storage, TreeRepository $treeRepo, FlowActions $actions ) {
		parent::__construct( $storage, $treeRepo );
		$this->actions = $actions;
	}

	/**
	 * @param bool $extend
	 */
	public function setExtendWatchlist( $extend ) {
		$this->extendWatchlist = (bool)$extend;
	}

	/**
	 * @param \stdClass[] $rows List of recentchange database rows
	 * @param bool $isWatchlist
	 * @suppress PhanParamSignatureMismatch The signature doesn't match, though
	 */
	public function loadMetadataBatch( $rows, $isWatchlist = false ) {
		$needed = [];
		foreach ( $rows as $row ) {
			if ( !isset( $row->rc_source ) || $row->rc_source !== RecentChangesListener::SRC_FLOW ) {
				continue;
			}
			if ( !isset( $row->rc_params ) ) {
				wfDebugLog( 'Flow', __METHOD__ . ': Bad row without rc_params passed in $rows' );
				continue;
			}
			$params = unserialize( $row->rc_params );
			if ( !$params ) {
				wfDebugLog( 'Flow', __METHOD__ . ": rc_params does not contain serialized content: {$row->rc_params}" );
				continue;
			}
			$changeData = $params['flow-workflow-change'];
			/**
			 * Check to make sure revision_type exists, this is to make sure corrupted
			 * flow recent change data doesn't throw error on the page.
			 * See bug 59106 for more detail
			 */
			if ( !isset( $changeData['revision_type'] ) ) {
				continue;
			}
			if ( $this->excludeFromChangesList( $isWatchlist, $changeData['action'] ) ) {
				continue;
			}
			if ( $isWatchlist && $this->isRecordHidden( $changeData ) ) {
				continue;
			}
			$revisionType = $changeData['revision_type'];
			$needed[$revisionType][] = UUID::create( $changeData['revision'] );
		}

		$found = [];
		foreach ( $needed as $type => $uids ) {
			$found[] = $this->storage->getMulti( $type, $uids );
		}

		$found = array_filter( $found );
		$count = count( $found );
		if ( $count === 0 ) {
			$results = [];
		} elseif ( $count === 1 ) {
			$results = reset( $found );
		} else {
			$results = array_merge( ...array_values( $found ) );
		}

		if ( $results ) {
			parent::loadMetadataBatch( $results );
		}
	}

	/**
	 * @param bool $isWatchlist Whether this is Special:Watchlist
	 * @param string $action The Flow action this line represents
	 * @return bool
	 */
	private function excludeFromChangesList( $isWatchlist, $action ) {
		// If we want to exclude things from watchlist, we can add exclude_from_watchlist
		if ( $isWatchlist ) {
			return false;
		} else {
			return (bool)$this->actions->getValue( $action, 'exclude_from_recentchanges' );
		}
	}

	/**
	 * @param null $cl No longer used
	 * @param RecentChange $rc
	 * @param bool $isWatchlist
	 * @return RecentChangesRow|bool False on failure
	 * @throws FlowException
	 */
	public function getResult( $cl, RecentChange $rc, $isWatchlist = false ) {
		$rcParams = $rc->getAttribute( 'rc_params' );
		$params = unserialize( $rcParams );
		if ( !$params ) {
			throw new FlowException( 'rc_params does not contain serialized content: ' . $rcParams );
		}
		$changeData = $params['flow-workflow-change'];

		if ( !is_array( $changeData ) ) {
			throw new FlowException( 'Flow data missing in recent changes.' );
		}

		/**
		 * Check to make sure revision_type exists, this is to make sure corrupted
		 * flow recent change data doesn't throw error on the page.
		 * See bug 59106 for more detail
		 */
		if ( !isset( $changeData['revision_type'] ) ) {
			throw new FlowException( 'Corrupted rc without changeData: ' . $rc->getAttribute( 'rc_id' ) );
		}

		if ( $this->excludeFromChangesList( $isWatchlist, $changeData['action'] ) ) {
			return false;
		}

		// Only show most recent items for watchlist
		if ( $isWatchlist && $this->isRecordHidden( $changeData ) ) {
			return false;
		}

		$alpha = UUID::create( $changeData['revision'] )->getAlphadecimal();
		if ( !isset( $this->revisionCache[$alpha] ) ) {
			LoggerFactory::getInstance( 'Flow' )->error(
				'Revision not found in revisionCache: {alpha}',
				[
					'alpha' => $alpha,
					'rcParams' => $rcParams,
				]
			);
			return false;
		}
		$revision = $this->revisionCache[$alpha];

		$res = new RecentChangesRow;
		$this->buildResult( $revision, 'timestamp', $res );
		$res->recentChange = $rc;

		return $res;
	}

	/**
	 * Determines if a flow record should be displayed in Special:Watchlist
	 *
	 * @param array $changeData
	 * @return bool
	 */
	protected function isRecordHidden( array $changeData ) {
		if ( $this->extendWatchlist ) {
			return false;
		}
		// Check for legacy action names and convert it
		$alias = $this->actions->getValue( $changeData['action'] );
		if ( is_string( $alias ) ) {
			$action = $alias;
		} else {
			$action = $changeData['action'];
		}
		// * Display the most recent new post, edit post, edit title for a topic
		// * Display the most recent header edit
		// * Display all new topic and moderation actions
		switch ( $action ) {
			case 'create-header':
			case 'edit-header':
				if (
					isset( $this->displayStatus['header-' . $changeData['workflow']] ) &&
					$this->displayStatus['header-' . $changeData['workflow']] !== $changeData['revision']
				) {
					return true;
				}
				$this->displayStatus['header-' . $changeData['workflow']] = $changeData['revision'];
			break;

			case 'hide-post':
			case 'hide-topic':
			case 'delete-post':
			case 'delete-topic':
			case 'suppress-post':
			case 'suppress-topic':
			case 'restore-post':
			case 'restore-topic':
			case 'lock-topic':
				// moderation actions are always shown when visible to the user
				return false;

			case 'new-topic':
			case 'reply':
			case 'edit-post':
			case 'edit-title':
			case 'create-topic-summary':
			case 'edit-topic-summary':
				if (
					isset( $this->displayStatus['topic-' . $changeData['workflow']] ) &&
					$this->displayStatus['topic-' . $changeData['workflow']] !== $changeData['revision']
				) {
					return true;
				}
				$this->displayStatus['topic-' . $changeData['workflow']] = $changeData['revision'];
			break;
		}

		return false;
	}

	protected function changeSeparator() {
		return ' <span class="mw-changeslist-separator">. .</span> ';
	}
}
