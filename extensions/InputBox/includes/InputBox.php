<?php
/**
 * Classes for InputBox extension
 *
 * @file
 * @ingroup Extensions
 */

use MediaWiki\MediaWikiServices;

/**
 * InputBox class
 */
class InputBox {

	/* Fields */

	private $mParser;
	private $mType = '';
	private $mWidth = 50;
	private $mPreload = null;
	private $mPreloadparams = null;
	private $mEditIntro = null;
	private $mUseVE = null;
	private $mSummary = null;
	private $mNosummary = null;
	private $mMinor = null;
	private $mPage = '';
	private $mBR = 'yes';
	private $mDefaultText = '';
	private $mPlaceholderText = '';
	private $mBGColor = 'transparent';
	private $mButtonLabel = '';
	private $mSearchButtonLabel = '';
	private $mFullTextButton = '';
	private $mLabelText = '';
	private $mHidden = '';
	private $mNamespaces = '';
	private $mID = '';
	private $mInline = false;
	private $mPrefix = '';
	private $mDir = '';
	private $mSearchFilter = '';
	private $mTour = '';
	private $mTextBoxAriaLabel = '';

	/* Functions */

	/**
	 * @param Parser $parser
	 */
	public function __construct( $parser ) {
		$this->mParser = $parser;
		// Default value for dir taken from the page language (bug 37018)
		$this->mDir = $this->mParser->getTargetLanguage()->getDir();
		// Split caches by language, to make sure visitors do not see a cached
		// version in a random language (since labels are in the user language)
		$this->mParser->getOptions()->getUserLangObj();
		$this->mParser->getOutput()->addModuleStyles( [
			'ext.inputBox.styles',
			'mediawiki.ui.input',
			'mediawiki.ui.checkbox',
		] );
	}

	public function render() {
		// Handle various types
		switch ( $this->mType ) {
			case 'create':
			case 'comment':
				$this->mParser->getOutput()->addModules( 'ext.inputBox' );
				return $this->getCreateForm();
			case 'move':
				return $this->getMoveForm();
			case 'commenttitle':
				return $this->getCommentForm();
			case 'search':
				return $this->getSearchForm( 'search' );
			case 'fulltext':
				return $this->getSearchForm( 'fulltext' );
			case 'search2':
				return $this->getSearchForm2();
			default:
				return Xml::tags( 'div', null,
					Xml::element( 'strong',
						[ 'class' => 'error' ],
						strlen( $this->mType ) > 0
							? wfMessage( 'inputbox-error-bad-type', $this->mType )->text()
							: wfMessage( 'inputbox-error-no-type' )->text()
					)
				);
		}
	}

	/**
	 * Returns the action name and value to use in inputboxes which redirects to edit pages.
	 * Decides, if the link should redirect to VE edit page (veaction=edit) or to wikitext editor
	 * (action=edit).
	 *
	 * @return Array Array with name and value data
	 */
	private function getEditActionArgs() {
		// default is wikitext editor
		$args = [
			'name' => 'action',
			'value' => 'edit',
		];
		// check, if VE is installed and VE editor is requested
		if ( $this->shouldUseVE() ) {
			$args = [
				'name' => 'veaction',
				'value' => 'edit',
			];
		}
		return $args;
	}

	/**
	 * Get common classes, that could be added and depend on, if
	 * a line break between a button and an input field is added or not.
	 *
	 * @return string
	 */
	private function getLinebreakClasses() {
		return strtolower( $this->mBR ) === '<br />' ? 'mw-inputbox-input ' : '';
	}

