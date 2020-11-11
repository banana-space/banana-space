<?php

class SpecialNuke extends SpecialPage {

	public function __construct() {
		parent::__construct( 'Nuke', 'nuke' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @param null|string $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->checkReadOnly();
		$this->outputHeader();

		$currentUser = $this->getUser();
		if ( $currentUser->isBlocked() ) {
			$block = $currentUser->getBlock();
			throw new UserBlockedError( $block );
		}

		$req = $this->getRequest();
		$target = trim( $req->getText( 'target', $par ) );

		// Normalise name
		if ( $target !== '' ) {
			$user = User::newFromName( $target );
			if ( $user ) {
				$target = $user->getName();
			}
		}

		$msg = $target === '' ?
			$this->msg( 'nuke-multiplepeople' )->inContentLanguage()->text() :
			$this->msg( 'nuke-defaultreason', $target )->
			inContentLanguage()->text();
		$reason = $req->getText( 'wpReason', $msg );

		$limit = $req->getInt( 'limit', 500 );
		$namespace = $req->getVal( 'namespace' );
		$namespace = ctype_digit( $namespace ) ? (int)$namespace : null;

		if ( $req->wasPosted()
			&& $currentUser->matchEditToken( $req->getVal( 'wpEditToken' ) )
		) {
			if ( $req->getVal( 'action' ) === 'delete' ) {
				$pages = $req->getArray( 'pages' );

				if ( $pages ) {
					$this->doDelete( $pages, $reason );

					return;
				}
			} elseif ( $req->getVal( 'action' ) === 'submit' ) {
				$this->listForm( $target, $reason, $limit, $namespace );
			} else {
				$this->promptForm();
			}
		} elseif ( $target === '' ) {
			$this->promptForm();
		} else {
			$this->listForm( $target, $reason, $limit, $namespace );
		}
	}

	/**
	 * Prompt for a username or IP address.
	 *
	 * @param string $userName
	 */
	protected function promptForm( $userName = '' ) {
		$out = $this->getOutput();

		$out->addWikiMsg( 'nuke-tools' );

		$formDescriptor = [
			'nuke-target' => [
				'id' => 'nuke-target',
				'default' => $userName,
				'label' => $this->msg( 'nuke-userorip' )->text(),
				'type' => 'user',
				'name' => 'target'
			],
			'nuke-pattern' => [
				'id' => 'nuke-pattern',
				'label' => $this->msg( 'nuke-pattern' )->text(),
				'maxLength' => 40,
				'type' => 'text',
				'name' => 'pattern'
			],
			'namespace' => [
				'id' => 'nuke-namespace',
				'type' => 'namespaceselect',
				'label' => $this->msg( 'nuke-namespace' )->text(),
				'all' => 'all',
				'name' => 'namespace'
			],
			'limit' => [
				'id' => 'nuke-limit',
				'maxLength' => 7,
				'default' => 500,
				'label' => $this->msg( 'nuke-maxpages' )->text(),
				'type' => 'int',
				'name' => 'limit'
			]
		];

		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->setName( 'massdelete' )
			->setFormIdentifier( 'massdelete' )
			->setWrapperLegendMsg( 'nuke' )
			->setSubmitTextMsg( 'nuke-submit-user' )
			->setSubmitName( 'nuke-submit-user' )
			->setAction( $this->getPageTitle()->getLocalURL( 'action=submit' ) )
			->setMethod( 'post' )
			->addHiddenField( 'wpEditToken', $this->getUser()->getEditToken() )
			->prepareForm()
			->displayForm( false );
	}

	/**
	 * Display list of pages to delete.
	 *
	 * @param string $username
	 * @param string $reason
	 * @param int $limit
	 * @param int|null $namespace
	 */
	protected function listForm( $username, $reason, $limit, $namespace = null ) {
		$out = $this->getOutput();

		$pages = $this->getNewPages( $username, $limit, $namespace );

		if ( count( $pages ) === 0 ) {
			if ( $username === '' ) {
				$out->addWikiMsg( 'nuke-nopages-global' );
			} else {
				$out->addWikiMsg( 'nuke-nopages', $username );
			}

			$this->promptForm( $username );

			return;
		}

		$out->addModules( 'ext.nuke.confirm' );

		if ( $username === '' ) {
			$out->addWikiMsg( 'nuke-list-multiple' );
		} else {
			$out->addWikiMsg( 'nuke-list', $username );
		}

		$nuke = $this->getPageTitle();

		$out->addHTML(
			Xml::openElement( 'form', [
					'action' => $nuke->getLocalURL( 'action=delete' ),
					'method' => 'post',
					'name' => 'nukelist' ]
			) .
			Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
			Xml::tags( 'p',
				null,
				Xml::inputLabel(
					$this->msg( 'deletecomment' )->text(), 'wpReason', 'wpReason', 70, $reason
				)
			)
		);

		// Select: All, None, Invert
		// ListToggle was introduced in 1.27, old code kept for B/C
		if ( class_exists( 'ListToggle' ) ) {
			$listToggle = new ListToggle( $this->getOutput() );
			$selectLinks = $listToggle->getHTML();
		} else {
			$out->addModules( 'ext.nuke' );

			$links = [];
			$links[] = '<a href="#" id="toggleall">' .
				$this->msg( 'powersearch-toggleall' )->escaped() . '</a>';
			$links[] = '<a href="#" id="togglenone">' .
				$this->msg( 'powersearch-togglenone' )->escaped() . '</a>';
			$links[] = '<a href="#" id="toggleinvert">' .
				$this->msg( 'nuke-toggleinvert' )->escaped() . '</a>';

			$selectLinks = Xml::tags( 'p',
				null,
				$this->msg( 'nuke-select' )
					->rawParams( $this->getLanguage()->commaList( $links ) )->escaped()
			);
		}

		$out->addHTML(
			$selectLinks .
			'<ul>'
		);

		$wordSeparator = $this->msg( 'word-separator' )->escaped();
		$commaSeparator = $this->msg( 'comma-separator' )->escaped();

		$linkRenderer = $this->getLinkRenderer();
		foreach ( $pages as $info ) {
			/**
			 * @var $title Title
			 */
			list( $title, $userName ) = $info;

			$image = $title->inNamespace( NS_FILE ) ? wfLocalFile( $title ) : false;
			$thumb = $image && $image->exists() ?
				$image->transform( [ 'width' => 120, 'height' => 120 ], 0 ) :
				false;

			$userNameText = $userName ?
				$this->msg( 'nuke-editby', $userName )->parse() . $commaSeparator :
				'';
			$changesLink = $linkRenderer->makeKnownLink(
				$title,
				$this->msg( 'nuke-viewchanges' )->text(),
				[],
				[ 'action' => 'history' ]
			);
			$out->addHTML( '<li>' .
				Xml::check(
					'pages[]',
					true,
					[ 'value' => $title->getPrefixedDBkey() ]
				) . '&#160;' .
				( $thumb ? $thumb->toHtml( [ 'desc-link' => true ] ) : '' ) .
				$linkRenderer->makeKnownLink( $title ) . $wordSeparator .
				$this->msg( 'parentheses' )->rawParams( $userNameText . $changesLink )->escaped() .
				"</li>\n" );
		}

		$out->addHTML(
			"</ul>\n" .
			Xml::submitButton( $this->msg( 'nuke-submit-delete' )->text() ) .
			'</form>'
		);
	}

	/**
	 * Gets a list of new pages by the specified user or everyone when none is specified.
	 *
	 * @param string $username
	 * @param int $limit
	 * @param int|null $namespace
	 *
	 * @return array
	 */
	protected function getNewPages( $username, $limit, $namespace = null ) {
		$dbr = wfGetDB( DB_REPLICA );

		$what = [
			'rc_namespace',
			'rc_title',
			'rc_timestamp',
		];

		$where = [ "(rc_new = 1) OR (rc_log_type = 'upload' AND rc_log_action = 'upload')" ];

		if ( class_exists( 'ActorMigration' ) ) {
			if ( $username === '' ) {
				$actorQuery = ActorMigration::newMigration()->getJoin( 'rc_user' );
				$what['rc_user_text'] = $actorQuery['fields']['rc_user_text'];
			} else {
				$actorQuery = ActorMigration::newMigration()
					->getWhere( $dbr, 'rc_user', User::newFromName( $username, false ) );
				$where[] = $actorQuery['conds'];
			}
		} else {
			$actorQuery = [ 'tables' => [], 'joins' => [] ];
			if ( $username === '' ) {
				$what[] = 'rc_user_text';
			} else {
				$where['rc_user_text'] = $username;
			}
		}

		if ( $namespace !== null ) {
			$where['rc_namespace'] = $namespace;
		}

		$pattern = $this->getRequest()->getText( 'pattern' );
		if ( !is_null( $pattern ) && trim( $pattern ) !== '' ) {
			// $pattern is a SQL pattern supporting wildcards, so buildLike
			// will not work.
			$where[] = 'rc_title LIKE ' . $dbr->addQuotes( $pattern );
		}
		$group = implode( ', ', $what );

		$result = $dbr->select(
			[ 'recentchanges' ] + $actorQuery['tables'],
			$what,
			$where,
			__METHOD__,
			[
				'ORDER BY' => 'rc_timestamp DESC',
				'GROUP BY' => $group,
				'LIMIT' => $limit
			],
			$actorQuery['joins']
		);

		$pages = [];

		foreach ( $result as $row ) {
			$pages[] = [
				Title::makeTitle( $row->rc_namespace, $row->rc_title ),
				$username === '' ? $row->rc_user_text : false
			];
		}

		// Allows other extensions to provide pages to be nuked that don't use
		// the recentchanges table the way mediawiki-core does
		Hooks::run( 'NukeGetNewPages', [ $username, $pattern, $namespace, $limit, &$pages ] );

		// Re-enforcing the limit *after* the hook because other extensions
		// may add and/or remove pages. We need to make sure we don't end up
		// with more pages than $limit.
		if ( count( $pages ) > $limit ) {
			$pages = array_slice( $pages, 0, $limit );
		}

		return $pages;
	}

	/**
	 * Does the actual deletion of the pages.
	 *
	 * @param array $pages The pages to delete
	 * @param string $reason
	 * @throws PermissionsError
	 */
	protected function doDelete( array $pages, $reason ) {
		$res = [];

		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );

			$deletionResult = false;
			if ( !Hooks::run( 'NukeDeletePage', [ $title, $reason, &$deletionResult ] ) ) {
				if ( $deletionResult ) {
					$res[] = $this->msg( 'nuke-deleted', $title->getPrefixedText() )->parse();
				} else {
					$res[] = $this->msg( 'nuke-not-deleted', $title->getPrefixedText() )->parse();
				}
				continue;
			}

			$file = $title->getNamespace() === NS_FILE ? wfLocalFile( $title ) : false;
			$permission_errors = $title->getUserPermissionsErrors( 'delete', $this->getUser() );

			if ( $permission_errors !== [] ) {
				throw new PermissionsError( 'delete', $permission_errors );
			}

			if ( $file ) {
				$oldimage = null; // Must be passed by reference
				$ok = FileDeleteForm::doDelete( $title, $file, $oldimage, $reason, false )->isOK();
			} else {
				$article = new Article( $title, 0 );
				$ok = $article->doDeleteArticle( $reason );
			}

			if ( $ok ) {
				$res[] = $this->msg( 'nuke-deleted', $title->getPrefixedText() )->parse();
			} else {
				$res[] = $this->msg( 'nuke-not-deleted', $title->getPrefixedText() )->parse();
			}
		}

		$this->getOutput()->addHTML( "<ul>\n<li>" . implode( "</li>\n<li>", $res ) . "</li>\n</ul>\n" );
		$this->getOutput()->addWikiMsg( 'nuke-delete-more' );
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		if ( !class_exists( 'UserNamePrefixSearch' ) ) { // check for version 1.27
			return [];
		}
		$user = User::newFromName( $search );
		if ( !$user ) {
			// No prefix suggestion for invalid user
			return [];
		}
		// Autocomplete subpage as user list - public to allow caching
		return UserNamePrefixSearch::search( 'public', $search, $limit, $offset );
	}

	protected function getGroupName() {
		return 'pagetools';
	}
}
