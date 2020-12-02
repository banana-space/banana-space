<?php
/**
 * Implements Special:Revisiondelete
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionRecord;

/**
 * Special page allowing users with the appropriate permissions to view
 * and hide revisions. Log items can also be hidden.
 *
 * @ingroup SpecialPage
 */
class SpecialRevisionDelete extends UnlistedSpecialPage {
	/** @var bool Was the DB modified in this request */
	protected $wasSaved = false;

	/** @var bool True if the submit button was clicked, and the form was posted */
	private $submitClicked;

	/** @var array Target ID list */
	private $ids;

	/** @var string Archive name, for reviewing deleted files */
	private $archiveName;

	/** @var string Edit token for securing image views against XSS */
	private $token;

	/** @var Title Title object for target parameter */
	private $targetObj;

	/** @var string Deletion type, may be revision, archive, oldimage, filearchive, logging. */
	private $typeName;

	/** @var array Array of checkbox specs (message, name, deletion bits) */
	private $checks;

	/** @var array UI Labels about the current type */
	private $typeLabels;

	/** @var RevDelList RevDelList object, storing the list of items to be deleted/undeleted */
	private $revDelList;

	/** @var bool Whether user is allowed to perform the action */
	private $mIsAllowed;

	/** @var string */
	private $otherReason;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var RepoGroup */
	private $repoGroup;

	/**
	 * UI labels for each type.
	 */
	private const UI_LABELS = [
		'revision' => [
			'check-label' => 'revdelete-hide-text',
			'success' => 'revdelete-success',
			'failure' => 'revdelete-failure',
			'text' => 'revdelete-text-text',
			'selected' => 'revdelete-selected-text',
		],
		'archive' => [
			'check-label' => 'revdelete-hide-text',
			'success' => 'revdelete-success',
			'failure' => 'revdelete-failure',
			'text' => 'revdelete-text-text',
			'selected' => 'revdelete-selected-text',
		],
		'oldimage' => [
			'check-label' => 'revdelete-hide-image',
			'success' => 'revdelete-success',
			'failure' => 'revdelete-failure',
			'text' => 'revdelete-text-file',
			'selected' => 'revdelete-selected-file',
		],
		'filearchive' => [
			'check-label' => 'revdelete-hide-image',
			'success' => 'revdelete-success',
			'failure' => 'revdelete-failure',
			'text' => 'revdelete-text-file',
			'selected' => 'revdelete-selected-file',
		],
		'logging' => [
			'check-label' => 'revdelete-hide-name',
			'success' => 'logdelete-success',
			'failure' => 'logdelete-failure',
			'text' => 'logdelete-text',
			'selected' => 'logdelete-selected',
		],
	];

	/**
	 * @inheritDoc
	 *
	 * @param PermissionManager $permissionManager
	 * @param RepoGroup $repoGroup
	 */
	public function __construct( PermissionManager $permissionManager, RepoGroup $repoGroup ) {
		parent::__construct( 'Revisiondelete', 'deleterevision' );

		$this->permissionManager = $permissionManager;
		$this->repoGroup = $repoGroup;
	}

	public function doesWrites() {
		return true;
	}

