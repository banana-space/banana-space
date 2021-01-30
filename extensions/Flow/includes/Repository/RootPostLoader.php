<?php

namespace Flow\Repository;

use Flow\Data\ManagerGroup;
use Flow\Exception\InvalidDataException;
use Flow\Model\PostRevision;
use Flow\Model\UUID;
use FormatJson;

/**
 * I'm pretty sure this will generally work for any subtree, not just the topic
 * root.  The problem is once you allow any subtree you need to handle the
 * depth and root post setters better, they make the assumption the root provided
 * is actually a root.
 */
class RootPostLoader {
	/**
	 * @var ManagerGroup
	 */
	protected $storage;

	/**
	 * @var TreeRepository
	 */
	protected $treeRepo;

	/**
	 * @param ManagerGroup $storage
	 * @param TreeRepository $treeRepo
	 */
	public function __construct( ManagerGroup $storage, TreeRepository $treeRepo ) {
		$this->storage = $storage;
		$this->treeRepo = $treeRepo;
	}

	/**
	 * Retrieves a single post and the related topic title.
	 *
	 * @param UUID|string $postId The uid of the post being requested
	 * @return (PostRevision|null)[] associative array with 'root' and 'post' keys. Array
	 *   values may be null if not found.
	 * @throws InvalidDataException
	 * @phan-return array{root:null|PostRevision,post:null|PostRevision}
	 */
	public function getWithRoot( $postId ) {
		$postId = UUID::create( $postId );
		$rootId = $this->treeRepo->findRoot( $postId );
		$found = $this->storage->findMulti(
			'PostRevision',
			[
				[ 'rev_type_id' => $postId ],
				[ 'rev_type_id' => $rootId ],
			],
			[ 'sort' => 'rev_id', 'order' => 'DESC', 'limit' => 1 ]
		);
		$res = [
			'post' => null,
			'root' => null,
		];
		if ( !$found ) {
			return $res;
		}
		foreach ( $found as $result ) {
			// limit = 1 means single result
			$post = reset( $result );
			if ( $postId->equals( $post->getPostId() ) ) {
				$res['post'] = $post;
			} elseif ( $rootId->equals( $post->getPostId() ) ) {
				$res['root'] = $post;
			} else {
				throw new InvalidDataException( 'Unmatched: ' . $post->getPostId()->getAlphadecimal() );
			}
		}
		// The above doesn't catch this condition
		if ( $postId->equals( $rootId ) ) {
			$res['root'] = $res['post'];
		}
		return $res;
	}

	/**
	 * @param UUID $topicId
	 * @return PostRevision
	 * @throws InvalidDataException
	 */
	public function get( $topicId ) {
		$result = $this->getMulti( [ $topicId ] );
		return reset( $result );
	}

