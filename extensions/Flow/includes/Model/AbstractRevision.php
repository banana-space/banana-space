<?php

namespace Flow\Model;

use ContentHandler;
use Flow\Collection\AbstractCollection;
use Flow\Conversion\Utils;
use Flow\Exception\DataModelException;
use Flow\Exception\InvalidDataException;
use Flow\Exception\PermissionException;
use Hooks;
use MediaWiki\MediaWikiServices;
use RecentChange;
use Sanitizer;
use Title;
use User;
use WikiPage;

abstract class AbstractRevision {
	public const MODERATED_NONE = '';
	public const MODERATED_HIDDEN = 'hide';
	public const MODERATED_DELETED = 'delete';
	public const MODERATED_SUPPRESSED = 'suppress';
	public const MODERATED_LOCKED = 'lock';

	/**
	 * List of available permission levels.
	 *
	 * @var string[]
	 */
	public static $perms = [
		self::MODERATED_NONE,
		self::MODERATED_HIDDEN,
		self::MODERATED_DELETED,
		self::MODERATED_SUPPRESSED,
		self::MODERATED_LOCKED,
	];

	/**
	 * List of moderation change types
	 *
	 * @var array|null
	 */
	protected static $moderationChangeTypes = null;

	/**
	 * @var UUID
	 */
	protected $revId;

	/**
	 * @var UserTuple
	 */
	protected $user;

	/**
	 * Array of flags strictly related to the content. Flags are reset when
	 * content changes.
	 *
	 * @var string[]
	 */
	protected $flags = [];

	/**
	 * Name of the action performed that generated this revision.
	 *
	 * @see FlowActions.php
	 * @var string
	 */
	protected $changeType;

	/**
	 * @var UUID|null The id of the revision prior to this one, or null if this is first revision
	 */
	protected $prevRevision;

	/**
	 * @var string|null Raw content of revision
	 */
	protected $content;

	/**
	 * @var string|null Only populated when external store is in use
	 */
	protected $contentUrl;

	/**
	 * @var string|null This is decompressed on-demand from $this->content in self::getContent()
	 */
	protected $decompressedContent;

	/**
	 * @var string[] Converted (wikitext|html) content, based off of $this->decompressedContent
	 */
	protected $convertedContent = [];

	/**
	 * html content has been allowed by the xss check.  When we find the next xss
	 * in the parser this hook allows preventing any display of hostile html. True
	 * means the content is allowed. False means not allowed. Null means unchecked
	 *
	 * @var bool
	 */
	protected $xssCheck;

	/**
	 * moderation states for the revision.  This is technically denormalized data
	 * since it can be overwritten and does not provide a full history.
	 * The tricky part is updating moderation is a new revision for hide and
	 * delete, but adjusts an existing revision for full suppression.
	 *
	 * @var string
	 */
	protected $moderationState = self::MODERATED_NONE;

	/**
	 * @var string|null
	 */
	protected $moderationTimestamp;

	/**
	 * @var UserTuple|null
	 */
	protected $moderatedBy;

	/**
	 * @var string|null
	 */
	protected $moderatedReason;

	/**
	 * @var UUID|null The id of the last content edit revision
	 */
	protected $lastEditId;

	/**
	 * @var UserTuple|null
	 */
	protected $lastEditUser;

	/**
	 * @var int Size of previous revision wikitext
	 */
	protected $previousContentLength = 0;

	/**
	 * @var int Size of current revision wikitext
	 */
	protected $contentLength = 0;

	/**
	 * Author of the first revision
	 *
	 * @var UserTuple
	 */
	protected $creator;