	/**
	 * Generate search form
	 * @param string $type
	 * @return string HTML
	 */
	public function getSearchForm( $type ) {
		global $wgNamespaceAliases;

		// Use button label fallbacks
		if ( !$this->mButtonLabel ) {
			$this->mButtonLabel = wfMessage( 'inputbox-tryexact' )->text();
		}
		if ( !$this->mSearchButtonLabel ) {
			$this->mSearchButtonLabel = wfMessage( 'inputbox-searchfulltext' )->text();
		}
		if ( $this->mID !== '' ) {
			$idArray = [ 'id' => Sanitizer::escapeIdForAttribute( $this->mID ) ];
		} else {
			$idArray = [];
		}
		// We need a unqiue id to link <label> to checkboxes, but also
		// want multiple <inputbox>'s to not be invalid html
		$idRandStr = Sanitizer::escapeIdForAttribute( '-' . $this->mID . wfRandom() );

		// Build HTML
		$htmlOut = Xml::openElement( 'div',
			[
				'class' => 'mw-inputbox-centered',
				'style' => $this->bgColorStyle(),
			]
		);
		$htmlOut .= Xml::openElement( 'form',
			[
				'name' => 'searchbox',
				'class' => 'searchbox',
				'action' => SpecialPage::getTitleFor( 'Search' )->getLocalUrl(),
			] + $idArray
		);

		$htmlOut .= $this->buildTextBox( [
			'class' => $this->getLinebreakClasses() . 'searchboxInput mw-ui-input mw-ui-input-inline',
			'name' => 'search',
			'type' => $this->mHidden ? 'hidden' : 'text',
			'value' => $this->mDefaultText,
			'placeholder' => $this->mPlaceholderText,
			'size' => $this->mWidth,
			'dir' => $this->mDir
		] );

		if ( $this->mPrefix != '' ) {
			$htmlOut .= Html::hidden( 'prefix', $this->mPrefix );
		}

		if ( $this->mSearchFilter != '' ) {
			$htmlOut .= Html::hidden( 'searchfilter', $this->mSearchFilter );
		}

		if ( $this->mTour != '' ) {
			$htmlOut .= Html::hidden( 'tour', $this->mTour );
		}

		$htmlOut .= $this->mBR;

		// Determine namespace checkboxes
		$namespacesArray = explode( ',', $this->mNamespaces );
		if ( $this->mNamespaces ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$namespaces = $contLang->getNamespaces();
			$nsAliases = array_merge( $contLang->getNamespaceAliases(), $wgNamespaceAliases );
			$showNamespaces = [];
			$checkedNS = [];
			// Check for valid namespaces
			foreach ( $namespacesArray as $userNS ) {
				$userNS = trim( $userNS ); // no whitespace

				// Namespace needs to be checked if flagged with "**"
				if ( strpos( $userNS, '**' ) ) {
					$userNS = str_replace( '**', '', $userNS );
					$checkedNS[$userNS] = true;
				}

				$mainMsg = wfMessage( 'inputbox-ns-main' )->inContentLanguage()->text();
				if ( $userNS == 'Main' || $userNS == $mainMsg ) {
					$i = 0;
				} elseif ( array_search( $userNS, $namespaces ) ) {
					$i = array_search( $userNS, $namespaces );
				} elseif ( isset( $nsAliases[$userNS] ) ) {
					$i = $nsAliases[$userNS];
				} else {
					continue; // Namespace not recognized, skip
				}
				$showNamespaces[$i] = $userNS;
				if ( isset( $checkedNS[$userNS] ) && $checkedNS[$userNS] ) {
					$checkedNS[$i] = true;
				}
			}

			// Show valid namespaces
			foreach ( $showNamespaces as $i => $name ) {
				$checked = [];
				// Namespace flagged with "**" or if it's the only one
				if ( ( isset( $checkedNS[$i] ) && $checkedNS[$i] ) || count( $showNamespaces ) == 1 ) {
					$checked = [ 'checked' => 'checked' ];
				}

				if ( count( $showNamespaces ) == 1 ) {
					// Checkbox
					$htmlOut .= Xml::element( 'input',
						[
							'type' => 'hidden',
							'name' => 'ns' . $i,
							'value' => 1,
							'id' => 'mw-inputbox-ns' . $i . $idRandStr
						] + $checked
					);
				} else {
					// Checkbox
					$htmlOut .= ' <div class="mw-inputbox-element mw-ui-checkbox">';
					$htmlOut .= Xml::element( 'input',
						[
							'type' => 'checkbox',
							'name' => 'ns' . $i,
							'value' => 1,
							'id' => 'mw-inputbox-ns' . $i . $idRandStr
						] + $checked
					);
					// Label
					$htmlOut .= Xml::label( $name, 'mw-inputbox-ns' . $i . $idRandStr );
					$htmlOut .= '</div> ';
				}
			}

			// Line break
			$htmlOut .= $this->mBR;
		} elseif ( $type == 'search' ) {
			// Go button
			$htmlOut .= Xml::element( 'input',
				[
					'type' => 'submit',
					'name' => 'go',
					'class' => 'mw-ui-button',
					'value' => $this->mButtonLabel
				]
			);
			$htmlOut .= "\u{00A0}";
		}

		// Search button
		$htmlOut .= Xml::element( 'input',
			[
				'type' => 'submit',
				'name' => 'fulltext',
				'class' => 'mw-ui-button',
				'value' => $this->mSearchButtonLabel
			]
		);

		// Hidden fulltext param for IE (bug 17161)
		if ( $type == 'fulltext' ) {
			$htmlOut .= Html::hidden( 'fulltext', 'Search' );
		}

		$htmlOut .= Xml::closeElement( 'form' );
		$htmlOut .= Xml::closeElement( 'div' );

		// Return HTML
		return $htmlOut;
	}

