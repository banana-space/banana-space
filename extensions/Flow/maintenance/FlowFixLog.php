<?php

use Flow\Collection\PostCollection;
use Flow\Container;
use Flow\Data\ManagerGroup;
use Flow\Exception\InvalidDataException;
use Flow\Model\PostRevision;
use Flow\Model\UUID;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";
require_once "$IP/includes/utils/RowUpdateGenerator.php";

/**
 * Fixes Flow log entries.
 *
 * @ingroup Maintenance
 */
class FlowFixLog extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Fixes Flow log entries' );

		$this->setBatchSize( 300 );

		$this->requireExtension( 'Flow' );
	}

	protected function getUpdateKey() {
		return 'FlowFixLog:version2';
	}

	protected function doDBUpdates() {
		$iterator = new BatchRowIterator( wfGetDB( DB_REPLICA ), 'logging', 'log_id', $this->mBatchSize );
		$iterator->setFetchColumns( [ 'log_id', 'log_params' ] );
		$iterator->addConditions( [
			'log_type' => [ 'delete', 'suppress' ],
			'log_action' => [
				'flow-delete-post', 'flow-suppress-post', 'flow-restore-post',
				'flow-delete-topic', 'flow-suppress-topic', 'flow-restore-topic',
			],
		] );

		$updater = new BatchRowUpdate(
			$iterator,
			new BatchRowWriter( wfGetDB( DB_MASTER ), 'logging' ),
			new LogRowUpdateGenerator( $this )
		);
		$updater->setOutput( [ $this, 'output' ] );
		$updater->execute();

		return true;
	}

	/**
	 * parent::output() is a protected method, only way to access it from a
	 * callback in php5.3 is to make a public function. In 5.4 can replace with
	 * a Closure.
	 *
	 * @param string $out
	 * @param mixed|null $channel
	 */
	public function output( $out, $channel = null ) {
		parent::output( $out, $channel );
	}

	/**
	 * parent::error() is a protected method, only way to access it from the
	 * outside is to make it public.
	 *
	 * @param string $err
	 * @param int $die
	 */
	public function error( $err, $die = 0 ) {
		parent::error( $err, $die );
	}
}

class LogRowUpdateGenerator implements RowUpdateGenerator {
	/**
	 * @var FlowFixLog
	 */
	protected $maintenance;

	/**
	 * @param FlowFixLog $maintenance
	 */
	public function __construct( FlowFixLog $maintenance ) {
		$this->maintenance = $maintenance;
	}

	public function update( $row ) {
		$updates = [];
		$logId = (int)$row->log_id;

		$params = unserialize( $row->log_params );
		if ( !$params ) {
			$this->maintenance->error( "Failed to unserialize log_params for log_id $logId" );
			return [];
		}

		$topic = false;
		$post = false;
		if ( isset( $params['topicId'] ) ) {
			$topic = $this->loadTopic( UUID::create( $params['topicId'] ) );
		}
		if ( isset( $params['postId'] ) ) {
			$post = $this->loadPost( UUID::create( $params['postId'] ) );
			$topic = $topic ?: $post->getRoot();
		}

		if ( !$topic ) {
			$this->maintenance->error( "Missing topicId & postId for log_id $logId" );
			return [];
		}

		try {
			// log_namespace & log_title used to be board, should be topic
			$updates['log_namespace'] = $topic->getTitle()->getNamespace();
			$updates['log_title'] = $topic->getTitle()->getDBkey();
		} catch ( \Exception $e ) {
			$this->maintenance->error( "Couldn't load Title for log_id $logId" );
			$updates = [];
		}

		if ( isset( $params['postId'] ) && $post ) {
			// posts used to save revision id instead of post id, let's make
			// sure it's actually the post id that's being saved!...
			$params['postId'] = $post->getId();
		}

		if ( !isset( $params['topicId'] ) ) {
			// posts didn't use to also store topicId, but we'll be using it to
			// enrich log entries' output - might as well store it right away
			$params['topicId'] = $topic->getId();
		}

		// we used to save (serialized) UUID objects; now we just save the
		// alphanumeric representation
		foreach ( $params as $key => $value ) {
			$params[$key] = $value instanceof UUID ? $value->getAlphadecimal() : $value;
		}

		// re-serialize params (UUID used to serialize more verbose; might
		// as well shrink that down now that we're updating anyway...)
		$updates['log_params'] = serialize( $params );

		return $updates;
	}

	/**
	 * @param UUID $topicId
	 * @return PostCollection
	 */
	protected function loadTopic( UUID $topicId ) {
		return PostCollection::newFromId( $topicId );
	}

	/**
	 * @param UUID $postId
	 * @return PostCollection|false
	 */
	protected function loadPost( UUID $postId ) {
		try {
			$collection = PostCollection::newFromId( $postId );

			// validate collection by attempting to fetch latest revision - if
			// this fails (likely will for old data), catch will be invoked
			$collection->getLastRevision();
			return $collection;
		} catch ( InvalidDataException $e ) {
			// posts used to mistakenly store revision ID instead of post ID

			/** @var ManagerGroup $storage */
			$storage = Container::get( 'storage' );
			$result = $storage->find(
				'PostRevision',
				[ 'rev_id' => $postId ],
				[ 'LIMIT' => 1 ]
			);

			if ( $result ) {
				/** @var PostRevision $revision */
				$revision = reset( $result );

				// now build collection from real post ID
				return $this->loadPost( $revision->getPostId() );
			}
		}

		return false;
	}
}

$maintClass = FlowFixLog::class;
require_once RUN_MAINTENANCE_IF_MAIN;