	/**
	 * @param UUID[] $topicIds
	 * @return PostRevision[]
	 * @throws InvalidDataException
	 */
	public function getMulti( array $topicIds ) {
		if ( !$topicIds ) {
			return [];
		}
		// load posts for all located post ids
		$allPostIds = $this->fetchRelatedPostIds( $topicIds );
		$queries = [];
		foreach ( $allPostIds as $postId ) {
			$queries[] = [ 'rev_type_id' => $postId ];
		}
		$found = $this->storage->findMulti( 'PostRevision', $queries, [
			'sort' => 'rev_id',
			'order' => 'DESC',
			'limit' => 1,
		] );
		/** @var PostRevision[] $posts */
		$posts = $children = [];
		foreach ( $found as $indexResult ) {
			$post = reset( $indexResult ); // limit => 1 means only 1 result per query
			if ( isset( $posts[$post->getPostId()->getAlphadecimal()] ) ) {
				throw new InvalidDataException(
					'Multiple results for id: ' . $post->getPostId()->getAlphadecimal(),
					'fail-load-data'
				);
			}
			$posts[$post->getPostId()->getAlphadecimal()] = $post;
		}
		$prettyPostIds = [];
		foreach ( $allPostIds as $id ) {
			$prettyPostIds[] = $id->getAlphadecimal();
		}
		$missing = array_diff( $prettyPostIds, array_keys( $posts ) );
		if ( $missing ) {
			// convert string uuid's into UUID objects
			/** @var UUID[] $missingUUID */
			$missingUUID = array_map( [ UUID::class, 'create' ], $missing );

			// we'll need to know parents to add stub post correctly in post hierarchy
			$parents = $this->treeRepo->fetchParentMap( $missingUUID );
			$missingParents = array_diff( $missing, array_keys( $parents ) );
			if ( $missingParents ) {
				// if we can't fetch a post's original position in the tree
				// hierarchy, we can't create a stub post to display, so bail
				throw new InvalidDataException(
					'Missing Posts & parents: ' . json_encode( $missingParents ),
					'fail-load-data'
				);
			}

			foreach ( $missingUUID as $postId ) {
				$content = wfMessage( 'flow-stub-post-content' )->text();
				$username = wfMessage( 'flow-system-usertext' )->text();
				$user = \User::newFromName( $username );

				// create a stub post instead of failing completely
				$post = PostRevision::newFromId( $postId, $user, $content, 'wikitext' );
				$post->setReplyToId( $parents[$postId->getAlphadecimal()] );
				$posts[$postId->getAlphadecimal()] = $post;

				wfDebugLog( 'Flow', __METHOD__ . ': Missing posts: ' . FormatJson::encode( $missing ) );
			}
		}
		// another helper to catch bugs in dev
		$extra = array_diff( array_keys( $posts ), $prettyPostIds );
		if ( $extra ) {
			throw new InvalidDataException(
				'Found unrequested posts: ' . FormatJson::encode( $extra ),
				'fail-load-data'
			);
		}

		// populate array of children
		foreach ( $posts as $post ) {
			if ( $post->getReplyToId() ) {
				$children[$post->getReplyToId()->getAlphadecimal()][$post->getPostId()->getAlphadecimal()] = $post;
			}
		}
		$extraParents = array_diff( array_keys( $children ), $prettyPostIds );
		if ( $extraParents ) {
			throw new InvalidDataException(
				'Found posts with unrequested parents: ' . FormatJson::encode( $extraParents ),
				'fail-load-data'
			);
		}

		foreach ( $posts as $postId => $post ) {
			$postChildren = [];
			$postDepth = 0;

			// link parents to their children
			if ( isset( $children[$postId] ) ) {
				// sort children with oldest items first
				ksort( $children[$postId] );
				$postChildren = $children[$postId];
			}

			// determine threading depth of post
			$replyToId = $post->getReplyToId();
			while ( $replyToId && isset( $children[$replyToId->getAlphadecimal()] ) ) {
				$postDepth++;
				$replyToId = $posts[$replyToId->getAlphadecimal()]->getReplyToId();
			}

			$post->setChildren( $postChildren );
			$post->setDepth( $postDepth );
		}

		// return only the requested posts, rest are available as children.
		// Return in same order as requested
		/** @var PostRevision[] $roots */
		$roots = [];
		foreach ( $topicIds as $id ) {
			$roots[$id->getAlphadecimal()] = $posts[$id->getAlphadecimal()];
		}
		// Attach every post in the tree to its root. setRootPost
		// recursively applies it to all children as well.
		foreach ( $roots as $post ) {
			$post->setRootPost( $post );
		}
		return $roots;
	}

	/**
	 * @param UUID[] $postIds
	 * @return UUID[] Map from alphadecimal id to UUID object
	 */
	protected function fetchRelatedPostIds( array $postIds ) {
		// list of all posts descendant from the provided $postIds
		$nodeList = $this->treeRepo->fetchSubtreeNodeList( $postIds );
		// merge all the children from the various posts into one array
		if ( !$nodeList ) {
			// It should have returned at least $postIds
			// TODO: log errors?
			$res = $postIds;
		} elseif ( count( $nodeList ) === 1 ) {
			$res = reset( $nodeList );
		} else {
			$res = array_merge( ...array_values( $nodeList ) );
		}

		$retval = [];
		foreach ( $res as $id ) {
			$retval[$id->getAlphadecimal()] = $id;
		}
		return $retval;
	}

	/**
	 * @return TreeRepository
	 */
	public function getTreeRepo() {
		return $this->treeRepo;
	}
}