	/**
	 * Generate search form version 2
	 * @return string
	 */
	public function getSearchForm2() {
		// Use button label fallbacks
		if ( !$this->mButtonLabel ) {
			$this->mButtonLabel = wfMessage( 'inputbox-tryexact' )->text();
		}

		if ( $this->mID !== '' ) {
			$unescapedID = $this->mID;
		} else {
			// The label element needs a unique id, use
			// random number to avoid multiple input boxes
			// having conflicts.
			$unescapedID = wfRandom();
		}
		$id = Sanitizer::escapeIdForAttribute( $unescapedID );
		$htmlLabel = '';
		if ( isset( $this->mLabelText ) && strlen( trim( $this->mLabelText ) ) ) {
			$htmlLabel = Xml::openElement( 'label', [ 'for' => 'bodySearchInput' . $id ] );
			$htmlLabel .= $this->mParser->recursiveTagParse( $this->mLabelText );
			$htmlLabel .= Xml::closeElement( 'label' );
		}
		$htmlOut = Xml::openElement( 'form',
			[
				'name' => 'bodySearch' . $id,
				'id' => 'bodySearch' . $id,
				'class' => 'bodySearch' . ( $this->mInline ? ' mw-inputbox-inline' : '' ),
				'action' => SpecialPage::getTitleFor( 'Search' )->getLocalUrl(),
			]
		);
		$htmlOut .= Xml::openElement( 'div',
			[
				'class' => 'bodySearchWrap' . ( $this->mInline ? ' mw-inputbox-inline' : '' ),
				'style' => $this->bgColorStyle(),
			]
		);
		$htmlOut .= $htmlLabel;

		$htmlOut .= $this->buildTextBox( [
			'type' => $this->mHidden ? 'hidden' : 'text',
			'name' => 'search',
			'class' => 'mw-ui-input mw-ui-input-inline',
			'size' => $this->mWidth,
			'id' => 'bodySearchInput' . $id,
			'dir' => $this->mDir,
			'placeholder' => $this->mPlaceholderText
		] );

		$htmlOut .= "\u{00A0}" . Xml::element( 'input',
			[
				'type' => 'submit',
				'name' => 'go',
				'value' => $this->mButtonLabel,
				'class' => 'mw-ui-button',
			]
		);

		// Better testing needed here!
		if ( !empty( $this->mFullTextButton ) ) {
			$htmlOut .= Xml::element( 'input',
				[
					'type' => 'submit',
					'name' => 'fulltext',
					'class' => 'mw-ui-button',
					'value' => $this->mSearchButtonLabel
				]
			);
		}

		$htmlOut .= Xml::closeElement( 'div' );
		$htmlOut .= Xml::closeElement( 'form' );

		// Return HTML
		return $htmlOut;
	}

