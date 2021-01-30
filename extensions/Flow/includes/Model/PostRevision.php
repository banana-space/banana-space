<?php

namespace Flow\Model;

use Flow\Collection\PostCollection;
use Flow\Container;
use Flow\Exception\DataModelException;
use Flow\Exception\FlowException;
use Flow\Repository\TreeRepository;
use Title;
use User;

class PostRevision extends AbstractRevision {
	public const MAX_TOPIC_LENGTH = 260;

	/**
	 * @var UUID
	 */
	protected $postId;

	// The rest of the properties are denormalized data that
	// must not change between revisions of same post

	/**
	 * @var UserTuple
	 */
	protected $origUser;

	/**
	 * @var UUID|null
	 */
	protected $replyToId;

	/**
	 * @var PostRevision[]|null Optionally loaded list of children for this post.
	 */
	protected $children;

	/**
	 * @var int|null Optionally loaded distance of this post from the
	 *   root of this post tree.
	 */
	protected $depth;

	/**
	 * @var PostRevision|null Optionally loaded root of this posts tree.
	 *   This is always a topic title.
	 */
	protected $rootPost;

	/**
	 * Create a brand new root post for a brand new topic.  Creating replies to
	 * an existing post(incl topic root) should use self::reply.
	 *
	 * @param Workflow $topic
	 * @param User $user
	 * @param string $content The title of the topic (they are Collection as well), in
	 *  topic-title-wikitext format.
	 * @return PostRevision
	 */
	public static function createTopicPost( Workflow $topic, User $user, $content ) {
		$format = 'topic-title-wikitext';
		$obj = static::newFromId( $topic->getId(), $user, $content, $format, $topic->getArticleTitle() );

		$obj->changeType = 'new-post';
		// A newly created post has no children, a depth of 0, and
		// is the root of its tree.
		$obj->setChildren( [] );
		$obj->setDepth( 0 );
		$obj->rootPost = $obj;

		return $obj;
	}

	/**
	 * DO NOT USE THIS METHOD!
	 *
	 * Seriously, you probably don't want to use this method, except from within
	 * this class.
	 *
	 * Although it may seem similar to Title::newFrom* or User::newFrom*, chances are slim to none
	 * that this will do what you'd expect.
	 *
	 * Unlike Title & User etc, a post is not something some object that can be
	 * used in isolation: a post should always be retrieved via it's parents,
	 * via a workflow, ...
	 *
	 * The only reasons we have this method are for creating root posts
	 * (called from PostRevision->create), and so when failing to load a
	 * post, we can create a stub object.
	 *
	 * @param UUID $uuid
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html
	 * @param Title|null $title
	 * @return PostRevision
	 */
	public static function newFromId( UUID $uuid, User $user, $content, $format, Title $title = null ) {
		$obj = new self;
		$obj->revId = UUID::create();
		$obj->postId = $uuid;

		$obj->user = UserTuple::newFromUser( $user );
		$obj->origUser = $obj->user;

		$obj->setReplyToId( null ); // not a reply to anything
		$obj->prevRevision = null; // no parent revision
		$obj->setContent( $content, $format, $title );

		return $obj;
	}

	/**
	 * @param string[] $row
	 * @param PostRevision|null $obj
	 * @return PostRevision
	 * @throws DataModelException
	 * @suppress PhanUndeclaredProperty Types not inferred
	 */
	public static function fromStorageRow( array $row, $obj = null ) {
		/** @var $obj PostRevision */
		$obj = parent::fromStorageRow( $row, $obj );
		$treeRevId = UUID::create( $row['tree_rev_id'] );

		if ( !$obj->revId->equals( $treeRevId ) ) {
			$treeRevIdStr = ( $treeRevId !== null )
				? $treeRevId->getAlphadecimal()
				: var_export( $row['tree_rev_id'], true );

			throw new DataModelException(
				'tree revision doesn\'t match provided revision: treeRevId ('
					. $treeRevIdStr . ') != obj->revId (' . $obj->revId->getAlphadecimal() . ')',
				'process-data'
			);
		}
		$obj->replyToId = $row['tree_parent_id'] ? UUID::create( $row['tree_parent_id'] ) : null;
		$obj->postId = UUID::create( $row['rev_type_id'] );
		$obj->origUser = UserTuple::newFromArray( $row, 'tree_orig_user_' );
		if ( !$obj->origUser ) {
			throw new DataModelException( 'Could not create UserTuple for tree_orig_user_' );
		}
		return $obj;
	}

	/**
	 * @param PostRevision $rev
	 * @return string[]
	 * @suppress PhanParamSignatureMismatch It doesn't match
	 */
	public static function toStorageRow( $rev ) {
		return parent::toStorageRow( $rev ) + [
			'tree_parent_id' => $rev->replyToId ? $rev->replyToId->getAlphadecimal() : null,
			'tree_rev_descendant_id' => $rev->postId->getAlphadecimal(),
			'tree_rev_id' => $rev->revId->getAlphadecimal(),
			// rest of tree_ is denormalized data about first post revision
			'tree_orig_user_id' => $rev->origUser->id,
			'tree_orig_user_ip' => $rev->origUser->ip,
			'tree_orig_user_wiki' => $rev->origUser->wiki,
		];
	}

