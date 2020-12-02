<?php
/**
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
 */

use MediaWiki\MediaWikiServices;

class SpecialReplaceText extends SpecialPage {
	private $target;
	private $replacement;
	private $use_regex;
	private $category;
	private $prefix;
	private $edit_pages;
	private $move_pages;
	private $selected_namespaces;
	private $doAnnounce;

	public function __construct() {
		parent::__construct( 'ReplaceText', 'replacetext' );
	}

	/**
	 * @inheritDoc
	 */
	public function doesWrites() {
		return true;
	}

	/**
	 * @param null|string $query
	 */
	function execute( $query ) {
		global $wgCompressRevisions, $wgExternalStores;

		if ( !$this->getUser()->isAllowed( 'replacetext' ) ) {
			throw new PermissionsError( 'replacetext' );
		}

		$out = $this->getOutput();
		// Replace Text can't be run with certain settings, due to the
		// changes they make to the DB storage setup.
		if ( $wgCompressRevisions ) {
			$errorMsg = "Error: text replacements cannot be run if \$wgCompressRevisions is set to true.";
			$out->addWikiTextAsContent( "<div class=\"errorbox\">$errorMsg</div>" );
			return;
		}
		if ( !empty( $wgExternalStores ) ) {
			$errorMsg = "Error: text replacements cannot be run if \$wgExternalStores is non-empty.";
			$out->addWikiTextAsContent( "<div class=\"errorbox\">$errorMsg</div>" );
			return;
		}

		$this->setHeaders();
		if ( $out->getResourceLoader()->getModule( 'mediawiki.special' ) !== null ) {
			$out->addModuleStyles( 'mediawiki.special' );
		}
		$this->doSpecialReplaceText();
	}

	/**
	 * @return array namespaces selected for search
	 */
	function getSelectedNamespaces() {
		if ( class_exists( MediaWikiServices::class ) ) {
			// MW 1.27+
			$all_namespaces = MediaWikiServices::getInstance()->getSearchEngineConfig()
				->searchableNamespaces();
		} else {
			/** @phan-suppress-next-line PhanUndeclaredStaticMethod */
			$all_namespaces = SearchEngine::searchableNamespaces();
		}
		$selected_namespaces = [];
		foreach ( $all_namespaces as $ns => $name ) {
			if ( $this->getRequest()->getCheck( 'ns' . $ns ) ) {
				$selected_namespaces[] = $ns;
			}
		}
		return $selected_namespaces;
	}

