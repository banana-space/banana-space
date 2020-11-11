<?php
/**
 * BaseTemplate class for the Timeless skin
 *
 * @ingroup Skins
 */
class TimelessTemplate extends BaseTemplate {

	/** @var array */
	protected $pileOfTools;

	/**
	 * Outputs the entire contents of the page
	 */
	public function execute() {
		$this->pileOfTools = $this->getPageTools();
		$userLinks = $this->getUserLinks();

		// Open html, body elements, etc
		$html = $this->get( 'headelement' );

		$html .= Html::openElement( 'div', [ 'id' => 'mw-wrapper', 'class' => $userLinks['class'] ] );

		$html .= Html::rawElement( 'div', [ 'id' => 'mw-header-container', 'class' => 'ts-container' ],
			Html::rawElement( 'div', [ 'id' => 'mw-header', 'class' => 'ts-inner' ],
				$userLinks['html'] .
				$this->getLogo( 'p-logo-text', 'text' ) .
				$this->getSearch()
			) .
			$this->getClear()
		);
		$html .= $this->getHeaderHack();

		// For mobile
		$html .= Html::element( 'div', [ 'id' => 'menus-cover' ] );

		$html .= Html::rawElement( 'div', [ 'id' => 'mw-content-container', 'class' => 'ts-container' ],
			Html::rawElement( 'div', [ 'id' => 'mw-content-block', 'class' => 'ts-inner' ],
				Html::rawElement( 'div', [ 'id' => 'mw-site-navigation' ],
					$this->getLogo( 'p-logo', 'image' ) .
					$this->getMainNavigation() .
					$this->getSidebarChunk(
						'site-tools',
						'timeless-sitetools',
						$this->getPortlet(
							'tb',
							$this->pileOfTools['general'],
							'timeless-sitetools'
						)
					)
				) .
				Html::rawElement( 'div', [ 'id' => 'mw-related-navigation' ],
					$this->getPageToolSidebar() .
					$this->getInterlanguageLinks() .
					$this->getCategories()
				) .
				Html::rawElement( 'div', [ 'id' => 'mw-content' ],
					Html::rawElement( 'div', [ 'id' => 'content', 'class' => 'mw-body',  'role' => 'main' ],
						$this->getSiteNotices() .
						$this->getIndicators() .
						Html::rawElement(
							'h1',
							[
								'id' => 'firstHeading',
								'class' => 'firstHeading',
								'lang' => $this->get( 'pageLanguage' )
							],
							$this->get( 'title' )
						) .
						Html::rawElement( 'div', [ 'id' => 'mw-page-header-links' ],
							$this->getPortlet(
								'namespaces',
								$this->pileOfTools['namespaces'],
								'timeless-namespaces'
							) .
							$this->getPortlet(
								'views',
								$this->pileOfTools['page-primary'],
								'timeless-pagetools'
							)
						) .
						$this->getClear() .
						Html::rawElement( 'div', [ 'class' => 'mw-body-content', 'id' => 'bodyContent' ],
							$this->getContentSub() .
							$this->get( 'bodytext' ) .
							$this->getClear()
						)
					)
				) .
				$this->getAfterContent() .
				$this->getClear()
			)
		);

		$html .= Html::rawElement( 'div', [ 'id' => 'mw-footer-container', 'class' => 'ts-container' ],
			Html::rawElement( 'div', [ 'id' => 'mw-footer', 'class' => 'ts-inner' ],
				$this->getFooter()
			)
		);

		$html .= Html::closeElement( 'div' );

		// BaseTemplate::printTrail() stuff (has no get version)
		// Required for RL to run
		$html .= MWDebug::getDebugHTML( $this->getSkin()->getContext() );
		$html .= $this->get( 'bottomscripts' );
		$html .= $this->get( 'reporttime' );

		$html .= Html::closeElement( 'body' );
		$html .= Html::closeElement( 'html' );

		// The unholy echo
		echo $html;
	}

