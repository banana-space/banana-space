<?php
/**
 * MonoBook nouveau.
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
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
 * @ingroup Skins
 */

/**
 * @ingroup Skins
 */
class MonoBookTemplate extends BaseTemplate {

	/**
	 * Template filter callback for MonoBook skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 */
	public function execute() {
		// Open html, body elements, etc
		$html = $this->get( 'headelement' );
		$html .= Html::openElement( 'div', [ 'id' => 'globalWrapper' ] );

		$html .= Html::openElement( 'div', [ 'id' => 'column-content' ] );
		$html .= Html::rawElement( 'div', [ 'id' => 'content', 'class' => 'mw-body',  'role' => 'main' ],
			Html::element( 'a', [ 'id' => 'top' ] ) .
			$this->getIfExists( 'sitenotice', [
				'wrapper' => 'div',
				'parameters' => [ 'id' => 'siteNotice', 'class' => 'mw-body-content' ]
			] ) .
			$this->getIndicators() .
			$this->getIfExists( 'title', [
				'loose' => true,
				'wrapper' => 'h1',
				'parameters' => [
					'id' => 'firstHeading',
					'class' => 'firstHeading',
					'lang' => $this->getSkin()->getTitle()->getPageViewLanguage()->getHtmlCode()
				]
			] ) .
			Html::rawElement( 'div', [ 'id' => 'bodyContent', 'class' => 'mw-body-content' ],
				Html::rawElement( 'div', [ 'id' => 'siteSub' ], $this->getMsg( 'tagline' )->parse() ) .
				Html::rawElement(
					'div',
					[ 'id' => 'contentSub', 'lang' => $this->get( 'userlang' ), 'dir' => $this->get( 'dir' ) ],
					$this->get( 'subtitle' )
				) .
				$this->getIfExists( 'undelete', [ 'wrapper' => 'div', 'parameters' => [
					'id' => 'contentSub2'
				] ] ) .
				$this->getIfExists( 'newtalk', [ 'wrapper' => 'div', 'parameters' => [
					'class' => 'usermessage'
				] ] ) .
				Html::element( 'div', [ 'id' => 'jump-to-nav' ] ) .
				Html::element( 'a', [ 'href' => '#column-one', 'class' => 'mw-jump-link' ],
					$this->getMsg( 'monobook-jumptonavigation' )->text()
				) .
				Html::element( 'a', [ 'href' => '#searchInput', 'class' => 'mw-jump-link' ],
					$this->getMsg( 'monobook-jumptosearch' )->text()
				) .
				'<!-- start content -->' .

				$this->get( 'bodytext' ) .
				$this->getIfExists( 'catlinks' ) .

				'<!-- end content -->' .
				$this->getClear()
			)
		);
		$html .= $this->deprecatedHookHack( 'MonoBookAfterContent' );
		$html .= $this->getIfExists( 'dataAfterContent' ) . $this->getClear();
		$html .= Html::closeElement( 'div' );

		$html .= Html::rawElement( 'div',
			[
				'id' => 'column-one',
				'lang' => $this->get( 'userlang' ),
				'dir' => $this->get( 'dir' )
			],
			Html::element( 'h2', [], $this->getMsg( 'navigation-heading' )->text() ) .
			$this->getCactions() .
			$this->getBox( 'personal', $this->getPersonalTools(), 'personaltools' ) .
			Html::rawElement( 'div', [ 'class' => 'portlet', 'id' => 'p-logo', 'role' => 'banner' ],
				Html::element( 'a',
					[
						'href' => $this->data['nav_urls']['mainpage']['href'],
						'class' => 'mw-wiki-logo',
					]
					+ Linker::tooltipAndAccesskeyAttribs( 'p-logo' )
				)
			) .
			Html::rawElement( 'div', [ 'id' => 'sidebar' ], $this->getRenderedSidebar() ) .
			$this->getMobileNavigationIcon(
				'sidebar',
				$this->getMsg( 'jumptonavigation' )->text()
			) .
			$this->getMobileNavigationIcon(
				'p-personal',
				$this->getMsg( 'monobook-jumptopersonal' )->text()
			) .
			$this->getMobileNavigationIcon(
				'globalWrapper',
				$this->getMsg( 'monobook-jumptotop' )->text()
			)
		);
		$html .= '<!-- end of the left (by default at least) column -->';

		$html .= $this->getClear();
		$html .= $this->getSimpleFooter();
		$html .= Html::closeElement( 'div' );

		$html .= $this->getTrail();

		$html .= Html::closeElement( 'body' );
		$html .= Html::closeElement( 'html' );

		// The unholy echo
		echo $html;
	}