	/**
	 * Do the actual display and logic of Special:ReplaceText.
	 */
	function doSpecialReplaceText() {
		$out = $this->getOutput();
		$request = $this->getRequest();

		$this->target = $request->getText( 'target' );
		$this->replacement = $request->getText( 'replacement' );
		$this->use_regex = $request->getBool( 'use_regex' );
		$this->category = $request->getText( 'category' );
		$this->prefix = $request->getText( 'prefix' );
		$this->edit_pages = $request->getBool( 'edit_pages' );
		$this->move_pages = $request->getBool( 'move_pages' );
		$this->doAnnounce = $request->getBool( 'doAnnounce' );
		$this->selected_namespaces = $this->getSelectedNamespaces();

		if ( $request->getCheck( 'continue' ) && $this->target === '' ) {
			$this->showForm( 'replacetext_givetarget' );
			return;
		}

		if ( $request->getCheck( 'replace' ) ) {

			// check for CSRF
			$user = $this->getUser();
			if ( !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
				$out->addWikiMsg( 'sessionfailure' );
				return;
			}

			$jobs = $this->createJobsForTextReplacements();
			JobQueueGroup::singleton()->push( $jobs );

			$count = $this->getLanguage()->formatNum( count( $jobs ) );
			$out->addWikiMsg(
				'replacetext_success',
				"<code><nowiki>{$this->target}</nowiki></code>",
				"<code><nowiki>{$this->replacement}</nowiki></code>",
				$count
			);

			// Link back
			$out->addHTML(
				ReplaceTextUtils::link(
					$this->getPageTitle(),
					$this->msg( 'replacetext_return' )->text()
				)
			);
			return;
		}

		if ( $request->getCheck( 'target' ) ) {
			// check for CSRF
			$user = $this->getUser();
			if ( !$user->matchEditToken( $request->getVal( 'token' ) ) ) {
				$out->addWikiMsg( 'sessionfailure' );
				return;
			}

			// first, check that at least one namespace has been
			// picked, and that either editing or moving pages
			// has been selected
			if ( count( $this->selected_namespaces ) == 0 ) {
				$this->showForm( 'replacetext_nonamespace' );
				return;
			}
			if ( !$this->edit_pages && !$this->move_pages ) {
				$this->showForm( 'replacetext_editormove' );
				return;
			}

			// If user is replacing text within pages...
			$titles_for_edit = $titles_for_move = $unmoveable_titles = [];
			if ( $this->edit_pages ) {
				$titles_for_edit = $this->getTitlesForEditingWithContext();
			}
			if ( $this->move_pages ) {
				list( $titles_for_move, $unmoveable_titles ) = $this->getTitlesForMoveAndUnmoveableTitles();
			}

			// If no results were found, check to see if a bad
			// category name was entered.
			if ( count( $titles_for_edit ) == 0 && count( $titles_for_move ) == 0 ) {
				$category_title = null;

				if ( !empty( $this->category ) ) {
					$category_title = Title::makeTitleSafe( NS_CATEGORY, $this->category );
					if ( !$category_title->exists() ) {
						$category_title = null;
					}
				}

				if ( $category_title !== null ) {
					$link = ReplaceTextUtils::link(
						$category_title,
						ucfirst( $this->category )
					);
					$out->addHTML(
						$this->msg( 'replacetext_nosuchcategory' )->rawParams( $link )->escaped()
					);
				} else {
					if ( $this->edit_pages ) {
						$out->addWikiMsg(
							'replacetext_noreplacement', "<code><nowiki>{$this->target}</nowiki></code>"
						);
					}

					if ( $this->move_pages ) {
						$out->addWikiMsg( 'replacetext_nomove', "<code><nowiki>{$this->target}</nowiki></code>" );
					}
				}
				// link back to starting form
				$out->addHTML(
					'<p>' .
					ReplaceTextUtils::link(
						$this->getPageTitle(),
						$this->msg( 'replacetext_return' )->text()
					)
					. '</p>'
				);
			} else {
				$warning_msg = $this->getAnyWarningMessageBeforeReplace( $titles_for_edit, $titles_for_move );
				if ( $warning_msg !== null ) {
					$out->addWikiTextAsContent(
						"<div class=\"errorbox\">$warning_msg</div><br clear=\"both\" />"
					);
				}

				$this->pageListForm( $titles_for_edit, $titles_for_move, $unmoveable_titles );
			}
			return;
		}

		// If we're still here, show the starting form.
		$this->showForm();
	}

	/**
	 * Returns the set of MediaWiki jobs that will do all the actual replacements.
	 *
	 * @return array jobs
	 */
	function createJobsForTextReplacements() {
		global $wgReplaceTextUser;

		$replacement_params = [];
		if ( $wgReplaceTextUser != null ) {
			$user = User::newFromName( $wgReplaceTextUser );
		} else {
			$user = $this->getUser();
		}

		$replacement_params['user_id'] = $user->getId();
		$replacement_params['target_str'] = $this->target;
		$replacement_params['replacement_str'] = $this->replacement;
		$replacement_params['use_regex'] = $this->use_regex;
		$replacement_params['edit_summary'] = $this->msg(
			'replacetext_editsummary',
			$this->target, $this->replacement
		)->inContentLanguage()->plain();
		$replacement_params['create_redirect'] = false;
		$replacement_params['watch_page'] = false;
		$replacement_params['doAnnounce'] = $this->doAnnounce;

		$request = $this->getRequest();
		foreach ( $request->getValues() as $key => $value ) {
			if ( $key == 'create-redirect' && $value == '1' ) {
				$replacement_params['create_redirect'] = true;
			} elseif ( $key == 'watch-pages' && $value == '1' ) {
				$replacement_params['watch_page'] = true;
			}
		}

		$jobs = [];
		foreach ( $request->getValues() as $key => $value ) {
			if ( $value == '1' && $key !== 'replace' && $key !== 'use_regex' ) {
				if ( strpos( $key, 'move-' ) !== false ) {
					$title = Title::newFromID( (int)substr( $key, 5 ) );
					$replacement_params['move_page'] = true;
				} else {
					$title = Title::newFromID( (int)$key );
				}
				if ( $title !== null ) {
					$jobs[] = new ReplaceTextJob( $title, $replacement_params );
				}
			}
		}

		return $jobs;
	}

