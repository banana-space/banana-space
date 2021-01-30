( function () {
	QUnit.module( 'ext.flow: mediawiki.ui.enhance' );

	QUnit.test( 'Forms with required fields have certain buttons disabled by default', function ( assert ) {
		var forms = [
			$( '<form><input class="mw-ui-input" required><button data-role="action" class="mw-ui-button">go</button></form>' ),
			$( '<form><input class="mw-ui-input" required><button data-role="submit" class="mw-ui-button">go</button></form>' ),
			$( '<form><textarea class="mw-ui-input"></textarea><input class="mw-ui-input"><button data-role="submit" class="mw-ui-button">go</button></form>' ),
			$( '<form><textarea class="mw-ui-input" required></textarea><button data-role="submit" class="mw-ui-button">go</button></form>' ),
			$( '<form><textarea class="mw-ui-input" required>foo</textarea><button data-role="submit" class="mw-ui-button">go</button></form>' ),
			$( '<form><textarea class="mw-ui-input" required>foo</textarea><input class="mw-ui-input" required><button data-role="submit" class="mw-ui-button">go</button></form>' )
		];

		forms.forEach( function ( $form ) {
			mw.flow.ui.enhance.enableFormWithRequiredFields( $form );
		} );

		assert.strictEqual( forms[ 0 ].find( 'button' ).prop( 'disabled' ), true,
			'Buttons with data-role=action are disabled when required fields are empty.' );
		assert.strictEqual( forms[ 1 ].find( 'button' ).prop( 'disabled' ), true,
			'Buttons with data-role=action are disabled when required fields are empty.' );
		assert.strictEqual( forms[ 2 ].find( 'button' ).prop( 'disabled' ), false,
			'Buttons with are enabled when no required fields in form.' );
		assert.strictEqual( forms[ 3 ].find( 'button' ).prop( 'disabled' ), true,
			'Buttons are disabled when textarea is required but empty.' );
		assert.strictEqual( forms[ 4 ].find( 'button' ).prop( 'disabled' ), false,
			'Buttons are enabled when required textarea has text.' );
		assert.strictEqual( forms[ 5 ].find( 'button' ).prop( 'disabled' ), true,
			'Buttons are disabled when required textarea but required input does not.' );
	} );

	QUnit.test( 'mw-ui-tooltip', function ( assert ) {
		var $body = $( document.body );

		assert.ok( mw.tooltip, 'mw.tooltip exists' );

		// Create a tooltip using body
		$body.attr( 'title', 'test' );
		assert.ok( mw.tooltip.show( $body ), 'mw.ui.tooltip.show returned something' );
		// eslint-disable-next-line no-jquery/no-sizzle
		assert.strictEqual( $( '.flow-ui-tooltip-content' ).filter( ':contains("test"):visible' ).length, 1,
			'Tooltip with text "test" is visible' );
		mw.tooltip.hide( $body );
		assert.strictEqual( $( '.flow-ui-tooltip-content' ).filter( ':contains("test")' ).length, 0,
			'Tooltip with text "test" is removed' );
		$body.attr( 'title', '' );
	} );

	QUnit.test( 'mw-ui-modal', function ( assert ) {
		var modal, $node;

		assert.ok( mw.tooltip, 'mw.Modal exists' );

		// Instantiation
		modal = mw.Modal();
		assert.strictEqual( modal.constructor, mw.Modal,
			'mw.Modal() returns mw.Modal instance' );

		modal = new mw.Modal();
		assert.strictEqual( modal.constructor, mw.Modal,
			'new mw.Modal() returns mw.Modal instance' );

		modal = mw.Modal( 'namefoo' );
		assert.strictEqual( modal.getName(), 'namefoo',
			'Modal sets name to "namefoo"' );

		// Title
		assert.strictEqual( modal.getNode().find( modal.headingSelector ).css( 'display' ), 'none',
			'Modal heading should be hidden with no title' );

		modal = mw.Modal( { title: 'titlefoo' } );
		assert.strictEqual( modal.getNode().find( modal.headingSelector ).text().indexOf( 'titlefoo' ) > -1, true,
			'Modal instantiation sets title to "titlefoo"' );

		modal.setTitle( 'titlebaz' );
		assert.strictEqual( modal.getNode().find( modal.headingSelector ).text().indexOf( 'titlebaz' ) > -1, true,
			'Modal setTitle to "titlebaz"' );

		// Content at instantiation
		modal = mw.Modal( { open: 'contentfoo' } );
		assert.strictEqual( modal.getContentNode().text(), 'contentfoo',
			'Modal instantiation sets content to "contentfoo"' );
		$node = modal.getNode();
		assert.strictEqual( $node.closest( 'body' ).length, 1,
			'Modal instantiation adds modal to body' );

		// Close
		modal.close();
		assert.strictEqual( $node.closest( 'body' ).length, 0,
			'Modal close removes it from page' );
		$node = null;

		// Content after instantiation
		modal = mw.Modal();

		modal.open( 'contentfoo' );
		assert.strictEqual( modal.getContentNode().html(), 'contentfoo',
			'Modal open string' );

		modal.open( '<h1>contentfoo</h1>' );
		assert.strictEqual( modal.getContentNode().html(), '<h1>contentfoo</h1>',
			'Modal open html string' );

		modal.open( $( '<h2>contentfoo</h2>' ) );
		assert.strictEqual( modal.getContentNode().html(), '<h2>contentfoo</h2>',
			'Modal open jQuery' );

		// @todo content Array
		// @todo content Object

		// Get nodes
		assert.strictEqual( modal.getNode().length, 1,
			'getNode has length' );
		assert.strictEqual( modal.getContentNode().length, 1,
			'getContentNode has length' );

		modal.close(); // kill the test modal

		// @todo setInteractiveHandler
		// @todo addSteps
		// @todo setStep
		// @todo getSteps
		// @todo prevOrClose
		// @todo nextOrSubmit
		// @todo prev
		// @todo next
		// @todo go
	} );

}() );