	/**
	 * Create a wrapped link to create a mobile toggle/jump icon
	 * Needs to be an on-page link (as opposed to drawing something on the fly for an
	 * onclick event) for no-js support.
	 *
	 * @param string $target link target
	 * @param string $title icon title
	 *
	 * @return string html empty link block
	 */
	protected function getMobileNavigationIcon( $target, $title ) {
		return Html::element( 'a', [
			'href' => "#$target",
			'title' => $title,
			'class' => 'menu-toggle',
			'id' => "$target-toggle"
		] );
	}

	/**
	 * Generate the cactions (content actions) tabs, as well as a second set of spoof tabs for mobile
	 *
	 * @return string html
	 */
	protected function getCactions() {
		$html = '';
		$allTabs = $this->data['content_actions'];
		$tabCount = count( $allTabs );

		// Normal cactions
		if ( $tabCount > 2 ) {
			$html .= $this->getBox( 'cactions', $allTabs, 'monobook-cactions-label' );
		} else {
			// Is redundant with spoof, hide normal cactions entirely in mobile
			$html .= $this->getBox( 'cactions', $allTabs, 'monobook-cactions-label',
				[ 'extra-classes' => 'nomobile' ]
			);
		}

		// Mobile cactions tabs
		$tabs = $this->data['content_navigation']['namespaces'];
		foreach ( $tabs as $tab => $attribs ) {
			$tabs[$tab]['id'] = $attribs['id'] . '-mobile';
			$tabs[$tab]['title'] = $attribs['text'];
		}

		if ( $tabCount !== 1 ) {
			// Is not special page or stuff, append a 'more'
			$tabs['more'] = [
				'text' => $this->getMsg( 'monobook-more-actions' )->text(),
				'href' => '#p-cactions',
				'id' => 'ca-more'
			];
		}
		$tabs['toolbox'] = [
			'text' => $this->getMsg( 'toolbox' )->text(),
			'href' => '#p-tb',
			'id' => 'ca-tools',
			'title' => $this->getMsg( 'toolbox' )->text()
		];

		$languages = $this->data['sidebar']['LANGUAGES'];
		if ( $languages !== false ) {
			$tabs['languages'] = [
				'text' => $this->getMsg( 'otherlanguages' )->text(),
				'href' => '#p-lang',
				'id' => 'ca-languages',
				'title' => $this->getMsg( 'otherlanguages' )->text()
			];
		}

		$html .= $this->getBox( 'cactions-mobile', $tabs, 'monobook-cactions-label' );

		return $html;
	}