	/**
	 * Returns the set of Titles whose contents would be modified by this
	 * replacement, along with the "search context" string for each one.
	 *
	 * @return array The set of Titles and their search context strings
	 */
	function getTitlesForEditingWithContext() {
		$titles_for_edit = [];

		$res = ReplaceTextSearch::doSearchQuery(
			$this->target,
			$this->selected_namespaces,
			$this->category,
			$this->prefix,
			$this->use_regex
		);

		foreach ( $res as $row ) {
			$title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if ( $title == null ) {
				continue;
			}
			$context = $this->extractContext( $row->old_text, $this->target, $this->use_regex );
			$titles_for_edit[] = [ $title, $context ];
		}

		return $titles_for_edit;
	}

	/**
	 * Returns two lists: the set of titles that would be moved/renamed by
	 * the current text replacement, and the set of titles that would
	 * ordinarily be moved but are not moveable, due to permissions or any
	 * other reason.
	 *
	 * @return array
	 */
	function getTitlesForMoveAndUnmoveableTitles() {
		$titles_for_move = [];
		$unmoveable_titles = [];

		$res = ReplaceTextSearch::getMatchingTitles(
			$this->target,
			$this->selected_namespaces,
			$this->category,
			$this->prefix,
			$this->use_regex
		);

		foreach ( $res as $row ) {
			$title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
			if ( $title == null ) {
				continue;
			}

			$new_title = ReplaceTextSearch::getReplacedTitle(
				$title,
				$this->target,
				$this->replacement,
				$this->use_regex
			);

			$mvPage = new MovePage( $title, $new_title );
			$moveStatus = $mvPage->isValidMove();
			$permissionStatus = $mvPage->checkPermissions( $this->getUser(), null );

			if ( $permissionStatus->isOK() && $moveStatus->isOK() ) {
				$titles_for_move[] = $title;
			} else {
				$unmoveable_titles[] = $title;
			}
		}

		return [ $titles_for_move, $unmoveable_titles ];
	}

	/**
	 * Get the warning message if the replacement string is either blank
	 * or found elsewhere on the wiki (since undoing the replacement
	 * would be difficult in either case).
	 *
	 * @param array $titles_for_edit
	 * @param array $titles_for_move
	 * @return string|null Warning message, if any
	 */
	function getAnyWarningMessageBeforeReplace( $titles_for_edit, $titles_for_move ) {
		if ( $this->replacement === '' ) {
			return $this->msg( 'replacetext_blankwarning' )->text();
		} elseif ( $this->use_regex ) {
			// If it's a regex, don't bother checking for existing
			// pages - if the replacement string includes wildcards,
			// it's a meaningless check.
			return null;
		} elseif ( count( $titles_for_edit ) > 0 ) {
			$res = ReplaceTextSearch::doSearchQuery(
				$this->replacement,
				$this->selected_namespaces,
				$this->category,
				$this->prefix,
				$this->use_regex
			);
			$count = $res->numRows();
			if ( $count > 0 ) {
				return $this->msg( 'replacetext_warning' )->numParams( $count )
					->params( "<code><nowiki>{$this->replacement}</nowiki></code>" )->text();
			}
		} elseif ( count( $titles_for_move ) > 0 ) {
			$res = ReplaceTextSearch::getMatchingTitles(
				$this->replacement,
				$this->selected_namespaces,
				$this->category,
				$this->prefix,
				$this->use_regex
			);
			$count = $res->numRows();
			if ( $count > 0 ) {
				return $this->msg( 'replacetext_warning' )->numParams( $count )
					->params( $this->replacement )->text();
			}
		}

		return null;
	}