	/**
	 * @param string[] $row
	 * @param AbstractRevision|null $obj
	 * @return AbstractRevision
	 * @throws DataModelException
	 */
	public static function fromStorageRow( array $row, $obj = null ) {
		if ( $obj === null ) {
			/** @var AbstractRevision $obj */
			$obj = new static; // @phan-suppress-current-line PhanTypeInstantiateAbstractStatic
		} elseif ( !$obj instanceof static ) {
			throw new DataModelException( 'wrong object type', 'process-data' );
		}
		$obj->revId = UUID::create( $row['rev_id'] );
		$obj->user = UserTuple::newFromArray( $row, 'rev_user_' );
		if ( $obj->user === null ) {
			throw new DataModelException( 'Could not load UserTuple for rev_user_' );
		}
		$obj->prevRevision = $row['rev_parent_id'] ? UUID::create( $row['rev_parent_id'] ) : null;
		$obj->changeType = $row['rev_change_type'];
		$obj->flags = array_filter( explode( ',', $row['rev_flags'] ) );
		$obj->content = $row['rev_content'];
		// null if external store is not being used
		$obj->contentUrl = $row['rev_content_url'] ?? null;
		$obj->decompressedContent = null;

		$obj->moderationState = $row['rev_mod_state'];
		$obj->moderatedBy = UserTuple::newFromArray( $row, 'rev_mod_user_' );
		$obj->moderationTimestamp = $row['rev_mod_timestamp'] ?: null;
		$obj->moderatedReason = isset( $row['rev_mod_reason'] ) && $row['rev_mod_reason']
			? $row['rev_mod_reason'] : null;

		// BC: 'suppress' used to be called 'censor' & 'lock' was 'close'
		$bc = [
			'censor' => self::MODERATED_SUPPRESSED,
			'close' => self::MODERATED_LOCKED,
		];
		$obj->moderationState = str_replace( array_keys( $bc ), array_values( $bc ), $obj->moderationState );

		// isset required because there is a possible db migration, cached data will not have it
		$obj->lastEditId = isset( $row['rev_last_edit_id'] ) && $row['rev_last_edit_id']
			? UUID::create( $row['rev_last_edit_id'] ) : null;
		$obj->lastEditUser = UserTuple::newFromArray( $row, 'rev_edit_user_' );

		$obj->contentLength = $row['rev_content_length'] ?? 0;
		$obj->previousContentLength = $row['rev_previous_content_length'] ?? 0;

		return $obj;
	}

	/**
	 * @param AbstractRevision $obj
	 * @return array
	 */
	public static function toStorageRow( $obj ) {
		return [
			'rev_id' => $obj->revId->getAlphadecimal(),
			'rev_user_id' => $obj->user->id,
			'rev_user_ip' => $obj->user->ip,
			'rev_user_wiki' => $obj->user->wiki,
			'rev_parent_id' => $obj->prevRevision ? $obj->prevRevision->getAlphadecimal() : null,
			'rev_change_type' => $obj->changeType,
			'rev_type' => $obj->getRevisionType(),
			'rev_type_id' => $obj->getCollectionId()->getAlphadecimal(),

			'rev_content' => $obj->content,
			'rev_content_url' => $obj->contentUrl,
			'rev_flags' => implode( ',', $obj->flags ),

			'rev_mod_state' => $obj->moderationState,
			'rev_mod_user_id' => $obj->moderatedBy ? $obj->moderatedBy->id : null,
			'rev_mod_user_ip' => $obj->moderatedBy ? $obj->moderatedBy->ip : null,
			'rev_mod_user_wiki' => $obj->moderatedBy ? $obj->moderatedBy->wiki : null,
			'rev_mod_timestamp' => $obj->moderationTimestamp,
			'rev_mod_reason' => $obj->moderatedReason,

			'rev_last_edit_id' => $obj->lastEditId ? $obj->lastEditId->getAlphadecimal() : null,
			'rev_edit_user_id' => $obj->lastEditUser ? $obj->lastEditUser->id : null,
			'rev_edit_user_ip' => $obj->lastEditUser ? $obj->lastEditUser->ip : null,
			'rev_edit_user_wiki' => $obj->lastEditUser ? $obj->lastEditUser->wiki : null,

			'rev_content_length' => $obj->contentLength,
			'rev_previous_content_length' => $obj->previousContentLength,
		];
	}

