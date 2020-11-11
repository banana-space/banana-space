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
				Html::rawElement( 'div', [ 'id' => 'jump-to-nav', 'class' => 'mw-jump' ],
					$this->getMsg( 'jumpto' )->escaped() .
					Html::element( 'a', [ 'href' => '#column-one' ],
						$this->getMsg( 'jumptonavigation' )->text()
					) .
					$this->getMsg( 'comma-separator' )->escaped() .
					Html::element( 'a', [ 'href' => '#searchInput' ],
						$this->getMsg( 'jumptosearch' )->text()
					)
				) .
				'<!-- start content -->' .

				$this->get( 'bodytext' ) .
				$this->getIfExists( 'catlinks' ) .

				'<!-- end content -->' .
				$this->getIfExists( 'dataAfterContent' ) .
				$this->getClear()
			)
		);
		$html .= $this->deprecatedHookHack( 'MonoBookAfterContent' );
		$html .= Html::closeElement( 'div' );

		$html .= Html::rawElement( 'div',
			[
				'id' => 'column-one',
				'lang' => $this->get( 'userlang' ),
				'dir' => $this->get( 'dir' )
			],
			Html::element( 'h2', [], $this->getMsg( 'navigation-heading' )->text() ) .
			$this->getBox( 'cactions', $this->data['content_actions'], 'views' ) .
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
			$this->getRenderedSidebar()
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
	 * Generate the full sidebar
	 *
	 * @return string html
	 */
	protected function getRenderedSidebar() {
		$sidebar = $this->data['sidebar'];
		$html = '';

		if ( !isset( $sidebar['SEARCH'] ) ) {
			$sidebar['SEARCH'] = true;
		}
		if ( !isset( $sidebar['TOOLBOX'] ) ) {
			$sidebar['TOOLBOX'] = true;
		}
		if ( !isset( $sidebar['LANGUAGES'] ) ) {
			$sidebar['LANGUAGES'] = true;
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
				$html .= $this->getToolboxBox();
			} elseif ( $boxName == 'LANGUAGES' ) {
				$html .= $this->getLanguageBox();
			} else {
				$html .= $this->getBox(
					$boxName,
					$content,
					null,
					[ 'extra-classes' => 'generated-sidebar' ]
				);
			}
		}

		return $html;
	}

	/**
	 * Generate the search, using config options for buttons (?)
	 *
	 * @return string html
	 */
	protected function getSearchBox() {
		$html = '';

		if ( $this->config->get( 'UseTwoButtonsSearchForm' ) ) {
			$optionButtons = '&#160; ' . $this->makeSearchButton(
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
	 * @return string html
	 */
	protected function getToolboxBox() {
		$html = '';
		$skin = $this;

		$html .= $this->getBox( 'tb', $this->getToolbox(), 'toolbox', [ 'hooks' => [
			// Deprecated hooks
			'MonoBookTemplateToolboxEnd' => [ &$skin ],
			'SkinTemplateToolboxEnd' => [ &$skin, true ]
		] ] );

		$html .= $this->deprecatedHookHack( 'MonoBookAfterToolbox' );

		return $html;
	}

	/**
	 * Generate the languages box
	 *
	 * @return string html
	 */
	protected function getLanguageBox() {
		$html = '';

		if ( $this->data['language_urls'] !== false ) {
			$html .= $this->getBox( 'lang', $this->data['language_urls'], 'otherlanguages' );
		}

		return $html;
	}

	/**
	 * Generate a sidebar box using getPortlet(); prefill some common stuff
	 *
	 * @param string $name
	 * @param array|string $contents
	 * @param null|string|array|bool $msg
	 * @param array $setOptions
	 *
	 * @return string html
	 */
	protected function getBox( $name, $contents, $msg = null, $setOptions = [] ) {
		$options = [
			'class' => 'portlet',
			'body-class' => 'pBody',
			'text-wrapper' => ''
		];
		foreach ( $setOptions as $key => $value ) {
			$options[$key] = $value;
		}

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
	 */
	protected function getPortlet( $name, $content, $msg = null, $setOptions = [] ) {
		// random stuff to override with any provided options
		$options = [
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
			// old toolbox hook support (use: [ 'SkinTemplateToolboxEnd' => [ &$skin, true ] ])
			'hooks' => '',
			// option to stick arbitrary stuff at the beginning of the ul
			'list-prepend' => ''
		];
		// set options based on input
		foreach ( $setOptions as $key => $value ) {
			$options[$key] = $value;
		}

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
			// Compatibility with extensions still using SkinTemplateToolboxEnd or similar
			if ( is_array( $options['hooks'] ) ) {
				foreach ( $options['hooks'] as $hook => $hookOptions ) {
					$contentText .= $this->deprecatedHookHack( $hook, $hookOptions );
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
	 * @param array $hookOptions args
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

		if ( ( $options['loose'] && $this->data[$object] != '' ) ||
			( !$options['loose'] && $this->data[$object] ) ) {
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
