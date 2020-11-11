( function ( mw, $ ) {
	function makeDialog( initialise ) {
		var $qf = $( '#qunit-fixture' ),
			$button = $( '<div>' ).appendTo( $qf ),
			dialog = new mw.mmv.ui.OptionsDialog( $qf, $button, { setMediaViewerEnabledOnClick: $.noop } );

		if ( initialise ) {
			dialog.initPanel();
		}

		return dialog;
	}

	QUnit.module( 'mmv.ui.viewingOptions', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', function ( assert ) {
		var dialog = makeDialog();
		assert.ok( dialog, 'Dialog is created successfully' );
	} );

	QUnit.test( 'Initialisation functions', function ( assert ) {
		var dialog = makeDialog( true );

		assert.ok( dialog.$disableDiv, 'Disable div is created.' );
		assert.ok( dialog.$enableDiv, 'Enable div is created.' );
		assert.ok( dialog.$disableConfirmation, 'Disable confirmation is created.' );
		assert.ok( dialog.$enableConfirmation, 'Enable confirmation is created.' );
	} );

	QUnit.test( 'Disable', function ( assert ) {
		var $header, $icon, $text, $textHeader, $textBody,
			$submitButton, $cancelButton, $aboutLink,
			dialog = makeDialog(),
			deferred = $.Deferred();

		this.sandbox.stub( dialog.config, 'setMediaViewerEnabledOnClick', function () {
			return deferred;
		} );

		dialog.initDisableDiv();

		$header = dialog.$disableDiv.find( 'h3.mw-mmv-options-dialog-header' );
		$icon = dialog.$disableDiv.find( 'div.mw-mmv-options-icon' );

		$text = dialog.$disableDiv.find( 'div.mw-mmv-options-text' );
		$textHeader = $text.find( 'p.mw-mmv-options-text-header' );
		$textBody = $text.find( 'p.mw-mmv-options-text-body' );
		$aboutLink = $text.find( 'a.mw-mmv-project-info-link' );
		$submitButton = dialog.$disableDiv.find( 'button.mw-mmv-options-submit-button' );
		$cancelButton = dialog.$disableDiv.find( 'button.mw-mmv-options-cancel-button' );

		assert.strictEqual( $header.length, 1, 'Disable header created successfully.' );
		assert.strictEqual( $header.text(), 'Disable Media Viewer?', 'Disable header has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $icon.length, 1, 'Icon created successfully.' );
		assert.strictEqual( $icon.html(), '&nbsp;', 'Icon has a blank space in it.' );

		assert.ok( $text, 'Text div created successfully.' );
		assert.strictEqual( $textHeader.length, 1, 'Text header created successfully.' );
		assert.strictEqual( $textHeader.text(), 'Skip this viewing feature for all files.', 'Text header has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $textBody.length, 1, 'Text body created successfully.' );
		assert.strictEqual( $textBody.text(), 'You can enable it later through the file details page.', 'Text body has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $aboutLink.length, 1, 'About link created successfully.' );
		assert.strictEqual( $aboutLink.text(), 'Learn more', 'About link has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $submitButton.length, 1, 'Disable button created successfully.' );
		assert.strictEqual( $submitButton.text(), 'Disable Media Viewer', 'Disable button has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $cancelButton.length, 1, 'Cancel button created successfully.' );
		assert.strictEqual( $cancelButton.text(), 'Cancel', 'Cancel button has correct text (if this fails, it may be due to i18n differences)' );

		$submitButton.click();

		assert.ok( !dialog.$disableConfirmation.hasClass( 'mw-mmv-shown' ), 'Disable confirmation not shown yet' );
		assert.ok( !dialog.$dialog.hasClass( 'mw-mmv-disable-confirmation-shown' ), 'Disable confirmation not shown yet' );

		// Pretend that the async call in mmv.js succeeded
		deferred.resolve();

		// The confirmation should appear
		assert.ok( dialog.$disableConfirmation.hasClass( 'mw-mmv-shown' ), 'Disable confirmation shown' );
		assert.ok( dialog.$dialog.hasClass( 'mw-mmv-disable-confirmation-shown' ), 'Disable confirmation shown' );
	} );

	QUnit.test( 'Enable', function ( assert ) {
		var $header, $icon, $text, $textHeader, $aboutLink,
			$submitButton, $cancelButton,
			dialog = makeDialog(),
			deferred = $.Deferred();

		this.sandbox.stub( dialog.config, 'setMediaViewerEnabledOnClick', function () {
			return deferred;
		} );

		dialog.initEnableDiv();

		$header = dialog.$enableDiv.find( 'h3.mw-mmv-options-dialog-header' );
		$icon = dialog.$enableDiv.find( 'div.mw-mmv-options-icon' );

		$text = dialog.$enableDiv.find( 'div.mw-mmv-options-text' );
		$textHeader = $text.find( 'p.mw-mmv-options-text-header' );
		$aboutLink = $text.find( 'a.mw-mmv-project-info-link' );
		$submitButton = dialog.$enableDiv.find( 'button.mw-mmv-options-submit-button' );
		$cancelButton = dialog.$enableDiv.find( 'button.mw-mmv-options-cancel-button' );

		assert.strictEqual( $header.length, 1, 'Enable header created successfully.' );
		assert.strictEqual( $header.text(), 'Enable Media Viewer?', 'Enable header has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $icon.length, 1, 'Icon created successfully.' );
		assert.strictEqual( $icon.html(), '&nbsp;', 'Icon has a blank space in it.' );

		assert.ok( $text, 'Text div created successfully.' );
		assert.strictEqual( $textHeader.length, 1, 'Text header created successfully.' );
		assert.strictEqual( $textHeader.text(), 'Enable this media viewing feature for all files by default.', 'Text header has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $aboutLink.length, 1, 'About link created successfully.' );
		assert.strictEqual( $aboutLink.text(), 'Learn more', 'About link has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $submitButton.length, 1, 'Enable button created successfully.' );
		assert.strictEqual( $submitButton.text(), 'Enable Media Viewer', 'Enable button has correct text (if this fails, it may be due to i18n differences)' );

		assert.strictEqual( $cancelButton.length, 1, 'Cancel button created successfully.' );
		assert.strictEqual( $cancelButton.text(), 'Cancel', 'Cancel button has correct text (if this fails, it may be due to i18n differences)' );

		$submitButton.click();

		assert.ok( !dialog.$enableConfirmation.hasClass( 'mw-mmv-shown' ), 'Enable confirmation not shown yet' );
		assert.ok( !dialog.$dialog.hasClass( 'mw-mmv-enable-confirmation-shown' ), 'Enable confirmation not shown yet' );

		// Pretend that the async call in mmv.js succeeded
		deferred.resolve();

		// The confirmation should appear
		assert.ok( dialog.$enableConfirmation.hasClass( 'mw-mmv-shown' ), 'Enable confirmation shown' );
		assert.ok( dialog.$dialog.hasClass( 'mw-mmv-enable-confirmation-shown' ), 'Enable confirmation shown' );
	} );
}( mediaWiki, jQuery ) );
