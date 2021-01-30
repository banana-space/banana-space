<?php

namespace Flow;

use Flow\Exception\FlowException;
use Flow\Exception\InvalidParameterException;
use Flow\Exception\PermissionException;
use Flow\Model\AbstractRevision;
use Flow\Model\PostRevision;
use Flow\Parsoid\ContentFixer;
use Flow\Repository\UserNameBatch;
use Linker;
use OutputPage;

/**
 * This class is slowly being deprecated. It used to house a minimalist
 * php templating system, it is now just a few of the helpers that were
 * reused in the new api responses and other parts of Flow.
 */
class Templating {
	/**
	 * @var UserNameBatch
	 */
	protected $usernames;

	/**
	 * @var UrlGenerator
	 */
	public $urlGenerator;

	/**
	 * @var OutputPage
	 */
	protected $output;

	/**
	 * @var RevisionActionPermissions
	 */
	protected $permissions;

	/**
	 * @var ContentFixer
	 */
	protected $contentFixer;

	/**
	 * @param UserNameBatch $usernames
	 * @param UrlGenerator $urlGenerator
	 * @param OutputPage $output
	 * @param ContentFixer $contentFixer
	 * @param RevisionActionPermissions $permissions
	 */
	public function __construct(
		UserNameBatch $usernames,
		UrlGenerator $urlGenerator,
		OutputPage $output,
		ContentFixer $contentFixer,
		RevisionActionPermissions $permissions
	) {
		$this->usernames = $usernames;
		$this->urlGenerator = $urlGenerator;
		$this->output = $output;
		$this->contentFixer = $contentFixer;
		$this->permissions = $permissions;
	}

	/**
	 * @return OutputPage
	 */
	public function getOutput() {
		return $this->output;
	}

	public function getUrlGenerator() {
		return $this->urlGenerator;
	}

	/**
	 * Returns pretty-printed user links + user tool links for history and
	 * RecentChanges pages.
	 *
	 * Moderation-aware.
	 *
	 * @param AbstractRevision $revision Revision to display
	 * @return string HTML
	 * @throws PermissionException
	 */
	public function getUserLinks( AbstractRevision $revision ) {
		if ( !$revision->isModerated() && !$this->permissions->isAllowed( $revision, 'history' ) ) {
			throw new PermissionException( 'Insufficient permissions to see userlinks for rev_id = ' .
				$revision->getRevisionId()->getAlphadecimal() );
		}

		// Convert to use MapCacheLRU?
		// if this specific revision is moderated, its usertext can always be
		// displayed, since it will be the moderator user
		static $cache = [];
		$userid = $revision->getUserId();
		$userip = $revision->getUserIp();
		if ( isset( $cache[$userid][$userip] ) ) {
			return $cache[$userid][$userip];
		}
		$username = $this->usernames->get( wfWikiID(), $userid, $userip );
		$cache[$userid][$userip] = $username ?
			Linker::userLink( $userid, $username ) . Linker::userToolLinks( $userid, $username ) :
			'';
		return $cache[$userid][$userip];
	}

	/**
	 * Usually the revisions's content can just be displayed. In the event
	 * of moderation, however, that info should not be exposed.
	 *
	 * If a specific i18n message is available for a certain moderation level,
	 * that message will be returned (well, unless the user actually has the
	 * required permissions to view the full content). Otherwise, in normal
	 * cases, the full content will be returned.
	 *
	 * The content-type of the return value varies on the $format parameter.
	 * Further processing in the final output stage must escape all formats
	 * other than the default 'html' and 'fixed-html'.
	 *
	 * @param AbstractRevision $revision Revision to display content for
	 * @param string $format Format to output content in one of:
	 *   (fixed-html|html|wikitext|topic-title-html|topic-title-wikitext|topic-title-plaintext)
	 * @return string HTML if requested, otherwise plain text
	 * @throws InvalidParameterException
	 */
	public function getContent( AbstractRevision $revision, $format = 'fixed-html' ) {
		if ( !in_array( $format, [ 'fixed-html', 'html', 'wikitext', 'topic-title-html',
				'topic-title-wikitext', 'topic-title-plaintext' ]
			)
		) {
			throw new InvalidParameterException( 'Invalid format: ' . $format );
		}

		$mainPermissionAction = ( $revision instanceof PostRevision && $revision->isTopicTitle() ) ?
			'view-topic-title' :
			'view';

		$allowed = $this->permissions->isAllowed( $revision, $mainPermissionAction );
		// Posts require view access to the topic title as well
		if ( $allowed && $revision instanceof PostRevision && !$revision->isTopicTitle() ) {
			$allowed = $this->permissions->isAllowed(
				$revision->getRootPost(),
				'view'
			);
		}

		if ( !$allowed ) {
			// failsafe - never output content if permissions aren't satisfied!
			return '';
		}

		if ( $format === 'fixed-html' ) {
			// Parsoid doesn't render redlinks & doesn't strip bad images
			$content = $this->contentFixer->getContent( $revision );
		} else {
			$content = $revision->getContent( $format );
		}

		return $content;
	}

	public function getModeratedRevision( AbstractRevision $revision ) {
		if ( $revision->isModerated() ) {
			return $revision;
		} else {
			try {
				return Container::get( 'collection.cache' )->getLastRevisionFor( $revision );
			} catch ( FlowException $e ) {
				wfDebugLog( 'Flow', "Failed loading last revision for revid " .
					$revision->getRevisionId()->getAlphadecimal() . " with collection id " .
					$revision->getCollectionId()->getAlphadecimal() );
				throw $e;
			}
		}
	}
}