	/**
	 * NOTE: No guarantee is made here regarding if $this is the newest revision.  Validation
	 * must happen externally.  DB *will* throw an exception if this attempts to write to db
	 * and it is not the most recent revision.
	 *
	 * @param User $user
	 * @return AbstractRevision
	 * @throws PermissionException
	 */
	public function newNullRevision( User $user ) {
		if ( !MediaWikiServices::getInstance()->getPermissionManager()
				->userHasRight( $user, 'edit' )
		) {
			throw new PermissionException( 'User does not have core edit permission',
				'insufficient-permission' );
		}
		$obj = clone $this;
		$obj->revId = UUID::create();
		$obj->user = UserTuple::newFromUser( $user );
		$obj->prevRevision = $this->revId;
		$obj->changeType = '';
		$obj->previousContentLength = $obj->contentLength;

		return $obj;
	}

	/**
	 * Create the next revision with new content
	 * or return itself when content is the same
	 *
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html
	 * @param string $changeType
	 * @param Title $title The article title of the related workflow
	 * @return AbstractRevision
	 */
	public function newNextRevision( User $user, $content, $format, $changeType, Title $title ) {
		$obj = $this->newNullRevision( $user );
		$obj->setNextContent( $user, $content, $format, $title );
		$obj->changeType = $changeType;
		return $this->hasSameContentAs( $obj ) ? $this : $obj;
	}

	/**
	 * @param User $user
	 * @param string $state
	 * @param string $changeType
	 * @param string $reason
	 * @return AbstractRevision|null
	 */
	public function moderate( User $user, $state, $changeType, $reason ) {
		if ( !$this->isValidModerationState( $state ) ) {
			wfWarn( __METHOD__ . ': Provided moderation state does not exist : ' . $state );
			return null;
		}

		$obj = $this->newNullRevision( $user );
		$obj->changeType = $changeType;

		// This is a bit hacky, but we store the restore reason
		// in the "moderated reason" field. Hmmph.
		$obj->moderatedReason = $reason;
		$obj->moderationState = $state;

		if ( $state === self::MODERATED_NONE ) {
			$obj->moderatedBy = null;
			$obj->moderationTimestamp = null;
		} else {
			$obj->moderatedBy = UserTuple::newFromUser( $user );
			$obj->moderationTimestamp = $obj->revId->getTimestamp();
		}

		// all moderation levels past lock report a size of 0
		if ( $obj->isModerated() && !$obj->isLocked() ) {
			$obj->contentLength = 0;
		} else {
			// reset content length (we may be restoring, in which case $obj's
			// current length will be 0)
			$obj->contentLength = $this->calculateContentLength();
		}

		return $obj;
	}

	/**
	 * @param string $state
	 * @return bool
	 */
	public function isValidModerationState( $state ) {
		return in_array( $state, self::$perms );
	}

	/**
	 * @return UUID
	 */
	public function getRevisionId() {
		return $this->revId;
	}

	/**
	 * @return bool
	 */
	public function hasHiddenContent() {
		return $this->moderationState === self::MODERATED_HIDDEN;
	}

	/**
	 * @return string
	 */
	public function getContentRaw() {
		if ( $this->decompressedContent === null ) {
			$this->decompressedContent = MediaWikiServices::getInstance()
				->getBlobStoreFactory()
				->newSqlBlobStore()
				->decompressData( $this->content, $this->flags );
		}

		return $this->decompressedContent;
	}

	/**
	 * Checks whether the content is retrievable.
	 *
	 * False is an error state, used when the content is unretrievable, e.g. due to data loss (T95580)
	 * or a temporary database error.
	 *
	 * This is unrelated to whether the content is loaded on-demand.
	 *
	 * @return bool
	 */
	public function isContentCurrentlyRetrievable() {
		return $this->content !== false;
	}