	/**
	 * Generate create page form
	 * @return string
	 */
	public function getCreateForm() {
		global $wgScript;

		if ( $this->mType == "comment" ) {
			if ( !$this->mButtonLabel ) {
				$this->mButtonLabel = wfMessage( 'inputbox-postcomment' )->text();
			}
		} else {
			if ( !$this->mButtonLabel ) {
				$this->mButtonLabel = wfMessage( 'inputbox-createarticle' )->text();
			}
		}

		$htmlOut = Xml::openElement( 'div',
			[
				'class' => 'mw-inputbox-centered',
				'style' => $this->bgColorStyle(),
			]
		);
		$createBoxParams = [
			'name' => 'createbox',
			'class' => 'createbox',
			'action' => $wgScript,
			'method' => 'get'
		];
		if ( $this->mID !== '' ) {
			$createBoxParams['id'] = Sanitizer::escapeIdForAttribute( $this->mID );
		}
		$htmlOut .= Xml::openElement( 'form', $createBoxParams );
		$editArgs = $this->getEditActionArgs();
		$htmlOut .= Html::hidden( $editArgs['name'], $editArgs['value'] );
		if ( $this->mPreload !== null ) {
			$htmlOut .= Html::hidden( 'preload', $this->mPreload );
		}
		if ( is_array( $this->mPreloadparams ) ) {
			foreach ( $this->mPreloadparams as $preloadparams ) {
				$htmlOut .= Html::hidden( 'preloadparams[]', $preloadparams );
			}
		}
		if ( $this->mEditIntro !== null ) {
			$htmlOut .= Html::hidden( 'editintro', $this->mEditIntro );
		}
		if ( $this->mSummary !== null ) {
			$htmlOut .= Html::hidden( 'summary', $this->mSummary );
		}
		if ( $this->mNosummary !== null ) {
			$htmlOut .= Html::hidden( 'nosummary', $this->mNosummary );
		}
		if ( $this->mPrefix !== '' ) {
			$htmlOut .= Html::hidden( 'prefix', $this->mPrefix );
		}
		if ( $this->mMinor !== null ) {
			$htmlOut .= Html::hidden( 'minor', $this->mMinor );
		}
		if ( $this->mType == 'comment' ) {
			$htmlOut .= Html::hidden( 'section', 'new' );
		}

		$htmlOut .= $this->buildTextBox( [
			'type' => $this->mHidden ? 'hidden' : 'text',
			'name' => 'title',
			'class' => $this->getLinebreakClasses() .
				'mw-ui-input mw-ui-input-inline createboxInput',
			'value' => $this->mDefaultText,
			'placeholder' => $this->mPlaceholderText,
			'size' => $this->mWidth,
			'dir' => $this->mDir
		] );

		$htmlOut .= $this->mBR;
		$htmlOut .= Xml::openElement( 'input',
			[
				'type' => 'submit',
				'name' => 'create',
				'class' => 'mw-ui-button mw-ui-progressive createboxButton',
				'value' => $this->mButtonLabel
			]
		);
		$htmlOut .= Xml::closeElement( 'form' );
		$htmlOut .= Xml::closeElement( 'div' );

		// Return HTML
		return $htmlOut;
	}

	/**
	 * Generate move page form
	 * @return string
	 */
	public function getMoveForm() {
		global $wgScript;

		if ( !$this->mButtonLabel ) {
			$this->mButtonLabel = wfMessage( 'inputbox-movearticle' )->text();
		}

		$htmlOut = Xml::openElement( 'div',
			[
				'class' => 'mw-inputbox-centered',
				'style' => $this->bgColorStyle(),
			]
		);
		$moveBoxParams = [
			'name' => 'movebox',
			'class' => 'mw-movebox',
			'action' => $wgScript,
			'method' => 'get'
		];
		if ( $this->mID !== '' ) {
			$moveBoxParams['id'] = Sanitizer::escapeIdForAttribute( $this->mID );
		}
		$htmlOut .= Xml::openElement( 'form', $moveBoxParams );
		$htmlOut .= Html::hidden( 'title',
			SpecialPage::getTitleFor( 'Movepage', $this->mPage )->getPrefixedText() );
		$htmlOut .= Html::hidden( 'wpReason', $this->mSummary );
		$htmlOut .= Html::hidden( 'prefix', $this->mPrefix );

		$htmlOut .= $this->buildTextBox( [
			'type' => $this->mHidden ? 'hidden' : 'text',
			'name' => 'wpNewTitle',
			'class' => $this->getLinebreakClasses() . 'mw-moveboxInput mw-ui-input mw-ui-input-inline',
			'value' => $this->mDefaultText,
			'placeholder' => $this->mPlaceholderText,
			'size' => $this->mWidth,
			'dir' => $this->mDir
		] );

		$htmlOut .= $this->mBR;
		$htmlOut .= Xml::openElement( 'input',
			[
				'type' => 'submit',
				'class' => 'mw-ui-button mw-ui-progressive',
				'value' => $this->mButtonLabel
			]
		);
		$htmlOut .= Xml::closeElement( 'form' );
		$htmlOut .= Xml::closeElement( 'div' );

		// Return HTML
		return $htmlOut;
	}