	/**
	 * Generates a block of navigation links with a header
	 *
	 * @param string $name
	 * @param array|string $content array of links for use with makeListItem, or a block of text
	 *        Expected array format:
	 * 	[
	 * 		$name => [
	 * 			'links' => [ '0' =>
	 * 				[ 'href' => ..., 'single-id' => ..., 'text' => ... ]
	 * 			],
	 * 			'id' => ...,
	 * 			'active' => ...
	 * 		],
	 * 		...
	 * 	]
	 * @param null|string|array|bool $msg
	 *
	 * @return string html
	 * @since 1.29
	 */
	protected function getPortlet( $name, $content, $msg = null ) {
		if ( $msg === null ) {
			$msg = $name;
		} elseif ( is_array( $msg ) ) {
			$msgString = array_shift( $msg );
			$msgParams = $msg;
			$msg = $msgString;
		}
		$msgObj = wfMessage( $msg );
		if ( $msgObj->exists() ) {
			if ( isset( $msgParams ) && !empty( $msgParams ) ) {
				$msgString = $this->getMsg( $msg, $msgParams )->parse();
			} else {
				$msgString = $msgObj->parse();
			}
		} else {
			$msgString = htmlspecialchars( $msg );
		}

		// HACK: Compatibility with extensions still using SkinTemplateToolboxEnd
		$hookContents = '';
		if ( $name == 'tb' ) {
			if ( isset( $boxes['TOOLBOX'] ) ) {
				ob_start();
				// We pass an extra 'true' at the end so extensions using BaseTemplateToolbox
				// can abort and avoid outputting double toolbox links
				// Avoid PHP 7.1 warning from passing $this by reference
				$template = $this;
				Hooks::run( 'SkinTemplateToolboxEnd', [ &$template, true ] );
				$hookContents = ob_get_contents();
				ob_end_clean();
				if ( !trim( $hookContents ) ) {
					$hookContents = '';
				}
			}
		}
		// END hack

		$labelId = Sanitizer::escapeId( "p-$name-label" );

		if ( is_array( $content ) ) {
			$contentText = Html::openElement( 'ul' );
			if ( $content !== [] ) {
				foreach ( $content as $key => $item ) {
					$contentText .= $this->makeListItem(
						$key,
						$item,
						[ 'text-wrapper' => [ 'tag' => 'span' ] ]
					);
				}
			}
			// Add in SkinTemplateToolboxEnd, if any
			$contentText .= $hookContents;
			$contentText .= Html::closeElement( 'ul' );
		} else {
			$contentText = $content;
		}

		$html = Html::rawElement( 'div', [
				'role' => 'navigation',
				'class' => [ 'mw-portlet', 'emptyPortlet' => !$content ],
				'id' => Sanitizer::escapeId( 'p-' . $name ),
				'title' => Linker::titleAttrib( 'p-' . $name ),
				'aria-labelledby' => $labelId
			],
			Html::rawElement( 'h3', [
					'id' => $labelId,
					'lang' => $this->get( 'userlang' ),
					'dir' => $this->get( 'dir' )
				],
				$msgString
			) .
			Html::rawElement( 'div', [ 'class' => 'mw-portlet-body' ],
				$contentText .
				$this->getAfterPortlet( $name )
			)
		);

		return $html;
	}

	/**
	 * Sidebar chunk containing one or more portlets
	 *
	 * @param string $id
	 * @param string $headerMessage
	 * @param string $content
	 *
	 * @return string html
	 */
	protected function getSidebarChunk( $id, $headerMessage, $content ) {
		$html = '';

		$html .= Html::rawElement(
			'div',
			[ 'id' => Sanitizer::escapeId( $id ), 'class' => 'sidebar-chunk' ],
			Html::rawElement( 'h2', [],
				Html::element( 'span', [],
					$this->getMsg( $headerMessage )->text()
				) .
				Html::element( 'div', [ 'class' => 'pokey' ] )
			) .
			Html::rawElement( 'div', [ 'class' => 'sidebar-inner' ], $content )
		);

		return $html;
	}