	public function execute( $par ) {
		$this->useTransactionalTimeLimit();

		$this->checkPermissions();
		$this->checkReadOnly();

		$output = $this->getOutput();
		$user = $this->getUser();

		$this->setHeaders();
		$this->outputHeader();
		$request = $this->getRequest();
		$this->submitClicked = $request->wasPosted() && $request->getBool( 'wpSubmit' );
		# Handle our many different possible input types.
		$ids = $request->getVal( 'ids' );
		if ( $ids !== null ) {
			# Allow CSV, for backwards compatibility, or a single ID for show/hide links
			$this->ids = explode( ',', $ids );
		} else {
			# Array input
			$this->ids = array_keys( $request->getArray( 'ids', [] ) );
		}
		// $this->ids = array_map( 'intval', $this->ids );
		$this->ids = array_unique( array_filter( $this->ids ) );

		$this->typeName = $request->getVal( 'type' );
		$this->targetObj = Title::newFromText( $request->getText( 'target' ) );

		# For reviewing deleted files...
		$this->archiveName = $request->getVal( 'file' );
		$this->token = $request->getVal( 'token' );
		if ( $this->archiveName && $this->targetObj ) {
			$this->tryShowFile( $this->archiveName );

			return;
		}

		$this->typeName = RevisionDeleter::getCanonicalTypeName( $this->typeName );

		# No targets?
		if ( !$this->typeName || count( $this->ids ) == 0 ) {
			throw new ErrorPageError( 'revdelete-nooldid-title', 'revdelete-nooldid-text' );
		}

		# Allow the list type to adjust the passed target
		$this->targetObj = RevisionDeleter::suggestTarget(
			$this->typeName,
			$this->targetObj,
			$this->ids
		);

		# We need a target page!
		if ( $this->targetObj === null ) {
			$output->addWikiMsg( 'undelete-header' );

			return;
		}

		// Check blocks
		if ( $this->permissionManager->isBlockedFrom( $user, $this->targetObj ) ) {
			throw new UserBlockedError(
				$user->getBlock(),
				$user,
				$this->getLanguage(),
				$request->getIP()
			);
		}

		$this->typeLabels = self::UI_LABELS[$this->typeName];
		$list = $this->getList();
		$list->reset();
		$this->mIsAllowed = $this->permissionManager->userHasRight( $user,
			RevisionDeleter::getRestriction( $this->typeName ) );
		$canViewSuppressedOnly = $this->permissionManager->userHasRight( $user, 'viewsuppressed' ) &&
			!$this->permissionManager->userHasRight( $user, 'suppressrevision' );
		$pageIsSuppressed = $list->areAnySuppressed();
		$this->mIsAllowed = $this->mIsAllowed && !( $canViewSuppressedOnly && $pageIsSuppressed );

		$this->otherReason = $request->getVal( 'wpReason' );
		# Give a link to the logs/hist for this page
		$this->showConvenienceLinks();

		# Initialise checkboxes
		$this->checks = [
			# Messages: revdelete-hide-text, revdelete-hide-image, revdelete-hide-name
			[ $this->typeLabels['check-label'], 'wpHidePrimary',
				RevisionDeleter::getRevdelConstant( $this->typeName )
			],
			[ 'revdelete-hide-comment', 'wpHideComment', RevisionRecord::DELETED_COMMENT ],
			[ 'revdelete-hide-user', 'wpHideUser', RevisionRecord::DELETED_USER ]
		];
		if ( $this->permissionManager->userHasRight( $user, 'suppressrevision' ) ) {
			$this->checks[] = [ 'revdelete-hide-restricted',
				'wpHideRestricted', RevisionRecord::DELETED_RESTRICTED ];
		}

		# Either submit or create our form
		if ( $this->mIsAllowed && $this->submitClicked ) {
			$this->submit();
		} else {
			$this->showForm();
		}

		if ( $this->permissionManager->userHasRight( $user, 'deletedhistory' ) ) {
			$qc = $this->getLogQueryCond();
			# Show relevant lines from the deletion log
			$deleteLogPage = new LogPage( 'delete' );
			$output->addHTML( "<h2>" . $deleteLogPage->getName()->escaped() . "</h2>\n" );
			LogEventsList::showLogExtract(
				$output,
				'delete',
				$this->targetObj,
				'', /* user */
				[ 'lim' => 25, 'conds' => $qc, 'useMaster' => $this->wasSaved ]
			);
		}
		# Show relevant lines from the suppression log
		if ( $this->permissionManager->userHasRight( $user, 'suppressionlog' ) ) {
			$suppressLogPage = new LogPage( 'suppress' );
			$output->addHTML( "<h2>" . $suppressLogPage->getName()->escaped() . "</h2>\n" );
			LogEventsList::showLogExtract(
				$output,
				'suppress',
				$this->targetObj,
				'',
				[ 'lim' => 25, 'conds' => $qc, 'useMaster' => $this->wasSaved ]
			);
		}
	}