	/**
	 * @param string|null $warning_msg Message to be shown at top of form
	 */
	function showForm( $warning_msg = null ) {
		$out = $this->getOutput();

		$out->addHTML(
			Xml::openElement(
				'form',
				[
					'id' => 'powersearch',
					'action' => $this->getPageTitle()->getFullURL(),
					'method' => 'post'
				]
			) . "\n" .
			Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) .
			Html::hidden( 'continue', 1 ) .
			Html::hidden( 'token', $out->getUser()->getEditToken() )
		);
		if ( $warning_msg === null ) {
			$out->addWikiMsg( 'replacetext_docu' );
		} else {
			$out->wrapWikiMsg(
				"<div class=\"errorbox\">\n$1\n</div><br clear=\"both\" />",
				$warning_msg
			);
		}

		$out->addHTML( '<table><tr><td style="vertical-align: top;">' );
		$out->addWikiMsg( 'replacetext_originaltext' );
		$out->addHTML( '</td><td>' );
		// 'width: auto' style is needed to override MediaWiki's
		// normal 'width: 100%', which causes the textarea to get
		// zero width in IE
		$out->addHTML(
			Xml::textarea( 'target', $this->target, 100, 5, [ 'style' => 'width: auto;' ] )
		);
		$out->addHTML( '</td></tr><tr><td style="vertical-align: top;">' );
		$out->addWikiMsg( 'replacetext_replacementtext' );
		$out->addHTML( '</td><td>' );
		$out->addHTML(
			Xml::textarea( 'replacement', $this->replacement, 100, 5, [ 'style' => 'width: auto;' ] )
		);
		$out->addHTML( '</td></tr></table>' );

		// MSSQL/SQLServer and SQLite unfortunately lack a REGEXP
		// function or operator by default, so disable regex(p)
		// searches for both these DB types.
		$dbr = wfGetDB( DB_REPLICA );
		if ( $dbr->getType() != 'sqlite' && $dbr->getType() != 'mssql' ) {
			$out->addHTML( Xml::tags( 'p', null,
					Xml::checkLabel(
						$this->msg( 'replacetext_useregex' )->text(),
						'use_regex', 'use_regex'
					)
				) . "\n" .
				Xml::element( 'p',
					[ 'style' => 'font-style: italic' ],
					$this->msg( 'replacetext_regexdocu' )->text()
				)
			);
		}