	/**
	 * DO NOT USE THIS METHOD to output the content; use
	 * Templating::getContent, which will do additional (permissions-based)
	 * checks to make sure it outputs something the user can see.
	 *
	 * @param string $format Format to output content in
	 *   (html|wikitext|topic-title-wikitext|topic-title-html|topic-title-plaintext)
	 * @return string
	 * @return-taint onlysafefor_htmlnoent
	 * @throws InvalidDataException
	 * @throws \Flow\Exception\WikitextException
	 */
	public function getContent( $format = 'html' ) {
		if ( !$this->isContentCurrentlyRetrievable() ) {
			wfDebugLog( 'Flow', __METHOD__ . ': Failed to load the content of revision with rev_id ' .
				$this->revId->getAlphadecimal() );

			$stubContent = wfMessage( 'flow-stub-post-content' )->parse();
			if ( !in_array( $format, [ 'html', 'fixed-html' ] ) ) {
				$stubContent = Sanitizer::stripAllTags( $stubContent );
			}

			return $stubContent;
		}

		if ( $this->xssCheck === false ) {
			return '';
		}
		$raw = $this->getContentRaw();
		$sourceFormat = $this->getContentFormat();
		if ( $this->xssCheck === null && $sourceFormat === 'html' ) {
			// returns true if no handler aborted the hook
			$this->xssCheck = Hooks::run( 'FlowCheckHtmlContentXss', [ $raw ] );
			if ( !$this->xssCheck ) {
				wfDebugLog( 'Flow', __METHOD__ . ': XSS check prevented display of revision ' .
					$this->revId->getAlphadecimal() );
				return '';
			}
		}

		if ( !isset( $this->convertedContent[$format] ) ) {
			if ( $sourceFormat === $format ) {
				$this->convertedContent[$format] = $raw;
				if ( in_array( $format, [ 'fixed-html', 'html' ] ) ) {
					// For backwards compatibility wrap old content with body tag if necessary,
					// and restore the <base> tag based on the base-url attribute on the body tag,
					// if any. All of this is done by decodeHeadInfo().
					$this->convertedContent[$format] = Utils::decodeHeadInfo( $raw );
				}
			} else {
				$this->convertedContent[$format] = Utils::convert(
					$sourceFormat,
					$format,
					$raw,
					$this->getCollection()->getTitle()
				);
			}
		}

		return $this->convertedContent[$format];
	}

	/**
	 * Gets the content in a wikitext format.  In this class, it will be 'wikitext',
	 * but this can be overriden in sub-classes (e.g. to 'topic-title-wikitext' for topic titles).
	 *
	 * DO NOT USE THIS METHOD to output the content; use Templating::getContent for security reasons.
	 *
	 * @return string Text in a wikitext-based format.
	 */
	public function getContentInWikitext() {
		return $this->getContent( $this->getWikitextFormat() );
	}

	/**
	 * Gets a wikitext format that is suitable for this revision.
	 * In this class, it will be 'wikitext', but this can be overriden in sub-classes
	 * (e.g. to 'topic-title-wikitext' for topic titles).
	 *
	 * @return string Format name
	 */
	public function getWikitextFormat() {
		return 'wikitext';
	}

	/**
	 * Gets the content in an HTML format.  In this class, it will be 'html',
	 * but this can be overriden in sub-classes (e.g. to 'topic-title-html' for topic titles).
	 *
	 * DO NOT USE THIS METHOD to output the content; use Templating::getContent for security reasons.
	 *
	 * @return string Text in an HTML-based format.
	 */
	public function getContentInHtml() {
		return $this->getContent( $this->getHtmlFormat() );
	}

	/**
	 * Gets an HTML format that is suitable for this revision.
	 * In this class, it will be 'html', but this can be overriden in sub-classes
	 * (e.g. to 'topic-title-html' for topic titles).
	 *
	 * @return string Format name
	 */
	public function getHtmlFormat() {
		return 'html';
	}

	/**
	 * @return UserTuple
	 */
	public function getUserTuple() {
		return $this->user;
	}

	/**
	 * @return int
	 */
	public function getUserId() {
		return $this->user->id;
	}

	/**
	 * @return string|null
	 */
	public function getUserIp() {
		return $this->user->ip;
	}