	/**
	 * Generate the full sidebar
	 *
	 * @return string html
	 * @suppress PhanTypeMismatchArgument $content is an array
	 * even though we are comparing it to boolean
	 */
	protected function getRenderedSidebar() {
		$sidebar = $this->data['sidebar'];
		$html = '';
		$languagesHTML = '';

		if ( !isset( $sidebar['SEARCH'] ) ) {
			$sidebar['SEARCH'] = true;
		}

		foreach ( $sidebar as $boxName => $content ) {
			if ( $content === false ) {
				continue;
			}

			// Numeric strings gets an integer when set as key, cast back - T73639
			$boxName = (string)$boxName;

			if ( $boxName == 'SEARCH' ) {
				$html .= $this->getSearchBox();
			} elseif ( $boxName == 'TOOLBOX' ) {
				$html .= $this->getToolboxBox( $content );
			} elseif ( $boxName == 'LANGUAGES' ) {
				$languagesHTML = $this->getLanguageBox( $content );
			} else {
				$html .= $this->getBox(
					$boxName,
					$content,
					null,
					[ 'extra-classes' => 'generated-sidebar' ]
				);
			}
		}

		// Output language portal last given it can be long
		// on articles which support multiple languages (T254546)
		return $html . $languagesHTML;
	}

	/**
	 * Generate the search, using config options for buttons (?)
	 *
	 * @return string html
	 */
	protected function getSearchBox() {
		$html = '';

		if ( $this->config->get( 'UseTwoButtonsSearchForm' ) ) {
			$optionButtons = "\u{00A0} " . $this->makeSearchButton(
				'fulltext',
				[ 'id' => 'mw-searchButton', 'class' => 'searchButton' ]
			);
		} else {
			$optionButtons = Html::rawElement( 'div', [],
				Html::rawElement( 'a', [ 'href' => $this->get( 'searchaction' ), 'rel' => 'search' ],
					$this->getMsg( 'powersearch-legend' )->escaped()
				)
			);
		}
		$searchInputId = 'searchInput';
		$searchForm = Html::rawElement( 'form', [
			'action' => $this->get( 'wgScript' ),
			'id' => 'searchform'
		],
			Html::hidden( 'title', $this->get( 'searchtitle' ) ) .
			$this->makeSearchInput( [ 'id' => $searchInputId ] ) .
			$this->makeSearchButton( 'go', [ 'id' => 'searchGoButton', 'class' => 'searchButton' ] ) .
			$optionButtons
		);

		$html .= $this->getBox( 'search', $searchForm, null, [
			'search-input-id' => $searchInputId,
			'role' => 'search',
			'body-id' => 'searchBody'
		] );

		return $html;
	}

	/**
	 * Generate the toolbox, complete with all three old hooks
	 *
	 * @param array $toolboxItems
	 * @return string html
	 */
	protected function getToolboxBox( $toolboxItems ) {
		$html = '';

		$html .= $this->getBox( 'tb', $toolboxItems, 'toolbox' );

		return $html;
	}

	/**
	 * Generate the languages box
	 *
	 * @param array $languages Interwiki language links
	 * @return string html
	 */
	protected function getLanguageBox( $languages ) {
		$html = '';
		$name = 'lang';

		if (
			$languages !== [] ||
			// Check getAfterPortlet to make sure the languages are shown
			// when empty but something has been injected in the portal. (T252841)
			$this->getAfterPortlet( $name )
		) {
			$html .= $this->getBox( $name, $languages, 'otherlanguages' );
		}

		return $html;
	}

	/**
	 * Generate a sidebar box using getPortlet(); prefill some common stuff
	 *
	 * @param string $name
	 * @param array|string $contents
	 * @param-taint $contents escapes_htmlnoent
	 * @param null|string|array|bool $msg
	 * @param array $setOptions
	 *
	 * @return string html
	 */
	protected function getBox( $name, $contents, $msg = null, $setOptions = [] ) {
		$options = array_merge( [
			'class' => 'portlet',
			'body-class' => 'pBody',
			'text-wrapper' => ''
		], $setOptions );

		// Do some special stuff for the personal menu
		if ( $name == 'personal' ) {
			$prependiture = '';

			// Extension:UniversalLanguageSelector order - T121793
			if ( array_key_exists( 'uls', $contents ) ) {
				$prependiture .= $this->makeListItem( 'uls', $contents['uls'] );
				unset( $contents['uls'] );
			}
			if ( !$this->getSkin()->getUser()->isLoggedIn() &&
				User::groupHasPermission( '*', 'edit' )
			) {
				$prependiture .= Html::rawElement(
					'li',
					[ 'id' => 'pt-anonuserpage' ],
					$this->getMsg( 'notloggedin' )->escaped()
				);
			}
			$options['list-prepend'] = $prependiture;
		}

		return $this->getPortlet( $name, $contents, $msg, $options );
	}

