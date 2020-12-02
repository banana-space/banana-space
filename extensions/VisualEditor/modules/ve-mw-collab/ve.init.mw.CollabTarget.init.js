/*!
 * VisualEditor MediaWiki CollabTarget init.
 *
 * @copyright 2011-2020 VisualEditor Team and others; see AUTHORS.txt
 * @license The MIT License (MIT); see LICENSE.txt
 */

/* eslint-disable no-jquery/no-global-selector */

( function () {
	var target,
		$specialTab = $( '#ca-nstab-special' ),
		$padTab = $( '#ca-pad' ),
		conf = mw.config.get( 'wgVisualEditorConfig' ),
		pageName = mw.config.get( 'collabPadPageName' ) || '',
		pageTitle = mw.Title.newFromText( pageName ),
		modules = [ OO.ui.isMobile() ? 'ext.visualEditor.collabTarget.mobile' : 'ext.visualEditor.collabTarget.desktop' ]
			// Add modules from $wgVisualEditorPluginModules
			.concat( conf.pluginModules.filter( mw.loader.getState ) ),
		modulePromise = mw.loader.using( modules ),
		progressBar = OO.ui.infuse( $( '.ve-init-mw-collabTarget-loading' ) ),
		documentNameInput = OO.ui.infuse( $( '.ve-init-mw-collabTarget-nameInput' ) ),
		documentNameButton = OO.ui.infuse( $( '.ve-init-mw-collabTarget-nameButton' ) ),
		importInput = OO.ui.infuse( $( '.ve-init-mw-collabTarget-importInput' ), {
			showImages: mw.config.get( 'wgVisualEditorConfig' ).usePageImages,
			showDescriptions: mw.config.get( 'wgVisualEditorConfig' ).usePageDescriptions
		} ),
		importButton = OO.ui.infuse( $( '.ve-init-mw-collabTarget-importButton' ) ),
		// Infuse the form last to avoid recursive infusion with no config
		form = OO.ui.infuse( $( '.ve-init-mw-collabTarget-form' ) );

	if ( !VisualEditorSupportCheck() ) {
		// VE not supported - say something?
		return;
	}

	function setTitle( title ) {
		$( '#firstHeading' ).text( title );
		document.title = title;
	}

	function showPage( title, importTitle ) {
		var specialTitle = mw.Title.newFromText( 'Special:CollabPad/' + title.toString() );

		setTitle( mw.msg( 'collabpad-doctitle', title.getPrefixedText() ) );

		mw.config.set( 'wgRelevantPageName', specialTitle.getPrefixedText() );
		mw.config.set( 'wgPageName', specialTitle.getPrefixedText() );
		if ( !$padTab.length ) {
			$padTab = $( '<li>' ).attr( 'id', 'ca-pad' ).addClass( 'selected' ).append(
				$( '<span>' ).append(
					$( '<a>' ).attr( 'href', '' ).text( title.getPrefixedText() )
				)
			);
		}
		$padTab.insertAfter( $specialTab.removeClass( 'selected' ) );

		progressBar.toggle( true );
		form.toggle( false );

		modulePromise.done( function () {
			var dummySurface, surfaceModel,
				isNewAuthor = !ve.init.platform.sessionStorage.get( 've-collab-author' ),
				username = mw.user.getName(),
				progressDeferred = ve.createDeferred();

			target = ve.init.mw.targetFactory.create( 'collab', title, conf.rebaserUrl, { importTitle: importTitle } );

			$( 'body' ).addClass( 've-activated ve-active' );

			$( '#content' ).prepend( target.$element );

			target.transformPage();
			$( '#firstHeading' ).addClass( 've-init-mw-desktopArticleTarget-uneditableContent' );

			// Add a dummy surface while the doc is loading
			dummySurface = target.addSurface( ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '' ) ) );
			dummySurface.setReadOnly( true );

			// TODO: Create the correct model surface type (ve.ui.Surface#createModel)
			surfaceModel = new ve.dm.Surface( ve.dm.converter.getModelFromDom( ve.createDocumentFromHtml( '' ) ) );
			surfaceModel.createSynchronizer(
				mw.config.get( 'wgWikiID' ) + '/' + title.toString(),
				{
					server: conf.rebaserUrl,
					// TODO: server could communicate with MW (via oauth?) to know the
					// current-user's name. Disable changing name if logged in?
					// Communicate an I-am-a-valid-user flag to other clients?
					defaultName: username
				}
			);

			dummySurface.createProgress( progressDeferred.promise(), ve.msg( 'visualeditor-rebase-client-connecting' ), true );

			surfaceModel.synchronizer.once( 'initDoc', function ( error ) {
				var initPromise;

				progressDeferred.resolve();
				// Resolving the progress bar doesn't close the window in this cycle,
				// so wait until we call clearSurfaces which destroys the window manager.
				setTimeout( function () {
					target.clearSurfaces();
					// Don't add the surface until the history has been applied
					target.addSurface( surfaceModel );
					if ( error ) {
						OO.ui.alert(
							$( '<p>' ).append(
								ve.htmlMsg( 'visualeditor-corrupted-document-error', $( '<pre>' ).text( error.stack ) )
							),
							{ title: ve.msg( 'visualeditor-corrupted-document-title' ), size: 'large' }
						).then( function () {
							// eslint-disable-next-line no-use-before-define
							showForm( true );
						} );
						return;
					}
					target.once( 'surfaceReady', function () {
						initPromise.then( function () {
							surfaceModel.selectFirstContentOffset();
							// For new anon users, open the author list so they can set their name
							if ( isNewAuthor && !username ) {
								target.actionsToolbar.tools.authorList.onSelect();
							}
						} );
					} );

					if ( target.importTitle && !surfaceModel.getDocument().getCompleteHistoryLength() ) {
						initPromise = mw.libs.ve.targetLoader.requestParsoidData( target.importTitle.toString(), { targetName: 'collabpad' } ).then( function ( response ) {
							var doc, dmDoc, fragment,
								data = response.visualeditor;

							if ( data && data.content ) {
								doc = target.constructor.static.parseDocument( data.content );
								dmDoc = target.constructor.static.createModelFromDom( doc );
								fragment = surfaceModel.getLinearFragment( new ve.Range( 0, 2 ) );
								fragment.insertDocument( dmDoc );

								target.etag = data.etag;
								target.baseTimeStamp = data.basetimestamp;
								target.startTimeStamp = data.starttimestamp;
								target.revid = data.oldid;

								// Store the document metadata as a hidden meta item
								fragment.collapseToEnd().insertContent( [
									{
										type: 'alienMeta',
										attributes: {
											importedDocument: {
												title: target.importTitle.toString(),
												etag: target.etag,
												baseTimeStamp: target.baseTimeStamp,
												startTimeStamp: target.startTimeStamp,
												revid: target.revid
											}
										}
									},
									{ type: '/alienMeta' }
								] );
							} else {
								// Import failed
								return ve.createDeferred().reject( 'No content for ' + target.importTitle ).promise();
							}
						} );
					} else {
						// No import, or history already exists
						initPromise = ve.createDeferred().resolve().promise();

						// Look for import metadata in document
						surfaceModel = target.getSurface().getModel();
						surfaceModel.getMetaList().getItemsInGroup( 'misc' ).some( function ( item ) {
							var importedDocument = item.getAttribute( 'importedDocument' );
							if ( importedDocument ) {
								target.importTitle = mw.Title.newFromText( importedDocument.title );
								target.etag = importedDocument.etag;
								target.baseTimeStamp = importedDocument.baseTimeStamp;
								target.startTimeStamp = importedDocument.startTimeStamp;
								target.revid = importedDocument.revid;
								return true;
							}
							return false;
						} );
					}
					initPromise.fail( function ( err ) {
						setTimeout( function () {
							throw new Error( err );
						} );
					} );
					initPromise.always( function () {
						progressDeferred.resolve();
					} );
				} );
			} );

		} ).always( function () {
			form.toggle( false );
			progressBar.toggle( false );
		} ).fail( function ( err ) {
			mw.log.error( err );
			// eslint-disable-next-line no-use-before-define
			showForm( true );
		} );
	}

	function showForm( pushState ) {
		var specialTitle = mw.Title.newFromText( 'Special:CollabPad' );

		if ( pushState ) {
			history.pushState( { tag: 'collabTarget' }, mw.msg( 'collabpad' ), specialTitle.getUrl() );
		}

		if ( target ) {
			$( '#firstHeading' ).removeClass( 've-init-mw-desktopArticleTarget-uneditableContent' );
			target.restorePage();
			target.destroy();

			$( 'body' ).removeClass( 've-activated ve-active' );
		}

		setTitle( mw.msg( 'collabpad' ) );
		mw.config.set( 'wgRelevantPageName', specialTitle.getPrefixedText() );
		mw.config.set( 'wgPageName', specialTitle.getPrefixedText() );
		if ( $padTab ) {
			$padTab.detach();
		}
		$specialTab.addClass( 'selected' );

		progressBar.toggle( false );
		form.toggle( true );
	}

	function loadTitle( title, importTitle ) {
		var specialTitle = mw.Title.newFromText( 'Special:CollabPad/' + title.toString() );
		if ( history.pushState ) {
			// TODO: Handle popstate
			history.pushState( { tag: 'collabTarget', title: title.toString() }, title.getMain(), specialTitle.getUrl() );
			showPage( title, importTitle );
		} else {
			location.href = specialTitle.getUrl();
		}
	}

	function getRandomTitle() {
		return Math.random().toString( 36 ).slice( 2 );
	}

	function onNameChange() {
		documentNameInput.getValidity().then( function () {
			documentNameButton.setDisabled( false );
		}, function () {
			documentNameButton.setDisabled( true );
		} );
	}

	function loadFromName() {
		documentNameInput.getValidity().then( function () {
			var title = mw.Title.newFromText(
				documentNameInput.getValue().trim() || getRandomTitle()
			);

			if ( title ) {
				loadTitle( title );
			} else {
				documentNameInput.focus();
			}
		} );
	}

	documentNameInput.setValidation( function ( value ) {
		// Empty input will create a random document name, otherwise must be valid
		return value === '' || !!mw.Title.newFromText( value );
	} );
	documentNameButton.setDisabled( false );

	documentNameInput.on( 'change', onNameChange );
	documentNameInput.on( 'enter', loadFromName );
	documentNameButton.on( 'click', loadFromName );
	onNameChange();

	function onImportChange() {
		importInput.getValidity().then( function () {
			importButton.setDisabled( false );
		}, function () {
			importButton.setDisabled( true );
		} );
	}

	function importTitle() {
		importInput.getValidity().then( function () {
			var title = mw.Title.newFromText( importInput.getValue().trim() );

			if ( title ) {
				loadTitle( mw.Title.newFromText( getRandomTitle() ), title );
			} else {
				documentNameInput.focus();
			}
		} );
	}

	importInput.setValidation( function ( value ) {
		// TODO: Check page exists?
		return !!mw.Title.newFromText( value );
	} );
	importInput.on( 'change', onImportChange );
	importInput.on( 'enter', importTitle );
	importButton.on( 'click', importTitle );
	onImportChange();

	if ( pageTitle ) {
		showPage( pageTitle );
	} else {
		showForm();
	}

	$specialTab.on( 'click', function ( e ) {
		showForm( true );
		e.preventDefault();
	} );

	// Tag current state
	if ( history.replaceState ) {
		history.replaceState( { tag: 'collabTarget', title: pageName }, document.title, location.href );
	}
	window.addEventListener( 'popstate', function ( e ) {
		if ( e.state && e.state.tag === 'collabTarget' ) {
			if ( e.state.title ) {
				showPage( mw.Title.newFromText( e.state.title ) );
			} else {
				showForm();
			}
		}
	} );
}() );
