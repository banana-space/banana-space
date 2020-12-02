/*!
 * VisualEditor MediaWiki DiffPage init.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */

( function () {
	var reviewModeButtonSelect, diffElement, lastDiff,
		$wikitextDiffContainer, $wikitextDiffHeader, $wikitextDiffBody,
		$visualDiffContainer = $( '<div>' ),
		$visualDiff = $( '<div>' ),
		progress = new OO.ui.ProgressBarWidget( { classes: [ 've-init-mw-diffPage-loading' ] } ),
		uri = new mw.Uri(),
		mode = uri.query.diffmode || mw.user.options.get( 'visualeditor-diffmode-historical' ) || 'source',
		conf = mw.config.get( 'wgVisualEditorConfig' ),
		pluginModules = conf.pluginModules.filter( mw.loader.getState );

	if ( mode !== 'visual' ) {
		// Enforce a valid mode, to avoid visual glitches in button-selection.
		mode = 'source';
	}

	$visualDiffContainer.append(
		progress.$element.addClass( 'oo-ui-element-hidden' ),
		$visualDiff
	);

	function onReviewModeButtonSelectSelect( item ) {
		var modulePromise, oldPageName, newPageName, isVisual,
			$revSlider = $( '.mw-revslider-container' ),
			oldId = mw.config.get( 'wgDiffOldId' ),
			newId = mw.config.get( 'wgDiffNewId' );

		if ( mw.config.get( 'wgCanonicalSpecialPageName' ) !== 'ComparePages' ) {
			oldPageName = newPageName = mw.config.get( 'wgRelevantPageName' );
		} else {
			oldPageName = uri.query.page1;
			newPageName = uri.query.page2;
		}

		mode = item.getData();
		isVisual = mode === 'visual';

		mw.user.options.set( 'visualeditor-diffmode-historical', mode );
		// Same as ve.init.target.getLocalApi()
		new mw.Api().saveOption( 'visualeditor-diffmode-historical', mode );
		$visualDiffContainer.toggleClass( 'oo-ui-element-hidden', !isVisual );
		$wikitextDiffBody.toggleClass( 'oo-ui-element-hidden', isVisual );
		$revSlider.toggleClass( 've-init-mw-diffPage-revSlider-visual', isVisual );
		if ( isVisual ) {
			// Highlight the headers using the same styles as the diff, to better indicate
			// the meaning of headers when not using two-column diff.
			$wikitextDiffHeader.find( '#mw-diff-otitle1' ).attr( 'data-diff-action', 'remove' );
			$wikitextDiffHeader.find( '#mw-diff-ntitle1' ).attr( 'data-diff-action', 'insert' );
		} else {
			$wikitextDiffHeader.find( '#mw-diff-otitle1' ).removeAttr( 'data-diff-action' );
			$wikitextDiffHeader.find( '#mw-diff-ntitle1' ).removeAttr( 'data-diff-action' );
		}

		if ( isVisual && !(
			lastDiff && lastDiff.oldId === oldId && lastDiff.newId === newId &&
			lastDiff.oldPageName === oldPageName && lastDiff.newPageName === newPageName
		) ) {
			$visualDiff.empty();
			progress.$element.removeClass( 'oo-ui-element-hidden' );
			// TODO: Load a smaller subset of VE for computing the visual diff
			modulePromise = mw.loader.using( [ 'ext.visualEditor.articleTarget' ].concat( pluginModules ) );
			mw.libs.ve.diffLoader.getVisualDiffGeneratorPromise( oldId, newId, modulePromise, oldPageName, newPageName ).then( function ( visualDiffGenerator ) {
				// This class is loaded via modulePromise above
				// eslint-disable-next-line no-undef
				diffElement = new ve.ui.DiffElement( visualDiffGenerator(), { classes: [ 've-init-mw-diffPage-diff' ] } );
				diffElement.$document.addClass( 'mw-parser-output' );

				progress.$element.addClass( 'oo-ui-element-hidden' );
				$visualDiff.append( diffElement.$element );
				lastDiff = {
					oldId: oldId,
					newId: newId,
					oldPageName: oldPageName,
					newPageName: newPageName
				};

				diffElement.positionDescriptions();
			} );
		}

		if ( history.replaceState ) {
			uri.query.diffmode = mode;
			history.replaceState( '', document.title, uri );
		}

	}

	mw.hook( 'wikipage.diff' ).add( function () {
		$wikitextDiffContainer = $( 'table.diff[data-mw="interface"]' );
		$wikitextDiffHeader = $wikitextDiffContainer.find( 'tr.diff-title' )
			.add( $wikitextDiffContainer.find( 'td.diff-multi, td.diff-notice' ).parent() );
		$wikitextDiffBody = $wikitextDiffContainer.find( 'tr' ).not( $wikitextDiffHeader );
		$wikitextDiffContainer.after( $visualDiffContainer );

		// The PHP widget was a ButtonGroupWidget, so replace with a
		// ButtonSelectWidget instead of infusing.
		reviewModeButtonSelect = new OO.ui.ButtonSelectWidget( {
			items: [
				new OO.ui.ButtonOptionWidget( { data: 'visual', icon: 'eye', label: mw.msg( 'visualeditor-savedialog-review-visual' ) } ),
				new OO.ui.ButtonOptionWidget( { data: 'source', icon: 'wikiText', label: mw.msg( 'visualeditor-savedialog-review-wikitext' ) } )
			]
		} );
		reviewModeButtonSelect.on( 'select', onReviewModeButtonSelectSelect );
		$( '.ve-init-mw-diffPage-diffMode' ).empty().append( reviewModeButtonSelect.$element );
		reviewModeButtonSelect.selectItemByData( mode );
	} );
}() );