	/**
	 * Generates a block of navigation links with a header
	 *
	 * @param string $name
	 * @param array|string $content array of links for use with makeListItem, or a block of text
	 * @param null|string|array $msg
	 * @param array $setOptions random crap to rename/do/whatever
	 *
	 * @return string html
	 * @suppress PhanTypeMismatchArgumentNullable Many false positives
	 */
	protected function getPortlet( $name, $content, $msg = null, $setOptions = [] ) {
		// random stuff to override with any provided options
		$options = array_merge( [
			// handle role=search a little differently
			'role' => 'navigation',
			'search-input-id' => 'searchInput',
			// extra classes/ids
			'id' => 'p-' . $name,
			'class' => 'mw-portlet',
			'extra-classes' => '',
			'body-id' => null,
			'body-class' => 'mw-portlet-body',
			'body-extra-classes' => '',
			// wrapper for individual list items
			'text-wrapper' => [ 'tag' => 'span' ],
			// option to stick arbitrary stuff at the beginning of the ul
			'list-prepend' => ''
		], $setOptions );

		// Handle the different $msg possibilities
		if ( $msg === null ) {
			$msg = $name;
			$msgParams = [];
		} elseif ( is_array( $msg ) ) {
			$msgString = array_shift( $msg );
			$msgParams = $msg;
			$msg = $msgString;
		} else {
			$msgParams = [];
		}
		$msgObj = $this->getMsg( $msg, $msgParams );
		if ( $msgObj->exists() ) {
			$msgString = $msgObj->parse();
		} else {
			$msgString = htmlspecialchars( $msg );
		}

		$labelId = Sanitizer::escapeIdForAttribute( "p-$name-label" );

		if ( is_array( $content ) ) {
			$contentText = Html::openElement( 'ul',
				[ 'lang' => $this->get( 'userlang' ), 'dir' => $this->get( 'dir' ) ]
			);
			$contentText .= $options['list-prepend'];
			foreach ( $content as $key => $item ) {
				if ( is_array( $options['text-wrapper'] ) ) {
					$contentText .= $this->makeListItem(
						$key,
						$item,
						[ 'text-wrapper' => $options['text-wrapper'] ]
					);
				} else {
					$contentText .= $this->makeListItem(
						$key,
						$item
					);
				}
			}
			$contentText .= Html::closeElement( 'ul' );
		} else {
			$contentText = $content;
		}

		// Special handling for role=search
		$divOptions = [
			'role' => $options['role'],
			'class' => $this->mergeClasses( $options['class'], $options['extra-classes'] ),
			'id' => Sanitizer::escapeIdForAttribute( $options['id'] ),
			'title' => Linker::titleAttrib( $options['id'] )
		];
		if ( $options['role'] !== 'search' ) {
			$divOptions['aria-labelledby'] = $labelId;
		}
		$labelOptions = [
			'id' => $labelId,
			'lang' => $this->get( 'userlang' ),
			'dir' => $this->get( 'dir' )
		];
		if ( $options['role'] == 'search' ) {
			$msgString = Html::rawElement( 'label', [ 'for' => $options['search-input-id'] ], $msgString );
		}

		$bodyDivOptions = [
			'class' => $this->mergeClasses( $options['body-class'], $options['body-extra-classes'] )
		];
		if ( is_string( $options['body-id'] ) ) {
			$bodyDivOptions['id'] = $options['body-id'];
		}

		$html = Html::rawElement( 'div', $divOptions,
			Html::rawElement( 'h3', $labelOptions, $msgString ) .
			Html::rawElement( 'div', $bodyDivOptions,
				$contentText .
				$this->getAfterPortlet( $name )
			)
		);

		return $html;
	}