	/**
	 * Generate new section form
	 * @return string
	 */
	public function getCommentForm() {
		global $wgScript;

		if ( !$this->mButtonLabel ) {
				$this->mButtonLabel = wfMessage( 'inputbox-postcommenttitle' )->text();
		}

		$htmlOut = Xml::openElement( 'div',
			[
				'class' => 'mw-inputbox-centered',
				'style' => $this->bgColorStyle(),
			]
		);
		$commentFormParams = [
			'name' => 'commentbox',
			'class' => 'commentbox',
			'action' => $wgScript,
			'method' => 'get'
		];
		if ( $this->mID !== '' ) {
			$commentFormParams['id'] = Sanitizer::escapeIdForAttribute( $this->mID );
		}
		$htmlOut .= Xml::openElement( 'form', $commentFormParams );
		$editArgs = $this->getEditActionArgs();
		$htmlOut .= Html::hidden( $editArgs['name'], $editArgs['value'] );
		if ( $this->mPreload !== null ) {
			$htmlOut .= Html::hidden( 'preload', $this->mPreload );
		}
		if ( is_array( $this->mPreloadparams ) ) {
			foreach ( $this->mPreloadparams as $preloadparams ) {
				$htmlOut .= Html::hidden( 'preloadparams[]', $preloadparams );
			}
		}
		if ( $this->mEditIntro !== null ) {
			$htmlOut .= Html::hidden( 'editintro', $this->mEditIntro );
		}

		$htmlOut .= $this->buildTextBox( [
			'type' => $this->mHidden ? 'hidden' : 'text',
			'name' => 'preloadtitle',
			'class' => $this->getLinebreakClasses() . 'commentboxInput mw-ui-input mw-ui-input-inline',
			'value' => $this->mDefaultText,
			'placeholder' => $this->mPlaceholderText,
			'size' => $this->mWidth,
			'dir' => $this->mDir
		] );

		$htmlOut .= Html::hidden( 'section', 'new' );
		$htmlOut .= Html::hidden( 'title', $this->mPage );
		$htmlOut .= $this->mBR;
		$htmlOut .= Xml::openElement( 'input',
			[
				'type' => 'submit',
				'name' => 'create',
				'class' => 'mw-ui-button mw-ui-progressive',
				'value' => $this->mButtonLabel
			]
		);
		$htmlOut .= Xml::closeElement( 'form' );
		$htmlOut .= Xml::closeElement( 'div' );

		// Return HTML
		return $htmlOut;
	}