	/**
	 * The logo and (optionally) site title
	 *
	 * @param string $id
	 * @param string $part whether it's only image, only text, or both
	 *
	 * @return string html
	 */
	protected function getLogo( $id = 'p-logo', $part = 'both' ) {
		$html = '';
		$language = $this->getSkin()->getLanguage();

		$html .= Html::openElement(
			'div',
			[
				'id' => Sanitizer::escapeId( $id ),
				'class' => 'mw-portlet',
				'role' => 'banner'
			]
		);
		if ( $part !== 'image' ) {
			$titleClass = '';
			if ( $language->hasVariants() ) {
				$siteTitle = $language->convert( $this->getMsg( 'timeless-sitetitle' )->text() );
			} else {
				$siteTitle = $this->getMsg( 'timeless-sitetitle' )->text();
			}
			// width is 11em; 13 characters will probably fit?
			if ( mb_strlen( $siteTitle ) > 13 ) {
				$titleClass = 'long';
			}
			$html .= Html::element( 'a', [
					'id' => 'p-banner',
					'class' => [ 'mw-wiki-title', $titleClass ],
					'href' => $this->data['nav_urls']['mainpage']['href']
				],
				$siteTitle
			);
		}
		if ( $part !== 'text' ) {
			$html .= Html::element( 'a', array_merge(
				[
					'class' => 'mw-wiki-logo',
					'href' => $this->data['nav_urls']['mainpage']['href']
				],
				Linker::tooltipAndAccesskeyAttribs( 'p-logo' )
			) );
		}
		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * The search box at the top
	 *
	 * @return string html
	 */
	protected function getSearch() {
		$html = '';

		$html .= Html::openElement( 'div', [ 'class' => 'mw-portlet', 'id' => 'p-search' ] );

		$html .= Html::rawElement(
			'h3',
			[ 'lang' => $this->get( 'userlang' ), 'dir' => $this->get( 'dir' ) ],
			Html::rawElement( 'label', [ 'for' => 'searchInput' ], $this->getMsg( 'search' )->text() )
		);

		$html .= Html::rawElement( 'form', [ 'action' => $this->get( 'wgScript' ), 'id' => 'searchform' ],
			Html::rawElement( 'div', [ 'id' => 'simpleSearch' ],
				Html::rawElement( 'div', [ 'id' => 'searchInput-container' ],
					$this->makeSearchInput( [
						'id' => 'searchInput',
						'placeholder' => $this->getMsg( 'timeless-search-placeholder' )->text(),
					] )
				) .
				Html::hidden( 'title', $this->get( 'searchtitle' ) ) .
				$this->makeSearchButton(
					'fulltext',
					[ 'id' => 'mw-searchButton', 'class' => 'searchButton mw-fallbackSearchButton' ]
				) .
				$this->makeSearchButton(
					'go',
					[ 'id' => 'searchButton', 'class' => 'searchButton' ]
				)
			)
		);

		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * Left sidebar navigation, usually
	 *
	 * @return string html
	 */
	protected function getMainNavigation() {
		$sidebar = $this->getSidebar();
		$html = '';

		// Already hardcoded into header
		$sidebar['SEARCH'] = false;
		// Parsed as part of pageTools
		$sidebar['TOOLBOX'] = false;
		// Forcibly removed to separate chunk
		$sidebar['LANGUAGES'] = false;

		foreach ( $sidebar as $name => $content ) {
			if ( $content === false ) {
				continue;
			}
			// Numeric strings gets an integer when set as key, cast back - T73639
			$name = (string)$name;
			$html .= $this->getPortlet( $name, $content['content'] );
		}

		$html = $this->getSidebarChunk( 'site-navigation', 'navigation', $html );

		return $html;
	}

	/**
	 * The colour bars
	 * Split this out so we don't have to look at it/can easily kill it later
	 *
	 * @return string html
	 */
	protected function getHeaderHack() {
		$html = '';

		// These are almost exactly the same and this is stupid.
		$html .= Html::rawElement( 'div', [ 'id' => 'mw-header-hack', 'class' => 'color-bar' ],
			Html::rawElement( 'div', [ 'class' => 'color-middle-container' ],
				Html::element( 'div', [ 'class' => 'color-middle' ] )
			) .
			Html::element( 'div', [ 'class' => 'color-left' ] ) .
			Html::element( 'div', [ 'class' => 'color-right' ] )
		);
		$html .= Html::rawElement( 'div', [ 'id' => 'mw-header-nav-hack' ],
			Html::rawElement( 'div', [ 'class' => 'color-bar' ],
				Html::rawElement( 'div', [ 'class' => 'color-middle-container' ],
					Html::element( 'div', [ 'class' => 'color-middle' ] )
				) .
				Html::element( 'div', [ 'class' => 'color-left' ] ) .
				Html::element( 'div', [ 'class' => 'color-right' ] )
			)
		);

		return $html;
	}

	/**
	 * Page tools in sidebar
	 *
	 * @return string html
	 */
	protected function getPageToolSidebar() {
		$pageTools = '';
		$pageTools .= $this->getPortlet(
			'cactions',
			$this->pileOfTools['page-secondary'],
			'timeless-pageactions'
		);
		$pageTools .= $this->getPortlet(
			'userpagetools',
			$this->pileOfTools['user'],
			'timeless-userpagetools'
		);
		$pageTools .= $this->getPortlet(
			'pagemisc',
			$this->pileOfTools['page-tertiary'],
			'timeless-pagemisc'
		);

		return $this->getSidebarChunk( 'page-tools', 'timeless-pageactions', $pageTools );
	}

	/**
	 * Personal/user links portlet for header
	 *
	 * @return array [ html, class ], where class is an extra class to apply to surrounding objects
	 * (for width adjustments)
	 */
	protected function getUserLinks() {
		$user = $this->getSkin()->getUser();
		$personalTools = $this->getPersonalTools();

		$html = '';
		$extraTools = [];

		// Remove Echo badges
		if ( isset( $personalTools['notifications-alert'] ) ) {
			$extraTools['notifications-alert'] = $personalTools['notifications-alert'];
			unset( $personalTools['notifications-alert'] );
		}
		if ( isset( $personalTools['notifications-notice'] ) ) {
			$extraTools['notifications-notice'] = $personalTools['notifications-notice'];
			unset( $personalTools['notifications-notice'] );
		}
		$class = empty( $extraTools ) ? '' : 'extension-icons';

		// Re-label some messages
		if ( isset( $personalTools['userpage'] ) ) {
			$personalTools['userpage']['links'][0]['text'] = $this->getMsg( 'timeless-userpage' )->text();
		}
		if ( isset( $personalTools['mytalk'] ) ) {
			$personalTools['mytalk']['links'][0]['text'] = $this->getMsg( 'timeless-talkpage' )->text();
		}

		// Labels
		if ( $user->isLoggedIn() ) {
			$userName = $user->getName();
			// Make sure it fits first (numbers slightly made up, may need adjusting)
			$fit = empty( $extraTools ) ? 13 : 9;
			if ( mb_strlen( $userName ) < $fit ) {
				$dropdownHeader = $userName;
			} else {
				$dropdownHeader = wfMessage( 'timeless-loggedin' )->text();
			}
			$headerMsg = [ 'timeless-loggedinas', $user->getName() ];
		} else {
			$dropdownHeader = wfMessage( 'timeless-anonymous' )->text();
			$headerMsg = 'timeless-notloggedin';
		}
		$html .= Html::openElement( 'div', [ 'id' => 'user-tools' ] );

		$html .= Html::rawElement( 'div', [ 'id' => 'personal' ],
			Html::rawElement( 'h2', [],
				Html::element( 'span', [], $dropdownHeader ) .
				Html::element( 'div', [ 'class' => 'pokey' ] )
			) .
			Html::rawElement( 'div', [ 'id' => 'personal-inner', 'class' => 'dropdown' ],
				$this->getPortlet( 'personal', $personalTools, $headerMsg )
			)
		);

		// Extra icon stuff (echo etc)
		if ( !empty( $extraTools ) ) {
			$iconList = '';
			foreach ( $extraTools as $key => $item ) {
				$iconList .= $this->makeListItem( $key, $item );
			}

			$html .= Html::rawElement(
				'div',
				[ 'id' => 'personal-extra', 'class' => 'p-body' ],
				Html::rawElement( 'ul', [], $iconList )
			);
		}

		$html .= Html::closeElement( 'div' );

		return [
			'html' => $html,
			'class' => $class
		];
	}

	/**
	 * Notices that may appear above the firstHeading
	 *
	 * @return string html
	 */
	protected function getSiteNotices() {
		$html = '';

		if ( $this->data['sitenotice'] ) {
			$html .= Html::rawElement( 'div', [ 'id' => 'siteNotice' ], $this->get( 'sitenotice' ) );
		}
		if ( $this->data['newtalk'] ) {
			$html .= Html::rawElement( 'div', [ 'class' => 'usermessage' ], $this->get( 'newtalk' ) );
		}

		return $html;
	}

	/**
	 * Links and information that may appear below the firstHeading
	 *
	 * @return string html
	 */
	protected function getContentSub() {
		$html = '';

		$html .= Html::openElement( 'div', [ 'id' => 'contentSub' ] );
		if ( $this->data['subtitle'] ) {
			$html .= $this->get( 'subtitle' );
		}
		if ( $this->data['undelete'] ) {
			$html .= $this->get( 'undelete' );
		}
		$html .= Html::closeElement( 'div' );

		return $html;
	}

	/**
	 * The data after content, catlinks, and potential other stuff that may appear within
	 * the content block but after the main content
	 *
	 * @return string html
	 */
	protected function getAfterContent() {
		$html = '';

		if ( $this->data['catlinks'] || $this->data['dataAfterContent'] ) {
			$html .= Html::openElement( 'div', [ 'id' => 'content-bottom-stuff' ] );
			if ( $this->data['catlinks'] ) {
				$html .= $this->get( 'catlinks' );
			}
			if ( $this->data['dataAfterContent'] ) {
				$html .= $this->get( 'dataAfterContent' );
			}
			$html .= Html::closeElement( 'div' );
		}

		return $html;
	}

	/**
	 * Generate pile of all the tools
	 *
	 * We can make a few assumptions based on where a tool started out:
	 *     If it's in the cactions region, it's a page tool, probably primary or secondary
	 *     ...that's all I can think of
	 *
	 * @return array of array of tools information (portlet formatting)
	 */
	protected function getPageTools() {
		$title = $this->getSkin()->getTitle();
		$namespace = $title->getNamespace();

		$sortedPileOfTools = [
			'namespaces' => [],
			'page-primary' => [],
			'page-secondary' => [],
			'user' => [],
			'page-tertiary' => [],
			'general' => []
		];

		// Tools specific to the page
		$pileOfEditTools = [];
		foreach ( $this->data['content_navigation'] as $navKey => $navBlock ) {
			// Just use namespaces items as they are
			if ( $navKey == 'namespaces' ) {
				if ( $namespace < 0 ) {
					// Put special page ns_pages in the more pile so they're not so lonely
					$sortedPileOfTools['page-tertiary'] = $navBlock;
				} else {
					$sortedPileOfTools['namespaces'] = $navBlock;
				}
			} else {
				$pileOfEditTools = array_merge( $pileOfEditTools, $navBlock );
			}
		}

		// Tools that may be general or page-related (typically the toolbox)
		$pileOfTools = $this->getToolbox();
		if ( $namespace >= 0 ) {
			$pileOfTools['pagelog'] = [
				'text' => $this->getMsg( 'timeless-pagelog' )->text(),
				'href' => SpecialPage::getTitleFor( 'Log', $title->getPrefixedText() )->getLocalURL(),
				'id' => 't-pagelog'
			];
		}
		$pileOfTools['more'] = [
			'text' => $this->getMsg( 'timeless-more' )->text(),
			'id' => 'ca-more',
			'class' => 'dropdown-toggle'
		];

		// Goes in the page-primary in mobile, doesn't appear otherwise
		if ( $this->data['language_urls'] !== false ) {
			$pileOfTools['languages'] = [
				'text' => $this->getMsg( 'timeless-languages' )->escaped(),
				'id' => 'ca-languages',
				'class' => 'dropdown-toggle'
			];
		}

		// This is really dumb, and you're an idiot for doing it this way.
		// Obviously if you're not the idiot who did this, I don't mean you.
		foreach ( $pileOfEditTools as $navKey => $navBlock ) {
			$currentSet = null;

			if ( in_array( $navKey, [
				'watch',
				'unwatch'
			] ) ) {
				$currentSet = 'namespaces';
			} elseif ( in_array( $navKey, [
				'edit',
				'view',
				'history',
				'addsection',
				'viewsource'
			] ) ) {
				$currentSet = 'page-primary';
			} elseif ( in_array( $navKey, [
				'delete',
				'rename',
				'protect',
				'unprotect',
				'move'
			] ) ) {
				$currentSet = 'page-secondary';
			} else {
				// Catch random extension ones?
				$currentSet = 'page-primary';
			}
			$sortedPileOfTools[$currentSet][$navKey] = $navBlock;
		}
		foreach ( $pileOfTools as $navKey => $navBlock ) {
			$currentSet = null;

			if ( in_array( $navKey, [
				'contributions',
				'more',
				'languages'
			] ) ) {
				$currentSet = 'page-primary';
			} elseif ( in_array( $navKey, [
				'blockip',
				'userrights',
				'log'
			] ) ) {
				$currentSet = 'user';
			} elseif ( in_array( $navKey, [
				'whatlinkshere',
				'print',
				'info',
				'pagelog',
				'recentchangeslinked',
				'permalink'
			] ) ) {
				$currentSet = 'page-tertiary';
			} else {
				$currentSet = 'general';
			}
			$sortedPileOfTools[$currentSet][$navKey] = $navBlock;
		}

		return $sortedPileOfTools;
	}

	/**
	 * Categories for the sidebar
	 *
	 * Assemble an array of categories, regardless of view mode. Just using Skin or
	 * OutputPage functions doesn't respect view modes (preview, history, whatever)
	 * But why? I have no idea what the purpose of this is.
	 *
	 * @return string html
	 */
	protected function getCategories() {
		global $wgContLang;

		$skin = $this->getSkin();
		$title = $skin->getTitle();
		$catList = false;
		$html = '';

		// Get list from outputpage if in preview; otherwise get list from title
		if ( in_array( $skin->getRequest()->getVal( 'action' ), [ 'submit', 'edit' ] ) ) {
			$allCats = [];
			// Can't just use getCategoryLinks because there's no equivalent for Title
			$allCats2 = $skin->getOutput()->getCategories();
			foreach ( $allCats2 as $displayName ) {
				$catTitle = Title::makeTitleSafe( NS_CATEGORY, $displayName );
				$allCats[] = $catTitle->getDBkey();
			}
		} else {
			// This is probably to trim out some excessive stuff. Unless I was just high on cough syrup.
			$allCats = array_keys( $title->getParentCategories() );

			$len = strlen( $wgContLang->getNsText( NS_CATEGORY ) . ':' );
			foreach ( $allCats as $i => $catName ) {
				$allCats[$i] = substr( $catName, $len );
			}
		}
		if ( $allCats !== [] ) {
			$dbr = wfGetDB( DB_REPLICA );
			$res = $dbr->select(
				[ 'page', 'page_props' ],
				[ 'page_id', 'page_title' ],
				[
					'page_title' => $allCats,
					'page_namespace' => NS_CATEGORY,
					'pp_propname' => 'hiddencat'
				],
				__METHOD__,
				[],
				[ 'page_props' => [ 'JOIN', 'pp_page = page_id' ] ]
			);
			$hiddenCats = [];
			foreach ( $res as $row ) {
				$hiddenCats[] = $row->page_title;
			}
			$normalCats = array_diff( $allCats, $hiddenCats );

			$normalCount = count( $normalCats );
			$hiddenCount = count( $hiddenCats );
			$count = $normalCount;

			// Mostly consistent with how Skin does it.
			// Doesn't have the classes. Either way can't be good for caching.
			if (
				$skin->getUser()->getBoolOption( 'showhiddencats' ) ||
				$title->getNamespace() == NS_CATEGORY
			) {
				$count += $hiddenCount;
			} else {
				/* We don't care if there are hidden ones. */
				$hiddenCount = 0;
			}

			// Assemble the html...
			if ( $count ) {
				if ( $normalCount ) {
					$catHeader = 'categories';
				} else {
					$catHeader = 'hidden-categories';
				}
				$catList = '';
				if ( $normalCount ) {
					$catList .= $this->getCatList( $normalCats, 'catlist-normal', 'categories' );
				}
				if ( $hiddenCount ) {
					$catList .= $this->getCatList(
						$hiddenCats,
						'catlist-hidden',
						[ 'hidden-categories', $hiddenCount ]
					);
				}
			}
		}
		if ( $catList ) {
			$html = $this->getSidebarChunk( 'catlinks-sidebar', $catHeader, $catList );
		}

		return $html;
	}

	/**
	 * List of categories
	 *
	 * @param array $list
	 * @param string $id
	 * @param string|array $message i18n message name or an array of [ message name, params ]
	 *
	 * @return string html
	 */
	protected function getCatList( $list, $id, $message ) {
		$html = '';

		$categories = [];
		// Generate portlet content
		foreach ( $list as $category ) {
			$title = Title::makeTitleSafe( NS_CATEGORY, $category );
			if ( !$title ) {
				continue;
			}
			$categories[ htmlspecialchars( $category ) ] = [ 'links' => [ 0 => [
				'href' => $title->getLinkURL(),
				'text' => $title->getText()
			] ] ];
		}

		$html .= $this->getPortlet( $id, $categories, $message );

		return $html;
	}

	/**
	 * Interlanguage links block, also with variants
	 *
	 * @return string html
	 */
	protected function getInterlanguageLinks() {
		$html = '';

		if ( isset( $this->data['variant_urls'] ) && $this->data['variant_urls'] !== false ) {
			$variants = $this->getPortlet( 'variants', $this->data['variant_urls'], true );
		} else {
			$variants = '';
		}
		if ( $this->data['language_urls'] !== false ) {
			$html .= $this->getSidebarChunk(
				'other-languages',
				'timeless-languages',
				$variants .
				$this->getPortlet(
					'lang',
					$this->data['language_urls'] ?: [],
					'otherlanguages'
				)
			);
		}

		return $html;
	}
}