	/**
	 * @param Workflow $workflow
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html
	 * @param string $changeType
	 * @return PostRevision
	 */
	public function reply( Workflow $workflow, User $user, $content, $format, $changeType = 'reply' ) {
		$reply = new self;

		// UUIDs should not be reused for different entities/entity types in the future.
		// (It is also inconsistent with newFromId, which uses separate ones.)
		// This may be changed here in the future.
		$reply->revId = $reply->postId = UUID::create();

		$reply->user = UserTuple::newFromUser( $user );
		$reply->origUser = $reply->user;
		$reply->replyToId = $this->postId;
		$reply->setContent( $content, $format, $workflow->getArticleTitle() );
		$reply->changeType = $changeType;
		$reply->setChildren( [] );
		$reply->setDepth( $this->getDepth() + 1 );
		$reply->rootPost = $this->rootPost;

		return $reply;
	}

	/**
	 * @return UUID
	 */
	public function getPostId() {
		return $this->postId;
	}

	/**
	 * @return UserTuple
	 */
	public function getCreatorTuple() {
		return $this->origUser;
	}

	/**
	 * @return bool
	 */
	public function isTopicTitle() {
		return $this->replyToId === null;
	}

	public function getContentFormat() {
		// The canonical format must always be topic-title-wikitext, because we
		// can not convert 'topic-title-html' to 'topic-title-wikitext'.
		if ( $this->isTopicTitle() ) {
			return 'topic-title-wikitext';
		} else {
			return parent::getContentFormat();
		}
	}

	/**
	 * Gets the desired storage format.
	 *
	 * @return string
	 */
	protected function getStorageFormat() {
		if ( $this->isTopicTitle() ) {
			return 'topic-title-wikitext';
		} else {
			return parent::getStorageFormat();
		}
	}

	/**
	 * Gets the appropriate wikitext format string for this revision.
	 *
	 * @return string 'wikitext' or 'topic-title-wikitext'
	 */
	public function getWikitextFormat() {
		if ( $this->isTopicTitle() ) {
			return 'topic-title-wikitext';
		} else {
			return parent::getWikitextFormat();
		}
	}

	/**
	 * Gets the appropriate HTML format string for this revision.
	 *
	 * @return string 'html' or 'topic-title-html'
	 */
	public function getHtmlFormat() {
		if ( $this->isTopicTitle() ) {
			return 'topic-title-html';
		} else {
			return parent::getHtmlFormat();
		}
	}

	/**
	 * @param UUID|null $id
	 */
	public function setReplyToId( UUID $id = null ) {
		$this->replyToId = $id;
	}

	/**
	 * @return UUID|null Id of the parent post, or null if this is the root
	 */
	public function getReplyToId() {
		return $this->replyToId;
	}

	/**
	 * @param PostRevision[] $children
	 */
	public function setChildren( array $children ) {
		$this->children = $children;
		if ( $this->rootPost ) {
			// Propagate root post into children.
			$this->setRootPost( $this->rootPost );
		}
	}

	/**
	 * @return PostRevision[]
	 * @throws DataModelException
	 */
	public function getChildren() {
		if ( $this->children === null ) {
			throw new DataModelException( 'Children not loaded for post: ' . $this->postId->getAlphadecimal(), 'process-data' );
		}
		return $this->children;
	}

	/**
	 * @param int $depth
	 */
	public function setDepth( $depth ) {
		$this->depth = (int)$depth;
	}

	/**
	 * @return int
	 * @throws DataModelException
	 */
	public function getDepth() {
		if ( $this->depth === null ) {
			/** @var TreeRepository $treeRepo */
			$treeRepo = Container::get( 'repository.tree' );
			$rootPath = $treeRepo->findRootPath( $this->getCollectionId() );
			$this->setDepth( count( $rootPath ) - 1 );
		}

		return $this->depth;
	}

	/**
	 * @param PostRevision $root
	 * @deprecated Use PostCollection::getRoot instead
	 */
	public function setRootPost( PostRevision $root ) {
		$this->rootPost = $root;
		if ( $this->children ) {
			// Propagate root post into children.
			foreach ( $this->children as $child ) {
				$child->setRootPost( $root );
			}
		}
	}

	/**
	 * @return PostRevision
	 * @throws DataModelException
	 * @deprecated Use PostCollection::getRoot instead
	 */
	public function getRootPost() {
		if ( $this->isTopicTitle() ) {
			return $this;
		} elseif ( $this->rootPost === null ) {
			$collection = $this->getCollection();
			$root = $collection->getRoot();
			return $root->getLastRevision();
		}
		return $this->rootPost;
	}

	/**
	 * Get the amount of posts in this topic.
	 *
	 * @return int
	 */
	public function getChildCount() {
		return count( $this->getChildren() );
	}

	/**
	 * Finds the provided postId within this posts descendants
	 *
	 * @param UUID $postId The id of the post to find.
	 * @return PostRevision|null
	 * @throws FlowException
	 */
	public function getDescendant( UUID $postId ) {
		if ( $this->children === null ) {
			throw new FlowException( 'Attempted to access post descendant, but children haven\'t yet been loaded.' );
		}
		foreach ( $this->children as $child ) {
			if ( $child->getPostId()->equals( $postId ) ) {
				return $child;
			}
			$found = $child->getDescendant( $postId );
			if ( $found !== null ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * @return string
	 */
	public function getRevisionType() {
		return 'post';
	}

	/**
	 * @param User $user
	 * @return bool
	 */
	public function isCreator( User $user ) {
		if ( $user->isAnon() ) {
			return false;
		}
		return $user->getId() == $this->getCreatorId();
	}

	/**
	 * @return UUID
	 */
	public function getCollectionId() {
		return $this->getPostId();
	}

	/**
	 * @return PostCollection
	 */
	public function getCollection() {
		return PostCollection::newFromRevision( $this );
	}
}