		// The interface is heavily based on the one in Special:Search.
		if ( class_exists( MediaWikiServices::class ) ) {
			// MW 1.27+
			$namespaces = MediaWikiServices::getInstance()->getSearchEngineConfig()
				->searchableNamespaces();
		} else {
			/** @phan-suppress-next-line PhanUndeclaredStaticMethod */
			$namespaces = SearchEngine::searchableNamespaces();
		}
		$tables = $this->namespaceTables( $namespaces );
		$out->addHTML(
			"<div class=\"mw-search-formheader\"></div>\n" .
			"<fieldset id=\"mw-searchoptions\">\n" .
			Xml::tags( 'h4', null, $this->msg( 'powersearch-ns' )->parse() )
		);
		// The ability to select/unselect groups of namespaces in the
		// search interface exists only in some skins, like Vector -
		// check for the presence of the 'powersearch-togglelabel'
		// message to see if we can use this functionality here.
		if ( $this->msg( 'powersearch-togglelabel' )->isDisabled() ) {
			// do nothing
		} else {
			$out->addHTML(
				Html::element(
					'div',
					[ 'id' => 'mw-search-togglebox' ]
				)
			);
		}
		$out->addHTML(
			Xml::element( 'div', [ 'class' => 'divider' ], '', false ) .
			"$tables\n</fieldset>"
		);
		// @todo FIXME: raw html messages
		$category_search_label = $this->msg( 'replacetext_categorysearch' )->escaped();
		$prefix_search_label = $this->msg( 'replacetext_prefixsearch' )->escaped();
		$rcPage = SpecialPage::getTitleFor( 'Recentchanges' );
		$rcPageName = $rcPage->getPrefixedText();
		$out->addHTML(
			"<fieldset id=\"mw-searchoptions\">\n" .
			Xml::tags( 'h4', null, $this->msg( 'replacetext_optionalfilters' )->parse() ) .
			Xml::element( 'div', [ 'class' => 'divider' ], '', false ) .
			"<p>$category_search_label\n" .
			Xml::input( 'category', 20, $this->category, [ 'type' => 'text' ] ) . '</p>' .
			"<p>$prefix_search_label\n" .
			Xml::input( 'prefix', 20, $this->prefix, [ 'type' => 'text' ] ) . '</p>' .
			"</fieldset>\n" .
			"<p>\n" .
			Xml::checkLabel(
				$this->msg( 'replacetext_editpages' )->text(), 'edit_pages', 'edit_pages', true
			) . '<br />' .
			Xml::checkLabel(
				$this->msg( 'replacetext_movepages' )->text(), 'move_pages', 'move_pages'
			) . '<br />' .
			Xml::checkLabel(
				$this->msg( 'replacetext_announce', $rcPageName )->text(), 'doAnnounce', 'doAnnounce', true
			) .
			"</p>\n" .
			Xml::submitButton( $this->msg( 'replacetext_continue' )->text() ) .
			Xml::closeElement( 'form' )
		);
		$out->addModules( 'ext.ReplaceText' );
	}

	/**
	 * Copied almost exactly from MediaWiki's SpecialSearch class, i.e.
	 * the search page
	 * @param string[] $namespaces
	 * @param int $rowsPerTable
	 * @return string HTML
	 */
	function namespaceTables( $namespaces, $rowsPerTable = 3 ) {
		global $wgContLang;
		// Group namespaces into rows according to subject.
		// Try not to make too many assumptions about namespace numbering.
		$rows = [];
		$tables = "";
		foreach ( $namespaces as $ns => $name ) {
			$subj = MWNamespace::getSubject( $ns );
			if ( !array_key_exists( $subj, $rows ) ) {
				$rows[$subj] = "";
			}
			$name = str_replace( '_', ' ', $name );
			if ( '' == $name ) {
				$name = $this->msg( 'blanknamespace' )->text();
			}
			$rows[$subj] .= Xml::openElement( 'td', [ 'style' => 'white-space: nowrap' ] ) .
				Xml::checkLabel( $name, "ns{$ns}", "mw-search-ns{$ns}", in_array( $ns, $namespaces ) ) .
				Xml::closeElement( 'td' ) . "\n";
		}
		$rows = array_values( $rows );
		$numRows = count( $rows );
		// Lay out namespaces in multiple floating two-column tables so they'll
		// be arranged nicely while still accommodating different screen widths
		// Float to the right on RTL wikis
		$tableStyle = $wgContLang->isRTL() ?
			'float: right; margin: 0 0 0em 1em' : 'float: left; margin: 0 1em 0em 0';
		// Build the final HTML table...
		for ( $i = 0; $i < $numRows; $i += $rowsPerTable ) {
			$tables .= Xml::openElement( 'table', [ 'style' => $tableStyle ] );
			for ( $j = $i; $j < $i + $rowsPerTable && $j < $numRows; $j++ ) {
				$tables .= "<tr>\n" . $rows[$j] . "</tr>";
			}
			$tables .= Xml::closeElement( 'table' ) . "\n";
		}
		return $tables;
	}

	/**
	 * @param array $titles_for_edit
	 * @param array $titles_for_move
	 * @param array $unmoveable_titles
	 */
	function pageListForm( $titles_for_edit, $titles_for_move, $unmoveable_titles ) {
		global $wgLang;

		$out = $this->getOutput();

		$formOpts = [
			'id' => 'choose_pages',
			'method' => 'post',
			'action' => $this->getPageTitle()->getFullUrl()
		];
		$out->addHTML(
			Xml::openElement( 'form', $formOpts ) . "\n" .
			Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) .
			Html::hidden( 'target', $this->target ) .
			Html::hidden( 'replacement', $this->replacement ) .
			Html::hidden( 'use_regex', $this->use_regex ) .
			Html::hidden( 'move_pages', $this->move_pages ) .
			Html::hidden( 'edit_pages', $this->edit_pages ) .
			Html::hidden( 'doAnnounce', $this->doAnnounce ) .
			Html::hidden( 'replace', 1 ) .
			Html::hidden( 'token', $out->getUser()->getEditToken() )
		);

		foreach ( $this->selected_namespaces as $ns ) {
			$out->addHTML( Html::hidden( 'ns' . $ns, 1 ) );
		}

		$out->addModules( "ext.ReplaceText" );
		$out->addModuleStyles( "ext.ReplaceTextStyles" );
		// Needed for bolding of search term.
		$out->addModuleStyles( "mediawiki.special.search.styles" );

		if ( count( $titles_for_edit ) > 0 ) {
			$out->addWikiMsg(
				'replacetext_choosepagesforedit',
				"<code><nowiki>{$this->target}</nowiki></code>",
				"<code><nowiki>{$this->replacement}</nowiki></code>",
				$wgLang->formatNum( count( $titles_for_edit ) )
			);

			foreach ( $titles_for_edit as $title_and_context ) {
				/**
				 * @var $title Title
				 */
				list( $title, $context ) = $title_and_context;
				$out->addHTML(
					Xml::check( $title->getArticleID(), true ) .
					ReplaceTextUtils::link( $title ) .
					" - <small>$context</small><br />\n"
				);
			}
			$out->addHTML( '<br />' );
		}

		if ( count( $titles_for_move ) > 0 ) {
			$out->addWikiMsg(
				'replacetext_choosepagesformove',
				$this->target, $this->replacement, $wgLang->formatNum( count( $titles_for_move ) )
			);
			foreach ( $titles_for_move as $title ) {
				$out->addHTML(
					Xml::check( 'move-' . $title->getArticleID(), true ) .
					ReplaceTextUtils::link( $title ) . "<br />\n"
				);
			}
			$out->addHTML( '<br />' );
			$out->addWikiMsg( 'replacetext_formovedpages' );
			$rcPage = SpecialPage::getTitleFor( 'Recentchanges' );
			$rcPageName = $rcPage->getPrefixedText();
			$out->addHTML(
				Xml::checkLabel(
					$this->msg( 'replacetext_savemovedpages' )->text(),
						'create-redirect', 'create-redirect', true ) . "<br />\n" .
				Xml::checkLabel(
					$this->msg( 'replacetext_watchmovedpages' )->text(),
					'watch-pages', 'watch-pages', false ) . '<br />'
			);
			$out->addHTML( '<br />' );
		}

		$out->addHTML(
			"<br />\n" .
			Xml::submitButton( $this->msg( 'replacetext_replace' )->text() ) . "\n"
		);

		// Only show "invert selections" link if there are more than
		// five pages.
		if ( count( $titles_for_edit ) + count( $titles_for_move ) > 5 ) {
			$buttonOpts = [
				'type' => 'button',
				'value' => $this->msg( 'replacetext_invertselections' )->text(),
				'disabled' => true,
				'id' => 'replacetext-invert',
				'class' => 'mw-replacetext-invert'
			];

			$out->addHTML(
				Xml::element( 'input', $buttonOpts )
			);
		}

		$out->addHTML( '</form>' );

		if ( count( $unmoveable_titles ) > 0 ) {
			$out->addWikiMsg( 'replacetext_cannotmove', $wgLang->formatNum( count( $unmoveable_titles ) ) );
			$text = "<ul>\n";
			foreach ( $unmoveable_titles as $title ) {
				$text .= "<li>" . ReplaceTextUtils::link( $title ) . "<br />\n";
			}
			$text .= "</ul>\n";
			$out->addHTML( $text );
		}
	}

	/**
	 * Extract context and highlights search text
	 *
	 * @todo The bolding needs to be fixed for regular expressions.
	 * @param string $text
	 * @param string $target
	 * @param bool $use_regex
	 * @return string
	 */
	function extractContext( $text, $target, $use_regex = false ) {
		global $wgLang;

		$cw = $this->getUser()->getOption( 'contextchars', 40 );

		// Get all indexes
		if ( $use_regex ) {
			preg_match_all( "/$target/Uu", $text, $matches, PREG_OFFSET_CAPTURE );
		} else {
			$targetq = preg_quote( $target, '/' );
			preg_match_all( "/$targetq/", $text, $matches, PREG_OFFSET_CAPTURE );
		}

		$poss = [];
		foreach ( $matches[0] as $_ ) {
			$poss[] = $_[1];
		}

		$cuts = [];
		// @codingStandardsIgnoreStart
		for ( $i = 0; $i < count( $poss ); $i++ ) {
		// @codingStandardsIgnoreEnd
			$index = $poss[$i];
			$len = strlen( $target );

			// Merge to the next if possible
			while ( isset( $poss[$i + 1] ) ) {
				if ( $poss[$i + 1] < $index + $len + $cw * 2 ) {
					$len += $poss[$i + 1] - $poss[$i];
					$i++;
				} else {
					// Can't merge, exit the inner loop
					break;
				}
			}
			$cuts[] = [ $index, $len ];
		}

		$context = '';
		foreach ( $cuts as $_ ) {
			list( $index, $len, ) = $_;
			$contextBefore = substr( $text, 0, $index );
			$contextAfter = substr( $text, $index + $len );
			if ( !is_callable( [ $wgLang, 'truncateForDatabase' ] ) ) {
				// Backwards compatibility code; remove once MW 1.30 is
				// no longer supported.
				$contextBefore =
					// @phan-suppress-next-line PhanUndeclaredMethod
					$wgLang->truncate( $contextBefore, - $cw, '...', false );
				$contextAfter =
					// @phan-suppress-next-line PhanUndeclaredMethod
					$wgLang->truncate( $contextAfter, $cw, '...', false );
			} else {
				$contextBefore =
					$wgLang->truncateForDatabase( $contextBefore, - $cw, '...', false );
				$contextAfter =
					$wgLang->truncateForDatabase( $contextAfter, $cw, '...', false );
			}
			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$context .= $this->convertWhiteSpaceToHTML( $contextBefore );
			$snippet = $this->convertWhiteSpaceToHTML( substr( $text, $index, $len ) );
			if ( $use_regex ) {
				$targetStr = "/$target/Uu";
			} else {
				$targetq = preg_quote( $this->convertWhiteSpaceToHTML( $target ), '/' );
				$targetStr = "/$targetq/i";
			}
			$context .= preg_replace( $targetStr, '<span class="searchmatch">\0</span>', $snippet );

			// @phan-suppress-next-line SecurityCheck-DoubleEscaped
			$context .= $this->convertWhiteSpaceToHTML( $contextAfter );
		}
		return $context;
	}

	private function convertWhiteSpaceToHTML( $message ) {
		$msg = htmlspecialchars( $message );
		$msg = preg_replace( '/^ /m', '&#160; ', $msg );
		$msg = preg_replace( '/ $/m', ' &#160;', $msg );
		$msg = preg_replace( '/  /', '&#160; ', $msg );
		# $msg = str_replace( "\n", '<br />', $msg );
		return $msg;
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'wiki';
	}
}
