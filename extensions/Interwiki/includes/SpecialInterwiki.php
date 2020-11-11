<?php
/**
 * Implements Special:Interwiki
 * @ingroup SpecialPage
 */
class SpecialInterwiki extends SpecialPage {
	/**
	 * Constructor - sets up the new special page
	 */
	public function __construct() {
		parent::__construct( 'Interwiki' );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Different description will be shown on Special:SpecialPage depending on
	 * whether the user can modify the data.
	 * @return String
	 */
	function getDescription() {
		return $this->msg( $this->canModify() ?
			'interwiki' : 'interwiki-title-norights' )->plain();
	}

	public function getSubpagesForPrefixSearch() {
		// delete, edit both require the prefix parameter.
		return [ 'add' ];
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par parameter passed to the page or null
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();

		$out = $this->getOutput();
		$request = $this->getRequest();

		$out->addModuleStyles( 'ext.interwiki.specialpage' );

		$action = $par ?: $request->getVal( 'action', $par );
		$return = $this->getPageTitle();

		switch ( $action ) {
		case 'delete':
		case 'edit':
		case 'add':
			if ( $this->canModify( $out ) ) {
				$this->showForm( $action );
			}
			$out->returnToMain( false, $return );
			break;
		case 'submit':
			if ( !$this->canModify( $out ) ) {
				// Error msg added by canModify()
			} elseif ( !$request->wasPosted() ||
				!$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) )
			) {
				// Prevent cross-site request forgeries
				$out->addWikiMsg( 'sessionfailure' );
			} else {
				$this->doSubmit();
			}
			$out->returnToMain( false, $return );
			break;
		default:
			$this->showList();
			break;
		}
	}

	/**
	 * Returns boolean whether the user can modify the data.
	 * @param OutputPage|bool $out If $wgOut object given, it adds the respective error message.
	 * @throws PermissionsError|ReadOnlyError
	 * @return bool
	 */
	public function canModify( $out = false ) {
		global $wgInterwikiCache;
		if ( !$this->getUser()->isAllowed( 'interwiki' ) ) {
			// Check permissions
			if ( $out ) {
				throw new PermissionsError( 'interwiki' );
			}

			return false;
		} elseif ( $wgInterwikiCache ) {
			// Editing the interwiki cache is not supported
			if ( $out ) {
				$out->addWikiMsg( 'interwiki-cached' );
			}

			return false;
		} elseif ( wfReadOnly() ) {
			throw new ReadOnlyError;
		}

		return true;
	}

	/**
	 * @param string $action The action of the form
	 */
	protected function showForm( $action ) {
		$request = $this->getRequest();

		$prefix = $request->getVal( 'prefix' );
		$wpPrefix = '';
		$label = [ 'class' => 'mw-label' ];
		$input = [ 'class' => 'mw-input' ];

		if ( $action === 'delete' ) {
			$topmessage = $this->msg( 'interwiki_delquestion', $prefix )->text();
			$intromessage = $this->msg( 'interwiki_deleting', $prefix )->escaped();
			$wpPrefix = Html::hidden( 'wpInterwikiPrefix', $prefix );
			$button = 'delete';
			$formContent = '';
		} elseif ( $action === 'edit' ) {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->selectRow( 'interwiki', '*', [ 'iw_prefix' => $prefix ], __METHOD__ );

			if ( !$row ) {
				$this->error( 'interwiki_editerror', $prefix );
				return;
			}

			$prefix = $prefixElement = $row->iw_prefix;
			$defaulturl = $row->iw_url;
			$trans = $row->iw_trans;
			$local = $row->iw_local;
			$wpPrefix = Html::hidden( 'wpInterwikiPrefix', $row->iw_prefix );
			$topmessage = $this->msg( 'interwiki_edittext' )->text();
			$intromessage = $this->msg( 'interwiki_editintro' )->escaped();
			$button = 'edit';
		} elseif ( $action === 'add' ) {
			$prefix = $request->getVal( 'wpInterwikiPrefix', $request->getVal( 'prefix' ) );
			$prefixElement = Xml::input( 'wpInterwikiPrefix', 20, $prefix,
				[ 'tabindex' => 1, 'id' => 'mw-interwiki-prefix', 'maxlength' => 20 ] );
			$local = $request->getCheck( 'wpInterwikiLocal' );
			$trans = $request->getCheck( 'wpInterwikiTrans' );
			$defaulturl = $request->getVal( 'wpInterwikiURL', $this->msg( 'interwiki-defaulturl' )->text() );
			$topmessage = $this->msg( 'interwiki_addtext' )->text();
			$intromessage = $this->msg( 'interwiki_addintro' )->escaped();
			$button = 'interwiki_addbutton';
		}

		if ( $action === 'add' || $action === 'edit' ) {
			$formContent = Html::rawElement( 'tr', null,
				Html::element( 'td', $label, $this->msg( 'interwiki-prefix-label' )->text() ) .
				Html::rawElement( 'td', null, '<code>' . $prefixElement . '</code>' )
			) . Html::rawElement(
				'tr',
				null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-local-label' )->text(), 'mw-interwiki-local' )
				) .
				Html::rawElement(
					'td',
					$input,
					Xml::check( 'wpInterwikiLocal', $local, [ 'id' => 'mw-interwiki-local' ] )
				)
			) . Html::rawElement( 'tr', null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-trans-label' )->text(), 'mw-interwiki-trans' )
				) .
				Html::rawElement(
					'td',
					$input,  Xml::check( 'wpInterwikiTrans', $trans, [ 'id' => 'mw-interwiki-trans' ] ) )
			) . Html::rawElement( 'tr', null,
				Html::rawElement(
					'td',
					$label,
					Xml::label( $this->msg( 'interwiki-url-label' )->text(), 'mw-interwiki-url' )
				) .
				Html::rawElement( 'td', $input, Xml::input( 'wpInterwikiURL', 60, $defaulturl,
					[ 'tabindex' => 1, 'maxlength' => 200, 'id' => 'mw-interwiki-url' ] ) )
			);
		}

		$form = Xml::fieldset( $topmessage, Html::rawElement(
			'form',
			[
				'id' => "mw-interwiki-{$action}form",
				'method' => 'post',
				'action' => $this->getPageTitle()->getLocalURL( [
					'action' => 'submit',
					'prefix' => $prefix
				] )
			],
			Html::rawElement( 'p', null, $intromessage ) .
			Html::rawElement( 'table', [ 'id' => "mw-interwiki-{$action}" ],
				$formContent . Html::rawElement( 'tr', null,
					Html::rawElement( 'td', $label, Xml::label( $this->msg( 'interwiki_reasonfield' )->text(),
						"mw-interwiki-{$action}reason" ) ) .
					Html::rawElement( 'td', $input, Xml::input( 'wpInterwikiReason', 60, '',
						[ 'tabindex' => 1, 'id' => "mw-interwiki-{$action}reason", 'maxlength' => 200 ] ) )
				) . Html::rawElement( 'tr', null,
					Html::rawElement( 'td', null, '' ) .
					Html::rawElement( 'td', [ 'class' => 'mw-submit' ],
						Xml::submitButton( $this->msg( $button )->text(), [ 'id' => 'mw-interwiki-submit' ] ) )
				) . $wpPrefix .
				Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
				Html::hidden( 'wpInterwikiAction', $action )
			)
		) );
		$this->getOutput()->addHTML( $form );
	}

	protected function doSubmit() {
		global $wgContLang;

		$request = $this->getRequest();
		$prefix = $request->getVal( 'wpInterwikiPrefix' );
		$do = $request->getVal( 'wpInterwikiAction' );
		// Show an error if the prefix is invalid (only when adding one).
		// Invalid characters for a title should also be invalid for a prefix.
		// Whitespace, ':', '&' and '=' are invalid, too.
		// (Bug 30599).
		global $wgLegalTitleChars;
		$validPrefixChars = preg_replace( '/[ :&=]/', '', $wgLegalTitleChars );
		if ( $do === 'add' && preg_match( "/\s|[^$validPrefixChars]/", $prefix ) ) {
			$this->error( 'interwiki-badprefix', htmlspecialchars( $prefix ) );
			$this->showForm( $do );
			return;
		}
		$reason = $request->getText( 'wpInterwikiReason' );
		$selfTitle = $this->getPageTitle();
		$dbw = wfGetDB( DB_MASTER );
		switch ( $do ) {
		case 'delete':
			$dbw->delete( 'interwiki', [ 'iw_prefix' => $prefix ], __METHOD__ );

			if ( $dbw->affectedRows() === 0 ) {
				$this->error( 'interwiki_delfailed', $prefix );
				$this->showForm( $do );
			} else {
				$this->getOutput()->addWikiMsg( 'interwiki_deleted', $prefix );
				$log = new LogPage( 'interwiki' );
				$log->addEntry( 'iw_delete', $selfTitle, $reason, [ $prefix ] );
				Interwiki::invalidateCache( $prefix );
			}
			break;
		/** @noinspection PhpMissingBreakStatementInspection */
		case 'add':
			$prefix = $wgContLang->lc( $prefix );
		case 'edit':
			$theurl = $request->getVal( 'wpInterwikiURL' );
			$local = $request->getCheck( 'wpInterwikiLocal' ) ? 1 : 0;
			$trans = $request->getCheck( 'wpInterwikiTrans' ) ? 1 : 0;
			$data = [
				'iw_prefix' => $prefix,
				'iw_url' => $theurl,
				'iw_local' => $local,
				'iw_trans' => $trans
			];

			if ( $prefix === '' || $theurl === '' ) {
				$this->error( 'interwiki-submit-empty' );
				$this->showForm( $do );
				return;
			}

			// Simple URL validation: check that the protocol is one of
			// the supported protocols for this wiki.
			// (bug 30600)
			if ( !wfParseUrl( $theurl ) ) {
				$this->error( 'interwiki-submit-invalidurl' );
				$this->showForm( $do );
				return;
			}

			if ( $do === 'add' ) {
				$dbw->insert( 'interwiki', $data, __METHOD__, 'IGNORE' );
			} else { // $do === 'edit'
				$dbw->update( 'interwiki', $data, [ 'iw_prefix' => $prefix ], __METHOD__, 'IGNORE' );
			}

			// used here: interwiki_addfailed, interwiki_added, interwiki_edited
			if ( $dbw->affectedRows() === 0 ) {
				$this->error( "interwiki_{$do}failed", $prefix );
				$this->showForm( $do );
			} else {
				$this->getOutput()->addWikiMsg( "interwiki_{$do}ed", $prefix );
				$log = new LogPage( 'interwiki' );
				$log->addEntry( 'iw_' . $do, $selfTitle, $reason, [ $prefix, $theurl, $trans, $local ] );
				Interwiki::invalidateCache( $prefix );
			}
			break;
		}
	}

	protected function showList() {
		global $wgInterwikiCentralDB, $wgInterwikiViewOnly;
		$canModify = $this->canModify();

		// Build lists
		if ( !method_exists( 'Interwiki', 'getAllPrefixes' ) ) {
			// version 2.0 is not backwards compatible (but will still display a nice error)
			$this->error( 'interwiki_error' );
			return;
		}
		$iwPrefixes = Interwiki::getAllPrefixes( null );
		$iwGlobalPrefixes = [];
		if ( $wgInterwikiCentralDB !== null && $wgInterwikiCentralDB !== wfWikiID() ) {
			// Fetch list from global table
			$dbrCentralDB = wfGetDB( DB_REPLICA, [], $wgInterwikiCentralDB );
			$res = $dbrCentralDB->select( 'interwiki', '*', false, __METHOD__ );
			$retval = [];
			foreach ( $res as $row ) {
				$row = (array)$row;
				if ( !Language::fetchLanguageName( $row['iw_prefix'] ) ) {
					$retval[] = $row;
				}
			}
			$iwGlobalPrefixes = $retval;
		}

		// Split out language links
		$iwLocalPrefixes = [];
		$iwLanguagePrefixes = [];
		foreach ( $iwPrefixes as $iwPrefix ) {
			if ( Language::fetchLanguageName( $iwPrefix['iw_prefix'] ) ) {
				$iwLanguagePrefixes[] = $iwPrefix;
			} else {
				$iwLocalPrefixes[] = $iwPrefix;
			}
		}

		// Page intro content
		$this->getOutput()->addWikiMsg( 'interwiki_intro' );

		// Add 'view log' link when possible
		if ( $wgInterwikiViewOnly === false ) {
			$logLink = Linker::link(
				SpecialPage::getTitleFor( 'Log', 'interwiki' ),
				$this->msg( 'interwiki-logtext' )->escaped()
			);
			$this->getOutput()->addHTML( '<p class="mw-interwiki-log">' . $logLink . '</p>' );
		}

		// Add 'add' link
		if ( $canModify ) {
			if ( count( $iwGlobalPrefixes ) !== 0 ) {
				$addtext = $this->msg( 'interwiki-addtext-local' )->escaped();
			} else {
				$addtext = $this->msg( 'interwiki_addtext' )->escaped();
			}
			$addlink = Linker::linkKnown( $this->getPageTitle( 'add' ), $addtext );
			$this->getOutput()->addHTML( '<p class="mw-interwiki-addlink">' . $addlink . '</p>' );
		}

		$this->getOutput()->addWikiMsg( 'interwiki-legend' );

		if ( ( !is_array( $iwPrefixes ) || count( $iwPrefixes ) === 0 ) &&
			( !is_array( $iwGlobalPrefixes ) || count( $iwGlobalPrefixes ) === 0 )
		) {
			// If the interwiki table(s) are empty, display an error message
			$this->error( 'interwiki_error' );
			return;
		}

		// Add the global table
		if ( count( $iwGlobalPrefixes ) !== 0 ) {
			$this->getOutput()->addHTML(
				'<h2 id="interwikitable-global">' .
				$this->msg( 'interwiki-global-links' )->parse() .
				'</h2>'
			);
			$this->getOutput()->addWikiMsg( 'interwiki-global-description' );

			// $canModify is false here because this is just a display of remote data
			$this->makeTable( false, $iwGlobalPrefixes );
		}

		// Add the local table
		if ( count( $iwLocalPrefixes ) !== 0 ) {
			if ( count( $iwGlobalPrefixes ) !== 0 ) {
				$this->getOutput()->addHTML(
					'<h2 id="interwikitable-local">' .
					$this->msg( 'interwiki-local-links' )->parse() .
					'</h2>'
				);
				$this->getOutput()->addWikiMsg( 'interwiki-local-description' );
			} else {
				$this->getOutput()->addHTML(
					'<h2 id="interwikitable-local">' .
					$this->msg( 'interwiki-links' )->parse() .
					'</h2>'
				);
				$this->getOutput()->addWikiMsg( 'interwiki-description' );
			}
			$this->makeTable( $canModify, $iwLocalPrefixes );
		}

		// Add the language table
		if ( count( $iwLanguagePrefixes ) !== 0 ) {
			$this->getOutput()->addHTML(
				'<h2 id="interwikitable-language">' .
				$this->msg( 'interwiki-language-links' )->parse() .
				'</h2>'
			);
			$this->getOutput()->addWikiMsg( 'interwiki-language-description' );

			$this->makeTable( $canModify, $iwLanguagePrefixes );
		}
	}

	protected function makeTable( $canModify, $iwPrefixes ) {
		// Output the existing Interwiki prefixes table header
		$out = '';
		$out .=	Html::openElement(
			'table',
			[ 'class' => 'mw-interwikitable wikitable sortable body' ]
		) . "\n";
		$out .= Html::openElement( 'tr', [ 'class' => 'interwikitable-header' ] ) .
			Html::element( 'th', null, $this->msg( 'interwiki_prefix' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_url' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_local' )->text() ) .
			Html::element( 'th', null, $this->msg( 'interwiki_trans' )->text() ) .
			( $canModify ?
				Html::element(
					'th',
					[ 'class' => 'unsortable' ],
					$this->msg( 'interwiki_edit' )->text()
				) :
				''
			);
		$out .= Html::closeElement( 'tr' ) . "\n";

		$selfTitle = $this->getPageTitle();

		// Output the existing Interwiki prefixes table rows
		foreach ( $iwPrefixes as $iwPrefix ) {
			$out .= Html::openElement( 'tr', [ 'class' => 'mw-interwikitable-row' ] );
			$out .= Html::element( 'td', [ 'class' => 'mw-interwikitable-prefix' ],
				$iwPrefix['iw_prefix'] );
			$out .= Html::element(
				'td',
				[ 'class' => 'mw-interwikitable-url' ],
				$iwPrefix['iw_url']
			);
			$attribs = [ 'class' => 'mw-interwikitable-local' ];
			// Green background for cells with "yes".
			if ( isset( $iwPrefix['iw_local'] ) && $iwPrefix['iw_local'] ) {
				$attribs['class'] .= ' mw-interwikitable-local-yes';
			}
			// The messages interwiki_0 and interwiki_1 are used here.
			$contents = isset( $iwPrefix['iw_local'] ) ?
				$this->msg( 'interwiki_' . $iwPrefix['iw_local'] )->text() :
				'-';
			$out .= Html::element( 'td', $attribs, $contents );
			$attribs = [ 'class' => 'mw-interwikitable-trans' ];
			// Green background for cells with "yes".
			if ( isset( $iwPrefix['iw_trans'] ) && $iwPrefix['iw_trans'] ) {
				$attribs['class'] .= ' mw-interwikitable-trans-yes';
			}
			// The messages interwiki_0 and interwiki_1 are used here.
			$contents = isset( $iwPrefix['iw_trans'] ) ?
				$this->msg( 'interwiki_' . $iwPrefix['iw_trans'] )->text() :
				'-';
			$out .= Html::element( 'td', $attribs, $contents );

			// Additional column when the interwiki table can be modified.
			if ( $canModify ) {
				$out .= Html::rawElement( 'td', [ 'class' => 'mw-interwikitable-modify' ],
					Linker::linkKnown( $selfTitle, $this->msg( 'edit' )->escaped(), [],
						[ 'action' => 'edit', 'prefix' => $iwPrefix['iw_prefix'] ] ) .
					$this->msg( 'comma-separator' ) .
					Linker::linkKnown( $selfTitle, $this->msg( 'delete' )->escaped(), [],
						[ 'action' => 'delete', 'prefix' => $iwPrefix['iw_prefix'] ] )
				);
			}
			$out .= Html::closeElement( 'tr' ) . "\n";
		}
		$out .= Html::closeElement( 'table' );

		$this->getOutput()->addHTML( $out );
	}

	protected function error() {
		$args = func_get_args();
		$this->getOutput()->wrapWikiMsg( "<p class='error'>$1</p>", $args );
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