	/**
	 * @return string
	 */
	public function getUserWiki() {
		return $this->user->wiki;
	}

	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user->createUser();
	}

	/**
	 * Should only be used for setting the initial content.  To set subsequent content
	 * use self::setNextContent
	 *
	 * @param string $content
	 * @param string $format wikitext|html|topic-title-wikitext
	 * @param Title|null $title When null the related workflow will be lazy-loaded to locate the title
	 * @throws DataModelException
	 */
	protected function setContent( $content, $format, Title $title = null ) {
		if ( $this->moderationState !== self::MODERATED_NONE ) {
			throw new DataModelException( 'TODO: Cannot change content of restricted revision',
				'process-data' );
		}

		if ( $this->content !== null ) {
			throw new DataModelException( 'Updating content must use setNextContent method', 'process-data' );
		}

		if ( !$title ) {
			$title = $this->getCollection()->getTitle();
		}

		if ( $format !== 'wikitext' && $format !== 'html' && $format !== 'topic-title-wikitext' ) {
			throw new DataModelException( 'Invalid format: Supported formats for new content are ' .
				'\'wikitext\', \'html\', and \'topic-title-wikitext\'' );
		}

		// never trust incoming html - roundtrip to wikitext first
		if ( $format === 'html' ) {
			$content = Utils::convert( $format, 'wikitext', $content, $title );
			$format = 'wikitext';
		}

		if ( $format === 'wikitext' ) {
			// Run pre-save transform
			$content = ContentHandler::makeContent( $content, $title, CONTENT_MODEL_WIKITEXT )
				->preSaveTransform(
					$title,
					$this->getUser(),
					WikiPage::factory( $title )->makeParserOptions( $this->getUser() )
				)
				->serialize( 'text/x-wiki' );
		}

		// Keep consistent with normal edit page, trim only trailing whitespaces
		$content = rtrim( $content );
		$this->convertedContent = [ $format => $content ];

		// convert content to desired storage format
		$storageFormat = $this->getStorageFormat();
		if ( $storageFormat !== $format ) {
			$this->convertedContent[$storageFormat] = Utils::convert(
				$format, $storageFormat, $content, $title );
		}

		$this->setContentRaw( $this->convertedContent );
	}

	/**
	 * Helper function for setContent(). Don't call this directly.
	 * Also called by the FlowReserializeRevisionContent maintenance script using reflection.
	 *
	 * $convertedContent may contain 'html', 'wikitext' or both, but must at least contain the
	 * storage format (as returned by getStorageFormat()).
	 *
	 * @param array $convertedContent [ 'html' => string, 'wikitext' => string ]
	 */
	protected function setContentRaw( $convertedContent ) {
		$storageFormat = $this->getStorageFormat();
		if ( !isset( $convertedContent[ $storageFormat ] ) ) {
			throw new DataModelException( 'Content not given in storage format ' . $storageFormat );
		}

		$this->convertedContent = $convertedContent;
		$this->content = $this->decompressedContent = $this->convertedContent[$storageFormat];
		$this->contentUrl = null;

		// should this only remove a subset of flags?
		$compressed = MediaWikiServices::getInstance()
			->getBlobStoreFactory()
			->newSqlBlobStore()
			->compressData( $this->content );
		$this->flags = array_filter( explode( ',', $compressed ) );
		$this->flags[] = $storageFormat;

		$this->contentLength = $this->calculateContentLength();
	}

	/**
	 * Apply new content to a revision.
	 *
	 * @param User $user
	 * @param string $content
	 * @param string $format wikitext|html|topic-title-wikitext
	 * @param Title|null $title When null the related workflow will be lazy-loaded to locate the title
	 * @throws DataModelException
	 */
	protected function setNextContent( User $user, $content, $format, Title $title = null ) {
		if ( $this->moderationState !== self::MODERATED_NONE ) {
			throw new DataModelException( 'Cannot change content of restricted revision', 'process-data' );
		}

		// Do we need this if check, or just the one in newNextRevision against the prior revision?
		if ( $content !== $this->getContent( $format ) ) {
			$this->content = null;
			$this->setContent( $content, $format, $title );
			$this->lastEditId = $this->getRevisionId();
			$this->lastEditUser = UserTuple::newFromUser( $user );
		}
	}

	/**
	 * @return string The content format of this revision
	 */
	public function getContentFormat() {
		return in_array( 'html', $this->flags ) ? 'html' : 'wikitext';
	}

	/**
	 * Determines the appropriate format to store content in.
	 * NOTE: The format of the current content is retrieved with getContentFormat
	 *
	 * @return string The name of the storage format.
	 */
	protected function getStorageFormat() {
		global $wgFlowContentFormat;

		return $wgFlowContentFormat;
	}

	/**
	 * @return UUID|null
	 */
	public function getPrevRevisionId() {
		return $this->prevRevision;
	}

	/**
	 * @return string
	 */
	public function getChangeType() {
		return $this->changeType;
	}

	/**
	 * @return string
	 */
	public function getModerationState() {
		return $this->moderationState;
	}

	/**
	 * @return string|null
	 */
	public function getModeratedReason() {
		return $this->moderatedReason;
	}

	/**
	 * @return bool
	 */
	public function isModerated() {
		return $this->moderationState !== self::MODERATED_NONE;
	}

	/**
	 * @return bool
	 */
	public function isHidden() {
		return $this->moderationState === self::MODERATED_HIDDEN;
	}

	/**
	 * @return bool
	 */
	public function isDeleted() {
		return $this->moderationState === self::MODERATED_DELETED;
	}

	/**
	 * @return bool
	 */
	public function isSuppressed() {
		return $this->moderationState === self::MODERATED_SUPPRESSED;
	}

	/**
	 * @return bool
	 */
	public function isLocked() {
		return $this->moderationState === self::MODERATED_LOCKED;
	}

	/**
	 * @return string|null Timestamp in TS_MW format
	 */
	public function getModerationTimestamp() {
		return $this->moderationTimestamp;
	}

	/**
	 * @param string|array $flags
	 * @return bool True when at least one flag in $flags is set
	 */
	public function isFlaggedAny( $flags ) {
		foreach ( (array)$flags as $flag ) {
			if ( false !== array_search( $flag, $this->flags ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string|array $flags
	 * @return bool
	 */
	public function isFlaggedAll( $flags ) {
		foreach ( (array)$flags as $flag ) {
			if ( false === array_search( $flag, $this->flags ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @return bool
	 */
	public function isFirstRevision() {
		return $this->prevRevision === null;
	}

	/**
	 * @return bool
	 */
	public function isOriginalContent() {
		return $this->lastEditId === null;
	}

	/**
	 * @return UUID
	 */
	public function getLastContentEditId() {
		return $this->lastEditId;
	}

	/**
	 * @return UserTuple
	 */
	public function getLastContentEditUserTuple() {
		return $this->lastEditUser;
	}

	/**
	 * @return int|null
	 */
	public function getLastContentEditUserId() {
		return $this->lastEditUser ? $this->lastEditUser->id : null;
	}

	/**
	 * @return string|null
	 */
	public function getLastContentEditUserIp() {
		return $this->lastEditUser ? $this->lastEditUser->ip : null;
	}

	/**
	 * @return string|null
	 */
	public function getLastContentEditUserWiki() {
		return $this->lastEditUser ? $this->lastEditUser->wiki : null;
	}

	/**
	 * @return UserTuple
	 */
	public function getModeratedByTuple() {
		return $this->moderatedBy;
	}

	/**
	 * @return int|null
	 */
	public function getModeratedByUserId() {
		return $this->moderatedBy ? $this->moderatedBy->id : null;
	}

	/**
	 * @return string|null
	 */
	public function getModeratedByUserIp() {
		return $this->moderatedBy ? $this->moderatedBy->ip : null;
	}

	/**
	 * @return string|null
	 */
	public function getModeratedByUserWiki() {
		return $this->moderatedBy ? $this->moderatedBy->wiki : null;
	}

	public static function getModerationChangeTypes() {
		if ( self::$moderationChangeTypes === null ) {
			self::$moderationChangeTypes = [];
			foreach ( self::$perms as $perm ) {
				if ( $perm != '' ) {
					self::$moderationChangeTypes[] = "{$perm}-topic";
					self::$moderationChangeTypes[] = "{$perm}-post";
				}
			}

			self::$moderationChangeTypes[] = 'restore-topic';
			self::$moderationChangeTypes[] = 'restore-post';
		}

		return self::$moderationChangeTypes;
	}

	public function isModerationChange() {
		return in_array( $this->getChangeType(), self::getModerationChangeTypes() );
	}

	/**
	 * @return int
	 */
	public function getContentLength() {
		return $this->contentLength;
	}

	// Only public for FlowUpdateRevisionContentLength.

	/**
	 * Determines the content length by measuring the actual content.
	 *
	 * @return int
	 */
	public function calculateContentLength() {
		return mb_strlen( $this->getContentInWikitext() );
	}

	/**
	 * @return int
	 */
	public function getPreviousContentLength() {
		return $this->previousContentLength;
	}

	/**
	 * Finds the RecentChange object associated with this flow revision.
	 *
	 * @return null|RecentChange
	 */
	public function getRecentChange() {
		$timestamp = $this->revId->getTimestamp();

		if ( !RecentChange::isInRCLifespan( $timestamp ) ) {
			// Too old to be in RC, don't even bother checking
			return null;
		}
		$workflow = $this->getCollection()->getWorkflow();
		if ( $this->changeType === 'new-post' ) {
			$title = $workflow->getOwnerTitle();
		} else {
			$title = $workflow->getArticleTitle();
		}
		$namespace = $title->getNamespace();

		$conditions = [
			'rc_title' => $title->getDBkey(),
			'rc_timestamp' => $timestamp,
			'rc_namespace' => $namespace
		];
		$options = [ 'USE INDEX' => [ 'recentchanges' => 'rc_timestamp' ] ];

		$dbr = wfGetDB( DB_REPLICA );
		$rcQuery = RecentChange::getQueryInfo();
		$rows = $dbr->select(
			$rcQuery['tables'],
			$rcQuery['fields'],
			$conditions,
			__METHOD__,
			$options,
			$rcQuery['joins']
		);

		if ( $rows === false ) {
			return null;
		}

		if ( $rows->numRows() === 1 ) {
			return RecentChange::newFromRow( $rows->fetchObject() );
		}

		// it is possible that more than 1 changes on the same page have the same timestamp
		// the revision id is hidden in rc_params['flow-workflow-change']['revision']
		$revId = $this->revId->getAlphadecimal();
		// @codingStandardsIgnoreStart
		while ( $row = $rows->next() ) {
		// @codingStandardsIgnoreEnd
			$rc = RecentChange::newFromRow( $row );
			$params = $rc->parseParams();
			if ( $params && $params['flow-workflow-change']['revision'] === $revId ) {
				return $rc;
			}
		}

		return null;
	}

	/**
	 * @return UserTuple
	 */
	public function getCreatorTuple() {
		if ( !$this->creator ) {
			if ( $this->isFirstRevision() ) {
				$this->creator = $this->user;
			} else {
				$this->creator = $this->getCollection()->getFirstRevision()->getUserTuple();
			}
		}

		return $this->creator;
	}

	/**
	 * Get the user ID of the user who created this summary.
	 *
	 * @return int The user ID
	 */
	public function getCreatorId() {
		return $this->getCreatorTuple()->id;
	}

	/**
	 * @return string
	 */
	public function getCreatorWiki() {
		return $this->getCreatorTuple()->wiki;
	}

	/**
	 * Get the user ip of the user who created this summary if it
	 * was created by an anonymous user
	 *
	 * @return string|null String if an creator is anon, or null if not.
	 */
	public function getCreatorIp() {
		return $this->getCreatorTuple()->ip;
	}

	/**
	 * @param AbstractRevision $revision
	 * @return bool
	 * @throws InvalidDataException
	 */
	protected function hasSameContentAs( AbstractRevision $revision ) {
		return $this->getContentInWikitext() === $revision->getContentInWikitext();
	}

	/**
	 * @return string
	 */
	abstract public function getRevisionType();

	/**
	 * @return UUID
	 */
	abstract public function getCollectionId();

	/**
	 * @return AbstractCollection
	 */
	abstract public function getCollection();
}