	/**
	 * Extract options from a blob of text
	 *
	 * @param string $text Tag contents
	 */
	public function extractOptions( $text ) {
		// Parse all possible options
		$values = [];
		foreach ( explode( "\n", $text ) as $line ) {
			if ( strpos( $line, '=' ) === false ) {
				continue;
			}
			list( $name, $value ) = explode( '=', $line, 2 );
			$name = strtolower( trim( $name ) );
			$value = Sanitizer::decodeCharReferences( trim( $value ) );
			if ( $name == 'preloadparams[]' ) {
				// We have to special-case this one because it's valid for it to appear more than once.
				$this->mPreloadparams[] = $value;
			} else {
				$values[ $name ] = $value;
			}
		}

		// Validate the dir value.
		if ( isset( $values['dir'] ) && !in_array( $values['dir'], [ 'ltr', 'rtl' ] ) ) {
			unset( $values['dir'] );
		}

		// Build list of options, with local member names
		$options = [
			'type' => 'mType',
			'width' => 'mWidth',
			'preload' => 'mPreload',
			'page' => 'mPage',
			'editintro' => 'mEditIntro',
			'useve' => 'mUseVE',
			'summary' => 'mSummary',
			'nosummary' => 'mNosummary',
			'minor' => 'mMinor',
			'break' => 'mBR',
			'default' => 'mDefaultText',
			'placeholder' => 'mPlaceholderText',
			'bgcolor' => 'mBGColor',
			'buttonlabel' => 'mButtonLabel',
			'searchbuttonlabel' => 'mSearchButtonLabel',
			'fulltextbutton' => 'mFullTextButton',
			'namespaces' => 'mNamespaces',
			'labeltext' => 'mLabelText',
			'hidden' => 'mHidden',
			'id' => 'mID',
			'inline' => 'mInline',
			'prefix' => 'mPrefix',
			'dir' => 'mDir',
			'searchfilter' => 'mSearchFilter',
			'tour' => 'mTour',
			'arialabel' => 'mTextBoxAriaLabel'
		];
		// Options we should maybe run through lang converter.
		$convertOptions = [
			'default' => true,
			'buttonlabel' => true,
			'searchbuttonlabel' => true,
			'placeholder' => true,
			'arialabel' => true
		];
		foreach ( $options as $name => $var ) {
			if ( isset( $values[$name] ) ) {
				$this->$var = $values[$name];
				if ( isset( $convertOptions[$name] ) ) {
					$this->$var = $this->languageConvert( $this->$var );
				}
			}
		}

		// Insert a line break if configured to do so
		$this->mBR = ( strtolower( $this->mBR ) == "no" ) ? ' ' : '<br />';

		// Validate the width; make sure it's a valid, positive integer
		$this->mWidth = intval( $this->mWidth <= 0 ? 50 : $this->mWidth );

		// Validate background color
		if ( !$this->isValidColor( $this->mBGColor ) ) {
			$this->mBGColor = 'transparent';
		}
	}

	/**
	 * Do a security check on the bgcolor parameter
	 * @param string $color
	 * @return bool
	 */
	public function isValidColor( $color ) {
		$regex = <<<REGEX
			/^ (
				[a-zA-Z]* |       # color names
				\# [0-9a-f]{3} |  # short hexadecimal
				\# [0-9a-f]{6} |  # long hexadecimal
				rgb \s* \( \s* (
					\d+ \s* , \s* \d+ \s* , \s* \d+ |    # rgb integer
					[0-9.]+% \s* , \s* [0-9.]+% \s* , \s* [0-9.]+%   # rgb percent
				) \s* \)
			) $ /xi
REGEX;
		return (bool)preg_match( $regex, $color );
	}

	/**
	 * Factory method to help build the textbox widget
	 * @param array $defaultAttr
	 * @return string
	 */
	private function buildTextBox( $defaultAttr ) {
		if ( $this->mTextBoxAriaLabel ) {
			$defaultAttr[ 'aria-label' ] = $this->mTextBoxAriaLabel;
		}

		return Xml::openElement( 'input', $defaultAttr );
	}

	private function bgColorStyle() {
		if ( $this->mBGColor != 'transparent' ) {
			return 'background-color: ' . $this->mBGColor . ';';
		}
		return '';
	}

	/**
	 * Returns true, if the VisualEditor is requested from the inputbox wikitext definition and
	 * if the VisualEditor extension is actually installed or not, false otherwise.
	 *
	 * @return bool
	 */
	private function shouldUseVE() {
		return ExtensionRegistry::getInstance()->isLoaded( 'VisualEditor' ) && $this->mUseVE !== null;
	}

	/**
	 * For compatability with pre T119158 behaviour
	 *
	 * If a field that is going to be used as an attribute
	 * and it contains "-{" in it, run it through language
	 * converter.
	 *
	 * Its not really clear if it would make more sense to
	 * always convert instead of only if -{ is present. This
	 * function just more or less restores the previous
	 * accidental behaviour.
	 *
	 * @see https://phabricator.wikimedia.org/T180485
	 * @param string $text
	 * @return string
	 */
	private function languageConvert( $text ) {
		$lang = $this->mParser->getTargetLanguage();
		if ( $lang->hasVariants() && strpos( $text, '-{' ) !== false ) {
			$text = $lang->convert( $text );
		}
		return $text;
	}
}