	/**
	 * Show some useful links in the subtitle
	 */
	protected function showConvenienceLinks() {
		$linkRenderer = $this->getLinkRenderer();
		# Give a link to the logs/hist for this page
		if ( $this->targetObj ) {
			// Also set header tabs to be for the target.
			$this->getSkin()->setRelevantTitle( $this->targetObj );

			$links = [];
			$links[] = $linkRenderer->makeKnownLink(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'viewpagelogs' )->text(),
				[],
				[ 'page' => $this->targetObj->getPrefixedText() ]
			);
			if ( !$this->targetObj->isSpecialPage() ) {
				# Give a link to the page history
				$links[] = $linkRenderer->makeKnownLink(
					$this->targetObj,
					$this->msg( 'pagehist' )->text(),
					[],
					[ 'action' => 'history' ]
				);
				# Link to deleted edits
				if ( $this->permissionManager->userHasRight( $this->getUser(), 'undelete' ) ) {
					$undelete = SpecialPage::getTitleFor( 'Undelete' );
					$links[] = $linkRenderer->makeKnownLink(
						$undelete,
						$this->msg( 'deletedhist' )->text(),
						[],
						[ 'target' => $this->targetObj->getPrefixedDBkey() ]
					);
				}
			}
			# Logs themselves don't have histories or archived revisions
			$this->getOutput()->addSubtitle( $this->getLanguage()->pipeList( $links ) );
		}
	}

	/**
	 * Get the condition used for fetching log snippets
	 * @return array
	 */
	protected function getLogQueryCond() {
		$conds = [];
		// Revision delete logs for these item
		$conds['log_type'] = [ 'delete', 'suppress' ];
		$conds['log_action'] = $this->getList()->getLogAction();
		$conds['ls_field'] = RevisionDeleter::getRelationType( $this->typeName );
		// Convert IDs to strings, since ls_value is a text field. This avoids
		// a fatal error in PostgreSQL: "operator does not exist: text = integer".
		$conds['ls_value'] = array_map( 'strval', $this->ids );

		return $conds;
	}

	/**
	 * Show a deleted file version requested by the visitor.
	 * @todo Mostly copied from Special:Undelete. Refactor.
	 * @param string $archiveName
	 * @throws MWException
	 * @throws PermissionsError
	 */
	protected function tryShowFile( $archiveName ) {
		$repo = $this->repoGroup->getLocalRepo();
		$oimage = $repo->newFromArchiveName( $this->targetObj, $archiveName );
		$oimage->load();
		// Check if user is allowed to see this file
		if ( !$oimage->exists() ) {
			$this->getOutput()->addWikiMsg( 'revdelete-no-file' );

			return;
		}
		$user = $this->getUser();
		if ( !$oimage->userCan( File::DELETED_FILE, $user ) ) {
			if ( $oimage->isDeleted( File::DELETED_RESTRICTED ) ) {
				throw new PermissionsError( 'suppressrevision' );
			} else {
				throw new PermissionsError( 'deletedtext' );
			}
		}
		if ( !$user->matchEditToken( $this->token, $archiveName ) ) {
			$lang = $this->getLanguage();
			$this->getOutput()->addWikiMsg( 'revdelete-show-file-confirm',
				$this->targetObj->getText(),
				$lang->userDate( $oimage->getTimestamp(), $user ),
				$lang->userTime( $oimage->getTimestamp(), $user ) );
			$this->getOutput()->addHTML(
				Xml::openElement( 'form', [
					'method' => 'POST',
					'action' => $this->getPageTitle()->getLocalURL( [
							'target' => $this->targetObj->getPrefixedDBkey(),
							'file' => $archiveName,
							'token' => $user->getEditToken( $archiveName ),
						] )
					]
				) .
				Xml::submitButton( $this->msg( 'revdelete-show-file-submit' )->text() ) .
				'</form>'
			);

			return;
		}
		$this->getOutput()->disable();
		# We mustn't allow the output to be CDN cached, otherwise
		# if an admin previews a deleted image, and it's cached, then
		# a user without appropriate permissions can toddle off and
		# nab the image, and CDN will serve it
		$this->getRequest()->response()->header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', 0 ) . ' GMT' );
		$this->getRequest()->response()->header(
			'Cache-Control: no-cache, no-store, max-age=0, must-revalidate'
		);
		$this->getRequest()->response()->header( 'Pragma: no-cache' );

		$key = $oimage->getStorageKey();
		$path = $repo->getZonePath( 'deleted' ) . '/' . $repo->getDeletedHashPath( $key ) . $key;
		$repo->streamFileWithStatus( $path );
	}

	/**
	 * Get the list object for this request
	 * @return RevDelList
	 */
	protected function getList() {
		if ( $this->revDelList === null ) {
			$this->revDelList = RevisionDeleter::createList(
				$this->typeName, $this->getContext(), $this->targetObj, $this->ids
			);
		}

		return $this->revDelList;
	}

	/**
	 * Show a list of items that we will operate on, and show a form with checkboxes
	 * which will allow the user to choose new visibility settings.
	 */
	protected function showForm() {
		$userAllowed = true;

		// Messages: revdelete-selected-text, revdelete-selected-file, logdelete-selected
		$out = $this->getOutput();
		$out->wrapWikiMsg( "<strong>$1</strong>", [ $this->typeLabels['selected'],
			$this->getLanguage()->formatNum( count( $this->ids ) ), $this->targetObj->getPrefixedText() ] );

		$this->addHelpLink( 'Help:RevisionDelete' );
		$out->addHTML( "<ul>" );

		$numRevisions = 0;
		// Live revisions...
		$list = $this->getList();
		for ( $list->reset(); $list->current(); $list->next() ) {
			$item = $list->current();

			if ( !$item->canView() ) {
				if ( !$this->submitClicked ) {
					throw new PermissionsError( 'suppressrevision' );
				}
				$userAllowed = false;
			}

			$numRevisions++;
			$out->addHTML( $item->getHTML() );
		}

		if ( !$numRevisions ) {
			throw new ErrorPageError( 'revdelete-nooldid-title', 'revdelete-nooldid-text' );
		}

		$out->addHTML( "</ul>" );
		// Explanation text
		$this->addUsageText();

		// Normal sysops can always see what they did, but can't always change it
		if ( !$userAllowed ) {
			return;
		}

		// Show form if the user can submit
		if ( $this->mIsAllowed ) {
			$suppressAllowed = $this->permissionManager
				->userHasRight( $this->getUser(), 'suppressrevision' );
			$out->addModules( [ 'mediawiki.special.revisionDelete' ] );
			$out->addModuleStyles( [ 'mediawiki.special',
				'mediawiki.interface.helpers.styles' ] );

			$dropDownReason = $this->msg( 'revdelete-reason-dropdown' )->inContentLanguage()->text();
			// Add additional specific reasons for suppress
			if ( $suppressAllowed ) {
				$dropDownReason .= "\n" . $this->msg( 'revdelete-reason-dropdown-suppress' )
					->inContentLanguage()->text();
			}

			$form = Xml::openElement( 'form', [ 'method' => 'post',
					'action' => $this->getPageTitle()->getLocalURL( [ 'action' => 'submit' ] ),
					'id' => 'mw-revdel-form-revisions' ] ) .
				Xml::fieldset( $this->msg( 'revdelete-legend' )->text() ) .
				$this->buildCheckBoxes() .
				Xml::openElement( 'table' ) .
				"<tr>\n" .
					'<td class="mw-label">' .
						Xml::label( $this->msg( 'revdelete-log' )->text(), 'wpRevDeleteReasonList' ) .
					'</td>' .
					'<td class="mw-input">' .
						Xml::listDropDown( 'wpRevDeleteReasonList',
							$dropDownReason,
							$this->msg( 'revdelete-reasonotherlist' )->inContentLanguage()->text(),
							$this->getRequest()->getText( 'wpRevDeleteReasonList', 'other' ), 'wpReasonDropDown'
						) .
					'</td>' .
				"</tr><tr>\n" .
					'<td class="mw-label">' .
						Xml::label( $this->msg( 'revdelete-otherreason' )->text(), 'wpReason' ) .
					'</td>' .
					'<td class="mw-input">' .
						Xml::input( 'wpReason', 60, $this->otherReason, [
							'id' => 'wpReason',
							// HTML maxlength uses "UTF-16 code units", which means that characters outside BMP
							// (e.g. emojis) count for two each. This limit is overridden in JS to instead count
							// Unicode codepoints.
							// "- 155" is to leave room for the 'wpRevDeleteReasonList' value.
							'maxlength' => CommentStore::COMMENT_CHARACTER_LIMIT - 155,
						] ) .
					'</td>' .
				"</tr><tr>\n" .
					'<td></td>' .
					'<td class="mw-submit">' .
						Xml::submitButton( $this->msg( 'revdelete-submit', $numRevisions )->text(),
							[ 'name' => 'wpSubmit' ] ) .
					'</td>' .
				"</tr>\n" .
				Xml::closeElement( 'table' ) .
				Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
				Html::hidden( 'target', $this->targetObj->getPrefixedText() ) .
				Html::hidden( 'type', $this->typeName ) .
				Html::hidden( 'ids', implode( ',', $this->ids ) ) .
				Xml::closeElement( 'fieldset' ) . "\n" .
				Xml::closeElement( 'form' ) . "\n";
			// Show link to edit the dropdown reasons
			if ( $this->permissionManager->userHasRight( $this->getUser(), 'editinterface' ) ) {
				$link = '';
				$linkRenderer = $this->getLinkRenderer();
				if ( $suppressAllowed ) {
					$link .= $linkRenderer->makeKnownLink(
						$this->msg( 'revdelete-reason-dropdown-suppress' )->inContentLanguage()->getTitle(),
						$this->msg( 'revdelete-edit-reasonlist-suppress' )->text(),
						[],
						[ 'action' => 'edit' ]
					);
					$link .= $this->msg( 'pipe-separator' )->escaped();
				}
				$link .= $linkRenderer->makeKnownLink(
					$this->msg( 'revdelete-reason-dropdown' )->inContentLanguage()->getTitle(),
					$this->msg( 'revdelete-edit-reasonlist' )->text(),
					[],
					[ 'action' => 'edit' ]
				);
				$form .= Xml::tags( 'p', [ 'class' => 'mw-revdel-editreasons' ], $link ) . "\n";
			}
		} else {
			$form = '';
		}
		$out->addHTML( $form );
	}

	/**
	 * Show some introductory text
	 * @todo FIXME: Wikimedia-specific policy text
	 */
	protected function addUsageText() {
		// Messages: revdelete-text-text, revdelete-text-file, logdelete-text
		$this->getOutput()->wrapWikiMsg(
			"<strong>$1</strong>\n$2", $this->typeLabels['text'],
			'revdelete-text-others'
		);

		if ( $this->permissionManager->userHasRight( $this->getUser(), 'suppressrevision' ) ) {
			$this->getOutput()->addWikiMsg( 'revdelete-suppress-text' );
		}

		if ( $this->mIsAllowed ) {
			$this->getOutput()->addWikiMsg( 'revdelete-confirm' );
		}
	}

	/**
	 * @return string HTML
	 */
	protected function buildCheckBoxes() {
		$html = '<table>';
		// If there is just one item, use checkboxes
		$list = $this->getList();
		if ( $list->length() == 1 ) {
			$list->reset();
			$bitfield = $list->current()->getBits(); // existing field

			if ( $this->submitClicked ) {
				$bitfield = RevisionDeleter::extractBitfield( $this->extractBitParams(), $bitfield );
			}

			foreach ( $this->checks as $item ) {
				// Messages: revdelete-hide-text, revdelete-hide-image, revdelete-hide-name,
				// revdelete-hide-comment, revdelete-hide-user, revdelete-hide-restricted
				list( $message, $name, $field ) = $item;
				$innerHTML = Xml::checkLabel(
					$this->msg( $message )->text(),
					$name,
					$name,
					$bitfield & $field
				);

				if ( $field == RevisionRecord::DELETED_RESTRICTED ) {
					$innerHTML = "<b>$innerHTML</b>";
				}

				$line = Xml::tags( 'td', [ 'class' => 'mw-input' ], $innerHTML );
				$html .= "<tr>$line</tr>\n";
			}
		} else {
			// Otherwise, use tri-state radios
			$html .= '<tr>';
			$html .= '<th class="mw-revdel-checkbox">'
				. $this->msg( 'revdelete-radio-same' )->escaped() . '</th>';
			$html .= '<th class="mw-revdel-checkbox">'
				. $this->msg( 'revdelete-radio-unset' )->escaped() . '</th>';
			$html .= '<th class="mw-revdel-checkbox">'
				. $this->msg( 'revdelete-radio-set' )->escaped() . '</th>';
			$html .= "<th></th></tr>\n";
			foreach ( $this->checks as $item ) {
				// Messages: revdelete-hide-text, revdelete-hide-image, revdelete-hide-name,
				// revdelete-hide-comment, revdelete-hide-user, revdelete-hide-restricted
				list( $message, $name, $field ) = $item;
				// If there are several items, use third state by default...
				if ( $this->submitClicked ) {
					$selected = $this->getRequest()->getInt( $name, 0 /* unchecked */ );
				} else {
					$selected = -1; // use existing field
				}
				$line = '<td class="mw-revdel-checkbox">' . Xml::radio( $name, -1, $selected == -1 ) . '</td>';
				$line .= '<td class="mw-revdel-checkbox">' . Xml::radio( $name, 0, $selected == 0 ) . '</td>';
				$line .= '<td class="mw-revdel-checkbox">' . Xml::radio( $name, 1, $selected == 1 ) . '</td>';
				$label = $this->msg( $message )->escaped();
				if ( $field == RevisionRecord::DELETED_RESTRICTED ) {
					$label = "<b>$label</b>";
				}
				$line .= "<td>$label</td>";
				$html .= "<tr>$line</tr>\n";
			}
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * UI entry point for form submission.
	 * @throws PermissionsError
	 * @return bool
	 */
	protected function submit() {
		# Check edit token on submission
		$token = $this->getRequest()->getVal( 'wpEditToken' );
		if ( $this->submitClicked && !$this->getUser()->matchEditToken( $token ) ) {
			$this->getOutput()->addWikiMsg( 'sessionfailure' );

			return false;
		}
		$bitParams = $this->extractBitParams();
		// from dropdown
		$listReason = $this->getRequest()->getText( 'wpRevDeleteReasonList', 'other' );
		$comment = $listReason;
		if ( $comment === 'other' ) {
			$comment = $this->otherReason;
		} elseif ( $this->otherReason !== '' ) {
			// Entry from drop down menu + additional comment
			$comment .= $this->msg( 'colon-separator' )->inContentLanguage()->text()
				. $this->otherReason;
		}
		# Can the user set this field?
		if ( $bitParams[RevisionRecord::DELETED_RESTRICTED] == 1
			&& !$this->permissionManager->userHasRight( $this->getUser(), 'suppressrevision' )
		) {
			throw new PermissionsError( 'suppressrevision' );
		}
		# If the save went through, go to success message...
		$status = $this->save( $bitParams, $comment );
		if ( $status->isGood() ) {
			$this->success();

			return true;
		} else {
			# ...otherwise, bounce back to form...
			$this->failure( $status );
		}

		return false;
	}

	/**
	 * Report that the submit operation succeeded
	 */
	protected function success() {
		// Messages: revdelete-success, logdelete-success
		$this->getOutput()->setPageTitle( $this->msg( 'actioncomplete' ) );
		$this->getOutput()->wrapWikiMsg(
			"<div class=\"successbox\">\n$1\n</div>",
			$this->typeLabels['success']
		);
		$this->wasSaved = true;
		$this->revDelList->reloadFromMaster();
		$this->showForm();
	}

	/**
	 * Report that the submit operation failed
	 * @param Status $status
	 */
	protected function failure( $status ) {
		// Messages: revdelete-failure, logdelete-failure
		$this->getOutput()->setPageTitle( $this->msg( 'actionfailed' ) );
		$this->getOutput()->wrapWikiTextAsInterface(
			'errorbox',
			$status->getWikiText( $this->typeLabels['failure'], false, $this->getLanguage() )
		);
		$this->showForm();
	}

	/**
	 * Put together an array that contains -1, 0, or the *_deleted const for each bit
	 *
	 * @return array
	 */
	protected function extractBitParams() {
		$bitfield = [];
		foreach ( $this->checks as $item ) {
			list( /* message */, $name, $field ) = $item;
			$val = $this->getRequest()->getInt( $name, 0 /* unchecked */ );
			if ( $val < -1 || $val > 1 ) {
				$val = -1; // -1 for existing value
			}
			$bitfield[$field] = $val;
		}
		if ( !isset( $bitfield[RevisionRecord::DELETED_RESTRICTED] ) ) {
			$bitfield[RevisionRecord::DELETED_RESTRICTED] = 0;
		}

		return $bitfield;
	}

	/**
	 * Do the write operations. Simple wrapper for RevDel*List::setVisibility().
	 * @param array $bitPars ExtractBitParams() bitfield array
	 * @param string $reason
	 * @return Status
	 */
	protected function save( array $bitPars, $reason ) {
		return $this->getList()->setVisibility(
			[ 'value' => $bitPars, 'comment' => $reason ]
		);
	}

	protected function getGroupName() {
		return 'pagetools';
	}
}