	/**
	 * Helper function for getPortlet
	 *
	 * Merge all provided css classes into a single array
	 * Account for possible different input methods matching what Html::element stuff takes
	 *
	 * @param string|array $class base portlet/body class
	 * @param string|array $extraClasses any extra classes to also include
	 *
	 * @return array all classes to apply
	 */
	protected function mergeClasses( $class, $extraClasses ) {
		if ( !is_array( $class ) ) {
			$class = [ $class ];
		}
		if ( !is_array( $extraClasses ) ) {
			$extraClasses = [ $extraClasses ];
		}

		return array_merge( $class, $extraClasses );
	}

	/**
	 * Wrapper to catch output of old hooks expecting to write directly to page
	 * We no longer do things that way.
	 *
	 * @param string $hook event
	 * @param mixed $hookOptions args
	 *
	 * @return string html
	 */
	protected function deprecatedHookHack( $hook, $hookOptions = [] ) {
		$hookContents = '';
		ob_start();
		Hooks::run( $hook, $hookOptions );
		$hookContents = ob_get_contents();
		ob_end_clean();
		if ( !trim( $hookContents ) ) {
			$hookContents = '';
		}

		return $hookContents;
	}

	/**
	 * Simple wrapper for random if-statement-wrapped $this->data things
	 *
	 * @param string $object name of thing
	 * @param array $setOptions
	 *
	 * @return string html
	 */
	protected function getIfExists( $object, $setOptions = [] ) {
		$options = [
			'loose' => false,
			'wrapper' => 'none',
			'parameters' => []
		];
		foreach ( $setOptions as $key => $value ) {
			$options[$key] = $value;
		}

		$html = '';

		// @phan-suppress-next-line PhanImpossibleCondition
		if ( ( $options['loose'] && $this->data[$object] != '' ) ||
			( !$options['loose'] && $this->data[$object] ) ) {
			// @phan-suppress-previous-line PhanRedundantCondition
			if ( $options['wrapper'] == 'none' ) {
				$html .= $this->get( $object );
			} else {
				$html .= Html::rawElement(
					$options['wrapper'],
					$options['parameters'],
					$this->get( $object )
				);
			}
		}

		return $html;
	}

	/**
	 * Renderer for getFooterIcons and getFooterLinks as a generic footer block
	 *
	 * @return string html
	 */
	protected function getSimpleFooter() {
		$validFooterIcons = $this->getFooterIcons( 'icononly' );
		$validFooterLinks = $this->getFooterLinks( 'flat' );

		$html = '';

		$html .= Html::openElement( 'div', [
			'id' => 'footer',
			'class' => 'mw-footer',
			'role' => 'contentinfo',
			'lang' => $this->get( 'userlang' ),
			'dir' => $this->get( 'dir' )
		] );

		foreach ( $validFooterIcons as $blockName => $footerIcons ) {
			$html .= Html::openElement( 'div', [
				'id' => Sanitizer::escapeIdForAttribute( "f-{$blockName}ico" ),
				'class' => 'footer-icons'
			] );
			foreach ( $footerIcons as $icon ) {
				$html .= $this->getSkin()->makeFooterIcon( $icon );
			}
			$html .= Html::closeElement( 'div' );
		}
		if ( count( $validFooterLinks ) > 0 ) {
			$html .= Html::openElement( 'ul', [ 'id' => 'f-list' ] );
			foreach ( $validFooterLinks as $aLink ) {
				$html .= Html::rawElement(
					'li',
					[ 'id' => Sanitizer::escapeIdForAttribute( $aLink ) ],
					$this->get( $aLink )
				);
			}
			$html .= Html::closeElement( 'ul' );
		}
		$html .= Html::closeElement( 'div' );

		return $html;
	}
}
